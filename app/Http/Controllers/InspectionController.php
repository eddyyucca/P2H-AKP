<?php

namespace App\Http\Controllers;

use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Models\Unit;
use App\Services\FollowUpService;
use App\Services\InspectionService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InspectionController extends Controller
{
    /** Form P2H. ?unit=LV-12 langsung membuka form unit itu; tanpa parameter, pilih unit dulu. */
    public function create(Request $request)
    {
        $code = Unit::normalizeCode((string) $request->query('unit', ''));
        $units = Unit::where('is_active', true)->orderBy('code')->get();

        $unit = $code !== '' ? $units->firstWhere('code', $code) : null;
        $unknown = $code !== '' && ! $unit ? $code : null;

        $version = $unit ? ChecklistVersion::activeFor($unit->category) : null;

        return view('p2h.fill', [
            'units' => $units,
            'unit' => $unit,
            'unknownCode' => $unknown,
            'version' => $version,
            'items' => $version ? $version->items()->get()->groupBy('group_name') : collect(),
            'shift' => InspectionService::shiftNow(),
            'fuelLevels' => config('p2h.fuel_levels'),
            'lastOdometer' => $unit?->lastOdometer(),
            'maxJump' => (int) config('p2h.odometer_max_jump_km'),
            'formToken' => bin2hex(random_bytes(16)),
        ]);
    }

    public function store(Request $request, InspectionService $service)
    {
        // Kiriman ganda (klik dua kali / refresh): token form yang sama membawa ke hasil yang sudah tersimpan.
        $token = preg_match('/^[a-f0-9]{16,64}$/', (string) $request->input('form_token')) ? (string) $request->input('form_token') : null;
        if ($token && ($done = Cache::get("p2h:form:$token"))) {
            return redirect()->route('p2h.show', $done)->with('just_submitted', true);
        }

        $unit = Unit::where('is_active', true)->where('code', Unit::normalizeCode((string) $request->input('unit')))->first();
        abort_unless($unit, 422, 'Unit tidak ditemukan atau tidak aktif.');

        $version = ChecklistVersion::activeFor($unit->category);
        abort_unless($version, 422, 'Unit ini belum punya checklist aktif.');

        $items = $version->items()->get();

        $rules = [
            'shift' => ['required', Rule::in(array_keys(Inspection::SHIFTS))],
            'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'fuel_level' => ['required', Rule::in(config('p2h.fuel_levels'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'answers' => ['required', 'array'],
            'confirm' => ['accepted'],
        ];
        $messages = [
            'odometer.required' => 'Odometer wajib diisi.',
            'fuel_level.required' => 'Level BBM wajib dipilih.',
            'answers.required' => 'Periksa semua item checklist.',
            'confirm.accepted' => 'Centang pernyataan bahwa hasil pemeriksaan ini benar.',
        ];

        foreach ($items as $item) {
            $rules["answers.{$item->id}"] = ['required', Rule::in(['ok', 'bad'])];
            $messages["answers.{$item->id}.required"] = "Item \"{$item->label}\" belum diperiksa.";
            $messages["answers.{$item->id}.in"] = "Jawaban item \"{$item->label}\" tidak valid.";
            $rules["answer_notes.{$item->id}"] = [
                Rule::requiredIf(fn () => $request->input("answers.{$item->id}") === 'bad'), 'nullable', 'string', 'min:3', 'max:300',
            ];
            $messages["answer_notes.{$item->id}.required"] = "Isi keterangan kerusakan untuk \"{$item->label}\".";
            $messages["answer_notes.{$item->id}.min"] = "Keterangan untuk \"{$item->label}\" terlalu singkat.";
            $rules["photos.{$item->id}"] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('p2h.photo_max_kb')];
            $messages["photos.{$item->id}.image"] = "Foto \"{$item->label}\" harus berupa gambar (JPG, PNG, atau WEBP).";
            $messages["photos.{$item->id}.mimes"] = "Foto \"{$item->label}\" harus berformat JPG, PNG, atau WEBP.";
            $messages["photos.{$item->id}.max"] = "Foto \"{$item->label}\" terlalu besar (maksimum ".round(config('p2h.photo_max_kb') / 1024).' MB).';
        }

        $data = $request->validate($rules, $messages);
        $this->checkOdometer($request, $unit, (int) $data['odometer']);

        // Kunci singkat per token: dua klik berurutan tidak menyimpan dua kali.
        if ($token && ! Cache::add("p2h:lock:$token", 1, 30)) {
            return back()->withInput()->with('error', 'Pengiriman sebelumnya masih diproses. Tunggu sebentar.');
        }

        try {
            $inspection = $service->submit(portal_user(), $unit, $version, array_merge($data, [
                'photos' => array_filter((array) $request->file('photos')),
                'declared' => true,
            ]));
            if ($token) {
                Cache::put("p2h:form:$token", $inspection->number, now()->addMinutes(30));
            }
        } finally {
            if ($token) {
                Cache::forget("p2h:lock:$token");
            }
        }

        return redirect()->route('p2h.show', $inspection)->with('just_submitted', true);
    }

    /** Odometer tidak boleh mundur; lonjakan besar harus dikonfirmasi pemeriksa. */
    private function checkOdometer(Request $request, Unit $unit, int $odometer): void
    {
        $last = $unit->lastOdometer();
        if ($last === null) {
            return;
        }

        if (config('p2h.odometer_strict') && $odometer < $last) {
            throw ValidationException::withMessages(['odometer' => 'Odometer ('.number_format($odometer, 0, ',', '.').' km) lebih kecil dari P2H sebelumnya ('.number_format($last, 0, ',', '.').' km). Periksa kembali angkanya.']);
        }

        $jump = $odometer - $last;
        if ($jump > (int) config('p2h.odometer_max_jump_km') && ! $request->boolean('odometer_confirm')) {
            $request->session()->flash('odometer_jump', $jump);

            throw ValidationException::withMessages(['odometer' => 'Selisih '.number_format($jump, 0, ',', '.').' km dari P2H sebelumnya ('.number_format($last, 0, ',', '.').' km). Centang konfirmasi bila angka sudah benar.']);
        }
    }

    /** Filter riwayat yang dipakai bersama oleh daftar dan ekspor. */
    private function filtered(Request $request, int $perPage): array
    {
        $user = portal_user();
        $query = Inspection::visibleTo($user)->latest('inspected_at')->latest('id');

        $filters = [
            'q' => trim((string) $request->query('q')),
            'status' => (string) $request->query('status'),
            'shift' => (string) $request->query('shift'),
            'follow_up' => (string) $request->query('follow_up'),
            'from' => (string) $request->query('from'),
            'to' => (string) $request->query('to'),
            'per_page' => $perPage,
        ];

        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->where('unit_code', 'like', $like)->orWhere('unit_plate', 'like', $like)
                ->orWhere('driver_name', 'like', $like)->orWhere('driver_nik', 'like', $like)->orWhere('number', 'like', $like));
        }
        if (in_array($filters['status'], ['layak', 'tidak_layak'], true)) {
            $query->where('status', $filters['status']);
        }
        if (array_key_exists($filters['shift'], Inspection::SHIFTS)) {
            $query->where('shift', $filters['shift']);
        }
        if (in_array($filters['follow_up'], ['open', 'in_progress', 'done', 'none'], true)) {
            $query->where('follow_up_status', $filters['follow_up']);
        } elseif ($filters['follow_up'] === 'active') {
            $query->openFindings();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
            $query->where('inspected_at', '>=', $filters['from'].' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
            $query->where('inspected_at', '<', \Carbon\Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return [$query, $filters];
    }

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 25, 50, 100], true) ? (int) $request->query('per_page') : 25;
        [$query, $filters] = $this->filtered($request, $perPage);

        return view('p2h.index', ['items' => $query->paginate($perPage)->withQueryString(), 'filters' => $filters, 'sees' => Inspection::seesAll(portal_user())]);
    }

    /** Ekspor riwayat (sesuai filter dan hak akses) ke CSV yang terbuka di Excel. */
    public function export(Request $request)
    {
        [$query, $filters] = $this->filtered($request, 25);

        return Csv::download('riwayat-p2h-'.now()->format('Ymd-His').'.csv',
            ['No. P2H', 'Tanggal', 'Jam', 'Shift', 'Unit', 'Nama unit', 'Plat', 'Pemeriksa', 'NIK', 'Odometer (km)', 'BBM', 'Hasil', 'Jumlah temuan', 'Temuan kritis', 'Status tindak lanjut', 'PIC', 'Tenggat', 'Catatan'],
            (function () use ($query) {
                foreach ($query->lazyById(500) as $i) {
                    yield [
                        $i->number, $i->inspected_at->format('Y-m-d'), $i->inspected_at->format('H:i'), $i->shiftLabel(), $i->unit_code, $i->unit_name, $i->unit_plate,
                        $i->driver_name, $i->driver_nik, $i->odometer, $i->fuel_level, $i->isFit() ? 'LAYAK' : 'TIDAK LAYAK', $i->bad_count, $i->critical_bad_count,
                        RepairController::STATUS_LABELS[$i->follow_up_status] ?? $i->follow_up_status, $i->follow_up_pic, $i->follow_up_due?->format('Y-m-d'), $i->notes,
                    ];
                }
            })());
    }

    private function findVisible(string $number): Inspection
    {
        return Inspection::visibleTo(portal_user())->where('number', $number)->firstOrFail();
    }

    public function show(string $inspection)
    {
        $item = $this->findVisible($inspection);

        return view('p2h.show', [
            'inspection' => $item,
            'answers' => $item->answers()->get()->groupBy('group_name'),
            'canFollowUp' => portal_user()->hasRole('admin', 'pengawas'),
            'timeline' => $item->followUps()->get(),
        ]);
    }

    /** Lembar P2H siap cetak (A4); dari browser bisa disimpan sebagai PDF. */
    public function print(string $inspection)
    {
        $item = $this->findVisible($inspection);

        return view('p2h.print', ['inspection' => $item, 'answers' => $item->answers()->get()->groupBy('group_name')]);
    }

    /** Foto bukti kerusakan, disajikan lewat aplikasi (disk privat) dengan pemeriksaan hak akses. */
    public function photo(InspectionAnswer $answer)
    {
        $this->findVisible(Inspection::findOrFail($answer->inspection_id)->number);
        abort_unless($answer->photo_path && Storage::disk(config('p2h.photo_disk'))->exists($answer->photo_path), 404);

        return Storage::disk(config('p2h.photo_disk'))->response($answer->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Pengawas menutup temuan (sudah ditangani), beserta catatan dan waktunya. Boleh langsung dari "menunggu". */
    public function followUp(Request $request, string $inspection, FollowUpService $followUps)
    {
        $item = $this->findVisible($inspection);
        abort_unless($item->isOpen(), 422, 'Temuan ini tidak menunggu tindak lanjut.');

        $data = $request->validate(['follow_up_note' => ['required', 'string', 'min:3', 'max:500']], [
            'follow_up_note.required' => 'Isi catatan tindak lanjut.',
            'follow_up_note.min' => 'Catatan tindak lanjut terlalu singkat.',
        ]);

        $followUps->close($item, portal_user()->name, $data['follow_up_note']);

        return redirect()->route('p2h.show', $item)->with('success', 'Tindak lanjut dicatat.');
    }

    /** Pengawas menandai temuan mulai dikerjakan: PIC/bengkel dan tenggat. */
    public function startFollowUp(Request $request, string $inspection, FollowUpService $followUps)
    {
        $item = $this->findVisible($inspection);
        abort_unless($item->isOpen(), 422, 'Temuan ini tidak menunggu tindak lanjut.');

        $data = $request->validate([
            'pic' => ['required', 'string', 'min:2', 'max:100'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'pic.required' => 'Isi penanggung jawab atau bengkel.',
            'due_date.after_or_equal' => 'Tenggat tidak boleh sebelum hari ini.',
        ]);

        $followUps->start($item, portal_user()->name, $data['pic'], $data['due_date'] ?? null, $data['note'] ?? null);

        return redirect()->route('p2h.show', $item)->with('success', 'Temuan ditandai sedang dikerjakan.');
    }
}
