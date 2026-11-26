<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Target & Feeding (AnnotechERP 14/03): a month's excess yarn
 * allowance per department; style change-overs finalised, reopened and
 * removed; the target sheet working Target[Gen Hr] out as Brickland does,
 * copied to another day; a line's allocations and plans; NPT booked by the
 * hour — and the daily line costing reading the target sheet.
 */
class WorkStudyTargetFeedingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    private PlanningLine $line;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing', 'code' => 'SEW-1']);
        $this->line = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE01', 'name' => 'Line 01', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE02', 'name' => 'Line 02', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
    }

    public function test_yarn_allowances_and_style_change_overs(): void
    {
        $factory = DB::table('factories')->where('company_id', $this->delta->id)->value('id');
        $dept = fn (string $code, string $name) => tap((string) Str::ulid(), fn ($id) => DB::table('departments')->insert(['id' => $id, 'company_id' => $this->delta->id, 'factory_id' => $factory, 'name' => $name, 'code' => $code, 'created_at' => now(), 'updated_at' => now()]));
        $knit = $dept('KNT', 'Knitting');
        $dye = $dept('DYE', 'Dyeing');

        // New excess yarn target: Brickland's message without a target; one a department a month.
        $row = ['company' => $this->delta->id, 'year' => 2026, 'month' => 10, 'department_id' => $knit, 'target_qty' => 350];
        $this->as()->postJson('/api/v1/erp/ws-yarn-targets', ['target_qty' => null] + $row)->assertUnprocessable()->assertJsonPath('errors.target_qty.0', 'Target is required.');
        $this->as()->postJson('/api/v1/erp/ws-yarn-targets', $row)->assertCreated()->assertJsonPath('data.month_name', 'October');
        $this->as()->postJson('/api/v1/erp/ws-yarn-targets', $row)->assertUnprocessable()->assertJsonValidationErrors('department_id');

        // The month's sheet: every department; a cleared cell drops the target; Copy From builds November unsaved.
        $sheet = $this->as()->getJson('/api/v1/workstudy/targets/yarn?period=2026-10')->assertOk()->json('data.rows');
        $this->assertEquals([['Dyeing', null], ['Knitting', 350]], array_map(fn ($r) => [$r['department'], $r['target_qty']], $sheet));
        $sheet[0]['target_qty'] = 120;
        $sheet[1]['target_qty'] = null;
        $this->as()->putJson('/api/v1/workstudy/targets/yarn', ['period' => '2026-10', 'rows' => $sheet])->assertOk();
        $this->assertSame([$dye], DB::table('ws_yarn_targets')->pluck('department_id')->all());
        $copy = $this->as()->getJson('/api/v1/workstudy/targets/yarn?period=2026-11&copy=2026-10')->json('data');
        $this->assertEquals(['2026-10', 120], [$copy['copied_from'], $copy['rows'][0]['target_qty']]);
        $this->assertSame(1, DB::table('ws_yarn_targets')->count());

        // A change-over: Brickland's message with no line; a finalised one is reopened, never removed.
        $sco = ['floor_id' => $this->floor->id, 'changeover_date' => '2026-10-25', 'new_buyer_id' => $this->buyer->id, 'new_order' => 'LPP-2026-000026', 'prev_buyer_id' => $this->buyer->id, 'prev_order' => 'LPP-2026-000025', 'prev_output_on' => '2026-10-24', 'sco_minutes' => 45, 'ft_hours' => 2, 'remarks' => 'Collar change'];
        $this->as()->postJson('/api/v1/erp/ws-changeovers', $sco)->assertUnprocessable()->assertJsonPath('errors.planning_line_id.0', 'Line is required.');
        $made = $this->as()->postJson('/api/v1/erp/ws-changeovers', ['planning_line_id' => $this->line->id] + $sco)->assertCreated()->json('data');
        $this->assertSame(['LINE01', 'OPEN', null], [$made['line'], $made['status'], $made['feeding_start']]);
        $board = $this->as()->getJson('/api/v1/workstudy/targets/changeovers?floor=SEW-1&from=2026-10-01&to=2026-10-31')->assertOk()->json('data.rows');
        $this->assertCount(1, $board);
        $this->as()->postJson('/api/v1/workstudy/targets/changeovers/action', ['action' => 'finalize', 'ids' => [$made['id']]])->assertOk();
        $this->as()->postJson('/api/v1/workstudy/targets/changeovers/action', ['action' => 'remove', 'ids' => [$made['id']]])->assertUnprocessable()->assertJsonPath('errors.ids.0', 'A finalised change-over can be reopened, but not removed.');
        $this->as()->deleteJson("/api/v1/erp/ws-changeovers/{$made['id']}")->assertUnprocessable();
        $this->as()->postJson('/api/v1/workstudy/targets/changeovers/action', ['action' => 'reopen', 'ids' => [$made['id']]])->assertOk();
        $this->as()->postJson('/api/v1/workstudy/targets/changeovers/action', ['action' => 'remove', 'ids' => [$made['id']]])->assertOk();
        $this->assertSame(0, DB::table('ws_changeovers')->count());
    }

    public function test_the_target_sheet_allocations_npt_and_the_line_costing(): void
    {
        // New sewing target: Brickland's message for no SMV, and its target — 36 people, 10 h, 65 %, 8.5 SMV → 1,651.
        $target = ['target_date' => '2026-10-25', 'floor_id' => $this->floor->id, 'planning_line_id' => $this->line->id, 'buyer_id' => $this->buyer->id, 'order_style' => 'LPP-2026-000025',
            'used_op' => 30, 'used_aso' => 6, 'layout_op' => 32, 'layout_aso' => 6, 't_effi' => 65, 'wh' => 10, 'prod_gen' => 800, 'prod_ot' => 170];
        $this->as()->postJson('/api/v1/erp/ws-sewing-targets', $target)->assertUnprocessable()->assertJsonPath('errors.smv.0', 'SMV must be greater than zero.');
        $made = $this->as()->postJson('/api/v1/erp/ws-sewing-targets', ['smv' => 8.5] + $target)->assertCreated()->json('data');
        $this->assertSame([1651, 1651, 970, 'BEHIND'], [$made['target_gen'], $made['ttl_target'], $made['ttl_prod'], $made['status']]);

        // The sheet: every line, a spare row on each; a second order filled in on the spare row; Target[Gen Hr] worked out, never typed.
        $day = 'floor=SEW-1&date=2026-10-25';
        $rows = $this->as()->getJson("/api/v1/workstudy/targets/sheet?{$day}")->assertOk()->json('data.rows');
        $this->assertSame([['LINE01', false], ['LINE01', true], ['LINE02', true]], array_map(fn ($r) => [$r['line'], $r['spare']], $rows));
        $rows[2] = ['order_style' => 'LPP-2026-000026', 'smv' => 0, 'used_op' => 20, 'used_aso' => 4, 't_effi' => 60, 'wh' => 8, 'target_gen' => 99999] + $rows[2];
        $this->as()->putJson("/api/v1/workstudy/targets/sheet?{$day}", ['rows' => $rows])->assertUnprocessable()->assertJsonValidationErrors(['rows.2.smv' => 'Line LINE02 has an order without an SMV — SMV must be greater than zero.']);
        $rows[2]['smv'] = 12;
        $saved = $this->as()->putJson("/api/v1/workstudy/targets/sheet?{$day}", ['rows' => $rows])->assertOk()->json('data.rows');
        $this->assertSame(576, collect($saved)->firstWhere('order_style', 'LPP-2026-000026')['target_gen']);

        // Start Copy brings the setup onto the next day with the production at nothing.
        $copied = $this->as()->postJson('/api/v1/workstudy/targets/sheet/copy?floor=SEW-1&date=2026-10-26', ['from' => '2026-10-25'])->assertOk()->json('data');
        $this->assertSame(2, $copied['copied']);
        $this->assertSame([1651, 0], [collect($copied['rows'])->firstWhere('order_style', 'LPP-2026-000025')['target_gen'], collect($copied['rows'])->firstWhere('order_style', 'LPP-2026-000025')['ttl_prod']]);
        $this->as()->postJson('/api/v1/workstudy/targets/sheet/copy?floor=SEW-1&date=2026-10-26', ['from' => '2026-10-25'])->assertOk()->assertJsonPath('data.copied', 0);

        // The daily line costing now reads the target sheet: its orders, OP, ASO, hours and target.
        Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000025', 'job_no' => 'LPP-2026-000025', 'style_no' => '529', 'buyer_id' => $this->buyer->id, 'item' => 'T-Shirt', 'total_qty' => 7920, 'unit_price' => 4.2, 'ship_date' => '2026-11-09', 'status' => 'CONFIRMED']);
        $config = $this->as()->getJson("/api/v1/workstudy/costing/day/config?{$day}")->assertOk()->json('data.rows');
        $this->assertSame(['LPP-2026-000025', 'LPP-2026-000026'], array_column($config, 'order_no'));
        $this->assertEquals(['529', 4.2], [$config[0]['style_no'], $config[0]['fob']]);
        $lines = $this->as()->getJson("/api/v1/workstudy/costing/day/lines?{$day}")->json('data.rows');
        $this->assertEquals([30, 6, 10, 1651], [$lines[0]['op'], $lines[0]['aso'], $lines[0]['wh'], $lines[0]['target']]);

        // Production Entry: three spare rows; Brickland's "970 · 19.4% [4030]"; an allocation needs its order.
        $line = 'floor=SEW-1&line=LINE01';
        $alloc = $this->as()->getJson("/api/v1/workstudy/targets/allocations?{$line}")->assertOk()->json('data.rows');
        $this->assertCount(3, $alloc);
        $alloc[0] = ['buyer_id' => $this->buyer->id, 'order_style' => 'LPP-2026-000025', 'style' => '529', 'item' => 'T-Shirt', 'smv' => 8.5, 'plan_start' => '2026-10-22', 'plan_end' => '2026-10-30', 'allocation' => 5000, 'production' => 970] + $alloc[0];
        $alloc[1] = ['allocation' => 200] + $alloc[1];
        $this->as()->putJson("/api/v1/workstudy/targets/allocations?{$line}", ['rows' => $alloc])->assertUnprocessable()->assertJsonValidationErrors('rows.1.order_style');
        $alloc[1]['allocation'] = null;
        $saved = $this->as()->putJson("/api/v1/workstudy/targets/allocations?{$line}", ['rows' => $alloc])->assertOk()->json('data.rows');
        $this->assertEquals([19.4, 4030], [$saved[0]['achieved_pct'], $saved[0]['remaining']]);

        // Production Plans: on target, then closed — a closed plan comes off the target.
        $plans = $this->as()->getJson("/api/v1/workstudy/targets/allocations?{$line}&view=plans")->json('data.rows');
        $this->assertCount(1, $plans);
        $this->as()->putJson("/api/v1/workstudy/targets/plans?{$line}", ['rows' => [['id' => $plans[0]['id'], 'in_target' => true, 'status' => 'RUNNING']]])->assertOk()->assertJsonPath('data.rows.0.in_target', true);
        $this->as()->putJson("/api/v1/workstudy/targets/plans?{$line}", ['rows' => [['id' => $plans[0]['id'], 'in_target' => true, 'status' => 'CLOSED']]])->assertOk()->assertJsonPath('data.rows.0.in_target', false);

        // NPT: manpower × -Min; a row needs its cause, people, minutes and hours.
        $npt = $this->as()->getJson("/api/v1/workstudy/targets/npt?{$line}&date=2026-10-25")->assertOk()->json('data.rows');
        $this->assertCount(5, $npt);
        $npt[0] = ['order_style' => 'LPP-2026-000025', 'reason_type' => 'Machine Breakdown', 'reason_details' => 'Overlock down', 'responsible_dept' => 'Maintenance', 'hour_slots' => [3], 'manpower' => 4, 'minutes' => 30] + $npt[0];
        $npt[1] = ['reason_type' => 'Power Failure'] + $npt[1];
        $this->as()->putJson("/api/v1/workstudy/targets/npt?{$line}&date=2026-10-25", ['rows' => $npt])->assertUnprocessable()->assertJsonValidationErrors(['rows.1.manpower', 'rows.1.minutes', 'rows.1.hour_slots']);
        $npt[1] = ['manpower' => 36, 'minutes' => 15, 'hour_slots' => [3, 4]] + $npt[1];
        $saved = $this->as()->putJson("/api/v1/workstudy/targets/npt?{$line}&date=2026-10-25", ['rows' => $npt])->assertOk()->json('data.rows');
        $this->assertSame([120, 540], [$saved[0]['npt'], $saved[1]['npt']]);
        $this->assertCount(7, $saved);

        // The sheets print and export.
        foreach (["targets/pdf?{$day}", "entry/pdf?{$line}", "plans/xlsx?{$line}", "npt/pdf?{$line}&date=2026-10-25"] as $file) {
            $this->as()->get("/api/v1/workstudy/targets/export/{$file}")->assertOk();
        }

        // A line chief reads the day's targets but changes nothing.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson("/api/v1/workstudy/targets/sheet?{$day}")->assertOk();
        $this->as($chief)->putJson("/api/v1/workstudy/targets/sheet?{$day}", ['rows' => []])->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
