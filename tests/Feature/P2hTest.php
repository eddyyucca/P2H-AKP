<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Unit;
use App\Support\DefaultChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P2hTest extends TestCase
{
    use RefreshDatabase;

    private ChecklistVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'p2h', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);

        // Migrasi sudah membuat checklist LV bawaan; RefreshDatabase menjalankannya di tiap tes.
        $this->version = ChecklistVersion::activeFor('LV');
    }

    private function as(string $role, string $id = '2', string $name = 'Budi Santoso', string $nik = '32600002'): static
    {
        return $this->loginPortal(['id' => $id, 'name' => $name, 'employee_no' => $nik, 'email' => "u$id@example.test", 'role' => $role, 'level' => 10]);
    }

    private function unit(string $code = 'LV-12', array $extra = []): Unit
    {
        return Unit::create($extra + ['code' => $code, 'name' => 'Double cabin 4x4', 'category' => 'LV', 'plate' => 'DT 8123 KC', 'is_active' => true]);
    }

    /** Jawaban semua item "ok", kecuali yang ditimpa. @param array<string,string> $bad label => keterangan */
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

        return $extra + ['unit' => $unit->code, 'confirm' => '1', 'shift' => 'D', 'odometer' => 48215, 'fuel_level' => '3/4', 'answers' => $answers, 'answer_notes' => $notes];
    }

    // --- Dasar ---

    public function test_default_lv_checklist_matches_the_mobile_prototype(): void
    {
        $this->assertSame('Versi Sep 2026', $this->version->name);
        $this->assertSame(16, $this->version->items->count());
        $this->assertSame(['DOKUMEN', 'EKSTERIOR', 'MESIN', 'KESELAMATAN'], $this->version->items->pluck('group_name')->unique()->values()->all());
        $this->assertTrue($this->version->items->firstWhere('label', 'Rem & rem tangan')->is_critical);
        $this->assertFalse($this->version->items->firstWhere('label', 'Kotak P3K')->is_critical);
        $this->assertNull(DefaultChecklist::seed('LV', 'Lagi'), 'tidak membuat ganda');
    }

    public function test_guests_go_to_portal_and_unknown_levels_are_refused(): void
    {
        foreach (['/', '/p2h', '/p2h/isi', '/p2h/riwayat', '/p2h/unit'] as $path) {
            $res = $this->get($path);
            $res->assertRedirect();
            $this->assertStringStartsWith('http://portal.test/login', $res->headers->get('Location'), $path);
        }
        $this->get('/up')->assertOk();
        $this->as('tamu')->get('/p2h')->assertForbidden();
    }

    public function test_every_level_can_open_the_dashboard(): void
    {
        foreach (['admin', 'pengawas', 'operator', 'viewer'] as $role) {
            $this->as($role)->get('/p2h')->assertOk()->assertSee('Dashboard P2H');
        }
        $this->as('operator')->get('/p2h')->assertSee('Cara mengisi P2H');
        $this->as('pengawas')->get('/p2h')->assertSee('Unit belum P2H hari ini');
    }

    // --- Mengisi P2H ---

    public function test_viewers_cannot_fill(): void
    {
        $unit = $this->unit();
        $this->as('viewer')->get('/p2h/isi')->assertForbidden();
        $this->as('viewer')->post('/p2h/isi', $this->payload($unit))->assertForbidden();
        $this->assertSame(0, Inspection::count());
    }

    public function test_the_unit_link_opens_the_form_for_that_unit(): void
    {
        $this->unit('LV-12');
        $this->unit('LV-99', ['is_active' => false]);

        $this->as('operator')->get('/p2h/isi?unit=lv-12')->assertOk()->assertSee('Rem &amp; rem tangan', false)->assertSee('Odometer');
        $this->as('operator')->get('/p2h/isi')->assertOk()->assertSee('Pilih unit yang diperiksa')->assertSee('LV-12')->assertDontSee('LV-99');
        $this->as('operator')->get('/p2h/isi?unit=LV-99')->assertOk()->assertSee('tidak ditemukan atau tidak aktif');
        $this->as('operator')->get('/p2h/isi?unit=ZZ-1')->assertOk()->assertSee('tidak ditemukan atau tidak aktif');
    }

    public function test_all_good_makes_the_unit_fit_and_records_the_inspector(): void
    {
        $unit = $this->unit();

        $res = $this->as('operator')->post('/p2h/isi', $this->payload($unit, [], ['notes' => 'Aman']));

        $inspection = Inspection::firstOrFail();
        $res->assertRedirect(route('p2h.show', $inspection));

        $this->assertSame('layak', $inspection->status);
        $this->assertSame(0, $inspection->bad_count);
        $this->assertSame('none', $inspection->follow_up_status);
        $this->assertSame('Budi Santoso', $inspection->driver_name);
        $this->assertSame('32600002', $inspection->driver_nik);
        $this->assertSame('2', $inspection->driver_portal_id);
        $this->assertSame(48215, $inspection->odometer);
        $this->assertSame('3/4', $inspection->fuel_level);
        $this->assertSame('Versi Sep 2026', $inspection->checklist_version_name);
        $this->assertSame(16, $inspection->answers()->count());
        $this->assertMatchesRegularExpression('/^P2H-LV12-\d{8}-D$/', $inspection->number);

        $this->as('operator')->get(route('p2h.show', $inspection))->assertOk()->assertSee('LAYAK')->assertSee('boleh digunakan');
    }

    public function test_a_broken_critical_item_makes_the_unit_unfit(): void
    {
        $unit = $this->unit();

        $this->as('operator')->post('/p2h/isi', $this->payload($unit, ['Rem & rem tangan' => 'Rem blong']))->assertRedirect();

        $i = Inspection::firstOrFail();
        $this->assertSame('tidak_layak', $i->status);
        $this->assertSame(1, $i->critical_bad_count);
        $this->assertSame('open', $i->follow_up_status);
        $this->assertSame('Rem blong', $i->answers()->where('result', 'bad')->value('note'));

        $this->as('operator')->get(route('p2h.show', $i))->assertSee('TIDAK LAYAK')->assertSee('tidak boleh digunakan');
    }

    public function test_a_broken_non_critical_item_is_a_finding_but_the_unit_stays_fit(): void
    {
        $unit = $this->unit();

        $this->as('operator')->post('/p2h/isi', $this->payload($unit, ['Kotak P3K' => 'Isi kurang']))->assertRedirect();

        $i = Inspection::firstOrFail();
        $this->assertSame('layak', $i->status);
        $this->assertSame(1, $i->bad_count);
        $this->assertSame(0, $i->critical_bad_count);
        $this->assertSame('open', $i->follow_up_status);
    }

    public function test_incomplete_or_invalid_submissions_are_rejected(): void
    {
        $unit = $this->unit();
        $full = $this->payload($unit);

        // satu item tidak diisi
        $missing = $full;
        unset($missing['answers'][$this->version->items->first()->id]);
        $this->as('operator')->post('/p2h/isi', $missing)->assertSessionHasErrors();

        // rusak tanpa keterangan
        $noNote = $this->payload($unit);
        $id = $this->version->items->firstWhere('label', 'Kaca & wiper')->id;
        $noNote['answers'][$id] = 'bad';
        $this->as('operator')->post('/p2h/isi', $noNote)->assertSessionHasErrors("answer_notes.$id");

        // odometer dan BBM wajib, nilai tidak sah ditolak
        $this->as('operator')->post('/p2h/isi', ['odometer' => '', 'fuel_level' => ''] + $full)->assertSessionHasErrors(['odometer', 'fuel_level']);
        $this->as('operator')->post('/p2h/isi', ['fuel_level' => 'Kosong melompong'] + $full)->assertSessionHasErrors('fuel_level');
        $this->as('operator')->post('/p2h/isi', ['shift' => 'X'] + $full)->assertSessionHasErrors('shift');
        $bogus = $full;
        $bogus['answers'][$this->version->items->first()->id] = 'mungkin';
        $this->as('operator')->post('/p2h/isi', $bogus)->assertSessionHasErrors();

        $this->assertSame(0, Inspection::count());
    }

    public function test_inactive_or_unknown_units_cannot_be_inspected(): void
    {
        $inactive = $this->unit('LV-50', ['is_active' => false]);

        $this->as('operator')->post('/p2h/isi', $this->payload($inactive))->assertStatus(422);
        $this->as('operator')->post('/p2h/isi', ['unit' => 'TIDAK-ADA'] + $this->payload($inactive))->assertStatus(422);
        $this->assertSame(0, Inspection::count());
    }

    public function test_re_inspection_in_the_same_shift_gets_a_suffix(): void
    {
        $unit = $this->unit();

        $this->as('operator')->post('/p2h/isi', $this->payload($unit, ['Rem & rem tangan' => 'Rem blong']));
        $this->as('operator')->post('/p2h/isi', $this->payload($unit));
        $this->as('operator')->post('/p2h/isi', $this->payload($unit));

        $numbers = Inspection::orderBy('id')->pluck('number')->all();
        $base = $numbers[0];
        $this->assertSame([$base, $base.'-2', $base.'-3'], $numbers);
    }

    public function test_history_keeps_its_own_copy_when_the_checklist_changes_later(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit, ['Kotak P3K' => 'Kosong']));
        $i = Inspection::firstOrFail();

        ChecklistItem::where('label', 'Kotak P3K')->update(['label' => 'Nama baru', 'is_critical' => true]);
        Unit::where('id', $unit->id)->update(['name' => 'Nama unit baru', 'plate' => 'XX 1 YY']);

        $this->as('admin', '1', 'Admin')->get(route('p2h.show', $i))->assertSee('Kotak P3K')->assertDontSee('Nama baru')->assertSee('Double cabin 4x4')->assertDontSee('Nama unit baru');
    }

    // --- Siapa melihat apa ---

    public function test_operators_only_see_their_own_inspections(): void
    {
        $unit = $this->unit();
        $this->as('operator', '2', 'Budi')->post('/p2h/isi', $this->payload($unit));
        $this->as('operator', '3', 'Sari', '32600003')->post('/p2h/isi', $this->payload($unit));
        [$budi, $sari] = [Inspection::where('driver_portal_id', '2')->first(), Inspection::where('driver_portal_id', '3')->first()];

        $this->as('operator', '2', 'Budi')->get('/p2h/riwayat')->assertSee($budi->number)->assertDontSee($sari->number);
        $this->as('operator', '2', 'Budi')->get(route('p2h.show', $budi))->assertOk();
        $this->as('operator', '2', 'Budi')->get(route('p2h.show', $sari))->assertNotFound();

        foreach (['admin', 'pengawas', 'viewer'] as $role) {
            $this->as($role, '9')->get('/p2h/riwayat')->assertSee($budi->number)->assertSee($sari->number);
            $this->as($role, '9')->get(route('p2h.show', $sari))->assertOk();
        }
    }

    public function test_history_filters(): void
    {
        $a = $this->unit('LV-12');
        $b = $this->unit('LV-13');
        $this->as('operator')->post('/p2h/isi', $this->payload($a));
        $this->as('operator')->post('/p2h/isi', $this->payload($b, ['Rem & rem tangan' => 'Rem blong'], ['shift' => 'N']));

        $this->as('admin')->get('/p2h/riwayat?status=tidak_layak')->assertSee('LV-13')->assertDontSee('LV-12');
        $this->as('admin')->get('/p2h/riwayat?status=layak')->assertSee('LV-12')->assertDontSee('LV-13');
        $this->as('admin')->get('/p2h/riwayat?shift=N')->assertSee('LV-13')->assertDontSee('LV-12');
        $this->as('admin')->get('/p2h/riwayat?follow_up=open')->assertSee('LV-13')->assertDontSee('LV-12');
        $this->as('admin')->get('/p2h/riwayat?q=LV12')->assertSee('LV-12')->assertDontSee('LV-13');
        $this->as('admin')->get('/p2h/riwayat?from='.today()->addDay()->toDateString())->assertDontSee('LV-12');
        $this->as('admin')->get('/p2h/riwayat?q=%25')->assertOk()->assertDontSee('LV-12');
    }

    public function test_dashboard_lists_units_not_inspected_today_and_open_findings(): void
    {
        $done = $this->unit('LV-12');
        $this->unit('LV-13');
        $this->as('operator')->post('/p2h/isi', $this->payload($done, ['Kotak P3K' => 'Kosong']));

        $this->as('pengawas')->get('/p2h')->assertOk()->assertSee('LV-13')->assertSee('1 dari 2');
        $this->as('pengawas')->get('/p2h')->assertSee('Temuan menunggu');
    }

    // --- Tindak lanjut ---

    public function test_supervisors_close_findings_with_a_note_and_timestamp(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit, ['Rem & rem tangan' => 'Rem blong']));
        $i = Inspection::firstOrFail();

        $this->as('operator')->post(route('p2h.followup', $i), ['follow_up_note' => 'Sudah'])->assertForbidden();
        $this->as('viewer')->post(route('p2h.followup', $i), ['follow_up_note' => 'Sudah'])->assertForbidden();
        $this->as('pengawas', '8', 'Pak Pengawas')->post(route('p2h.followup', $i), ['follow_up_note' => ''])->assertSessionHasErrors('follow_up_note');

        $this->as('pengawas', '8', 'Pak Pengawas')->post(route('p2h.followup', $i), ['follow_up_note' => 'Kampas rem diganti di workshop'])->assertRedirect(route('p2h.show', $i));

        $i->refresh();
        $this->assertSame('done', $i->follow_up_status);
        $this->assertSame('Pak Pengawas', $i->follow_up_by_name);
        $this->assertNotNull($i->follow_up_at);
        $this->assertSame('Kampas rem diganti di workshop', $i->follow_up_note);

        // sudah selesai: tidak bisa ditutup dua kali
        $this->as('pengawas', '8')->post(route('p2h.followup', $i), ['follow_up_note' => 'Lagi'])->assertStatus(422);
    }

    public function test_inspections_without_findings_have_no_follow_up_to_close(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit));

        $this->as('pengawas')->post(route('p2h.followup', Inspection::firstOrFail()), ['follow_up_note' => 'Tidak perlu'])->assertStatus(422);
    }

    // --- Unit ---

    public function test_only_admin_manages_units(): void
    {
        foreach (['pengawas', 'operator', 'viewer'] as $role) {
            $this->as($role)->get('/p2h/unit/baru')->assertForbidden();
            $this->as($role)->post('/p2h/unit', ['code' => 'X-1', 'name' => 'X', 'category' => 'LV'])->assertForbidden();
        }
        $this->as('operator')->get('/p2h/unit')->assertForbidden();

        $this->as('admin')->post('/p2h/unit', ['code' => ' lv-20 ', 'name' => 'Hilux', 'category' => 'LV', 'plate' => 'DT 1 A'])->assertRedirect(route('p2h.units'));
        $this->assertSame('LV-20', Unit::firstOrFail()->code);

        $this->as('pengawas')->get('/p2h/unit')->assertOk()->assertSee('LV-20');
        $this->as('viewer')->get('/p2h/unit')->assertOk()->assertDontSee('Tambah unit');
    }

    public function test_unit_validation_and_locked_code(): void
    {
        $unit = $this->unit('LV-12');

        $this->as('admin')->post('/p2h/unit', ['code' => 'lv-12', 'name' => 'Dobel', 'category' => 'LV'])->assertSessionHasErrors('code');
        $this->as('admin')->post('/p2h/unit', ['code' => 'LV 13', 'name' => 'Spasi', 'category' => 'LV'])->assertSessionHasErrors('code');
        $this->as('admin')->post('/p2h/unit', ['code' => 'LV-13', 'name' => 'Tanpa checklist', 'category' => 'EXC'])->assertSessionHasErrors('category');

        $this->as('admin')->put(route('p2h.units.update', $unit), ['code' => 'GANTI-1', 'name' => 'Nama baru', 'category' => 'LV', 'plate' => 'AB 1 CD'])->assertRedirect();
        $unit->refresh();
        $this->assertSame('LV-12', $unit->code, 'kode terkunci');
        $this->assertSame('Nama baru', $unit->name);
    }

    public function test_deactivating_a_unit_keeps_its_history_and_hides_it_from_the_form(): void
    {
        $unit = $this->unit();
        $this->as('operator')->post('/p2h/isi', $this->payload($unit));

        $this->as('admin')->post(route('p2h.units.toggle', $unit))->assertRedirect();
        $this->assertFalse($unit->fresh()->is_active);

        $this->as('operator')->get('/p2h/isi')->assertDontSee('/p2h/isi?unit=LV-12', false);
        $this->as('admin')->get('/p2h/riwayat')->assertSee('LV-12');
    }

    // --- Checklist ---

    public function test_only_admin_edits_checklists_and_others_may_read(): void
    {
        foreach (['pengawas', 'viewer'] as $role) {
            $this->as($role)->get('/p2h/checklist')->assertOk()->assertSee('Rem &amp; rem tangan', false)->assertDontSee('Buat versi baru');
            $this->as($role)->post('/p2h/checklist/versi', ['name' => 'X', 'category' => 'LV'])->assertForbidden();
        }
        $this->as('operator')->get('/p2h/checklist')->assertForbidden();
        $this->as('admin')->get('/p2h/checklist')->assertOk()->assertSee('Buat versi baru');
    }

    public function test_a_new_version_is_a_draft_copy_until_activated(): void
    {
        $this->as('admin')->post('/p2h/checklist/versi', ['name' => 'Versi Nov 2026', 'category' => 'lv', 'copy_from' => $this->version->id])->assertRedirect();

        $draft = ChecklistVersion::where('name', 'Versi Nov 2026')->firstOrFail();
        $this->assertFalse($draft->is_active);
        $this->assertSame(16, $draft->items()->count());
        $this->assertSame($this->version->id, ChecklistVersion::activeFor('LV')->id, 'versi lama tetap dipakai');

        // sunting draf, lalu aktifkan
        $this->as('admin')->post(route('p2h.checklist.item.store', $draft), ['group_name' => 'mesin', 'label' => 'Level oli hidrolik', 'is_critical' => '1'])->assertRedirect();
        $this->assertSame(17, $draft->items()->count());
        $this->assertTrue($draft->items()->where('label', 'Level oli hidrolik')->first()->is_critical);
        $this->assertSame('MESIN', $draft->items()->where('label', 'Level oli hidrolik')->value('group_name'));

        $this->as('admin')->post(route('p2h.checklist.version.activate', $draft))->assertRedirect();
        $this->assertSame($draft->id, ChecklistVersion::activeFor('LV')->id);
        $this->assertFalse($this->version->fresh()->is_active);

        // unit memakai versi baru pada P2H berikutnya
        $unit = $this->unit();
        $this->as('operator')->get('/p2h/isi?unit=LV-12')->assertSee('Level oli hidrolik')->assertSee('Versi Nov 2026');
    }

    public function test_empty_versions_cannot_be_activated_and_active_versions_keep_one_item(): void
    {
        $this->as('admin')->post('/p2h/checklist/versi', ['name' => 'Kosong', 'category' => 'EXC'])->assertRedirect();
        $empty = ChecklistVersion::where('name', 'Kosong')->firstOrFail();

        $this->as('admin')->post(route('p2h.checklist.version.activate', $empty))->assertSessionHas('error');
        $this->assertFalse($empty->fresh()->is_active);

        $single = ChecklistVersion::create(['category' => 'SOLO', 'name' => 'Satu', 'is_active' => true]);
        $item = $single->items()->create(['group_name' => 'A', 'label' => 'Satu-satunya', 'is_critical' => false, 'sort_order' => 1]);
        $this->as('admin')->delete(route('p2h.checklist.item.destroy', $item))->assertSessionHas('error');
        $this->assertSame(1, $single->items()->count());
    }

    public function test_item_edit_and_delete(): void
    {
        $item = $this->version->items->firstWhere('label', 'Kotak P3K');

        $this->as('admin')->put(route('p2h.checklist.item.update', $item), ['group_name' => 'keselamatan', 'label' => 'Kotak P3K lengkap', 'is_critical' => '1', 'sort_order' => 99])->assertRedirect();
        $item->refresh();
        $this->assertSame('Kotak P3K lengkap', $item->label);
        $this->assertTrue($item->is_critical);
        $this->assertSame(99, $item->sort_order);

        $this->as('admin')->delete(route('p2h.checklist.item.destroy', $item))->assertRedirect();
        $this->assertSame(15, $this->version->items()->count());
    }

    public function test_pages_escape_user_text(): void
    {
        $unit = $this->unit('LV-12', ['name' => '<script>alert(1)</script>']);

        $this->as('admin')->get('/p2h/unit')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
