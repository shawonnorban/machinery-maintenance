<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\WorkStudy\Models\WsChangeover;
use App\Modules\WorkStudy\Models\WsLineCosting;
use App\Modules\WorkStudy\Models\WsLineProduction;
use App\Modules\WorkStudy\Models\WsNptEntry;
use App\Modules\WorkStudy\Models\WsSectionResult;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use App\Modules\WorkStudy\Support\WsReports;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Reports (AnnotechERP 14/02): Brickland's 36 reports with their
 * categories and parameters; the ones Work Study holds the figures for read
 * live from the target sheet, NPT, the daily line costing, section results
 * and change-overs; the rest say which module they wait for; PDF and Excel;
 * a report run counting the rows it found.
 */
class WorkStudyReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    private PlanningLine $line;

    private PlanningLine $line2;

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
        $this->line2 = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'LINE02', 'name' => 'Line 02', 'std_manpower' => 36, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
    }

    public function test_reports_read_work_study_sheets(): void
    {
        $base = ['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'buyer_id' => $this->buyer->id, 'smv' => 10, 't_effi' => 65, 'wh' => 10, 'used_op' => 30, 'used_aso' => 6, 'sort_order' => 1];
        // 36 people × 10 h × 60 = 21,600 minutes; 1,500 pieces × 10 SMV = 15,000 minutes produced → 69.44 %.
        WsSewingTarget::query()->create($base + ['target_date' => '2026-10-25', 'planning_line_id' => $this->line->id, 'order_style' => 'ORD-1', 'gmt_item' => 'T-Shirt', 'target_gen' => 1404, 'target_ot' => 200, 'prod_gen' => 1300, 'prod_ot' => 200]);
        WsSewingTarget::query()->create($base + ['target_date' => '2026-10-25', 'planning_line_id' => $this->line2->id, 'order_style' => 'ORD-2', 'target_gen' => 1000, 'target_ot' => 0, 'prod_gen' => 1100, 'prod_ot' => 0, 'is_car' => true]);
        WsSewingTarget::query()->create($base + ['target_date' => '2026-10-26', 'planning_line_id' => $this->line->id, 'order_style' => 'ORD-1', 'target_gen' => 1404, 'target_ot' => 0, 'prod_gen' => 1000, 'prod_ot' => 0, 'is_off' => true]);

        $options = $this->as()->getJson('/api/v1/workstudy/reports/options')->assertOk()->json('data');
        $this->assertCount(36, $options['reports']);
        $this->assertSame(['efficiency', 'Efficiency', null], [$options['reports'][0]['key'], $options['reports'][0]['category'], $options['reports'][0]['pending']]);
        $this->assertSame(['SEW-1/LINE01', 'SEW-1/LINE02'], array_column($options['lines'], 'value'));

        // Efficiency: produced minutes over available minutes, the off row left out; a day is required.
        $eff = $this->as()->getJson('/api/v1/workstudy/reports/efficiency/preview?date=2026-10-25')->assertOk()->json('data');
        $this->assertSame([69.44, 50.93], array_map(fn ($r) => $r['effi'], $eff['rows']));
        $this->assertSame([2600, 60.19], [(int) $eff['totals']['prod'], $eff['totals']['effi']]);
        $this->as()->getJson('/api/v1/workstudy/reports/efficiency/preview')->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertCount(1, $this->as()->getJson('/api/v1/workstudy/reports/efficiency/preview?date=2026-10-25&order=ORD-2')->json('data.rows'));
        $this->assertCount(0, $this->as()->getJson('/api/v1/workstudy/reports/efficiency/preview?date=2026-10-25&office=NONE')->json('data.rows'));

        // Target & achievement over a range; monthly achievement without the "car" rows; general and OT apart.
        $range = 'from=2026-10-01&to=2026-10-31';
        $ach = $this->as()->getJson("/api/v1/workstudy/reports/target-achievement/preview?{$range}")->json('data');
        $this->assertSame([[1604, 1500, 93.52], [1000, 1100, 110]], array_map(fn ($r) => [$r['target'], $r['prod'], $r['achv']], $ach['rows']));
        $this->assertSame(1500, $this->as()->getJson("/api/v1/workstudy/reports/monthly-achievement/preview?{$range}&car=1")->json('data.rows.0.prod'));
        $genOt = $this->as()->getJson("/api/v1/workstudy/reports/target-gen-ot/preview?{$range}")->json('data.rows.0');
        $this->assertSame([1404, 1300, 92.59, 200, 200, 100], [$genOt['target_gen'], $genOt['prod_gen'], $genOt['achv_gen'], $genOt['target_ot'], $genOt['prod_ot'], $genOt['achv_ot']]);
        $this->as()->getJson('/api/v1/workstudy/reports/target-achievement/preview?from=2026-10-31&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->as()->getJson('/api/v1/workstudy/reports/target-achievement/preview?from=2026-01-01&to=2026-12-31')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->assertSame(140, $this->as()->getJson('/api/v1/workstudy/reports/daily-line-target/preview?date=2026-10-25&lines=SEW-1/LINE01')->json('data.rows.0.hourly'));

        // NPT and change-overs as booked.
        WsNptEntry::query()->create(['company_id' => $this->delta->id, 'npt_date' => '2026-10-25', 'floor_id' => $this->floor->id, 'planning_line_id' => $this->line->id, 'order_style' => 'ORD-1', 'reason_type' => 'Machine Breakdown', 'hour_slots' => [3], 'manpower' => 4, 'minutes' => 30, 'npt' => 120]);
        $this->assertSame([120, 120], [$this->as()->getJson("/api/v1/workstudy/reports/npt/preview?{$range}")->json('data.rows.0.npt'), (int) $this->as()->getJson("/api/v1/workstudy/reports/npt/preview?{$range}")->json('data.totals.npt')]);
        WsChangeover::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'planning_line_id' => $this->line->id, 'changeover_date' => '2026-10-24', 'new_order' => 'ORD-1', 'prev_order' => 'ORD-0', 'sco_minutes' => 40, 'finalized' => true]);
        $sco = $this->as()->getJson("/api/v1/workstudy/reports/line-feeding-sco/preview?{$range}")->json('data.rows.0');
        $this->assertSame(['ORD-1', 40, '2026-10-25', '2026-10-25', 'FINALIZED'], [$sco['new_order'], $sco['sco'], $sco['feeding_start'], $sco['feeding_end'], $sco['status']]);

        // Line profit from the daily line costing: 500 pieces at 2.5 a piece in CM, no salary rate or overhead booked.
        WsLineCosting::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'cost_date' => '2026-10-25', 'buyer_id' => $this->buyer->id, 'style_no' => 'STY-1', 'order_no' => 'ORD-1', 'gmt_item' => 'T-Shirt', 'smv' => 10, 'fob' => 4, 'rate_cut' => 0.5, 'rate_sew' => 1.5, 'rate_fin' => 0.5]);
        WsLineProduction::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'prod_date' => '2026-10-25', 'planning_line_id' => $this->line->id, 'buyer_id' => $this->buyer->id, 'order_no' => 'ORD-1', 'item_name' => 'T-Shirt', 'op' => 30, 'aso' => 6, 'wh' => 10, 'target' => 1404, 'prod_g' => 400, 'prod_aot' => 100]);
        $pl = $this->as()->getJson("/api/v1/workstudy/reports/line-pl/preview?{$range}")->json('data.rows.0');
        $this->assertSame(['SEW-1', 'LINE01', 1250.0, 2000.0], [$pl['floor'], $pl['line'], (float) $pl['cm_earn'], (float) $pl['fob_earn']]);
        $slab = $this->as()->getJson("/api/v1/workstudy/reports/line-pl-hour-slab/preview?{$range}")->json('data.rows.0');
        $this->assertSame([1000.0, 250.0, 1250.0], [(float) $slab['pl_g'], (float) $slab['pl_aot'], (float) $slab['profit']]);
        $this->assertSame(1250.0, (float) $this->as()->getJson('/api/v1/workstudy/reports/daily-costing/preview?date=2026-10-25')->json('data.rows.0.profit'));
        $this->assertSame('T-Shirt', $this->as()->getJson("/api/v1/workstudy/reports/item-pl/preview?{$range}")->json('data.rows.0.item_name'));
        $this->assertCount(0, $this->as()->getJson("/api/v1/workstudy/reports/order-pl/preview?{$range}&order=ORD-9")->json('data.rows'));

        // Section profit added up over the range.
        $section = (string) Str::ulid();
        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => DB::table('factories')->where('company_id', $this->delta->id)->value('id'), 'name' => 'Production', 'code' => 'PRD', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sections')->insert(['id' => $section, 'company_id' => $this->delta->id, 'department_id' => $dept, 'name' => 'Cutting', 'code' => 'CUT', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['2026-10-25' => 8000, '2026-10-26' => 6000] as $date => $income) {
            WsSectionResult::query()->create(['company_id' => $this->delta->id, 'result_date' => $date, 'section_id' => $section, 'income' => $income, 'oh_cost' => 2000, 'mat_cost' => 3000, 'expense' => 500]);
        }
        $sp = $this->as()->getJson("/api/v1/workstudy/reports/section-pl/preview?{$range}")->json('data.rows.0');
        $this->assertSame(['Cutting', 2, 14000.0, 3000.0, 'PROFIT'], [$sp['section'], $sp['days'], (float) $sp['income'], (float) $sp['profit'], $sp['standing']]);

        // A report whose figures live in a module not built says so.
        $wip = $this->as()->getJson('/api/v1/workstudy/reports/input-output-wip/preview?date=2026-10-25')->assertOk()->json('data');
        $this->assertSame(['garments', []], [$wip['pending'], $wip['rows']]);
        $this->assertCount(17, array_filter(WsReports::REPORTS, fn ($r) => $r[2] === null));

        // PDF and Excel.
        $this->assertStringStartsWith('%PDF', $this->as()->get("/api/v1/workstudy/reports/target-achievement/export/pdf?{$range}")->assertOk()->getContent());
        $this->as()->get('/api/v1/workstudy/reports/efficiency/export/xlsx?date=2026-10-25')->assertOk();
        $this->as()->get('/api/v1/workstudy/reports/input-output-wip/export/pdf?date=2026-10-25')->assertNotFound();

        // A report run: Brickland's message without a report; the rows it found that day.
        $this->as()->postJson('/api/v1/erp/ws-report-runs', ['production_date' => '2026-10-25'])->assertUnprocessable()->assertJsonPath('errors.report_key.0', 'Report is required.');
        $run = $this->as()->postJson('/api/v1/erp/ws-report-runs', ['report_key' => 'efficiency', 'floor_id' => $this->floor->id, 'production_date' => '2026-10-25', 'export_excel' => false])->assertCreated()->json('data');
        $this->assertSame(['Efficiency Report', 'Efficiency', 'All', 2], [$run['report'], $run['category'], $run['unit'], $run['rows_count']]);
        $this->assertSame(0, $this->as()->postJson('/api/v1/erp/ws-report-runs', ['report_key' => 'finishing_wip', 'production_date' => '2026-10-25'])->assertCreated()->json('data.rows_count'));

        // A line chief has no part in the reports.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/workstudy/reports/options')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
