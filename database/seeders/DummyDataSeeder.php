<?php

namespace Database\Seeders;

use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Unit;
use App\Support\DefaultChecklist;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk menganalisis dashboard dan laporan: 28 unit (LV, HD, EXC), 60 hari P2H dua shift,
 * dengan unit bermasalah, unit tidak layak, unit lama tidak diperiksa, temuan berbagai umur, dan jadwal servis.
 *
 *   php artisan db:seed --class=DummyDataSeeder
 *
 * Aman dijalankan ulang: data dummy lama dihapus dulu. Semua data dummy bisa dikenali dari catatan unit "DUMMY"
 * dan versi checklist "Data dummy", dan dihapus dengan  php artisan db:seed --class=DummyDataSeeder -- hapus
 * (atau panggil DummyDataSeeder::purge()).
 */
class DummyDataSeeder extends Seeder
{
    private const DAYS = 60;

    private const SUPERVISORS = ['Hendra Wijaya', 'Rina Kartika', 'Agus Salim'];

    private const WORKSHOPS = ['Workshop LV', 'Bengkel Mitra Jaya', 'Workshop Alat Berat', 'Vendor Ban Nusantara', 'Tim Elektrikal'];

    private const BAD_NOTES = ['Aus, perlu diganti', 'Tidak berfungsi', 'Kurang / hampir habis', 'Bocor halus', 'Longgar, perlu dikencangkan', 'Rusak, segera perbaiki'];

    private const DRIVERS = [
        'Budi Santoso', 'Sari Wulandari', 'Dedi Kurniawan', 'Eko Prasetyo', 'Fajar Nugroho', 'Gita Permata',
        'Hadi Susanto', 'Indra Saputra', 'Joko Widodo', 'Kurnia Lestari', 'Lukman Hakim', 'Maya Anggraini',
    ];

    /** Bobot kerusakan per label: item yang sering rusak di lapangan lebih besar peluangnya. */
    private const HOT_ITEMS = ['Ban', 'Lampu', 'Kotak P3K', 'Kaca', 'Oli', 'Ganjal', 'Radio', 'Wiper', 'Pelumasan'];

    public function run(): void
    {
        mt_srand(20261022);
        self::purge();

        DB::transaction(function () {
            $versions = $this->checklists();
            $units = $this->units();
            $this->inspections($units, $versions);
        });

        $this->command?->info(Unit::where('notes', 'DUMMY')->count().' unit dummy, '.Inspection::whereIn('unit_id', Unit::where('notes', 'DUMMY')->pluck('id'))->count().' P2H dibuat.');
    }

    /** Menghapus semua data dummy (unit, P2H beserta jawabannya, dan checklist dummy). */
    public static function purge(): void
    {
        DB::transaction(function () {
            $unitIds = Unit::where('notes', 'DUMMY')->pluck('id');
            Inspection::whereIn('unit_id', $unitIds)->delete(); // jawaban dan log tindak lanjut ikut terhapus (cascade)
            Unit::whereIn('id', $unitIds)->delete();
            ChecklistVersion::where('created_by_name', 'Data dummy')->each(fn ($v) => $v->items()->delete() && $v->delete());
        });
    }

    /** @return array<string, ChecklistVersion> */
    private function checklists(): array
    {
        $lv = ChecklistVersion::activeFor('LV') ?? DefaultChecklist::seed('LV', 'Versi Sep 2026');

        $make = fn (string $category, string $name, array $groups) => ChecklistVersion::activeFor($category) ?? $this->makeVersion($category, $name, $groups);

        return [
            'LV' => $lv,
            'HD' => $make('HD', 'Dump Truck Okt 2026', [
                'DOKUMEN' => [['Kartu izin operasi (KIO)', true], ['SIMPER operator berlaku', true]],
                'EKSTERIOR' => [['Ban & baut roda', true], ['Lampu utama & rotary', true], ['Kaca & wiper', false], ['Bak dump & engsel', true]],
                'MESIN' => [['Oli mesin', false], ['Air radiator', false], ['Oli hidrolik', true], ['Tidak ada kebocoran', false]],
                'KESELAMATAN' => [['Rem servis & parkir', true], ['Sabuk pengaman', true], ['APAR terisi', true], ['Alarm mundur & klakson', true], ['Radio komunikasi', true], ['Pelumasan (grease) harian', false]],
            ]),
            'EXC' => $make('EXC', 'Excavator Okt 2026', [
                'DOKUMEN' => [['Kartu izin operasi (KIO)', true], ['SIMPER operator berlaku', true]],
                'EKSTERIOR' => [['Track & roller', true], ['Lampu kerja', false], ['Kaca kabin', false], ['Bucket & gigi bucket', true]],
                'MESIN' => [['Oli mesin', false], ['Oli hidrolik & selang', true], ['Air radiator', false]],
                'KESELAMATAN' => [['Rem swing & parkir', true], ['Sabuk pengaman', true], ['APAR terisi', true], ['Alarm mundur', true], ['Pelumasan (grease) harian', false]],
            ]),
        ];
    }

    private function makeVersion(string $category, string $name, array $groups): ChecklistVersion
    {
        $version = ChecklistVersion::create(['category' => $category, 'name' => $name, 'is_active' => true, 'activated_at' => now()->subDays(self::DAYS), 'created_by_name' => 'Data dummy']);
        $order = 0;
        foreach ($groups as $group => $items) {
            foreach ($items as [$label, $critical]) {
                $version->items()->create(['group_name' => $group, 'label' => $label, 'is_critical' => $critical, 'sort_order' => ++$order]);
            }
        }

        return $version;
    }

    /** @return array<int, array{unit: Unit, profile: array}> */
    private function units(): array
    {
        $defs = [];
        for ($i = 101; $i <= 118; $i++) {
            $defs[] = ['LV-'.$i, ['Double cabin 4x4', 'Single cabin', 'SUV 4x4', 'Minibus'][$i % 4], 'LV', 'DT '.(8000 + $i).' KC'];
        }
        for ($i = 201; $i <= 206; $i++) {
            $defs[] = ['HD-'.$i, 'Dump truck 40T', 'HD', 'HD'.$i];
        }
        for ($i = 301; $i <= 304; $i++) {
            $defs[] = ['EX-'.$i, 'Excavator 30T', 'EXC', 'EX'.$i];
        }

        // Profil khusus untuk bahan analisis.
        $special = [
            'LV-107' => ['fail' => 0.34],                         // sering bermasalah
            'LV-112' => ['fail' => 0.28, 'critical' => 0.4],
            'HD-203' => ['fail' => 0.32, 'critical' => 0.35],
            'LV-109' => ['comply' => 0.5],                        // jarang diperiksa
            'EX-302' => ['comply' => 0.6, 'fail' => 0.2],
            'LV-116' => ['stop_days_ago' => 3],                   // lama tidak diperiksa
            'LV-118' => ['inactive_days_ago' => 21],              // dinonaktifkan
            'LV-105' => ['unfit_now' => true],                    // tidak layak sekarang
            'HD-205' => ['unfit_now' => true],
        ];

        // Kode yang sudah dipakai data asli dilewati: data asli tidak disentuh.
        $taken = Unit::pluck('code')->all();
        $defs = array_values(array_filter($defs, fn ($d) => ! in_array($d[0], $taken, true)));

        $out = [];
        foreach ($defs as $k => [$code, $name, $category, $plate]) {
            $unit = Unit::create(['code' => $code, 'name' => $name, 'category' => $category, 'plate' => $plate, 'notes' => 'DUMMY', 'is_active' => ! isset($special[$code]['inactive_days_ago'])]);
            $out[] = ['unit' => $unit, 'profile' => ($special[$code] ?? []) + [
                'fail' => $category === 'LV' ? 0.07 + mt_rand(0, 6) / 100 : 0.10 + mt_rand(0, 6) / 100,
                'critical' => 0.18, 'comply' => 0.9 + mt_rand(0, 8) / 100,
                'drivers' => [self::DRIVERS[$k % 12], self::DRIVERS[($k + 5) % 12]],
                'odometer' => mt_rand(18000, 95000),
                'km_per_shift' => $category === 'LV' ? mt_rand(40, 120) : mt_rand(20, 70),
            ]];
        }

        return $out;
    }

    private function inspections(array $units, array $versions): void
    {
        $now = now();
        $start = today()->subDays(self::DAYS - 1);

        foreach ($units as ['unit' => $unit, 'profile' => $p]) {
            $version = $versions[$unit->category];
            $items = $version->items()->get();
            $odometer = $p['odometer'];
            $lastIndex = ['D' => null, 'N' => null];
            $madeToday = [];

            for ($d = 0; $d < self::DAYS; $d++) {
                $day = $start->copy()->addDays($d);
                $daysAgo = self::DAYS - 1 - $d;

                if (isset($p['stop_days_ago']) && $daysAgo < $p['stop_days_ago']) {
                    break;
                }
                if (isset($p['inactive_days_ago']) && $daysAgo < $p['inactive_days_ago']) {
                    break;
                }

                foreach (['D' => [6, 9], 'N' => [18, 21]] as $shift => [$h1, $h2]) {
                    $at = $day->copy()->setTime(mt_rand($h1, $h2), mt_rand(0, 59));
                    if ($at->gt($now)) {
                        continue;
                    }
                    $weekend = $day->isWeekend() ? 0.85 : 1.0;
                    $comply = $p['comply'] * $weekend * ($shift === 'N' ? 0.88 : 1.0);
                    $forced = ($p['unfit_now'] ?? false) && $daysAgo === 0 && $shift === 'D';
                    if (! $forced && mt_rand(0, 999) / 1000 > $comply) {
                        continue;
                    }

                    $odometer += $p['km_per_shift'] + mt_rand(-15, 25);
                    $this->makeInspection($unit, $version, $items, $p, $at, $shift, $odometer, $forced, $now);
                }
            }
        }

        // Jadwal servis: sebagian unit sudah dekat/lewat, sebagian masih jauh.
        $i = 0;
        foreach ($units as ['unit' => $unit]) {
            if ($unit->category !== 'LV' && $unit->category !== 'HD') {
                continue;
            }
            $odo = $unit->lastOdometer();
            if ($odo === null) {
                continue;
            }
            $interval = $unit->category === 'LV' ? 5000 : 10000;
            $left = [-300, 150, 420, 1800, 3200, 4600][$i++ % 6];
            $unit->update(['service_interval_km' => $interval, 'last_service_km' => max($odo + $left - $interval, 0)]);
        }
    }

    private function makeInspection(Unit $unit, ChecklistVersion $version, $items, array $p, Carbon $at, string $shift, int $odometer, bool $forced, Carbon $now): void
    {
        // Item yang rusak: biasanya 0, kadang 1-3; item yang sering rusak lebih mungkin terpilih.
        $bad = [];
        if ($forced || mt_rand(0, 999) / 1000 < $p['fail']) {
            $count = $forced ? mt_rand(1, 2) : [1, 1, 1, 2, 2, 3][mt_rand(0, 5)];
            $wantCritical = $forced || mt_rand(0, 999) / 1000 < $p['critical'];
            $pool = $items->filter(fn ($it) => $wantCritical ? true : ! $it->is_critical)->values();
            if ($wantCritical) {
                $crit = $items->where('is_critical', true)->values();
                $bad[] = $crit[mt_rand(0, $crit->count() - 1)]->id;
            }
            for ($n = count($bad); $n < $count && $pool->isNotEmpty(); $n++) {
                $hot = $pool->filter(fn ($it) => collect(self::HOT_ITEMS)->contains(fn ($h) => str_contains($it->label, $h)))->values();
                $pick = ($hot->isNotEmpty() && mt_rand(0, 99) < 55 ? $hot : $pool)->random();
                $bad[] = $pick->id;
            }
            $bad = array_values(array_unique($bad));
        }

        $badItems = $items->whereIn('id', $bad);
        $critical = $badItems->where('is_critical', true)->count();
        $badCount = $badItems->count();
        $driver = mt_rand(0, 99) < 70 ? $p['drivers'][0] : (mt_rand(0, 99) < 50 ? $p['drivers'][1] : self::DRIVERS[mt_rand(0, 11)]);
        $driverNo = array_search($driver, self::DRIVERS, true);

        $inspection = Inspection::create([
            'number' => 'P2H-'.preg_replace('/[^A-Z0-9]/', '', $unit->code).'-'.$at->format('Ymd').'-'.$shift,
            'unit_id' => $unit->id, 'unit_code' => $unit->code, 'unit_name' => $unit->name, 'unit_plate' => $unit->plate,
            'checklist_version_id' => $version->id, 'checklist_version_name' => $version->name,
            'inspected_at' => $at, 'shift' => $shift, 'odometer' => $odometer,
            'fuel_level' => ['Penuh', 'Penuh', '3/4', '3/4', '1/2', '1/4', 'Hampir habis'][mt_rand(0, 6)],
            'status' => $critical > 0 ? 'tidak_layak' : 'layak', 'bad_count' => $badCount, 'critical_bad_count' => $critical,
            'notes' => mt_rand(0, 9) === 0 ? ['Unit habis dicuci', 'Pakai untuk hauling', 'Perlu cek ban belakang', 'Cuaca hujan, jalan licin'][mt_rand(0, 3)] : null,
            'declared_at' => $at,
            'driver_portal_id' => (string) (501 + $driverNo), 'driver_name' => $driver, 'driver_nik' => '326001'.str_pad((string) (10 + $driverNo), 2, '0', STR_PAD_LEFT),
            'follow_up_status' => $badCount > 0 ? 'open' : 'none',
        ]);

        DB::table('inspections')->where('id', $inspection->id)->update(['created_at' => $at, 'updated_at' => $at]);

        DB::table('inspection_answers')->insert($items->map(fn ($it) => [
            'inspection_id' => $inspection->id, 'group_name' => $it->group_name, 'label' => $it->label, 'is_critical' => $it->is_critical,
            'result' => in_array($it->id, $bad, true) ? 'bad' : 'ok',
            'note' => in_array($it->id, $bad, true) ? self::BAD_NOTES[mt_rand(0, count(self::BAD_NOTES) - 1)] : null,
            'sort_order' => $it->sort_order,
        ])->all());

        if ($badCount > 0) {
            $this->followUp($inspection, $critical > 0, $now, $forced);
        }
    }

    /** Menentukan nasib temuan menurut umurnya: yang lama hampir semua selesai, yang baru masih terbuka. */
    private function followUp(Inspection $inspection, bool $critical, Carbon $now, bool $forceOpen): void
    {
        $ageDays = $inspection->inspected_at->diffInHours($now) / 24;
        $roll = mt_rand(0, 99);

        $done = match (true) {
            $forceOpen => false,
            $ageDays > 5 => $roll < 94,
            $ageDays > 2 => $roll < 60,
            default => $roll < 15,
        };
        $started = $done || match (true) {
            $forceOpen => false,
            $ageDays > 5 => true,
            $ageDays > 2 => $roll < 85,
            default => $roll < 40,
        };

        $supervisor = self::SUPERVISORS[mt_rand(0, count(self::SUPERVISORS) - 1)];
        $pic = self::WORKSHOPS[mt_rand(0, count(self::WORKSHOPS) - 1)];
        $logs = [];

        if ($started) {
            $startAt = $inspection->inspected_at->copy()->addHours(mt_rand(1, $critical ? 8 : 24));
            $due = $startAt->copy()->addDays(mt_rand(1, 4))->startOfDay();
            $logs[] = ['status' => 'in_progress', 'note' => 'Dijadwalkan perbaikan', 'pic' => $pic, 'due_date' => $due->toDateString(), 'by_name' => $supervisor, 'created_at' => $startAt];
            $update = ['follow_up_status' => 'in_progress', 'follow_up_pic' => $pic, 'follow_up_due' => $due->toDateString()];

            if ($done) {
                $doneAt = $inspection->inspected_at->copy()->addHours($critical ? mt_rand(4, 48) : mt_rand(12, 96));
                $doneAt = $doneAt->gt($startAt) ? $doneAt : $startAt->copy()->addHours(2);
                if ($doneAt->gt($now)) {
                    $doneAt = $now->copy()->subMinutes(mt_rand(5, 120));
                }
                $note = ['Sudah diganti di workshop', 'Sudah diperbaiki dan diuji', 'Komponen diganti baru', 'Dilengkapi dan dicek ulang'][mt_rand(0, 3)];
                $logs[] = ['status' => 'done', 'note' => $note, 'pic' => null, 'due_date' => null, 'by_name' => $supervisor, 'created_at' => $doneAt];
                $update = $update + ['follow_up_status' => 'done', 'follow_up_by_name' => $supervisor, 'follow_up_at' => $doneAt, 'follow_up_note' => $note];
                $update['follow_up_status'] = 'done';
            }
            DB::table('inspections')->where('id', $inspection->id)->update($update);
        }

        foreach ($logs as $log) {
            DB::table('inspection_follow_ups')->insert($log + ['inspection_id' => $inspection->id]);
        }
    }
}
