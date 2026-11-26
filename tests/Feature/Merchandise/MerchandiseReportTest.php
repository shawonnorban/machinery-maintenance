<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Http\Controllers\Api\MerchandiseReportApiController;
use App\Modules\Merchandise\Models\Budget;
use App\Modules\Merchandise\Models\BudgetLine;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderTna;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Reports (AnnotechERP 06/13): every report of the set
 * previews from the module's tables as of the From Date; the order,
 * booking, size, budget and T&A figures read through; the reports that
 * wait on modules not built say so.
 */
class MerchandiseReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-16 10:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_every_report_previews_and_the_figures_read_through(): void
    {
        $order = $this->order();
        $this->budget($order);
        $this->booking($order);
        $this->calendar($order);

        // Every report answers, with its columns.
        foreach (MerchandiseReportApiController::REPORTS as $key) {
            $data = $this->report($key);
            $this->assertSame($key, $data['report']);
            $this->assertNotEmpty($data['columns'], $key);
        }

        $this->assertSame([['LPP', $order->order_no, '637PK', '2026-10-08', '2026-11-01', 480.0, 960.0]], array_map(
            fn ($r) => [$r['buyer'], $r['order_no'], $r['style'], $r['conf_date'], $r['ship_date'], (float) $r['qty'], (float) $r['value']], $this->report('order-confirmation-list')['rows'],
        ));
        $this->assertSame([['BFB-00001', 'BULK', $order->order_no, 'APPROVED', '39.8 kg']], array_map(
            fn ($r) => [$r['booking_no'], $r['booking_type'], $r['order_no'], $r['status'], $r['qty_label']], $this->report('bulk-fabric-booking')['rows'],
        ));
        $this->assertSame([['Navy', 'M', 240.0], ['Navy', 'S', 120.0], ['White', 'M', 120.0]], array_map(fn ($r) => [$r['colour'], $r['size'], (float) $r['qty']], $this->report('order-size-breakdown')['rows']));

        // Budget: 200 yarn + 50 knitting + 120 CM = 370 against an order worth 960 (FOB is not a cost).
        $sheet = $this->report('budget-sheet')['rows'][0];
        $this->assertEquals([960, 370, 590, true], [$sheet['order_value'], $sheet['budget_cost'], $sheet['margin'], $sheet['approved']]);
        $this->assertEquals(61.46, $this->report('order-budget-costing')['rows'][0]['margin_pct']);
        $this->assertEquals([['yarn', 200, 210, -10], ['knitting', 50, 40, 10], ['cm', 120, 0, 120]], array_map(fn ($r) => [$r['head'], $r['budget'], $r['actual'], $r['variance']], $this->report('budget-vs-actual')['rows']));
        $chart = $this->report('budget-vs-actual-chart');
        $this->assertEquals([[$order->order_no, 370, 250]], array_map(fn ($r) => [$r['order_no'], $r['budget'], $r['actual']], $chart['rows']));
        $this->assertSame(['label' => 'order_no', 'series' => ['budget', 'actual']], $chart['chart']);
        $this->assertSame(['knitting'], array_column($this->report('textile-budget-vs-actual')['rows'], 'head'));
        $this->assertEquals([['LPP', 1, 480, 960, 2, 370, 590]], array_map(fn ($r) => [$r['buyer'], $r['orders'], $r['qty'], $r['order_value'], $r['avg_fob'], $r['budget_cost'], $r['net_margin']], $this->report('buyer-costing-margin')['rows']));
        $this->assertEquals([['LPP', '2026-11', 120, 3]], array_map(fn ($r) => [$r['buyer'], $r['month'], $r['cm'], $r['cm_per_dz']], $this->report('buyer-cm-earning')['rows']));

        // T&A: Issue PI planned 10 Oct and open (6 days late), Sample Approval done on its day, Ex-Factory not yet due.
        $this->assertSame([[$order->order_no, 'Issue PI', '2026-10-10', null, 6]], array_map(fn ($r) => [$r['order_no'], $r['task'], $r['plan_date'], $r['actual_date'], $r['days_late']], $this->report('task-tna-fail')['rows']));
        $this->assertEquals([['General Process', 2, 1, 50.0], ['Shipment', 1, 0, 0.0]], array_map(fn ($r) => [$r['department'], $r['tasks'], $r['failed'], $r['fail_pct']], $this->report('dept-tna-fail')['rows']));
        $this->assertSame([['LPP', 'General Process', 2, 1]], array_map(fn ($r) => [$r['buyer'], $r['department'], $r['tasks'], $r['failed']], array_slice($this->report('buyer-dept-tna-fail')['rows'], 0, 1)));
        $this->assertSame(['637PK', 'General Process'], [$this->report('style-dept-tna-fail')['rows'][0]['style'], $this->report('style-dept-tna-fail')['rows'][0]['department']]);
        $this->assertSame([['Ex-Factory', 1, 0, 1, 0], ['Issue PI', 1, 0, 1, 1], ['Sample Approval', 1, 1, 0, 0]], array_map(fn ($r) => [$r['task'], $r['instances'], $r['done'], $r['open'], $r['failed']], $this->report('tna-pass-fail-summary')['rows']));

        // Figures from modules not built read zero, and the report says which.
        $tracking = $this->report('order-tracking');
        $this->assertSame(['production', 0, 0], [$tracking['pending_module'], $tracking['rows'][0]['cut'], $tracking['rows'][0]['shipped']]);
        $this->assertSame(['stores', 39.8], [$this->report('finish-fabric-stock')['pending_module'], (float) $this->report('finish-fabric-stock')['rows'][0]['balance']]);
        $this->assertSame([null, []], [$this->report('order-confirmation-list')['pending_module'], $this->report('trims-stock')['rows']]);

        // From a later date the order book is empty.
        $this->assertSame([], $this->report('order-confirmation-list', '2026-10-09')['rows']);
        $this->as()->getJson('/api/v1/merchandise/reports/no-such-report')->assertUnprocessable();
    }

    public function test_reports_are_closed_to_a_technician_and_open_to_a_manager(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/merchandise/reports/order-confirmation-list')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/merchandise/reports/order-confirmation-list?from=2026-01-01')->assertOk();
    }

    private function report(string $key, string $from = '2026-01-01'): array
    {
        return $this->as()->getJson("/api/v1/merchandise/reports/{$key}?from={$from}")->assertOk()->json('data');
    }

    private function budget(Order $order): void
    {
        $budget = Budget::query()->create(['budget_no' => 'BGT-2026-000001', 'order_id' => $order->id, 'style_id' => $order->style_id, 'currency' => 'USD', 'exchange_rate' => 1,
            'process_costs' => ['knitting' => 50], 'overall_costs' => ['cm' => 120, 'fob' => 999], 'actual_costs' => ['yarn' => 210, 'knitting' => 40], 'approved_at' => '2026-10-10 09:00:00']);
        BudgetLine::query()->create(['budget_id' => $budget->id, 'section' => 'YARN', 'description' => 'Cotton 30/1', 'uom' => 'KG', 'qty' => 100, 'rate' => 2, 'sort_order' => 1]);
    }

    private function booking(Order $order): void
    {
        $sj = FabricType::query()->create(['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1, 'active' => true])->id;
        $b = FabricBooking::query()->create(['booking_type' => 'BULK', 'booking_no' => 'BFB-00001', 'booking_date' => '2026-10-12', 'order_id' => $order->id, 'style_id' => $order->style_id, 'currency' => 'USD', 'conv_rate' => 1, 'status' => 'APPROVED']);
        FabricBookingLine::query()->create(['booking_id' => $b->id, 'fabric_kind' => 'KNIT', 'fabric_type_id' => $sj, 'gmt_style_id' => $order->style_id, 'consumption' => 1.2, 'finish_qty' => 39.8, 'cons_unit' => 'KG', 'disp_order' => 1]);
    }

    private function calendar(Order $order): void
    {
        $general = TnaTaskGroup::query()->create(['code' => 'GP', 'name' => 'General Process', 'active' => true])->id;
        $shipment = TnaTaskGroup::query()->create(['code' => 'SH', 'name' => 'Shipment', 'active' => true])->id;
        foreach ([['Issue PI', $general, '2026-10-10', null], ['Sample Approval', $general, '2026-10-12', '2026-10-12'], ['Ex-Factory', $shipment, '2026-11-01', null]] as $i => [$name, $group, $plan, $actual]) {
            $task = TnaTask::query()->create(['tna_task_group_id' => $group, 'code' => "T{$i}", 'sort_order' => $i, 'name' => $name, 'active' => true])->id;
            OrderTna::query()->create(['order_id' => $order->id, 'tna_task_id' => $task, 'plan_date' => $plan, 'actual_date' => $actual, 'sort_order' => $i + 1]);
        }
    }

    /** A confirmed order of 637PK (LPP): 480 pieces at 2, confirmed 8 Oct, shipping 1 Nov, with its sizes. */
    private function order(): Order
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $id = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 480, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/orders/{$id}/sizes", ['rows' => [['colour' => 'Navy', 'size' => 'S', 'qty' => 120], ['colour' => 'Navy', 'size' => 'M', 'qty' => 240], ['colour' => 'White', 'size' => 'M', 'qty' => 120]]])->assertOk();
        $order = Order::query()->findOrFail($id);
        $order->forceFill(['status' => 'CONFIRMED', 'order_type' => 'BULK_NEW', 'conf_date' => '2026-10-08', 'ship_date' => '2026-11-01', 'po_no' => '1531', 'buyer_id' => $buyer])->save();

        return $order->refresh();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
