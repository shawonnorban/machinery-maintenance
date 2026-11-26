<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Models\PlanningStyleSmv;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Capacity Planning → Projections, Master Plan, Master Plan List and Revision
 * Request (AnnotechERP 12/02): an order's plan laid out as Brickland lays it
 * — capacity from manpower, hours, efficiency and SMV, the quantity shared by
 * capacity, sewing placed backward from shipment on free working days, the
 * activities from the lead times — saved, confirmed and locked, then revised
 * through a request into V2.
 */
class PlanningMasterPlanTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $unit;

    private User $owner;

    private User $chief;

    private PlanningLine $l13;

    private PlanningLine $l01;

    private PlanningLine $l14;

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
        $this->chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001']);
        $line = fn (string $code, int $mp, int $mc, float $h) => PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $floor->id, 'code' => $code, 'name' => "Line {$code}", 'std_manpower' => $mp, 'std_machines' => $mc, 'std_hours' => $h, 'std_efficiency' => 65, 'active' => true]);
        $this->l13 = $line('13', 33, 25, 10);
        $this->l14 = $line('14', 33, 25, 10);
        $this->l01 = $line('LINE01', 50, 40, 8);
        // Friday off: the default working week (Settings), HR & Payroll's calendar.
        FactoryCalendarSetup::factory($this->delta->id, $this->unit->id, 'SHIFT_BASED');
        PlanningStyleSmv::query()->create(['company_id' => $this->delta->id, 'style_no' => '529', 'smv' => 8.5, 'active' => true]);
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $order = fn (string $no, string $style, int $qty, string $ship) => Order::query()->create(['company_id' => $this->delta->id, 'order_no' => $no, 'job_no' => $no, 'style_no' => $style, 'buyer_id' => $buyer->id, 'po_no' => '11591533', 'total_qty' => $qty, 'ship_date' => $ship, 'status' => 'CONFIRMED']);
        $order('LPP-2026-000025', '529', 7920, '2026-11-09');
        $order('LPP-2026-000026', '529', 1620, '2026-11-09');
        $order('LPP-2026-000099', 'NO-SMV', 500, '2026-11-20');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_a_plan_is_laid_out_saved_confirmed_and_revised(): void
    {
        // Projections: November's confirmed orders, none planned yet.
        $proj = $this->as()->getJson('/api/v1/planning/projections?year=2026&month=11')->assertOk()->json('data');
        $this->assertSame(['orders' => 3, 'qty' => 10040, 'pending' => 3, 'locked' => 0], $proj['tiles']);
        $this->assertSame(3, $proj['counts']['11']);

        // The order opens with its SMV from the master and the lines with their free days (Fridays off).
        $opened = $this->as()->getJson('/api/v1/planning/master-plans/order/lpp-2026-000025')->assertOk()->json('data');
        $this->assertSame([8.5, 'MASTER', true, '2026-11-08'], [$opened['order']['smv'], $opened['order']['smv_source'], $opened['order']['can_plan'], $opened['order']['last_sewing']]);
        $this->assertEquals([['13', 12, 'FAC01 › FLR-001'], ['14', 12, 'FAC01 › FLR-001'], ['LINE01', 12, 'FAC01 › FLR-001']], array_map(fn ($l) => [$l['code'], $l['free_days'], $l['path']], $opened['lines']));
        $this->assertNull($opened['plan']);
        $this->assertSame(['no_smv'], $this->as()->getJson('/api/v1/planning/master-plans/order/LPP-2026-000099')->json('data.order.problems'));

        // Calculate: Brickland's own numbers.
        $body = ['order_no' => 'LPP-2026-000025', 'direction' => 'BACKWARD', 'auto' => true, 'lines' => [
            ['line_id' => $this->l13->id, 'manpower' => 33, 'machines' => 25, 'hours' => 10, 'efficiency' => 65],
            ['line_id' => $this->l01->id, 'manpower' => 50, 'machines' => 40, 'hours' => 8, 'efficiency' => 65],
        ]];
        $this->as()->postJson('/api/v1/planning/master-plans/calculate', ['lines' => []] + $body)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Select one or more available lines to calculate the plan.');
        $calc = $this->as()->postJson('/api/v1/planning/master-plans/calculate', $body)->assertOk()->json('data');
        $this->assertEquals([[151, 1510, 3578, '2026-11-05', '2026-11-08', 3], [229, 1832, 4342, '2026-11-05', '2026-11-08', 3]], array_map(fn ($l) => [$l['hourly_capacity'], $l['daily_capacity'], $l['allocated_qty'], $l['sewing_start'], $l['sewing_end'], $l['days']], $calc['lines']));
        $this->assertSame([3342, 2.37, 7920, true], [$calc['daily_capacity'], $calc['required_days'], $calc['scheduled_qty'], $calc['fully_allocated']]);
        $this->assertSame([['2026-11-05', 1510], ['2026-11-05', 1832], ['2026-11-07', 1510], ['2026-11-07', 1832], ['2026-11-08', 558], ['2026-11-08', 678]], array_map(fn ($d) => [$d['day'], $d['target']], $calc['days']));
        $this->assertSame([
            ['ACCESSORIES_BOOKING', 5, '2026-10-23', '2026-10-27'], ['ACCESSORIES_INHOUSE', 7, '2026-10-28', '2026-11-03'], ['FABRIC_BOOKING', 5, '2026-10-20', '2026-10-24'],
            ['FABRIC_INHOUSE', 10, '2026-10-25', '2026-11-03'], ['CUTTING', 2, '2026-11-04', '2026-11-05'], ['SEWING', 4, '2026-11-05', '2026-11-08'],
            ['FINISHING', 1, '2026-11-09', '2026-11-09'], ['PACKING', 1, '2026-11-09', '2026-11-09'], ['SHIPMENT', 1, '2026-11-09', '2026-11-09'],
        ], array_map(fn ($a) => [$a['activity'], $a['duration'], $a['start_date'], $a['end_date']], $calc['activities']));
        $this->assertSame(['ACCESSORIES_BOOKING', 'FABRIC_BOOKING'], $calc['lead_passed']);

        // Save as Draft: MP-{ship month}{number} V1; only a planner may.
        $this->as($this->chief)->postJson('/api/v1/planning/master-plans', $body)->assertForbidden();
        $draft = $this->as()->postJson('/api/v1/planning/master-plans', $body + ['remarks' => 'First cut'])->assertOk()->json('data');
        $this->assertSame(['MP-2026110001', 1, 'DRAFT', 7920, '13, LINE01'], [$draft['plan_no'], $draft['version'], $draft['status'], $draft['allocated_qty'], implode(', ', array_column($draft['lines'], 'code'))]);
        $draft = $this->as()->postJson('/api/v1/planning/master-plans', $body + ['remarks' => 'Second cut'])->assertOk()->json('data');
        $this->assertSame(['MP-2026110001', 'Second cut'], [$draft['plan_no'], $draft['remarks']]);

        // Confirm & Lock: the line days it holds are taken from other orders.
        $this->as($this->chief)->postJson('/api/v1/planning/master-plans/MP-2026110001/confirm')->assertForbidden();
        $locked = $this->as()->postJson('/api/v1/planning/master-plans/MP-2026110001/confirm')->assertOk()->json('data');
        $this->assertSame(['LOCKED', 'NOT_STARTED', '2026-11-08', 10026, 0], [$locked['status'], $locked['forecast']['status'], $locked['forecast']['current_forecast'], $locked['forecast']['capacity_to_deadline'], $locked['forecast']['extra_capacity']]);
        $this->assertSame(['LOCKED', 'UPDATED', 'CREATED'], array_column($locked['events'], 'action'));
        $other = $this->as()->getJson('/api/v1/planning/master-plans/order/LPP-2026-000026')->json('data.lines');
        $this->assertEquals([['13', 9], ['14', 12], ['LINE01', 9]], array_map(fn ($l) => [$l['code'], $l['free_days']], $other));
        $this->as()->postJson('/api/v1/planning/master-plans', $body)->assertUnprocessable()->assertJsonValidationErrors('order_no');
        $this->assertSame([['LPP-2026-000025', 'LOCKED']], array_map(fn ($r) => [$r['order_no'], $r['status']], array_values(array_filter($this->as()->getJson('/api/v1/planning/projections?year=2026&month=11')->json('data.rows'), fn ($r) => $r['status'] !== 'PENDING'))));

        // Revision: requested, approved → V2 opens as a draft; V1 stays in force until V2 is locked.
        $this->as()->postJson('/api/v1/planning/master-plans/MP-2026110001/revisions', [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Give the reason for the change.');
        $this->as()->postJson('/api/v1/planning/master-plans/MP-2026110001/revisions', ['reason' => 'Buyer moved ex-factory'])->assertOk()->assertJsonPath('data.awaiting', true);
        $this->as()->postJson('/api/v1/planning/master-plans/MP-2026110001/revisions', ['reason' => 'Again'])->assertUnprocessable();
        $this->assertSame(['AWAITING'], array_column($this->as()->getJson('/api/v1/planning/master-plans?status=AWAITING')->json('data'), 'status'));
        $req = $this->as()->getJson('/api/v1/planning/revision-requests')->json('data.0');
        $this->assertSame(['PENDING', 'MP-2026110001', 1, 'LPP-2026-000025'], [$req['status'], $req['plan_no'], $req['version'], $req['order_no']]);
        $this->as()->postJson("/api/v1/planning/revision-requests/{$req['id']}/reject", [])->assertUnprocessable()->assertJsonPath('errors.note.0', 'Give the reason for the decision.');
        $this->as()->postJson("/api/v1/planning/revision-requests/{$req['id']}/approve")->assertOk()->assertJsonPath('data.revised_version', 2)->assertJsonPath('data.revised_status', 'DRAFT');
        $this->as()->postJson("/api/v1/planning/revision-requests/{$req['id']}/approve")->assertUnprocessable();
        $list = $this->as()->getJson('/api/v1/planning/master-plans')->json('data');
        $this->assertSame([[2, 'DRAFT'], [1, 'LOCKED']], array_map(fn ($r) => [$r['version'], $r['status']], $list));

        // V2 is re-planned on line 14 and locked; V1 becomes Revised.
        $v2 = $this->as()->postJson('/api/v1/planning/master-plans', ['lines' => [['line_id' => $this->l14->id, 'manpower' => 33, 'machines' => 25, 'hours' => 10, 'efficiency' => 65], $body['lines'][1]]] + $body)->assertOk()->json('data');
        $this->assertSame([2, '14, LINE01'], [$v2['version'], implode(', ', array_column($v2['lines'], 'code'))]);
        $this->as()->postJson('/api/v1/planning/master-plans/MP-2026110001/confirm')->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame([[2, 'LOCKED'], [1, 'REVISED']], array_map(fn ($r) => [$r['version'], $r['status']], $this->as()->getJson('/api/v1/planning/master-plans')->json('data')));
        $this->as()->getJson('/api/v1/planning/master-plans/MP-2026110001?v=1')->assertOk()->assertJsonPath('data.status', 'REVISED');
        $this->as()->get('/api/v1/planning/master-plans/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->as($this->chief)->getJson('/api/v1/planning/master-plans')->assertOk();
    }

    public function test_a_draft_can_be_deleted_and_forward_planning_starts_from_a_date(): void
    {
        $body = ['order_no' => 'LPP-2026-000026', 'direction' => 'FORWARD', 'forward_from' => '2026-11-01', 'auto' => true, 'lines' => [
            ['line_id' => $this->l13->id, 'manpower' => 33, 'machines' => 25, 'hours' => 10, 'efficiency' => 65],
        ]];
        $this->as()->postJson('/api/v1/planning/master-plans/calculate', ['forward_from' => null] + $body)->assertUnprocessable()->assertJsonPath('errors.forward_from.0', 'Pick the date to plan forward from.');
        $calc = $this->as()->postJson('/api/v1/planning/master-plans/calculate', $body)->assertOk()->json('data');
        $this->assertSame(['2026-11-01', '2026-11-02', 2], [$calc['sewing_start'], $calc['sewing_end'], $calc['lines'][0]['days']]);
        $this->as()->postJson('/api/v1/planning/master-plans/calculate', ['order_no' => 'LPP-2026-000099'] + $body)->assertUnprocessable()->assertJsonPath('errors.order_no.0', 'Fix the missing shipment date or SMV at its source, then come back.');
        $draft = $this->as()->postJson('/api/v1/planning/master-plans', $body)->assertOk()->json('data');
        $this->as()->deleteJson("/api/v1/planning/master-plans/{$draft['plan_no']}/draft")->assertOk();
        $this->assertSame([], $this->as()->getJson('/api/v1/planning/master-plans')->json('data'));
        $this->as()->getJson('/api/v1/planning/master-plans/MP-2026110009')->assertUnprocessable();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
