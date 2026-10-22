<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Models\Unit;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Laporan bulanan: rekap per unit dan daftar temuan, bisa diekspor ke CSV atau dicetak/PDF dari browser. */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$month, $category] = $this->params($request);
        $rows = $this->recap($month, $category);

        return view('p2h.report', [
            'month' => $month,
            'category' => $category,
            'categories' => Unit::query()->distinct()->orderBy('category')->pluck('category'),
            'rows' => $rows,
            'totals' => [
                'p2h' => $rows->sum('total'), 'fit' => $rows->sum('fit'), 'unfit' => $rows->sum('unfit'),
                'findings' => $rows->sum('findings'), 'open' => $rows->sum('open'),
                'compliance' => $rows->whereNotNull('compliance')->isEmpty() ? null : (int) round($rows->whereNotNull('compliance')->avg('compliance')),
            ],
        ]);
    }

    public function exportRecap(Request $request)
    {
        [$month, $category] = $this->params($request);

        return Csv::download('rekap-p2h-'.$month->format('Y-m').'.csv',
            ['Unit', 'Nama unit', 'Kategori', 'Jumlah P2H', 'Layak', 'Tidak layak', 'P2H bertemuan', 'Temuan belum selesai', 'Kepatuhan (%)', 'Odometer awal', 'Odometer akhir', 'Jarak (km)'],
            $this->recap($month, $category)->map(fn ($r) => [
                $r['code'], $r['name'], $r['category'], $r['total'], $r['fit'], $r['unfit'], $r['findings'], $r['open'],
                $r['compliance'], $r['odo_start'], $r['odo_end'], $r['distance'],
            ])->all());
    }

    public function exportFindings(Request $request)
    {
        [$month, $category] = $this->params($request);
        $unitIds = $category !== '' ? Unit::where('category', $category)->pluck('id') : null;

        $rows = InspectionAnswer::query()
            ->join('inspections', 'inspections.id', '=', 'inspection_answers.inspection_id')
            ->where('inspection_answers.result', 'bad')
            ->whereBetween('inspections.inspected_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($unitIds, fn ($q) => $q->whereIn('inspections.unit_id', $unitIds))
            ->orderBy('inspections.inspected_at')
            ->get(['inspections.number', 'inspections.inspected_at', 'inspections.shift', 'inspections.unit_code', 'inspections.driver_name', 'inspections.follow_up_status',
                'inspections.follow_up_pic', 'inspections.follow_up_note', 'inspection_answers.group_name', 'inspection_answers.label', 'inspection_answers.is_critical', 'inspection_answers.note']);

        return Csv::download('temuan-p2h-'.$month->format('Y-m').'.csv',
            ['No. P2H', 'Tanggal', 'Shift', 'Unit', 'Pemeriksa', 'Kelompok', 'Item', 'Kritis', 'Keterangan', 'Status tindak lanjut', 'PIC', 'Catatan penanganan'],
            $rows->map(fn ($r) => [
                $r->number, Carbon::parse($r->inspected_at)->format('Y-m-d H:i'), Inspection::SHIFTS[$r->shift] ?? $r->shift, $r->unit_code, $r->driver_name,
                $r->group_name, $r->label, $r->is_critical ? 'Ya' : 'Tidak', $r->note,
                RepairController::STATUS_LABELS[$r->follow_up_status] ?? $r->follow_up_status, $r->follow_up_pic, $r->follow_up_note,
            ])->all());
    }

    /** @return array{0: Carbon, 1: string} */
    private function params(Request $request): array
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('bulan'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('bulan').'-01')->startOfDay()
            : today()->startOfMonth();

        $category = trim((string) $request->query('kategori'));

        return [$month, $category];
    }

    /** Rekap per unit untuk satu bulan. */
    private function recap(Carbon $month, string $category)
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $units = Unit::query()->when($category !== '', fn ($q) => $q->where('category', $category))->orderBy('code')->get();
        $inspections = Inspection::whereIn('unit_id', $units->pluck('id'))->whereBetween('inspected_at', [$start, $end])
            ->orderBy('inspected_at')->get(['unit_id', 'inspected_at', 'shift', 'status', 'bad_count', 'follow_up_status', 'odometer'])->groupBy('unit_id');

        // Hari yang sudah berjalan pada bulan itu x shift wajib = jumlah P2H yang diharapkan per unit aktif.
        $elapsed = $end->lt(today()) ? $month->daysInMonth : ($start->gt(today()) ? 0 : today()->day);
        $expected = $elapsed * count(config('p2h.required_shifts'));

        return $units->map(function (Unit $u) use ($inspections, $expected) {
            $list = $inspections->get($u->id, collect());
            if ($list->isEmpty() && ! $u->is_active) {
                return null;
            }

            $slots = $list->filter(fn ($i) => in_array($i->shift, config('p2h.required_shifts'), true))
                ->map(fn ($i) => $i->inspected_at->toDateString().'|'.$i->shift)->unique()->count();
            $odos = $list->whereNotNull('odometer')->pluck('odometer');

            return [
                'code' => $u->code, 'name' => $u->name, 'category' => $u->category,
                'total' => $list->count(),
                'fit' => $list->where('status', 'layak')->count(),
                'unfit' => $list->where('status', 'tidak_layak')->count(),
                'findings' => $list->where('bad_count', '>', 0)->count(),
                'open' => $list->whereIn('follow_up_status', Inspection::OPEN_STATES)->count(),
                'compliance' => $expected > 0 && $u->is_active ? (int) round(min($slots, $expected) / $expected * 100) : null,
                'odo_start' => $odos->first(), 'odo_end' => $odos->last(),
                'distance' => $odos->count() > 1 ? $odos->last() - $odos->first() : null,
            ];
        })->filter()->values();
    }
}
