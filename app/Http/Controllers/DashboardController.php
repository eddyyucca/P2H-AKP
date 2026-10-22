<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Models\Unit;
use App\Services\InspectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard P2H. Yang melihat semua data (admin, pengawas, viewer) mendapat gambaran seluruh unit;
     * operator hanya melihat angka dan grafik dari P2H miliknya sendiri.
     */
    public function home(Request $request)
    {
        $user = portal_user();
        $sees = Inspection::seesAll($user);

        // --- Periode: 7/14/30 hari terakhir, atau rentang tanggal kustom (maksimum 92 hari). ---
        [$from, $to, $days, $custom] = $this->period($request);

        $category = trim((string) $request->query('kategori'));
        $categories = Unit::query()->distinct()->orderBy('category')->pluck('category');
        if (! $categories->contains($category)) {
            $category = '';
        }
        $unitIds = $category !== '' ? Unit::where('category', $category)->pluck('id') : null;

        $visible = function () use ($user, $unitIds) {
            return Inspection::visibleTo($user)->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds));
        };
        $today = fn () => $visible()->where('inspected_at', '>=', today())->where('inspected_at', '<', today()->addDay());

        // --- Hari ini ---
        $activeUnitList = Unit::where('is_active', true)->when($category !== '', fn ($q) => $q->where('category', $category))->orderBy('code')->get();
        $activeIds = $activeUnitList->pluck('id');
        $activeUnits = $activeUnitList->count();

        $todayRows = $today()->orderBy('inspected_at')->orderBy('id')->get(['id', 'unit_id', 'status', 'shift']);
        $latestPerUnit = $todayRows->whereIn('unit_id', $activeIds)->keyBy('unit_id'); // pemeriksaan terakhir tiap unit aktif

        $stats = [
            'today' => $todayRows->count(),
            'fit' => $todayRows->where('status', 'layak')->count(),
            'unfit' => $todayRows->where('status', 'tidak_layak')->count(),
            'day' => $todayRows->where('shift', 'D')->count(),
            'night' => $todayRows->where('shift', 'N')->count(),
            'open' => $visible()->openFindings()->count(),
            'units_done' => $latestPerUnit->count(),
            'units_active' => $activeUnits,
        ];
        $stats['compliance'] = $activeUnits > 0 ? (int) round($latestPerUnit->count() / $activeUnits * 100) : null;

        // Kepatuhan per shift: unit aktif yang sudah diperiksa pada tiap shift wajib hari ini.
        $shiftCompliance = [];
        foreach (config('p2h.required_shifts') as $s) {
            $done = $todayRows->where('shift', $s)->whereIn('unit_id', $activeIds)->pluck('unit_id')->unique()->count();
            $shiftCompliance[$s] = [
                'label' => Inspection::SHIFTS[$s] ?? $s, 'done' => $done, 'total' => $activeUnits,
                'percent' => $activeUnits > 0 ? (int) round($done / $activeUnits * 100) : null,
            ];
        }

        // Status unit menurut P2H terakhirnya hari ini (hanya untuk yang melihat semua unit).
        $unitStatus = $sees ? [
            'layak' => $latestPerUnit->where('status', 'layak')->count(),
            'tidak_layak' => $latestPerUnit->where('status', 'tidak_layak')->count(),
            'belum' => max($activeUnits - $latestPerUnit->count(), 0),
        ] : null;

        // --- Periode ---
        $period = fn () => $visible()->whereBetween('inspected_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $perDay = $period()->selectRaw('DATE(inspected_at) as d, status, COUNT(*) as n')->groupBy('d', 'status')->get();
        $labels = [];
        $fit = [];
        $unfit = [];
        $dayKeys = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $dayKeys[] = $key;
            $labels[] = $day->translatedFormat($days > 14 ? 'd/m' : 'D d/m');
            $fit[] = (int) $perDay->where('d', $key)->where('status', 'layak')->sum('n');
            $unfit[] = (int) $perDay->where('d', $key)->where('status', 'tidak_layak')->sum('n');
        }

        $periodTotal = array_sum($fit) + array_sum($unfit);
        $periodStats = [
            'total' => $periodTotal,
            'fit' => array_sum($fit),
            'unfit' => array_sum($unfit),
            'fit_rate' => $periodTotal > 0 ? (int) round(array_sum($fit) / $periodTotal * 100) : null,
            'findings' => $period()->where('bad_count', '>', 0)->count(),
        ];

        // --- Item yang paling sering rusak dalam periode ---
        $topItems = InspectionAnswer::query()
            ->join('inspections', 'inspections.id', '=', 'inspection_answers.inspection_id')
            ->where('inspection_answers.result', 'bad')
            ->whereBetween('inspections.inspected_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when(! $sees, fn ($q) => $q->where('inspections.driver_portal_id', (string) $user->id))
            ->when($unitIds, fn ($q) => $q->whereIn('inspections.unit_id', $unitIds))
            ->selectRaw('inspection_answers.label as label, inspection_answers.is_critical as is_critical, COUNT(*) as n')
            ->groupBy('inspection_answers.label', 'inspection_answers.is_critical')
            ->orderByDesc('n')->orderBy('label')->limit(8)->get();

        // --- Unit dengan temuan terbanyak dalam periode ---
        $topUnits = $sees
            ? $period()->where('bad_count', '>', 0)
                ->selectRaw('unit_code, COUNT(*) as n, SUM(CASE WHEN status = \'tidak_layak\' THEN 1 ELSE 0 END) as unfit')
                ->groupBy('unit_code')->orderByDesc('n')->orderBy('unit_code')->limit(5)->get()
            : collect();

        // --- Umur temuan terbuka dan waktu penyelesaian rata-rata ---
        $openRows = $visible()->openFindings()->get(['inspected_at', 'follow_up_due', 'follow_up_status']);
        $aging = ['lt1' => 0, 'd1_3' => 0, 'gt3' => 0];
        foreach ($openRows as $r) {
            $age = $r->inspected_at->diffInDays(now());
            $aging[$age < 1 ? 'lt1' : ($age <= 3 ? 'd1_3' : 'gt3')]++;
        }
        $doneRows = $visible()->where('follow_up_status', 'done')->whereNotNull('follow_up_at')
            ->whereBetween('follow_up_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get(['inspected_at', 'follow_up_at']);
        $mttrHours = $doneRows->isEmpty() ? null : round($doneRows->avg(fn ($r) => $r->inspected_at->diffInMinutes($r->follow_up_at, true)) / 60, 1);
        $findingsStats = [
            'aging' => $aging,
            'overdue' => $openRows->filter(fn ($r) => $r->follow_up_due !== null && $r->follow_up_due->lt(today()))->count(),
            'in_progress' => $openRows->where('follow_up_status', 'in_progress')->count(),
            'mttr_hours' => $mttrHours,
            'done' => $doneRows->count(),
        ];

        // --- Hanya untuk yang melihat seluruh unit ---
        $fleet = null;
        $complianceTrend = null;
        $heatmap = null;
        $topDrivers = collect();
        $alerts = [];
        if ($sees) {
            $fleet = $this->fleetNow($activeUnitList);
            $complianceTrend = $this->complianceTrend($dayKeys, $activeIds, $activeUnits, $from, $to);
            $heatmap = $this->heatmap($activeUnitList, array_slice($dayKeys, -14));
            $topDrivers = $period()->selectRaw('driver_name, COUNT(*) as n, SUM(CASE WHEN bad_count > 0 THEN 1 ELSE 0 END) as findings')
                ->groupBy('driver_portal_id', 'driver_name')->orderByDesc('n')->orderBy('driver_name')->limit(5)->get();
            $alerts = $this->alerts($fleet, $findingsStats);
        }

        return view('p2h.home', [
            'stats' => $stats,
            'periodStats' => $periodStats,
            'findingsStats' => $findingsStats,
            'shiftCompliance' => $shiftCompliance,
            'days' => $days,
            'custom' => $custom,
            'from' => $from,
            'to' => $to,
            'category' => $category,
            'categories' => $categories,
            'sees' => $sees,
            'fleet' => $fleet,
            'heatmap' => $heatmap,
            'heatmapDays' => array_map(fn ($d) => Carbon::parse($d), array_slice($dayKeys, -14)),
            'topDrivers' => $topDrivers,
            'alerts' => $alerts,
            'chart' => [
                'labels' => $labels,
                'fit' => $fit,
                'unfit' => $unfit,
                'compliance' => $complianceTrend,
                'unitStatus' => $unitStatus,
                'items' => $topItems->map(fn ($r) => ['label' => $r->label, 'n' => (int) $r->n, 'critical' => (bool) $r->is_critical])->values()->all(),
            ],
            'topUnits' => $topUnits,
            'pending' => $sees ? $activeUnitList->whereNotIn('id', $latestPerUnit->keys())->values() : collect(),
            'followUps' => $visible()->openFindings()->orderByRaw("CASE WHEN status = 'tidak_layak' THEN 0 ELSE 1 END")->oldest('inspected_at')->limit(8)->get(),
            'recent' => $visible()->latest('inspected_at')->limit(8)->get(),
            'canFill' => $user->hasRole('admin', 'pengawas', 'operator'),
            'shift' => InspectionService::shiftNow(),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: int, 3: bool} */
    private function period(Request $request): array
    {
        $dari = (string) $request->query('dari');
        $sampai = (string) $request->query('sampai');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
            try {
                $from = Carbon::parse($dari)->startOfDay();
                $to = Carbon::parse($sampai)->startOfDay();
                $span = (int) $from->diffInDays($to) + 1;
                if ($from->lte($to) && $span <= 92 && $to->lte(today())) {
                    return [$from, $to, $span, true];
                }
            } catch (\Throwable) {
                // tanggal tidak sah: pakai periode bawaan
            }
        }

        $days = in_array((int) $request->query('hari'), [7, 14, 30], true) ? (int) $request->query('hari') : 14;

        return [today()->subDays($days - 1), today(), $days, false];
    }

    /**
     * P2H terakhir tiap unit aktif (kapan pun), untuk status "terakhir diketahui".
     *
     * @return array{rows: \Illuminate\Support\Collection, unfit: \Illuminate\Support\Collection, stale: \Illuminate\Support\Collection, service: \Illuminate\Support\Collection}
     */
    private function fleetNow($units): array
    {
        $ids = $units->pluck('id');
        $latestIds = Inspection::whereIn('unit_id', $ids)->selectRaw('MAX(id) as id')->groupBy('unit_id')->pluck('id');
        $latest = Inspection::whereIn('id', $latestIds)->get()->keyBy('unit_id');

        $staleAfter = now()->subHours((int) config('p2h.stale_after_hours'));
        $rows = $units->map(fn (Unit $u) => (object) ['unit' => $u, 'last' => $latest->get($u->id)]);

        $service = $rows->filter(fn ($r) => $r->unit->service_interval_km)->map(function ($r) {
            $left = Unit::serviceRemaining($r->unit->service_interval_km, $r->unit->last_service_km, $r->last?->odometer);

            return $left !== null && $left <= (int) config('p2h.service_warning_km') ? (object) ['unit' => $r->unit, 'left' => $left, 'odometer' => $r->last->odometer] : null;
        })->filter()->sortBy('left')->values();

        return [
            'rows' => $rows,
            'unfit' => $rows->filter(fn ($r) => $r->last && $r->last->status === 'tidak_layak')->sortBy(fn ($r) => $r->last->inspected_at)->values(),
            'stale' => $rows->filter(fn ($r) => ! $r->last || $r->last->inspected_at->lt($staleAfter))->values(),
            'service' => $service,
        ];
    }

    /** Persentase unit aktif yang diperiksa pada tiap hari. @return array<int, int> */
    private function complianceTrend(array $dayKeys, $activeIds, int $activeUnits, Carbon $from, Carbon $to): array
    {
        if ($activeUnits === 0) {
            return array_fill(0, count($dayKeys), 0);
        }

        $rows = Inspection::whereIn('unit_id', $activeIds)
            ->whereBetween('inspected_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(inspected_at) as d, COUNT(DISTINCT unit_id) as n')->groupBy('d')->pluck('n', 'd');

        return array_map(fn ($k) => (int) round(min((int) ($rows[$k] ?? 0), $activeUnits) / $activeUnits * 100), $dayKeys);
    }

    /**
     * Matriks unit x tanggal: 'bad' bila ada P2H tidak layak hari itu, 'ok' bila ada P2H, kosong bila tidak ada.
     *
     * @return array{units: \Illuminate\Support\Collection, cells: array<int, array<string, string>>}
     */
    private function heatmap($units, array $dayKeys): array
    {
        $units = $units->take(60);
        $rows = Inspection::whereIn('unit_id', $units->pluck('id'))
            ->whereBetween('inspected_at', [Carbon::parse($dayKeys[0])->startOfDay(), Carbon::parse(end($dayKeys))->endOfDay()])
            ->selectRaw('unit_id, DATE(inspected_at) as d, MAX(CASE WHEN status = \'tidak_layak\' THEN 1 ELSE 0 END) as bad')
            ->groupBy('unit_id', 'd')->get();

        $cells = [];
        foreach ($rows as $r) {
            $cells[$r->unit_id][$r->d] = $r->bad ? 'bad' : 'ok';
        }

        return ['units' => $units, 'cells' => $cells];
    }

    /** Pemberitahuan di dashboard (dalam aplikasi). @return array<int, array{level: string, icon: string, text: string, href: string}> */
    private function alerts(array $fleet, array $findings): array
    {
        $alerts = [];
        if ($fleet['unfit']->isNotEmpty()) {
            $alerts[] = ['level' => 'danger', 'icon' => 'bi-x-octagon-fill', 'href' => route('p2h.repairs'),
                'text' => $fleet['unfit']->count().' unit TIDAK LAYAK menurut P2H terakhirnya: '.$fleet['unfit']->take(5)->map(fn ($r) => $r->unit->code)->implode(', ').'.'];
        }
        if ($findings['overdue'] > 0) {
            $alerts[] = ['level' => 'warning', 'icon' => 'bi-alarm-fill', 'href' => route('p2h.repairs', ['filter' => 'terlambat']),
                'text' => $findings['overdue'].' temuan lewat tenggat penanganan.'];
        }
        if ($fleet['stale']->isNotEmpty()) {
            $alerts[] = ['level' => 'secondary', 'icon' => 'bi-hourglass-split', 'href' => '#pendingUnits',
                'text' => $fleet['stale']->count().' unit belum diperiksa lebih dari '.config('p2h.stale_after_hours').' jam.'];
        }
        if ($fleet['service']->isNotEmpty()) {
            $alerts[] = ['level' => 'info', 'icon' => 'bi-wrench-adjustable-circle-fill', 'href' => route('p2h.units'),
                'text' => $fleet['service']->count().' unit mendekati atau melewati jadwal servis: '.$fleet['service']->take(5)->map(fn ($r) => $r->unit->code)->implode(', ').'.'];
        }

        return $alerts;
    }
}
