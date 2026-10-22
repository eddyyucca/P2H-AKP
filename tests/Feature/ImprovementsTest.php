<?php

namespace Tests\Feature;

use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Unit;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private ChecklistVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'p2h', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
        $this->version = ChecklistVersion::activeFor('LV');
    }

    private function as(string $role, string $id = '2', string $name = 'Budi Santoso'): static
    {
        return $this->loginPortal(['id' => $id, 'name' => $name, 'employee_no' => '3260000'.$id, 'role' => $role, 'level' => 10]);
    }

    private function unit(string $code = 'LV-12', array $extra = []): Unit
    {
        return Unit::create($extra + ['code' => $code, 'name' => 'Double cabin 4x4', 'category' => 'LV', 'plate' => 'DT 1 A', 'is_active' => true]);
    }

    private function payload(Unit $unit, array $bad = [], array $extra = []): array
    {
        $answers = [];
        $notes = [];
        foreach ($this->version->items as $item) {
            if (isset($bad[$item->label])) {
                $answers[$item->id] = 'bad';
                $notes[$item->id] = $bad[$item->label];
            } else {
                $answers[$item->id] = 'ok';
            }
        }

        return $extra + ['unit' => $unit->code, 'confirm' => '1', 'shift' => 'D', 'odometer' => 1000, 'fuel_level' => 'Penuh', 'answers' => $answers, 'answer_notes' => $notes];
    }

    /** P2H langsung di database dengan waktu bebas. */
    private function inspection(Unit $unit, string $status, \DateTimeInterface $at, array $extra = []): Inspection
    {
        static $n = 0;

        return Inspection::create($extra + [
            'number' => 'X-'.$unit->code.'-'.(++$n), 'unit_id' => $unit->id, 'unit_code' => $unit->code, 'unit_name' => $unit->name,
            'checklist_version_id' => $this->version->id, 'checklist_version_name' => $this->version->name,
            'inspected_at' => $at, 'shift' => 'D', 'odometer' => 1000, 'fuel_level' => 'Penuh', 'status' => $status,
            'bad_count' => $status === 'layak' ? 0 : 1, 'critical_bad_count' => $status === 'tidak_layak' ? 1 : 0,
            'driver_portal_id' => '2', 'driver_name' => 'Pemeriksa', 'driver_nik' => '1',
            'follow_up_status' => $status === 'layak' ? 'none' : 'open',
        ]);
    }

    // --- Dashboard ---

    public function test_compliance_is_tracked_per_required_shift(): void
    {
        $a = $this->unit('LV-1');
        $this->unit('LV-2');
        $this->inspection($a, 'layak', today()->setTime(8, 0), ['shift' => 'D']);
        $this->inspection($a, 'layak', today()->setTime(20, 0), ['shift' => 'N']);
        $this->inspection($this->unit('LV-3'), 'layak', today()->setTime(9, 0), ['shift' => 'D']);

        $this->as('admin')->get('/p2h')->assertViewHas('shiftCompliance', fn ($s) => $s['D']['done'] === 2 && $s['N']['done'] === 1 && $s['D']['total'] === 3 && $s['D']['percent'] === 67);
    }

    public function test_an_unfit_unit_stays_flagged_until_a_later_inspection_even_across_days(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'tidak_layak', today()->subDays(3)->setTime(8, 0));

        $res = $this->as('pengawas')->get('/p2h');
        $res->assertViewHas('fleet', fn ($f) => $f['unfit']->count() === 1 && $f['unfit']->first()->unit->is($unit));
        $res->assertViewHas('alerts', fn ($a) => collect($a)->contains(fn ($x) => str_contains($x['text'], 'TIDAK LAYAK') && str_contains($x['text'], 'LV-1')));

        $this->inspection($unit, 'layak', now());
        $this->as('pengawas')->get('/p2h')->assertViewHas('fleet', fn ($f) => $f['unfit']->isEmpty());
    }

    public function test_units_without_recent_inspections_are_reported_stale(): void
    {
        $this->unit('LV-1');
        $fresh = $this->unit('LV-2');
        $this->inspection($fresh, 'layak', now()->subHours(2));

        $this->as('admin')->get('/p2h')->assertViewHas('fleet', fn ($f) => $f['stale']->pluck('unit.code')->all() === ['LV-1']);
    }

    public function test_custom_range_and_category_filter(): void
    {
        $lv = $this->unit('LV-1');
        $exc = $this->unit('EX-1', ['category' => 'EXC']);
        ChecklistVersion::create(['category' => 'EXC', 'name' => 'Exc', 'is_active' => true]);
        $this->inspection($lv, 'layak', today()->subDays(5)->setTime(8, 0));
        $this->inspection($exc, 'layak', today()->subDays(5)->setTime(9, 0));
        $this->inspection($lv, 'layak', today()->subDays(20)->setTime(9, 0));

        $range = ['dari' => today()->subDays(6)->toDateString(), 'sampai' => today()->toDateString()];
        $this->as('admin')->get('/p2h?'.http_build_query($range))->assertViewHas('days', 7)->assertViewHas('custom', true)
            ->assertViewHas('periodStats', fn ($p) => $p['total'] === 2);
        $this->as('admin')->get('/p2h?'.http_build_query($range + ['kategori' => 'EXC']))->assertViewHas('periodStats', fn ($p) => $p['total'] === 1);

        // rentang terlalu panjang, terbalik, atau di masa depan -> kembali ke bawaan
        $this->as('admin')->get('/p2h?dari=2020-01-01&sampai=2026-12-31')->assertViewHas('custom', false)->assertViewHas('days', 14);
        $this->as('admin')->get('/p2h?dari=2026-10-10&sampai=2026-10-01')->assertViewHas('custom', false);
        $this->as('admin')->get('/p2h?kategori=ngawur')->assertViewHas('category', '');
    }

    public function test_finding_aging_overdue_and_resolution_time(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'layak', now()->subHours(2), ['bad_count' => 1, 'follow_up_status' => 'open']);
        $this->inspection($unit, 'layak', now()->subDays(2), ['bad_count' => 1, 'follow_up_status' => 'in_progress', 'follow_up_due' => today()->subDay()]);
        $this->inspection($unit, 'layak', now()->subDays(6), ['bad_count' => 1, 'follow_up_status' => 'open']);
        $this->inspection($unit, 'layak', now()->subHours(30), ['bad_count' => 1, 'follow_up_status' => 'done', 'follow_up_at' => now()->subHours(24)]);

        $this->as('admin')->get('/p2h')->assertViewHas('findingsStats', fn ($f) => $f['aging'] === ['lt1' => 1, 'd1_3' => 1, 'gt3' => 1]
            && $f['overdue'] === 1 && $f['in_progress'] === 1 && $f['done'] === 1 && abs($f['mttr_hours'] - 6.0) < 0.2);
    }

    public function test_fleet_widgets_are_hidden_from_operators(): void
    {
        $this->unit('LV-1');
        $res = $this->as('operator')->get('/p2h');
        $res->assertOk()->assertViewHas('fleet', null)->assertViewHas('heatmap', null)->assertDontSee('Peta P2H unit')->assertDontSee('Unit tidak layak saat ini');
        $this->assertNull($res->viewData('chart')['compliance']);

        $this->as('viewer')->get('/p2h')->assertOk()->assertSee('Peta P2H unit')->assertSee('Tren kepatuhan harian');
    }

    // --- Tindak lanjut ---

    public function test_findings_move_through_in_progress_to_done_with_a_timeline(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit, ['Rem & rem tangan' => 'Rem blong']));
        $i = Inspection::firstOrFail();

        $this->as('operator')->post(route('p2h.followup.start', $i), ['pic' => 'Bengkel A'])->assertForbidden();
        $this->as('pengawas', '8', 'Pak Pengawas')->post(route('p2h.followup.start', $i), ['pic' => ''])->assertSessionHasErrors('pic');
        $this->as('pengawas', '8', 'Pak Pengawas')->post(route('p2h.followup.start', $i), ['pic' => 'Bengkel A', 'due_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('due_date');

        $due = today()->addDays(2)->toDateString();
        $this->as('pengawas', '8', 'Pak Pengawas')->post(route('p2h.followup.start', $i), ['pic' => 'Bengkel A', 'due_date' => $due, 'note' => 'Menunggu kampas'])->assertRedirect(route('p2h.show', $i));
        $i->refresh();
        $this->assertSame('in_progress', $i->follow_up_status);
        $this->assertSame('Bengkel A', $i->follow_up_pic);
        $this->assertSame($due, $i->follow_up_due->toDateString());

        $this->as('pengawas', '8', 'Pak Pengawas')->post(route('p2h.followup', $i), ['follow_up_note' => 'Kampas diganti'])->assertRedirect();
        $this->assertSame('done', $i->fresh()->follow_up_status);

        $this->assertSame(['in_progress', 'done'], $i->followUps()->pluck('status')->all());
        $this->as('admin')->get(route('p2h.show', $i))->assertSee('Menunggu kampas')->assertSee('Kampas diganti')->assertSee('Bengkel A');

        // sudah selesai: tidak bisa dimulai lagi
        $this->as('pengawas', '8')->post(route('p2h.followup.start', $i), ['pic' => 'X'])->assertStatus(422);
    }

    public function test_a_clean_re_inspection_closes_open_findings_of_the_same_unit_only(): void
    {
        $a = $this->unit('LV-12');
        $b = $this->unit('LV-13');
        $this->as('operator')->post('/p2h/isi', $this->payload($a, ['Rem & rem tangan' => 'Rem blong']));
        $this->as('operator')->post('/p2h/isi', $this->payload($b, ['Kotak P3K' => 'Kosong']));
        $this->as('operator')->post('/p2h/isi', $this->payload($a, ['Kotak P3K' => 'Kosong'], ['shift' => 'N']));
        $first = Inspection::where('unit_id', $a->id)->orderBy('id')->first();
        $this->assertSame('open', $first->fresh()->follow_up_status, 'P2H ulang yang masih bertemuan tidak menutup apa pun');

        $this->as('operator')->post('/p2h/isi', $this->payload($a, [], ['shift' => 'N']));
        $clean = Inspection::where('unit_id', $a->id)->latest('id')->first();

        $this->assertSame(2, Inspection::where('unit_id', $a->id)->where('follow_up_status', 'done')->count());
        $this->assertSame($clean->number, $first->fresh()->resolved_by_number);
        $this->assertSame('Sistem', $first->fresh()->follow_up_by_name);
        $this->assertSame('open', Inspection::where('unit_id', $b->id)->first()->follow_up_status, 'unit lain tidak tersentuh');
        $this->as('admin')->get(route('p2h.show', $first))->assertSee('Selesai otomatis');
    }

    public function test_repair_queue_orders_unfit_first_then_oldest_and_filters(): void
    {
        $unit = $this->unit('LV-1');
        $minor = $this->inspection($unit, 'layak', now()->subDays(5), ['bad_count' => 1, 'follow_up_status' => 'open']);
        $unfit = $this->inspection($unit, 'tidak_layak', now()->subDay());
        $late = $this->inspection($unit, 'layak', now()->subDays(2), ['bad_count' => 1, 'follow_up_status' => 'in_progress', 'follow_up_due' => today()->subDay()]);
        $this->inspection($unit, 'layak', now(), ['bad_count' => 1, 'follow_up_status' => 'done']);

        $this->as('viewer')->get('/p2h/perbaikan')->assertOk()
            ->assertViewHas('items', fn ($p) => $p->pluck('id')->all() === [$unfit->id, $minor->id, $late->id])
            ->assertViewHas('counts', fn ($c) => $c[''] === 3 && $c['kritis'] === 1 && $c['terlambat'] === 1 && $c['dikerjakan'] === 1 && $c['menunggu'] === 2);
        $this->as('admin')->get('/p2h/perbaikan?filter=terlambat')->assertViewHas('items', fn ($p) => $p->pluck('id')->all() === [$late->id]);
        $this->as('admin')->get('/p2h/perbaikan?filter=kritis')->assertViewHas('items', fn ($p) => $p->pluck('id')->all() === [$unfit->id]);

        $this->as('operator')->get('/p2h/perbaikan')->assertForbidden();
    }

    // --- Kualitas data ---

    public function test_the_inspector_must_declare_the_result_true(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', ['confirm' => ''] + $this->payload($unit))->assertSessionHasErrors('confirm');
        $this->assertSame(0, Inspection::count());

        $this->as('operator')->post('/p2h/isi', $this->payload($unit))->assertRedirect();
        $this->assertNotNull(Inspection::firstOrFail()->declared_at);
    }

    public function test_odometer_cannot_go_backwards_and_big_jumps_need_confirmation(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit, [], ['odometer' => 50000]))->assertRedirect();

        $this->as('operator')->post('/p2h/isi', $this->payload($unit, [], ['odometer' => 49000]))->assertSessionHasErrors('odometer');
        $this->as('operator')->post('/p2h/isi', $this->payload($unit, [], ['odometer' => 53000]))->assertSessionHasErrors('odometer')->assertSessionHas('odometer_jump', 3000);
        $this->assertSame(1, Inspection::count());

        $this->as('operator')->post('/p2h/isi', $this->payload($unit, [], ['odometer' => 53000, 'odometer_confirm' => '1']))->assertRedirect();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit, [], ['odometer' => 53000]))->assertRedirect(); // sama persis boleh
        $this->assertSame(3, Inspection::count());
        $this->assertSame(53000, $unit->lastOdometer());
    }

    public function test_a_repeated_form_token_does_not_create_a_second_inspection(): void
    {
        $unit = $this->unit();
        $payload = $this->payload($unit, [], ['form_token' => str_repeat('ab', 16)]);

        $first = $this->as('operator')->post('/p2h/isi', $payload);
        $second = $this->as('operator')->post('/p2h/isi', $payload);

        $this->assertSame(1, Inspection::count());
        $first->assertRedirect(route('p2h.show', Inspection::first()));
        $second->assertRedirect(route('p2h.show', Inspection::first()));
    }

    public function test_numbers_are_unique_under_collision(): void
    {
        $unit = $this->unit();
        // Menyiapkan baris dengan nomor persis seperti yang akan dihitung: layanan harus memilih nomor berikutnya.
        $base = 'P2H-LV12-'.now()->format('Ymd').'-D';
        $this->inspection($unit, 'layak', now(), ['number' => $base]);
        $this->inspection($unit, 'layak', now(), ['number' => $base.'-2']);

        $this->as('operator')->post('/p2h/isi', $this->payload($unit))->assertRedirect();
        $this->assertSame($base.'-3', Inspection::latest('id')->value('number'));
    }

    public function test_damage_photos_are_compressed_stored_privately_and_access_checked(): void
    {
        Storage::fake('local');
        $unit = $this->unit();
        $broken = $this->version->items->firstWhere('label', 'Kotak P3K');

        $payload = $this->payload($unit, ['Kotak P3K' => 'Kosong']);
        $payload['photos'] = [$broken->id => UploadedFile::fake()->image('rusak.jpg', 2400, 1800)];
        $this->as('operator', '2')->post('/p2h/isi', $payload)->assertRedirect();

        $answer = Inspection::firstOrFail()->answers()->where('result', 'bad')->firstOrFail();
        $this->assertNotNull($answer->photo_path);
        Storage::disk('local')->assertExists($answer->photo_path);
        if (function_exists('imagecreatefromstring')) {
            [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($answer->photo_path));
            $this->assertSame(1280, max($w, $h), 'dikecilkan ke sisi terpanjang 1280 px');
        }

        $this->as('operator', '2')->get(route('p2h.photo', $answer->id))->assertOk();
        $this->as('admin', '9')->get(route('p2h.photo', $answer->id))->assertOk();
        $this->as('operator', '3')->get(route('p2h.photo', $answer->id))->assertNotFound();
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $unit = $this->unit();
        $broken = $this->version->items->firstWhere('label', 'Kotak P3K');

        $payload = $this->payload($unit, ['Kotak P3K' => 'Kosong']);
        $payload['photos'] = [$broken->id => UploadedFile::fake()->create('virus.php', 10, 'application/x-php')];
        $this->as('operator')->post('/p2h/isi', $payload)->assertSessionHasErrors("photos.{$broken->id}");
        $this->assertSame(0, Inspection::count());
    }

    public function test_unprocessable_requests_get_a_friendly_page(): void
    {
        $unit = $this->unit('LV-50', ['is_active' => false]);

        $this->as('operator')->post('/p2h/isi', $this->payload($unit))->assertStatus(422)->assertSee('Tidak dapat diproses')->assertSee('tidak ditemukan atau tidak aktif');
    }

    // --- Laporan ---

    public function test_history_export_follows_filters_and_visibility(): void
    {
        $unit = $this->unit();
        $this->as('operator', '2', 'Budi')->post('/p2h/isi', $this->payload($unit));
        $this->as('operator', '3', 'Sari')->post('/p2h/isi', $this->payload($unit, ['Rem & rem tangan' => 'Rem blong']));

        $admin = $this->as('admin', '9')->get('/p2h/riwayat/ekspor');
        $admin->assertOk();
        $csv = $admin->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Budi', $csv);
        $this->assertStringContainsString('TIDAK LAYAK', $csv);

        $only = $this->as('admin', '9')->get('/p2h/riwayat/ekspor?status=tidak_layak')->streamedContent();
        $this->assertStringNotContainsString('Budi', $only);
        $this->assertStringContainsString('Sari', $only);

        $mine = $this->as('operator', '2', 'Budi')->get('/p2h/riwayat/ekspor')->streamedContent();
        $this->assertStringContainsString('Budi', $mine);
        $this->assertStringNotContainsString('Sari', $mine);
    }

    public function test_csv_cells_cannot_inject_formulas(): void
    {
        $this->assertSame("'=SUM(A1)", Csv::cell('=SUM(A1)'));
        $this->assertSame("'@cmd", Csv::cell('@cmd'));
        $this->assertSame('-5', Csv::cell('-5'));
        $this->assertSame('LV-12', Csv::cell('LV-12'));
        $this->assertSame('', Csv::cell(null));
    }

    public function test_printable_sheet_respects_visibility(): void
    {
        $unit = $this->unit();
        $this->as('operator', '2')->post('/p2h/isi', $this->payload($unit, ['Kotak P3K' => 'Kosong']));
        $i = Inspection::firstOrFail();

        $this->as('operator', '2')->get(route('p2h.print', $i))->assertOk()->assertSee('LEMBAR P2H')->assertSee($i->number)->assertSee('Kotak P3K');
        $this->as('operator', '3')->get(route('p2h.print', $i))->assertNotFound();
    }

    public function test_monthly_report_recap_and_exports(): void
    {
        $a = $this->unit('LV-1');
        $this->unit('LV-2');
        $this->unit('LV-9', ['is_active' => false]);
        $month = today()->startOfMonth();
        $this->inspection($a, 'layak', $month->copy()->setTime(8, 0), ['odometer' => 1000]);
        $this->inspection($a, 'tidak_layak', $month->copy()->setTime(20, 0), ['odometer' => 1250, 'shift' => 'N']);

        $res = $this->as('viewer')->get('/p2h/laporan?bulan='.$month->format('Y-m'));
        $res->assertOk()->assertSee('LV-1')->assertSee('LV-2')->assertDontSee('LV-9');
        $res->assertViewHas('rows', fn ($r) => $r->firstWhere('code', 'LV-1')['total'] === 2 && $r->firstWhere('code', 'LV-1')['distance'] === 250
            && $r->firstWhere('code', 'LV-1')['unfit'] === 1 && $r->firstWhere('code', 'LV-2')['total'] === 0);

        $recap = $this->as('viewer')->get('/p2h/laporan/rekap.csv?bulan='.$month->format('Y-m'))->streamedContent();
        $this->assertStringContainsString('LV-1;', $recap);
        $this->assertStringContainsString('Kepatuhan', $recap);

        $this->as('admin')->get('/p2h/laporan/temuan.csv')->assertOk();
        $this->as('operator')->get('/p2h/laporan')->assertForbidden();
        $this->as('operator')->get('/p2h/laporan/rekap.csv')->assertForbidden();
        $this->as('admin')->get('/p2h/laporan?bulan=ngawur')->assertOk();
    }

    // --- Master unit ---

    public function test_units_can_be_imported_from_csv(): void
    {
        $this->unit('LV-1');
        $csv = "kode;nama;kategori;plat\nlv-101;Double cabin;LV;DT 1 A\nLV-1;Duplikat;LV;\nXX 9;Kode salah;LV;\nLV-103;Kategori asing;ZZZ;\nLV-104;Tanpa kategori;;DT 4 D\n";
        $file = UploadedFile::fake()->createWithContent('unit.csv', $csv);

        $this->as('operator')->post('/p2h/unit/impor', ['file' => $file])->assertForbidden();
        $this->as('admin')->post('/p2h/unit/impor', ['file' => $file])->assertRedirect(route('p2h.units.import'));

        $this->assertSame(['LV-1', 'LV-101', 'LV-104'], Unit::orderBy('code')->pluck('code')->all());
        $this->assertSame('LV', Unit::where('code', 'LV-104')->value('category'));

        $result = $this->as('admin')->get('/p2h/unit/impor')->assertOk()->viewData('categories');
        $this->assertTrue($result->contains('LV'));
        $this->as('admin')->get('/p2h/unit/impor/contoh.csv')->assertOk();
        $this->as('admin')->post('/p2h/unit/impor', [])->assertSessionHasErrors('file');
    }

    public function test_service_reminders_follow_the_odometer(): void
    {
        $unit = $this->unit('LV-1', ['service_interval_km' => 5000, 'last_service_km' => 10000]);
        $this->assertSame(250, Unit::serviceRemaining(5000, 10000, 14750));
        $this->assertSame(-100, Unit::serviceRemaining(5000, 10000, 15100));
        $this->assertNull(Unit::serviceRemaining(null, 0, 100));

        $this->inspection($unit, 'layak', now(), ['odometer' => 14800]);
        $this->as('admin')->get('/p2h')->assertViewHas('fleet', fn ($f) => $f['service']->count() === 1 && $f['service']->first()->left === 200)
            ->assertSee('jadwal servis');
        $this->as('admin')->get('/p2h/unit')->assertSee('Servis 200 km lagi');

        $this->as('pengawas')->post(route('p2h.units.serviced', $unit))->assertForbidden();
        $this->as('admin')->post(route('p2h.units.serviced', $unit))->assertRedirect();
        $this->assertSame(14800, $unit->fresh()->last_service_km);
        $this->as('admin')->get('/p2h')->assertViewHas('fleet', fn ($f) => $f['service']->isEmpty());

        $this->as('admin')->put(route('p2h.units.update', $unit), ['name' => 'X', 'category' => 'LV', 'service_interval_km' => 50])->assertSessionHasErrors('service_interval_km');
        $this->as('admin')->put(route('p2h.units.update', $unit), ['name' => 'X', 'category' => 'LV', 'service_interval_km' => 7500, 'last_service_km' => 14000])->assertRedirect();
        $this->assertSame(7500, $unit->fresh()->service_interval_km);
    }

    public function test_sidebar_shows_the_open_findings_badge_to_those_who_see_everything(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'tidak_layak', now());

        $this->as('admin')->get('/p2h')->assertSee('Antrian Perbaikan')->assertSee('Laporan');
        $this->as('operator')->get('/p2h')->assertDontSee('Antrian Perbaikan');
    }

    public function test_pagination_uses_bootstrap_markup_in_indonesian(): void
    {
        $unit = $this->unit('LV-1');
        foreach (range(1, 30) as $n) {
            $this->inspection($unit, 'layak', now()->subMinutes($n));
        }

        $this->as('admin')->get('/p2h/riwayat')->assertOk()
            ->assertSee('class="pagination"', false)->assertSee('page-link', false)
            ->assertSee('Menampilkan')->assertSee('Berikutnya')
            ->assertDontSee('pagination.next')->assertDontSee('Showing')
            ->assertDontSee('tailwind', false);
        $this->as('admin')->get('/p2h/riwayat?page=2')->assertOk()->assertSee('Sebelumnya');
    }
}
