<?php

declare(strict_types=1);

namespace Tests\Feature\FabricPlanning;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric Requirement → Reports (AnnotechERP 13/03): the catalogue as Brickland
 * ships it, each report read live from the plans, capacity, boards and
 * masters, report runs that count the rows for their unit, buyer and week, the
 * workbook — and who may run them.
 */
class FabricPlanningReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $unit;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        CarbonImmutable::setTestNow('2026-10-25 10:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00'));
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reports_read_the_module_live_and_runs_count_their_rows(): void
    {
        $catalogue = $this->as()->getJson('/api/v1/fabric-planning/reports')->assertOk()->json('data');
        $this->assertSame(['FP-BATCH-PLAN', 'FP-UNPLACED', 'FP-MACHINE-LOAD', 'FP-OVER-CAPACITY', 'FP-SCHEDULE', 'FP-ROUTING', 'FP-HOLIDAY', 'FP-BATCH-HOUR'], array_column($catalogue, 'code'));
        $this->assertSame(['Machine Load vs Capacity', 'CAPACITY'], [$catalogue[2]['name'], $catalogue[2]['category']]);

        // The module's data: two machines' capacity for W44, three batch plans (one without a machine), a board, a holiday.
        [$big, $small] = [$this->dyeMachine('MFL-01'), $this->dyeMachine('DM-05')];
        $group = $this->as()->getJson('/api/v1/erp/dye-colour-groups')->json('data.0.id');
        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        foreach ([[$big, 1000, 100], [$small, 250, 100]] as [$m, $kg, $pct]) {
            $this->as()->postJson('/api/v1/erp/fp-machine-capacities', ['dye_machine_id' => $m, 'week_start' => '2026-10-24', 'capacity_kg' => $kg, 'working_days' => 6, 'holidays' => 1, 'max_load_pct' => $pct, 'capacity_hours' => 144])->assertCreated();
        }
        $plan = fn (array $extra) => $this->as()->postJson('/api/v1/fabric-planning/batch-plans', $extra + ['week_start' => '2026-10-26', 'colour_group_id' => $group, 'dyeing_method' => 'EXHAUST', 'batches' => 1, 'status' => 'PLANNED'])->assertCreated();
        $plan(['buyer_id' => $lpp->id, 'style_job' => '529', 'dye_machine_id' => $big, 'lines' => [['fabric' => 'S/J', 'grey_req' => 700, 'bp_qty' => 650]]]);
        $plan(['dye_machine_id' => $small, 'bp_qty' => 300, 'batches' => 2]);
        $plan(['buyer_id' => $lpp->id, 'bp_qty' => 120]);
        $this->as()->postJson('/api/v1/erp/fp-schedule-plans', ['name' => 'W44 floor', 'basis' => 'SHIPMENT', 'scale' => 'WEEK', 'cell_width' => 100])->assertCreated();
        $this->as()->putJson('/api/v1/fabric-planning/schedule-plans/SP-00001/batches', ['rows' => [['bp_no' => 'BP-00002', 'planned_start' => '2026-10-26'], ['bp_no' => 'BP-00001']]])->assertOk();
        Department::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knitting & Dyeing', 'code' => 'KND']);
        $this->as()->putJson('/api/v1/fabric-planning/sections/KND/days', ['rows' => [['factory_id' => $this->unit->id, 'day' => '2026-10-30', 'day_type' => 'WEEKLY_OFF', 'name' => 'Friday']]])->assertOk();

        $rows = fn (string $code) => $this->as()->getJson("/api/v1/fabric-planning/reports/{$code}")->assertOk()->json('data.rows');
        $this->assertSame([['BP-00001', 'LPP', 'MFL-01', 650.0, 'Planned'], ['BP-00002', null, 'DM-05', 300.0, 'Planned'], ['BP-00003', 'LPP', null, 120.0, 'Planned']], array_map(fn ($r) => [$r['bp_no'], $r['buyer'], $r['machine'], (float) $r['qty'], $r['status']], $rows('FP-BATCH-PLAN')));
        $this->assertSame(['BP-00003'], array_column($rows('fp-unplaced'), 'bp_no'));
        $load = $rows('FP-MACHINE-LOAD');
        $this->assertSame([['MFL-01', 'Dhaka Unit 1', 1000.0, 650.0, 65, 'WITHIN'], ['DM-05', 'Dhaka Unit 1', 250.0, 300.0, 120, 'OVER']], array_map(fn ($r) => [$r['machine'], $r['unit'], (float) $r['max_load'], (float) $r['planned'], $r['used'], $r['tone']], $load));
        $this->assertSame([['DM-05', 50.0]], array_map(fn ($r) => [$r['machine'], (float) $r['over_by']], $rows('FP-OVER-CAPACITY')));
        $this->assertSame([['SP-00001 — W44 floor', 1, 'BP-00002', '2026-10-26'], ['SP-00001 — W44 floor', 2, 'BP-00001', null]], array_map(fn ($r) => [$r['plan'], $r['seq'], $r['bp_no'], $r['start']], $rows('FP-SCHEDULE')));
        $this->assertSame([['Dhaka Unit 1', 'Knitting & Dyeing', '2026-10-30', 'Weekly Off', 'Friday']], array_map(fn ($r) => array_values(array_intersect_key($r, array_flip(['office', 'section', 'date', 'holiday_type', 'name']))), $rows('FP-HOLIDAY')));
        $this->assertSame([], $rows('FP-BATCH-HOUR'));
        $this->assertSame(['FP-MACHINE-LOAD', ['unit', 'machine', 'week', 'max_load', 'planned', 'batches', 'used', 'status']], [$this->as()->getJson('/api/v1/fabric-planning/reports/FP-MACHINE-LOAD')->json('data.code'), $this->as()->getJson('/api/v1/fabric-planning/reports/FP-MACHINE-LOAD')->json('data.columns')]);
        $this->as()->getJson('/api/v1/fabric-planning/reports/FP-NOPE')->assertNotFound();
        $this->as()->get('/api/v1/fabric-planning/reports/FP-MACHINE-LOAD/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // New report run: Brickland's message without a report; the rows are counted for the run's buyer and week.
        $this->as()->postJson('/api/v1/erp/fp-report-runs', [])->assertUnprocessable()->assertJsonPath('errors.report_id.0', 'Report is required.');
        $register = $catalogue[0]['id'];
        $all = $this->as()->postJson('/api/v1/erp/fp-report-runs', ['report_id' => $register, 'export_excel' => true])->assertCreated()->json('data');
        $this->assertSame(['Batch Plan Register', 'PLAN', 3, true, null], [$all['report'], $all['category'], $all['rows'], $all['export_excel'], $all['week']]);
        $lppRun = $this->as()->postJson('/api/v1/erp/fp-report-runs', ['report_id' => $register, 'buyer_id' => $lpp->id, 'week_start' => '2026-10-28'])->assertCreated()->json('data');
        $this->assertSame([2, '2026-10-24', '2026-W44 · 24-Oct → 30-Oct'], [$lppRun['rows'], $lppRun['week_start'], $lppRun['week']]);
        $this->as()->patchJson("/api/v1/erp/fp-report-runs/{$lppRun['id']}", ['week_start' => '2026-11-07'])->assertOk()->assertJsonPath('data.rows', 0);
        $this->as()->postJson('/api/v1/erp/fp-report-runs', ['report_id' => $catalogue[3]['id'], 'factory_id' => $this->unit->id])->assertCreated()->assertJsonPath('data.rows', 1);
        $this->as()->getJson('/api/v1/erp/fp-report-runs')->assertJsonCount(3, 'data');

        // Who may: a store manager does not run them; a factory manager does.
        $store = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($store)->getJson('/api/v1/fabric-planning/reports')->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/fabric-planning/reports/FP-BATCH-PLAN')->assertOk();
    }

    private function dyeMachine(string $no): string
    {
        static $floor = null;
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->getJson('/api/v1/erp/origin-countries')->json('data.0.id')
            ?? $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->json('data.id');
        if (! $floor || ! Floor::query()->whereKey($floor)->exists()) {
            $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Dye', 'code' => 'B1']);
            $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dye Floor', 'code' => 'DF-1'])->id;
        }

        return $this->as()->postJson('/api/v1/erp/dye-machines', [
            'machine_no' => $no, 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'origin_country_id' => $country, 'capacity' => 1000, 'capacity_unit' => 'KG', 'sl_no' => $no, 'floor_id' => $floor, 'mc_version' => '0', 'prod_status' => 'FREE',
        ])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
