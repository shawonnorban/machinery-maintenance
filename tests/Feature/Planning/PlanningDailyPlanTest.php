<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Models\PlanningStyleSmv;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Capacity Planning → Daily Plan, Daily Plan List and Plan vs Actual &
 * Forecast (AnnotechERP 12/02): a locked plan's day laid out from its lines,
 * an HR shift setting the hours, saved and confirmed; output typed once the
 * day has come; and the forecast, the tiles and the day-by-day progress that
 * follow — on Brickland's own numbers.
 */
class PlanningDailyPlanTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $chief;

    private PlanningLine $l13;

    private PlanningLine $l01;

    private PlanningLine $l14;

    private HrShift $general;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->at('2026-10-25 10:00:00');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001']);
        $line = fn (string $code, int $mp, int $mc, float $h) => PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $floor->id, 'code' => $code, 'name' => "Line {$code}", 'std_manpower' => $mp, 'std_machines' => $mc, 'std_hours' => $h, 'std_efficiency' => 65, 'active' => true]);
        $this->l13 = $line('13', 33, 25, 10);
        $this->l14 = $line('14', 33, 25, 10);
        $this->l01 = $line('LINE01', 50, 40, 8);
        // Friday off: the default working week (Settings), HR & Payroll's calendar.
        FactoryCalendarSetup::factory($this->delta->id, $unit->id, 'SHIFT_BASED');
        PlanningStyleSmv::query()->create(['company_id' => $this->delta->id, 'style_no' => '529', 'smv' => 8.5, 'active' => true]);
        $this->general = HrShift::query()->create(['company_id' => $this->delta->id, 'code' => 'ST-GEN', 'name' => 'General', 'start_time' => '09:00', 'end_time' => '18:00', 'break_min' => 60, 'working_hours' => 8, 'active' => true]);
        HrShift::query()->create(['company_id' => $this->delta->id, 'code' => 'ST-NGT', 'name' => 'Night', 'start_time' => '21:00', 'end_time' => '06:00', 'break_min' => 60, 'cross_day' => true, 'active' => true]);
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $order = fn (string $no, int $qty) => Order::query()->create(['company_id' => $this->delta->id, 'order_no' => $no, 'job_no' => $no, 'style_no' => '529', 'buyer_id' => $buyer->id, 'po_no' => '11591533', 'total_qty' => $qty, 'ship_date' => '2026-11-09', 'status' => 'CONFIRMED']);
        $order('LPP-2026-000025', 7920);
        $order('LPP-2026-000026', 1620);

        // MP-2026110001 V1, locked: lines 13 and LINE01 sewing 05 → 08 Nov (Brickland's MP-2026110004).
        $this->as()->postJson('/api/v1/planning/master-plans', ['order_no' => 'LPP-2026-000025', 'direction' => 'BACKWARD', 'auto' => true, 'lines' => [
            ['line_id' => $this->l13->id, 'manpower' => 33, 'machines' => 25, 'hours' => 10, 'efficiency' => 65],
            ['line_id' => $this->l01->id, 'manpower' => 50, 'machines' => 40, 'hours' => 8, 'efficiency' => 65],
        ]])->assertOk();
        $this->as()->postJson('/api/v1/planning/master-plans/MP-2026110001/confirm')->assertOk();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_a_day_is_planned_from_the_locked_plan_saved_and_confirmed(): void
    {
        // Only an order with a locked plan can be planned by day.
        $this->assertSame([['LPP-2026-000025', 'LPP-2026-000025 · LPP · sewing 2026-11-05 → 2026-11-08']], array_map(fn ($o) => [$o['value'], $o['label']], $this->as()->getJson('/api/v1/planning/daily-plans/orders')->json('data')));
        $this->as()->getJson('/api/v1/planning/daily-plans/day?date=2026-11-05&order=LPP-2026-000026')->assertUnprocessable()->assertJsonPath('errors.order_no.0', 'Order LPP-2026-000026 has no locked master plan.');

        // The day loads the plan's lines with their resources, capacity and master target.
        $day = $this->as()->getJson('/api/v1/planning/daily-plans/day?date=2026-11-05&order=lpp-2026-000025')->assertOk()->json('data');
        $entry = $day['entry'];
        $this->assertSame(['MP-2026110001', 1, false, false], [$entry['plan_no'], $entry['version'], $entry['can_type'], $entry['saved']]);
        $this->assertEquals([['13', 'FLR-001', 33, 10, 151, 1510, 1510, 1510, 'NOT_SAVED'], ['LINE01', 'FLR-001', 50, 8, 229, 1832, 1832, 1832, 'NOT_SAVED']], array_map(fn ($r) => [$r['code'], $r['floor'], $r['manpower'], $r['hours'], $r['hourly_capacity'], $r['daily_capacity'], $r['master_target'], $r['daily_target'], $r['status']], $entry['rows']));
        $this->assertEquals([['ST-GEN — General (09:00 AM–06:00 PM)', 8], ['ST-NGT — Night (09:00 PM–06:00 AM)', 8]], array_map(fn ($s) => [$s['label'], $s['hours']], $entry['shifts']));
        $this->assertSame(['14'], array_column($entry['add_lines'], 'label'));
        $this->assertSame([10026, 'NOT_STARTED'], [$entry['forecast']['capacity_to_deadline'], $entry['forecast']['status']]);
        $this->assertSame([], $day['dashboard']);

        // Save Daily Plan: line 13 on the General shift (8 hours) at 1,208; output cannot be typed for a day to come.
        $rows = [
            ['line_id' => $this->l13->id, 'shift_id' => $this->general->id, 'manpower' => 33, 'machines' => 25, 'hours' => 8, 'efficiency' => 65, 'daily_target' => 1208],
            ['line_id' => $this->l01->id, 'manpower' => 50, 'machines' => 40, 'hours' => 8, 'efficiency' => 65, 'daily_target' => 1832],
        ];
        $body = ['date' => '2026-11-05', 'order_no' => 'LPP-2026-000025', 'rows' => $rows];
        $this->as($this->chief)->postJson('/api/v1/planning/daily-plans', $body)->assertForbidden();
        $this->as()->postJson('/api/v1/planning/daily-plans', ['rows' => [['typed_output' => 5] + $rows[0], $rows[1]]] + $body)->assertUnprocessable()->assertJsonValidationErrors(['rows.0.typed_output' => 'Output can be typed only once the day has come.']);
        $saved = $this->as()->postJson('/api/v1/planning/daily-plans', $body)->assertOk()->json('data');
        $this->assertSame([['13', 1208, 1510, 1208, 'PLANNED'], ['LINE01', 1832, 1832, 1832, 'PLANNED']], array_map(fn ($r) => [$r['code'], $r['daily_capacity'], $r['master_target'], $r['daily_target'], $r['status']], $saved['entry']['rows']));
        $this->assertSame($this->general->id, $saved['entry']['rows'][0]['shift_id']);
        $this->assertSame(9724, $saved['entry']['forecast']['capacity_to_deadline']);
        $this->assertEquals([['LPP-2026-000025', '13', 1208, 1208, 'PLANNED'], ['LPP-2026-000025', 'LINE01', 1832, 1832, 'PLANNED']], array_map(fn ($d) => [$d['order_no'], $d['line'], $d['capacity'], $d['target'], $d['status']], $saved['dashboard']));

        // Another day: LINE01 alone, then line 14 added and LINE01 removed — what is no longer listed goes.
        $this->as()->postJson('/api/v1/planning/daily-plans/confirm', ['date' => '2026-11-07', 'order_no' => 'LPP-2026-000025'])->assertUnprocessable()->assertJsonPath('errors.date.0', 'Save the daily plan before confirming the day.');
        $this->as()->postJson('/api/v1/planning/daily-plans', ['date' => '2026-11-07', 'rows' => [$rows[1]]] + $body)->assertOk();
        $moved = $this->as()->postJson('/api/v1/planning/daily-plans', ['date' => '2026-11-07', 'rows' => [['line_id' => $this->l14->id, 'manpower' => 33, 'machines' => 25, 'hours' => 10, 'efficiency' => 65, 'daily_target' => 1510]]] + $body)->assertOk()->json('data');
        $this->assertSame([['14', 0, 1510]], array_map(fn ($r) => [$r['code'], $r['master_target'], $r['daily_capacity']], $moved['entry']['rows']));

        // Daily Plan List, newest first, filtered by line and status.
        $list = $this->as()->getJson('/api/v1/planning/daily-plans')->assertOk()->json('data.rows');
        $this->assertSame([['2026-11-07', '14'], ['2026-11-05', '13'], ['2026-11-05', 'LINE01']], array_map(fn ($r) => [$r['date'], $r['line']], $this->sortList($list)));
        $this->assertSame('MP-2026110001 V1', $list[0]['master_plan']);
        $this->assertCount(1, $this->as()->getJson("/api/v1/planning/daily-plans?line={$this->l13->id}")->json('data.rows'));

        // Confirm Day closes the resources; output and remarks still change.
        $confirmed = $this->as()->postJson('/api/v1/planning/daily-plans/confirm', ['date' => '2026-11-05', 'order_no' => 'LPP-2026-000025'])->assertOk()->json('data');
        $this->assertTrue($confirmed['entry']['confirmed']);
        $this->as()->postJson('/api/v1/planning/daily-plans', ['rows' => [['manpower' => 20, 'remarks' => 'Operators short'] + $rows[0], $rows[1]]] + $body)->assertOk();
        $after = $this->as()->getJson('/api/v1/planning/daily-plans?status=CONFIRMED')->json('data.rows');
        $this->assertEquals([['13', 33, 'Operators short'], ['LINE01', 50, null]], array_map(fn ($r) => [$r['line'], $r['manpower'], $r['remarks']], $this->sortList($after)));
        $this->as($this->chief)->getJson('/api/v1/planning/daily-plans')->assertOk();
    }

    public function test_output_typed_on_the_day_moves_the_forecast_and_plan_vs_actual(): void
    {
        $rows = [
            ['line_id' => $this->l13->id, 'shift_id' => $this->general->id, 'manpower' => 33, 'machines' => 25, 'hours' => 8, 'efficiency' => 65, 'daily_target' => 1208],
            ['line_id' => $this->l01->id, 'manpower' => 50, 'machines' => 40, 'hours' => 8, 'efficiency' => 65, 'daily_target' => 1832],
        ];
        $body = ['date' => '2026-11-05', 'order_no' => 'LPP-2026-000025', 'rows' => $rows];
        $this->as()->postJson('/api/v1/planning/daily-plans', $body)->assertOk();

        // Before the day: nothing made, the plan not started; it counts as on track.
        $pva = $this->as()->getJson('/api/v1/planning/plan-vs-actual?from=2026-11-01&to=2026-11-30')->assertOk()->json('data');
        $this->assertSame(['locked' => 1, 'AT_RISK' => 0, 'DELAYED' => 0, 'ON_TRACK' => 1, 'AHEAD' => 0, 'COMPLETED' => 0], $pva['tiles']);
        $this->assertSame(['LPP-2026-000025', '13, LINE01', 0, 7920, '2026-11-08', 'NOT_STARTED'], [$pva['rows'][0]['order_no'], $pva['rows'][0]['lines'], $pva['rows'][0]['produced'], $pva['rows'][0]['remaining'], $pva['rows'][0]['current_forecast'], $pva['rows'][0]['status']]);
        $this->assertSame([['2026-11-05', '13', 1208, null], ['2026-11-05', 'LINE01', 1832, null], ['2026-11-07', '13', 1510, null]], array_map(fn ($d) => [$d['date'], $d['line'], $d['plan_target'], $d['actual']], array_slice($pva['progress'], 0, 3)));
        $this->assertCount(6, $pva['progress']);

        // On the day, output is typed: 1,100 on line 13, 1,900 on LINE01.
        $this->at('2026-11-05 18:00:00');
        $this->assertTrue($this->as()->getJson('/api/v1/planning/daily-plans/day?date=2026-11-05&order=LPP-2026-000025')->json('data.entry.can_type'));
        $saved = $this->as()->postJson('/api/v1/planning/daily-plans', ['rows' => [['typed_output' => 1100] + $rows[0], ['typed_output' => 1900, 'remarks' => 'Overtime'] + $rows[1]]] + $body)->assertOk()->json('data');
        $f = $saved['entry']['forecast'];
        $this->assertSame([3000, 4920, 37, '2026-11-08', 'ON_TRACK'], [$f['produced'], $f['remaining'], $f['percent'], $f['current_forecast'], $f['status']]);

        $pva = $this->as()->getJson('/api/v1/planning/plan-vs-actual?from=2026-11-05&to=2026-11-05')->json('data');
        $this->assertSame([3000, 'ON_TRACK'], [$pva['rows'][0]['produced'], $pva['rows'][0]['status']]);
        $this->assertSame([['13', 1208, 1100, -108, 6820, null], ['LINE01', 1832, 1900, 68, 4920, 'Overtime']], array_map(fn ($d) => [$d['line'], $d['plan_target'], $d['actual'], $d['variance'], $d['balance'], $d['remarks']], $pva['progress']));
        // The balance runs on from what was made before the window.
        $this->assertSame([4920, 4920], array_column(array_slice($this->as()->getJson("/api/v1/planning/plan-vs-actual?from=2026-11-07&line={$this->l13->id}")->json('data.progress'), 0, 2), 'balance'));
        $this->assertSame([], $this->as()->getJson('/api/v1/planning/plan-vs-actual?q=nothing-like-this')->json('data.rows'));
        $this->as()->get('/api/v1/planning/plan-vs-actual/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_line_capacity_and_the_timeline_read_the_month(): void
    {
        // Available = manpower × hours × 60 × efficiency on the factory's 26 working days; planned = target × SMV.
        $lcv = $this->as()->getJson('/api/v1/planning/line-capacity?year=2026&month=11')->assertOk()->json('data');
        $this->assertSame(['lines' => 3, 'available_h' => 17914, 'planned_h' => 1122, 'util' => 6, 'free' => 72, 'over' => 0], $lcv['tiles']);
        $rows = collect($lcv['rows'])->keyBy('code');
        $cell = fn (string $line, int $day) => collect($rows[$line]['days'])->firstWhere('date', sprintf('2026-11-%02d', $day));
        $this->assertSame(['FAC01 › FLR-001', 3, 26, 9], [$rows['13']['path'], $rows['13']['loaded'], $rows['13']['working'], $rows['13']['util']]);
        $this->assertSame([[100, 'FULL', 1510], [37, 'PART', 558]], [[$cell('13', 5)['pct'], $cell('13', 5)['state'], $cell('13', 5)['pieces']], [$cell('13', 8)['pct'], $cell('13', 8)['state'], $cell('13', 8)['pieces']]]);
        $this->assertSame(['HOLIDAY', 'FREE', 0], [$cell('13', 6)['state'], $cell('14', 5)['state'], $rows['14']['loaded']]);
        $this->assertSame([100, 'FULL'], [$cell('LINE01', 5)['pct'], $cell('LINE01', 5)['state']]);

        // A saved daily plan takes the master plan's place for its line and day.
        $this->as()->postJson('/api/v1/planning/daily-plans', ['date' => '2026-11-05', 'order_no' => 'LPP-2026-000025', 'rows' => [
            ['line_id' => $this->l13->id, 'manpower' => 33, 'machines' => 25, 'hours' => 8, 'efficiency' => 65, 'daily_target' => 1208],
            ['line_id' => $this->l01->id, 'manpower' => 50, 'machines' => 40, 'hours' => 8, 'efficiency' => 65, 'daily_target' => 2000],
        ]])->assertOk();
        $rows = collect($this->as()->getJson('/api/v1/planning/line-capacity?year=2026&month=11')->json('data.rows'))->keyBy('code');
        $cell = fn (string $line, int $day) => collect($rows[$line]['days'])->firstWhere('date', sprintf('2026-11-%02d', $day));
        $this->assertSame([80, 'PART', 1208], [$cell('13', 5)['pct'], $cell('13', 5)['state'], $cell('13', 5)['pieces']]);
        $this->assertSame([109, 'OVER'], [$cell('LINE01', 5)['pct'], $cell('LINE01', 5)['state']]);
        $this->assertSame(1, $this->as()->getJson('/api/v1/planning/line-capacity?year=2026&month=11')->json('data.tiles.over'));

        // Gantt / Timeline: the plans with an activity in the month, by which plans and a search.
        $tl = $this->as()->getJson('/api/v1/planning/timeline?year=2026&month=11')->assertOk()->json('data');
        $this->assertSame(['2026-11-01', '2026-11-30'], [$tl['from'], $tl['to']]);
        $this->assertSame([['LPP-2026-000025', 'MP-2026110001', 'LOCKED', '13, LINE01', 9]], array_map(fn ($r) => [$r['order_no'], $r['plan_no'], $r['status'], $r['lines'], count($r['activities'])], $tl['rows']));
        $this->assertSame(['SEWING', '2026-11-05', '2026-11-08', 'SELECTED_LINES'], array_values(array_intersect_key(collect($tl['rows'][0]['activities'])->firstWhere('activity', 'SEWING'), array_flip(['activity', 'start_date', 'end_date', 'based_on']))));
        $this->assertCount(1, $this->as()->getJson('/api/v1/planning/timeline?year=2026&month=10')->json('data.rows'));
        $this->assertSame([], $this->as()->getJson('/api/v1/planning/timeline?year=2026&month=12')->json('data.rows'));
        $this->assertSame([], $this->as()->getJson('/api/v1/planning/timeline?year=2026&month=11&plans=DRAFT')->json('data.rows'));
        $this->assertCount(1, $this->as()->getJson('/api/v1/planning/timeline?year=2026&month=11&q=line01')->json('data.rows'));
        $this->assertSame([], $this->as()->getJson('/api/v1/planning/timeline?year=2026&month=11&q=zzz')->json('data.rows'));
        $this->as($this->chief)->getJson('/api/v1/planning/line-capacity')->assertOk();
    }

    /** @param list<array<string, mixed>> $rows */
    private function sortList(array $rows): array
    {
        usort($rows, fn ($a, $b) => [$b['date'], $a['line']] <=> [$a['date'], $b['line']]);

        return $rows;
    }

    private function at(string $moment): void
    {
        CarbonImmutable::setTestNow($moment);
        $this->travelTo(CarbonImmutable::parse($moment));
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
