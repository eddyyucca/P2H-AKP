<?php

namespace Tests\Feature;

use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Unit;
use App\Services\InspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'p2h', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
    }

    private function as(string $role, string $id = '2', string $name = 'Budi'): static
    {
        return $this->loginPortal(['id' => $id, 'name' => $name, 'employee_no' => '3260000'.$id, 'role' => $role, 'level' => 10]);
    }

    private function unit(string $code, bool $active = true): Unit
    {
        return Unit::create(['code' => $code, 'name' => 'Unit '.$code, 'category' => 'LV', 'is_active' => $active]);
    }

    /** Membuat P2H langsung di database dengan waktu bebas, agar tren harian bisa diuji. */
    private function inspection(Unit $unit, string $status, \DateTimeInterface $at, string $driverId = '2', int $bad = 0, string $shift = 'D', string $followUp = 'none'): Inspection
    {
        static $n = 0;
        $version = ChecklistVersion::activeFor('LV');

        return Inspection::create([
            'number' => 'T-'.$unit->code.'-'.(++$n), 'unit_id' => $unit->id, 'unit_code' => $unit->code, 'unit_name' => $unit->name,
            'checklist_version_id' => $version->id, 'checklist_version_name' => $version->name,
            'inspected_at' => $at, 'shift' => $shift, 'odometer' => 1000, 'fuel_level' => 'Penuh', 'status' => $status,
            'bad_count' => $bad, 'critical_bad_count' => $status === 'tidak_layak' ? 1 : 0,
            'driver_portal_id' => $driverId, 'driver_name' => 'Pemeriksa '.$driverId, 'driver_nik' => '1',
            'follow_up_status' => $followUp,
        ]);
    }

    private function portalUser(): \Akp\PortalSso\PortalUser
    {
        return \Akp\PortalSso\PortalUser::fromArray(['id' => '2', 'name' => 'Budi', 'employee_no' => '1', 'email' => null, 'role' => 'operator', 'level' => 10, 'is_admin' => false]);
    }

    private function chart($response): array
    {
        return $response->viewData('chart');
    }

    public function test_daily_trend_counts_fit_and_unfit_per_day_and_fills_empty_days(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'layak', today()->setTime(8, 0));
        $this->inspection($unit, 'layak', today()->setTime(9, 0));
        $this->inspection($unit, 'tidak_layak', today()->setTime(10, 0), bad: 1);
        $this->inspection($unit, 'layak', today()->subDays(2)->setTime(8, 0));

        $chart = $this->chart($this->as('admin')->get('/p2h?hari=7'));

        $this->assertCount(7, $chart['labels']);
        $this->assertCount(7, $chart['fit']);
        $this->assertSame([0, 0, 0, 0, 1, 0, 2], $chart['fit'], 'hari kosong terisi 0; hari ini di ujung kanan');
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], $chart['unfit']);
    }

    public function test_period_switch_and_invalid_values(): void
    {
        $this->as('admin')->get('/p2h?hari=30')->assertOk()->assertViewHas('days', 30);
        $this->as('admin')->get('/p2h?hari=7')->assertViewHas('days', 7);
        $this->as('admin')->get('/p2h?hari=999')->assertViewHas('days', 14);
        $this->as('admin')->get('/p2h?hari=abc')->assertViewHas('days', 14);
        $this->assertCount(30, $this->chart($this->as('admin')->get('/p2h?hari=30'))['labels']);
    }

    public function test_inspections_outside_the_period_are_not_counted(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'layak', today()->subDays(20)->setTime(8, 0));

        $res = $this->as('admin')->get('/p2h?hari=7');
        $this->assertSame(0, array_sum($this->chart($res)['fit']));
        $res->assertViewHas('periodStats', fn ($p) => $p['total'] === 0 && $p['fit_rate'] === null);

        $this->as('admin')->get('/p2h?hari=30')->assertViewHas('periodStats', fn ($p) => $p['total'] === 1 && $p['fit_rate'] === 100);
    }

    public function test_compliance_and_unit_status_for_today(): void
    {
        $a = $this->unit('LV-1');
        $b = $this->unit('LV-2');
        $this->unit('LV-3');
        $this->unit('LV-9', active: false);

        $this->inspection($a, 'layak', today()->setTime(7, 0));
        $this->inspection($b, 'layak', today()->setTime(7, 30));
        $this->inspection($b, 'tidak_layak', today()->setTime(9, 0), bad: 1, followUp: 'open'); // P2H terakhir LV-2 hari ini

        $res = $this->as('pengawas')->get('/p2h');

        $res->assertViewHas('stats', fn ($s) => $s['units_active'] === 3 && $s['units_done'] === 2 && $s['compliance'] === 67 && $s['today'] === 3);
        $this->assertSame(['layak' => 1, 'tidak_layak' => 1, 'belum' => 1], $this->chart($res)['unitStatus']);
        $res->assertSee('LV-3')->assertDontSee('LV-9');
    }

    public function test_compliance_is_empty_when_there_are_no_active_units(): void
    {
        $this->as('admin')->get('/p2h')->assertOk()->assertViewHas('stats', fn ($s) => $s['compliance'] === null)->assertSee('Belum ada unit aktif');
    }

    public function test_most_broken_items_ranked_with_critical_flag(): void
    {
        $unit = $this->unit('LV-1');
        $svc = app(InspectionService::class);
        $version = ChecklistVersion::activeFor('LV');

        foreach ([1, 2, 3] as $_) {
            $this->submit($svc, $unit, $version, ['Rem & rem tangan', 'Kotak P3K']);
        }
        $this->submit($svc, $unit, $version, ['Kotak P3K']);

        $items = $this->chart($this->as('admin')->get('/p2h'))['items'];

        $this->assertSame('Kotak P3K', $items[0]['label']);
        $this->assertSame(4, $items[0]['n']);
        $this->assertFalse($items[0]['critical']);
        $this->assertSame('Rem & rem tangan', $items[1]['label']);
        $this->assertSame(3, $items[1]['n']);
        $this->assertTrue($items[1]['critical']);
    }

    private function submit(InspectionService $svc, Unit $unit, ChecklistVersion $version, array $badLabels): Inspection
    {
        $answers = [];
        $notes = [];
        foreach ($version->items as $item) {
            $answers[$item->id] = in_array($item->label, $badLabels, true) ? 'bad' : 'ok';
            if ($answers[$item->id] === 'bad') {
                $notes[$item->id] = 'rusak';
            }
        }

        return $svc->submit($this->portalUser(), $unit, $version, [
            'shift' => 'D', 'odometer' => 1, 'fuel_level' => 'Penuh', 'notes' => null, 'answers' => $answers, 'answer_notes' => $notes,
        ]);
    }

    public function test_top_units_by_findings_only_for_those_who_see_everything(): void
    {
        $a = $this->unit('LV-1');
        $b = $this->unit('LV-2');
        foreach (range(1, 3) as $i) {
            $this->inspection($a, 'layak', today()->setTime($i, 0), bad: 1, followUp: 'open');
        }
        $this->inspection($b, 'tidak_layak', today()->setTime(5, 0), bad: 2, followUp: 'open');
        $this->inspection($b, 'layak', today()->setTime(6, 0));

        $res = $this->as('admin')->get('/p2h');
        $res->assertViewHas('topUnits', fn ($t) => $t->pluck('unit_code')->all() === ['LV-1', 'LV-2'] && (int) $t->first()->n === 3 && (int) $t->last()->unfit === 1);

        $this->as('operator', '9')->get('/p2h')->assertViewHas('topUnits', fn ($t) => $t->isEmpty());
    }

    public function test_operators_only_see_their_own_numbers_and_no_fleet_data(): void
    {
        $unit = $this->unit('LV-1');
        $this->unit('LV-2');
        $this->inspection($unit, 'layak', today()->setTime(8, 0), driverId: '9');
        $this->inspection($unit, 'tidak_layak', today()->setTime(9, 0), driverId: '3', bad: 1, followUp: 'open');

        $res = $this->as('operator', '9')->get('/p2h');

        $res->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['today'] === 1 && $s['fit'] === 1 && $s['unfit'] === 0 && $s['open'] === 0)
            ->assertViewHas('periodStats', fn ($p) => $p['total'] === 1)
            ->assertViewHas('pending', fn ($p) => $p->isEmpty())
            ->assertDontSee('Kepatuhan P2H hari ini')
            ->assertDontSee('Unit dengan temuan terbanyak');
        $this->assertNull($this->chart($res)['unitStatus']);
        $this->assertSame(1, array_sum($this->chart($res)['fit']) + array_sum($this->chart($res)['unfit']));
    }

    public function test_follow_ups_list_puts_unfit_units_first(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'layak', today()->setTime(10, 0), bad: 1, followUp: 'open');
        $unfit = $this->inspection($unit, 'tidak_layak', today()->setTime(8, 0), bad: 1, followUp: 'open');

        $this->as('pengawas')->get('/p2h')->assertViewHas('followUps', fn ($f) => $f->first()->is($unfit) && $f->count() === 2);
    }

    public function test_shift_split_and_page_renders_for_every_level_even_when_empty(): void
    {
        $unit = $this->unit('LV-1');
        $this->inspection($unit, 'layak', today()->setTime(8, 0), shift: 'D');
        $this->inspection($unit, 'layak', today()->setTime(20, 0), shift: 'N');
        $this->inspection($unit, 'layak', today()->setTime(21, 0), shift: 'N');

        $this->as('admin')->get('/p2h')->assertViewHas('stats', fn ($s) => $s['day'] === 1 && $s['night'] === 2);

        foreach (['admin', 'pengawas', 'operator', 'viewer'] as $role) {
            $this->as($role, '55')->get('/p2h')->assertOk()->assertSee('chart.umd.min.js');
        }
    }
}
