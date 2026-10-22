<?php

namespace App\Http\Controllers;

use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Unit;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master unit (kendaraan/alat) yang diperiksa lewat P2H. */
class UnitController extends Controller
{
    private function categories()
    {
        return ChecklistVersion::query()->distinct()->orderBy('category')->pluck('category');
    }

    public function index(Request $request)
    {
        $query = Unit::query()->orderByDesc('is_active')->orderBy('code');

        if (filled($q = trim((string) $request->query('q')))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('plate', 'like', $like));
        }

        $units = $query->paginate(25)->withQueryString();
        $odometers = Inspection::whereIn('id', Inspection::whereIn('unit_id', $units->pluck('id'))->whereNotNull('odometer')->selectRaw('MAX(id) as id')->groupBy('unit_id')->pluck('id'))
            ->pluck('odometer', 'unit_id');

        return view('p2h.units.index', [
            'units' => $units,
            'odometers' => $odometers,
            'q' => $q,
            'canEdit' => portal_user()->hasRole('admin'),
        ]);
    }

    public function create()
    {
        return view('p2h.units.form', ['unit' => new Unit(['category' => 'LV', 'is_active' => true]), 'categories' => $this->categories()]);
    }

    private function rules(?Unit $unit): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-_.]*$/', Rule::unique('units', 'code')->ignore($unit?->id)],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:30', Rule::in($this->categories()->all())],
            'plate' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:255'],
            'service_interval_km' => ['nullable', 'integer', 'min:100', 'max:200000'],
            'last_service_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ];
    }

    private const MESSAGES = [
        'code.required' => 'Kode unit wajib diisi.',
        'code.regex' => 'Kode unit hanya boleh huruf, angka, strip, titik, dan garis bawah (tanpa spasi).',
        'code.unique' => 'Kode unit ini sudah dipakai.',
        'name.required' => 'Nama atau jenis unit wajib diisi.',
        'service_interval_km.min' => 'Interval servis minimal 100 km.',
        'category.in' => 'Kategori belum punya checklist. Buat checklist untuk kategori itu dulu.',
    ];

    public function store(Request $request)
    {
        // Kode dinormalkan (huruf besar, tanpa spasi tepi) SEBELUM diperiksa keunikannya.
        $request->merge(['code' => Unit::normalizeCode((string) $request->input('code', ''))]);

        $data = $request->validate($this->rules(null), self::MESSAGES);

        $unit = Unit::create($data + ['is_active' => true]);

        return redirect()->route('p2h.units')->with('success', "Unit {$unit->code} ditambahkan.");
    }

    public function edit(Unit $unit)
    {
        return view('p2h.units.form', ['unit' => $unit, 'categories' => $this->categories()]);
    }

    /** Kode unit terkunci setelah dibuat: kode itu tersimpan di riwayat P2H. */
    public function update(Request $request, Unit $unit)
    {
        $rules = $this->rules($unit);
        unset($rules['code']);
        $data = $request->validate($rules, self::MESSAGES);

        $unit->update($data);

        return redirect()->route('p2h.units')->with('success', "Unit {$unit->code} diperbarui.");
    }

    /** Mencatat servis dilakukan pada odometer terakhir unit; hitungan sisa km mulai dari nol lagi. */
    public function serviced(Unit $unit)
    {
        $odometer = $unit->lastOdometer();
        if ($odometer === null) {
            return redirect()->route('p2h.units')->with('error', "Unit {$unit->code} belum punya P2H, odometer terakhir belum diketahui.");
        }

        $unit->update(['last_service_km' => $odometer]);

        return redirect()->route('p2h.units')->with('success', "Servis {$unit->code} dicatat pada ".number_format($odometer, 0, ',', '.').' km.');
    }

    public function importForm()
    {
        return view('p2h.units.import', ['categories' => $this->categories()]);
    }

    public function importTemplate()
    {
        return Csv::download('contoh-impor-unit.csv', ['kode', 'nama', 'kategori', 'plat'], [
            ['LV-101', 'Double cabin 4x4', 'LV', 'DT 8101 KC'],
            ['LV-102', 'Single cabin', 'LV', 'DT 8102 KC'],
        ]);
    }

    /** Impor unit dari CSV (kode, nama, kategori, plat). Kode yang sudah ada dilewati; baris bermasalah dilaporkan. */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:1024']], [
            'file.required' => 'Pilih berkas CSV.', 'file.mimes' => 'Berkas harus berformat CSV.', 'file.max' => 'Berkas maksimum 1 MB.',
        ]);

        $path = $request->file('file')->getRealPath();
        $first = (string) fgets(fopen($path, 'r'));
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $categories = $this->categories()->all();

        $created = 0;
        $skipped = [];
        $errors = [];
        $handle = fopen($path, 'r');
        $line = 0;
        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            $cols = array_map(fn ($c) => trim(str_replace("\xEF\xBB\xBF", '', (string) $c)), $cols);
            if ($line === 1 && strtolower($cols[0] ?? '') === 'kode') {
                continue;
            }
            if (count(array_filter($cols, fn ($c) => $c !== '')) === 0) {
                continue;
            }
            if ($created + count($skipped) + count($errors) >= 500) {
                $errors[] = 'Baris '.$line.' dan seterusnya tidak diproses (maksimum 500 baris per impor).';
                break;
            }

            [$code, $name, $category, $plate] = [Unit::normalizeCode($cols[0] ?? ''), $cols[1] ?? '', strtoupper($cols[2] ?? ''), $cols[3] ?? ''];
            if ($category === '') {
                $category = 'LV';
            }

            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_.]{0,29}$/', $code)) {
                $errors[] = "Baris $line: kode unit tidak sah.";
            } elseif ($name === '' || mb_strlen($name) > 120) {
                $errors[] = "Baris $line ($code): nama kosong atau terlalu panjang.";
            } elseif (! in_array($category, $categories, true)) {
                $errors[] = "Baris $line ($code): kategori $category belum punya checklist.";
            } elseif (Unit::where('code', $code)->exists()) {
                $skipped[] = $code;
            } else {
                Unit::create(['code' => $code, 'name' => $name, 'category' => $category, 'plate' => $plate !== '' ? mb_substr($plate, 0, 30) : null, 'is_active' => true]);
                $created++;
            }
        }
        fclose($handle);

        return redirect()->route('p2h.units.import')->with('import_result', ['created' => $created, 'skipped' => $skipped, 'errors' => $errors]);
    }

    public function toggle(Unit $unit)
    {
        $unit->update(['is_active' => ! $unit->is_active]);

        return redirect()->route('p2h.units')->with('success', "Unit {$unit->code} ".($unit->is_active ? 'diaktifkan.' : 'dinonaktifkan. Riwayatnya tetap tersimpan.'));
    }
}
