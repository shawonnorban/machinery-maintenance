<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\WorkStudy\Models\WsLineCosting;
use App\Modules\WorkStudy\Models\WsLineProduction;
use App\Modules\WorkStudy\Models\WsOhCost;
use App\Modules\WorkStudy\Models\WsOhHead;
use App\Modules\WorkStudy\Support\WsCosting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → OT & Salary (AnnotechERP 14/06): a month's salary and OT rates,
 * all four at once, and the year's Cost Particulars sheet; the line costing
 * paying a day's wages and every overtime hour at them, as Brickland does; a
 * floor's overtime plan — its manpower groups from HR, so many hours from
 * 6PM — and who may change it.
 */
class WorkStudyOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

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
    }

    public function test_salary_rates_cost_a_lines_day_and_its_overtime_as_brickland_does(): void
    {
        // Brickland's message when a rate is missing; one line a month.
        $rate = ['company' => $this->delta->id, 'year' => 2026, 'month' => 10, 'op_salary' => 12000, 'aso_salary' => 9000, 'op_ot_rate' => 60, 'aso_ot_rate' => 45];
        $this->as()->postJson('/api/v1/erp/ws-salary-rates', ['aso_ot_rate' => null] + $rate)->assertUnprocessable()->assertJsonPath('errors.aso_ot_rate.0', 'All four rates are required.');
        $this->as()->postJson('/api/v1/erp/ws-salary-rates', $rate)->assertCreated()->assertJsonPath('data.period_label', 'October 2026');
        $this->as()->postJson('/api/v1/erp/ws-salary-rates', $rate)->assertUnprocessable()->assertJsonValidationErrors('month');

        // Cost Particulars: all twelve months; a half-filled month is refused, a month cleared leaves the register.
        $sheet = $this->as()->getJson('/api/v1/workstudy/ot/rates?year=2026')->assertOk()->json('data.rows');
        $this->assertCount(12, $sheet);
        $this->assertEquals([true, 12000], [$sheet[9]['costed'], $sheet[9]['op_salary']]);
        $sheet[10] = ['op_salary' => 12500] + $sheet[10];
        $this->as()->putJson('/api/v1/workstudy/ot/rates', ['year' => 2026, 'rows' => $sheet])->assertUnprocessable()->assertJsonValidationErrors(['rows.10.op_salary' => 'November needs all four rates, or none.']);
        $sheet[10] = ['op_salary' => 12500, 'aso_salary' => 9200, 'op_ot_rate' => 62, 'aso_ot_rate' => 46] + $sheet[10];
        $saved = $this->as()->putJson('/api/v1/workstudy/ot/rates', ['year' => 2026, 'rows' => $sheet])->assertOk()->json('data.rows');
        $this->assertTrue($saved[10]['costed']);
        $saved[10] = ['op_salary' => 0, 'aso_salary' => 0, 'op_ot_rate' => 0, 'aso_ot_rate' => 0] + $saved[10];
        $this->as()->putJson('/api/v1/workstudy/ot/rates', ['year' => 2026, 'rows' => $saved])->assertOk();
        $this->assertSame(['2026-10'], DB::table('ws_salary_rates')->pluck('period')->all());

        // Brickland's probe line (25 Oct 2026): OP 30, ASO 6, 10 hours, 970 pcs; 60/40/20 man-minutes of overtime.
        $line = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE01', 'name' => 'Line 01', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        WsLineCosting::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'cost_date' => '2026-10-25', 'style_no' => '529', 'order_no' => 'PROBE-ORD-01', 'smv' => 8.5, 'fob' => 4.2, 'rate_cut' => 0.6, 'rate_sew' => 3.5, 'rate_fin' => 1.1]);
        WsLineProduction::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'prod_date' => '2026-10-25', 'planning_line_id' => $line->id, 'order_no' => 'PROBE-ORD-01', 'item_name' => 'T-Shirt',
            'op' => 30, 'aso' => 6, 'wh' => 10, 'target' => 1000, 'prod_g' => 800, 'prod_aot' => 100, 'prod_eot' => 50, 'prod_2ot' => 20, 'manot_aot' => 60, 'manot_eot' => 40, 'manot_2ot' => 20]);
        WsCosting::ensure($this->delta->id);
        $wages = WsOhHead::query()->where('name', 'Wages And Allowance')->firstOrFail();
        $salary = WsOhHead::query()->where('name', 'Salary And Allowance')->firstOrFail();
        WsOhCost::query()->create(['company_id' => $this->delta->id, 'period' => '2026-10', 'head_id' => $wages->id, 'monthly_expense' => 250000, 'work_days' => 26, 'cutting' => 20000, 'sewing' => 130000]);
        WsOhCost::query()->create(['company_id' => $this->delta->id, 'period' => '2026-10', 'head_id' => $salary->id, 'monthly_expense' => 52000, 'sewing' => 52000]);

        // Sal[g] = (30 × 12,000 + 6 × 9,000) / 26; an overtime man-hour at (30 × 60 + 6 × 45) / 36 = 57.50 — Brickland's figures.
        $r = $this->as()->getJson('/api/v1/workstudy/costing/day/profit?floor=SEW-1&date=2026-10-25')->assertOk()->json('data');
        $this->assertFalse($r['no_rate']);
        $row = $r['rows'][0];
        $this->assertEquals([15923.08, -18170.69, 57.5, -338.45, 38.33, 19.17], [$row['sal_g'], $row['pl_g'], $row['otbill_aot'], $row['pl_aot'], $row['otbill_eot'], $row['otbill_2ot']]);
        $this->assertEquals([23807.31, 5044.0, -18763.31], [$row['total_cost'], $row['cm_earn'], $row['profit']]);

        // The year prints and exports.
        $this->assertStringStartsWith('%PDF-', (string) $this->as()->get('/api/v1/workstudy/ot/export/rates/pdf?year=2026')->assertOk()->getContent());
        $this->as()->get('/api/v1/workstudy/ot/export/rates/xlsx?year=2026')->assertOk();

        // Only whoever manages OT & Salary may change it.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/erp/ws-salary-rates')->assertForbidden();
        $this->as($chief)->getJson('/api/v1/workstudy/ot/rates?year=2026')->assertForbidden();
    }

    public function test_a_floors_overtime_is_planned_hour_by_hour_for_its_manpower_groups(): void
    {
        $line = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE01', 'name' => 'Line 01', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => DB::table('buildings')->where('id', $this->floor->building_id)->value('factory_id'), 'name' => 'Sewing', 'code' => 'SEW', 'created_at' => now(), 'updated_at' => now()]);
        $op = (string) Str::ulid();
        DB::table('designations')->insert(['id' => $op, 'company_id' => $this->delta->id, 'name' => 'Jr. Operator', 'code' => 'JOP', 'department_id' => $dept, 'created_at' => now(), 'updated_at' => now()]);
        // Six operators posted to the floor, through HR & Payroll's own form.
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        foreach (range(1, 6) as $n) {
            $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => "W00{$n}", 'name' => "Worker {$n}", 'nid' => "12345678{$n}0", 'mobile' => '01712345678', 'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();
        }
        DB::table('hr_employees')->where('company_id', $this->delta->id)->update(['floor_id' => $this->floor->id, 'department_id' => $dept, 'designation_id' => $op]);

        $options = $this->as()->getJson('/api/v1/workstudy/ot/options')->assertOk()->json('data');
        $this->assertSame(['9th(6PM)', '10th(7PM)', '11th(8PM)', '12th(9PM)', '13th(10PM)', '14th(11PM)', '15th(12PM)', '17th(2AM)', '20th(5AM)'], array_column($options['slots'], 'label'));
        $this->assertSame(['Sewing — LINE01'], array_column($options['lines'], 'label'));

        // The sheet: the group HR posts to the floor, its headcount as MP (Gen.).
        $day = 'floor=SEW-1&date=2026-10-25';
        $groups = $this->as()->getJson("/api/v1/workstudy/ot/plan?{$day}")->assertOk()->json('data.rows');
        $this->assertSame([['Sewing', null, 'Jr. Operator', 6, []]], array_map(fn ($g) => [$g['department'], $g['line'], $g['designation'], $g['mp_gen'], $g['slots']], $groups));

        // New OT plan row: Brickland's message with nobody per hour; three hours from 6PM.
        $row = ['plan_date' => '2026-10-25', 'floor_id' => $this->floor->id, 'planning_line_id' => $line->id, 'department_id' => $dept, 'designation_id' => $op, 'mp_gen' => 36, 'wh_gen' => 8, 'hour_count' => 3, 'op_per_hour' => 0, 'aso_per_hour' => 0];
        $this->as()->postJson('/api/v1/erp/ws-ot-plans', $row)->assertUnprocessable()->assertJsonPath('errors.op_per_hour.0', 'Plan at least one operator or ASO per hour.');
        $made = $this->as()->postJson('/api/v1/erp/ws-ot-plans', ['op_per_hour' => 30, 'aso_per_hour' => 6] + $row)->assertCreated()->json('data');
        $this->assertSame([3, 'LINE01', 'Sewing', 'Jr. Operator'], [$made['hours'], $made['line'], $made['department'], $made['designation']]);
        $this->assertSame([9, 10, 11], array_keys(DB::table('ws_ot_plans')->value('slots') ? json_decode(DB::table('ws_ot_plans')->value('slots'), true) : []));
        $this->as()->postJson('/api/v1/erp/ws-ot-plans', ['op_per_hour' => 30] + $row)->assertUnprocessable()->assertJsonPath('errors.designation_id.0', 'That group already has a plan for this floor and day — change it on the OT Plan sheet.');

        // The sheet lists both groups; the planned one carries its hours.
        $groups = $this->as()->getJson("/api/v1/workstudy/ot/plan?{$day}")->json('data.rows');
        $this->assertCount(2, $groups);
        $planned = collect($groups)->firstWhere('line', 'LINE01');
        $this->assertSame(['op' => 30, 'aso' => 6], $planned['slots']['11']);

        // Saving: more people in an hour than MP (Gen.) is refused; a group cleared of hours is withdrawn.
        $i = array_search($planned, $groups, true);
        $groups[$i]['slots']['17'] = ['op' => 40, 'aso' => 0];
        $this->as()->putJson("/api/v1/workstudy/ot/plan?{$day}", ['rows' => $groups])->assertUnprocessable()->assertJsonValidationErrors("rows.{$i}.mp_gen");
        $groups[$i]['slots'] = ['17' => ['op' => 20, 'aso' => 4]];
        $saved = $this->as()->putJson("/api/v1/workstudy/ot/plan?{$day}", ['rows' => $groups])->assertOk()->json('data.rows');
        $this->assertSame(['17' => ['op' => 20, 'aso' => 4]], collect($saved)->firstWhere('line', 'LINE01')['slots']);
        $this->as()->getJson('/api/v1/erp/ws-ot-plans')->assertOk()->assertJsonPath('data.0.hours', 1);

        $this->assertSame('%PDF-', substr((string) $this->as()->get("/api/v1/workstudy/ot/export/ot-plan/pdf?{$day}")->assertOk()->getContent(), 0, 5));

        $groups = collect($saved)->map(fn ($g) => ['slots' => []] + $g)->all();
        $this->as()->putJson("/api/v1/workstudy/ot/plan?{$day}", ['rows' => $groups])->assertOk();
        $this->assertSame(0, DB::table('ws_ot_plans')->count());
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
