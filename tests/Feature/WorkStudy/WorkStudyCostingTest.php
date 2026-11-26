<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningDailyPlan;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\WorkStudy\Models\WsOhHead;
use App\Modules\WorkStudy\Models\WsOhSection;
use App\Modules\WorkStudy\Models\WsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Costing (AnnotechERP 14/05): a specification's CM per booking
 * month and the GMT Item List reading it; the overhead sections and heads as
 * Brickland ships them, a head's month and the Monthly Cost Re-arrange sheet;
 * a floor's day — the orders loaded on it, what each line made — and the
 * profit or loss each line ran at, with the overhead shared as Brickland
 * shares it; the PDF and Excel; and who may change them.
 */
class WorkStudyCostingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Buyer $buyer;

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
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
    }

    public function test_a_cm_rate_is_quoted_once_per_specification_and_month_and_reaches_the_item_list(): void
    {
        $spec = WsSpec::query()->create(['company_id' => $this->delta->id, 'style_no' => '529', 'variation_no' => 1, 'description' => 'Crew neck tee', 'op_manpower' => 30, 'aso_manpower' => 6, 'sewing_smv' => 8.5]);
        $options = $this->as()->getJson('/api/v1/workstudy/costing/options')->assertOk()->json('data');
        $this->assertSame(['529/1 — Crew neck tee'], array_column($options['specs'], 'label'));
        $this->assertSame(['LPP (LPP)'], array_column($options['buyers'], 'label'));

        // Brickland's messages: CM/Dz required; the month written as 2026-08; one CM per specification per month.
        $rate = ['spec_id' => $spec->id, 'buyer_id' => $this->buyer->id, 'booking_month' => '2026-10', 'order_qty' => 1200, 'cm_per_dozen' => 14.5, 'budget_exp_pct' => 6, 'lead_time_days' => 75, 'confirm_date' => '2026-08-05', 'ship_date' => '2026-10-20'];
        $this->as()->postJson('/api/v1/erp/ws-cm-rates', ['cm_per_dozen' => null] + $rate)->assertUnprocessable()->assertJsonPath('errors.cm_per_dozen.0', 'CM/Dz is required.');
        $this->as()->postJson('/api/v1/erp/ws-cm-rates', ['booking_month' => 'Oct 2026'] + $rate)->assertUnprocessable()->assertJsonPath('errors.booking_month.0', 'Booking Month is written as 2026-08.');
        $this->as()->postJson('/api/v1/erp/ws-cm-rates', ['ship_date' => '2026-08-01'] + $rate)->assertUnprocessable()->assertJsonValidationErrors('ship_date');
        $made = $this->as()->postJson('/api/v1/erp/ws-cm-rates', $rate)->assertCreated()->json('data');
        $this->assertSame(['529/1', 'LPP'], [$made['spec'], $made['buyer']]);
        $this->as()->postJson('/api/v1/erp/ws-cm-rates', $rate)->assertUnprocessable()->assertJsonPath('errors.spec_id.0', 'This specification already has a CM rate for that booking month.');
        $this->as()->postJson('/api/v1/erp/ws-cm-rates', ['booking_month' => '2026-11', 'cm_per_dozen' => 15] + $rate)->assertCreated();

        // The GMT Item List shows the CM quoted for the order's booking month.
        Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000001', 'job_no' => 'LPP-2026-000001', 'style_no' => '529', 'buyer_id' => $this->buyer->id, 'item' => 'Tee', 'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'unit' => 'PCS', 'conf_date' => '2026-10-12', 'ship_date' => '2026-12-20', 'status' => 'CONFIRMED']);
        $row = $this->as()->getJson('/api/v1/workstudy/item-list')->assertOk()->json('data.rows.0');
        $this->assertEquals([14.5, 6], [$row['cm_dz'], $row['bgt_exp']]);

        // Only whoever manages costing may change it.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/erp/ws-cm-rates')->assertForbidden();
        $this->as($chief)->getJson('/api/v1/workstudy/costing/sheet?period=2026-10')->assertForbidden();
    }

    public function test_the_monthly_sheet_re_arranges_overhead_and_the_day_shows_each_lines_profit(): void
    {
        // Brickland's eleven sections and their heads come with the first look.
        $options = $this->as()->getJson('/api/v1/workstudy/costing/options')->assertOk()->json('data');
        $this->assertCount(11, $options['sections']);
        $this->assertSame(['Garments Section', 19, false], [$options['sections'][0]['label'], $options['sections'][0]['count'], $options['sections'][0]['target']]);
        $this->assertTrue($options['sections'][1]['target']);
        $this->assertSame(149, WsOhHead::query()->count());
        $garments = WsOhSection::query()->where('name', 'Garments Section')->firstOrFail();
        $wages = WsOhHead::query()->where('section_id', $garments->id)->where('name', 'Wages And Allowance')->firstOrFail();
        $salary = WsOhHead::query()->where('section_id', $garments->id)->where('name', 'Salary And Allowance')->firstOrFail();

        // A section's heads edited in place; a new section (an allocation target) through the dialog.
        $heads = $this->as()->getJson("/api/v1/workstudy/costing/sections/{$garments->id}/heads")->assertOk()->json('data');
        $heads[] = ['id' => null, 'name' => 'Security Bill', 'name_bn' => 'সিকিউরিটি বিল', 'sort_order' => 20, 'active' => true];
        $this->as()->putJson("/api/v1/workstudy/costing/sections/{$garments->id}/heads", ['rows' => $heads])->assertOk()->assertJsonCount(20, 'data');
        $this->as()->postJson('/api/v1/erp/ws-oh-sections', ['name' => 'Washing Section', 'is_target' => true])->assertCreated()->assertJsonPath('data.is_target', true);

        // New cost head: one line per head per month, the money's whereabouts left to the sheet.
        $head = ['company' => $this->delta->id, 'year' => 2026, 'month' => 10, 'head_id' => $wages->id, 'monthly_expense' => 250000, 'work_days' => 26];
        $this->as()->postJson('/api/v1/erp/ws-oh-costs', ['monthly_expense' => null] + $head)->assertUnprocessable()->assertJsonPath('errors.monthly_expense.0', 'Monthly Expense is required.');
        $line = $this->as()->postJson('/api/v1/erp/ws-oh-costs', $head)->assertCreated()->json('data');
        $this->assertSame(['October 2026', 'Garments Section', 'Wages And Allowance', 9615.38, null], [$line['period_label'], $line['section'], $line['particular'], $line['per_day'], $line['rearranged']]);
        $this->as()->postJson('/api/v1/erp/ws-oh-costs', $head)->assertUnprocessable()->assertJsonPath('errors.head_id.0', 'This cost head already has a line for that month — change it on the sheet.');

        // The sheet: every active head in section order; a head with figures cannot leave its section.
        $sheet = $this->as()->getJson('/api/v1/workstudy/costing/sheet?period=2026-10')->assertOk()->json('data');
        $this->assertSame(['Garments Section', 'Overtime'], [$sheet['rows'][0]['section'], $sheet['rows'][0]['particular']]);
        $rows = collect($sheet['rows'])->map(fn ($r) => match ($r['head_id']) {
            $wages->id => ['cutting' => 20000, 'sewing' => 130000] + $r,
            // Working days left blank: the sheet reads the line above.
            $salary->id => ['monthly_expense' => 52000, 'sewing' => 52000] + $r,
            default => $r,
        })->all();
        $this->as()->putJson('/api/v1/workstudy/costing/sheet', ['period' => '2026-10', 'rows' => [['monthly_expense' => null, 'sewing' => 10] + $rows[0]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.monthly_expense');
        $saved = $this->as()->putJson('/api/v1/workstudy/costing/sheet', ['period' => '2026-10', 'rows' => $rows])->assertOk()->json('data.rows');
        $this->assertEquals(130000, collect($saved)->firstWhere('head_id', $wages->id)['sewing']);
        $this->assertEquals(150000, collect($this->as()->getJson('/api/v1/erp/ws-oh-costs')->assertOk()->json('data'))->firstWhere('particular', 'Wages And Allowance')['rearranged']);
        $heads = $this->as()->getJson("/api/v1/workstudy/costing/sections/{$garments->id}/heads")->json('data');
        $this->as()->putJson("/api/v1/workstudy/costing/sections/{$garments->id}/heads", ['rows' => array_values(array_filter($heads, fn ($h) => $h['id'] !== $wages->id))])
            ->assertUnprocessable()->assertJsonPath('errors.rows.0', 'Wages And Allowance has figures on a month — clear Active instead.');

        // Copy From builds November from October without writing it.
        $copy = $this->as()->getJson('/api/v1/workstudy/costing/sheet?period=2026-11&copy=2026-10')->assertOk()->json('data');
        $this->assertSame(['2026-11', '2026-10'], [$copy['period'], $copy['copied_from']]);
        $this->assertEquals(250000, collect($copy['rows'])->firstWhere('head_id', $wages->id)['monthly_expense']);
        $this->assertNull(collect($this->as()->getJson('/api/v1/workstudy/costing/sheet?period=2026-11')->json('data.rows'))->firstWhere('head_id', $wages->id)['monthly_expense']);

        // A floor's day: the order the line plan loaded, costed on Order Config Data.
        $line01 = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE01', 'name' => 'Line 01', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE02', 'name' => 'Line 02', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        WsSpec::query()->create(['company_id' => $this->delta->id, 'style_no' => '529', 'variation_no' => 1, 'description' => 'Crew neck tee', 'op_manpower' => 30, 'aso_manpower' => 6, 'sewing_smv' => 8.5]);
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000025', 'job_no' => 'LPP-2026-000025', 'style_no' => '529', 'buyer_id' => $this->buyer->id, 'item' => 'T-Shirt', 'total_qty' => 7920, 'unit_price' => 4.2, 'ship_date' => '2026-11-09', 'status' => 'CONFIRMED']);
        $plan = (string) Str::ulid();
        DB::table('planning_master_plans')->insert(['id' => $plan, 'company_id' => $this->delta->id, 'plan_no' => 'MP-2026100001', 'order_id' => $order->id, 'status' => 'LOCKED', 'smv' => 8.5, 'smv_source' => 'STYLE', 'order_qty' => 7920, 'ship_date' => '2026-11-09', 'created_at' => now(), 'updated_at' => now()]);
        PlanningDailyPlan::query()->create(['company_id' => $this->delta->id, 'plan_date' => '2026-10-25', 'order_id' => $order->id, 'master_plan_id' => $plan, 'planning_line_id' => $line01->id, 'manpower' => 36, 'machines' => 30, 'hours' => 10, 'efficiency' => 65, 'smv' => 8.5, 'daily_capacity' => 1650, 'master_target' => 1000, 'daily_target' => 1000]);

        $day = 'floor=SEW-1&date=2026-10-25';
        $config = $this->as()->getJson("/api/v1/workstudy/costing/day/config?{$day}")->assertOk()->json('data.rows');
        $this->assertSame([['LPP-2026-000025', '529', 'T-Shirt', true, null]], array_map(fn ($r) => [$r['order_no'], $r['style_no'], $r['gmt_item'], $r['loaded'], $r['id']], $config));
        $this->assertEquals([8.5, 4.2], [$config[0]['smv'], $config[0]['fob']]);
        $this->as()->putJson("/api/v1/workstudy/costing/day/config?{$day}", ['rows' => [['smv' => 0] + $config[0]]])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.smv' => 'LPP-2026-000025 is costed without an SMV.']);
        $costed = $this->as()->putJson("/api/v1/workstudy/costing/day/config?{$day}", ['rows' => [['bgt_as_cm_pct' => 12, 'exc_rate' => 110, 'rate_cut' => 0.6, 'rate_sew' => 3.5, 'rate_fin' => 1.1] + $config[0]]])->assertOk()->json('data.rows.0');
        $this->assertNotNull($costed['id']);
        $this->as()->getJson('/api/v1/erp/ws-line-costings')->assertOk()->assertJsonPath('data.0.floor_code', 'SEW-1');

        // Line Wise Prod: every line on the floor, LINE01 filled from the plan and its specification.
        $lines = $this->as()->getJson("/api/v1/workstudy/costing/day/lines?{$day}")->assertOk()->json('data.rows');
        $this->assertSame(['LINE01', 'LINE02'], array_column($lines, 'line'));
        $this->assertSame(['LPP-2026-000025', 30, 6, 1000], [$lines[0]['order_no'], $lines[0]['op'], $lines[0]['aso'], $lines[0]['target']]);
        $lines[0] = ['prod_g' => 800, 'prod_aot' => 100, 'prod_eot' => 50, 'prod_2ot' => 20, 'prod_5ot' => 0, 'manot_aot' => 60, 'manot_eot' => 40, 'manot_2ot' => 20] + $lines[0];
        $lines[1] = ['prod_g' => 5] + $lines[1];
        $this->as()->putJson("/api/v1/workstudy/costing/day/lines?{$day}", ['rows' => $lines])->assertUnprocessable()->assertJsonValidationErrors(['rows.1.order_no' => 'Line 2 produced pieces but names no order.']);
        $lines[1]['prod_g'] = null;
        $this->as()->putJson("/api/v1/workstudy/costing/day/lines?{$day}", ['rows' => $lines])->assertOk()->assertJsonPath('data.rows.0.saved', true)->assertJsonPath('data.rows.1.saved', false);

        // Overall Profit Lost, as Brickland worked it out: 970 pcs at 8.5 SMV on 36 people for 10 hours is 38.17%;
        // the day's overhead (150,000 + 52,000 over 26 days) shared by the one line that ran, then by its output across the tiers.
        $profit = $this->as()->getJson("/api/v1/workstudy/costing/day/profit?{$day}")->assertOk()->json('data');
        $r = $profit['rows'][0];
        $this->assertEquals([38.17, 4074.0, 0.0, 6407.61, 4160.0, -2247.61], [$r['effi'], $r['fob_earn'], $r['sal_g'], $r['oh_g'], $r['cm_g'], $r['pl_g']]);
        $this->assertEquals([800.95, 520.0, 400.48, 160.19, 7769.23, 5044.0, -2725.23], [$r['oh_aot'], $r['cm_aot'], $r['oh_eot'], $r['oh_2ot'], $r['total_cost'], $r['cm_earn'], $r['profit']]);
        $this->assertTrue($profit['no_rate']);
        $this->assertFalse($profit['no_overhead']);

        // The sheets print and export.
        $pdf = $this->as()->get("/api/v1/workstudy/costing/export/day-profit/pdf?{$day}")->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());
        $this->as()->get('/api/v1/workstudy/costing/export/oh-sheet/xlsx?period=2026-10')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->as()->getJson('/api/v1/workstudy/costing/day/lines?floor=NOPE&date=2026-10-25')->assertUnprocessable()->assertJsonPath('errors.floor.0', 'Floor is required.');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
