<?php

declare(strict_types=1);

namespace Tests\Feature\SalesShipment;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Sales & Shipment → Report (AnnotechERP 21/07): the report set (each report
 * reads only its own boxes; values in BDT; Pending = Order Qty less the
 * larger of net delivered and shipped; a status only on the reports of its
 * kind), Order-wise Tracking (every event in order, the balances above) and
 * Notifications & Alerts (worked out live from the papers) — and who may.
 */
class SalesShipmentReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $so;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $buyer = MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => 'SUP-1', 'name' => 'Arafah Knit Composite', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $printer = MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => 'SUB-1', 'name' => 'Bestex Printing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $usd = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $usd, 'company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 110, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $item = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'PL-01', 'name' => 'Poly Bag', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true])->id;
        $wh = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $wh->id, 'name' => 'Accessories Store', 'code' => 'ST-ACC', 'active' => true])->id;

        // SO: one line, sizes M 500 and L 300 at 2 USD (rate 110) — 176,000 BDT.
        $this->so = $this->as()->postJson('/api/v1/sales-shipment/orders', [
            'search_for' => 'SALES_ORDER', 'so_type' => 'GENERAL', 'customer_id' => $buyer, 'receive_date' => '2026-11-01', 'program_by' => 'Rahim', 'program_to' => 'Karim', 'bill_to_id' => $buyer,
            'delivery_target_date' => '2026-11-20', 'same_as_bill_to' => true, 'payment_method' => 'LC', 'currency_id' => $usd, 'conv_rate' => 110, 'mode_of_shipment' => 'By Truck', 'po_wo_no' => 'WO-1',
            'shipment_date' => '2026-11-25', 'lines' => [['sl_no' => 1, 'item_id' => $item, 'rate' => 2, 'style_ref' => 'ST-77']],
            'sizes' => [['sl_no' => 1, 'size' => 'M', 'qty' => 500], ['sl_no' => 1, 'size' => 'L', 'qty' => 300]],
        ])->assertCreated()->json('data.so_no');
        foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
            $this->as()->postJson("/api/v1/sales-shipment/orders/{$this->so}/{$s}")->assertOk();
        }
        $m = (string) DB::table('ss_sales_order_sizes')->where('size', 'M')->value('id');
        $l = (string) DB::table('ss_sales_order_sizes')->where('size', 'L')->value('id');

        // 300 of M through the gate, 40 back: net 260.
        $this->as()->postJson('/api/v1/sales-shipment/challans', ['so_no' => $this->so, 'customer_id' => $buyer, 'chalan_date' => '2026-11-05', 'store_id' => $store, 'delivery_target_date' => '2026-11-20',
            'mode_of_shipment' => 'By Truck', 'currency_id' => $usd, 'conv_rate' => 110, 'carrier_name' => 'Karim Transport', 'lines' => [['size_id' => $m, 'qty' => 300]]])->assertCreated();
        $this->act('challans', 'DC-20261105-0001', 'submit');
        $this->act('challans', 'DC-20261105-0001', 'deliver');
        $line = $this->as()->getJson('/api/v1/sales-shipment/challan-returns/source/DC-20261105-0001')->json('data.lines.0.challan_line_id');
        $this->as()->postJson('/api/v1/sales-shipment/challan-returns', ['chalan_no' => 'DC-20261105-0001', 'return_date' => '2026-11-06', 'reason' => 'Quality Issue', 'lines' => [['challan_line_id' => $line, 'qty' => 40]]])->assertCreated();
        $this->act('challan-returns', 'DCR-2026-000001', 'confirm');

        // 200 of M shipped (SHP-1, confirmed); 50 of L planned for the 12th (SHP-2).
        $this->as()->postJson('/api/v1/sales-shipment/shipments', ['so_no' => $this->so, 'plan_date' => '2026-11-06', 'shipment_date' => '2026-11-07', 'ex_factory_date' => '2026-11-07', 'shipment_mode' => 'Sea',
            'lines' => [['size_id' => $m, 'qty' => 200]]])->assertCreated();
        DB::table('ss_shipments')->where('shipment_no', 'SHP-2026-000001')->update(['stage' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $this->owner->id]);
        DB::table('ss_shipment_lines')->update(['shipped_qty' => DB::raw('qty')]);
        $this->as()->postJson('/api/v1/sales-shipment/shipments', ['so_no' => $this->so, 'plan_date' => '2026-11-07', 'shipment_date' => '2026-11-12', 'ex_factory_date' => '2026-11-11', 'shipment_mode' => 'Sea',
            'lines' => [['size_id' => $l, 'qty' => 50]]])->assertCreated();
        $this->as()->postJson('/api/v1/sales-shipment/certificates', ['certificate_name' => 'OEKO-TEX', 'certificate_no' => 'OT-1', 'shipment_no' => 'SHP-2026-000002', 'issue_date' => '2026-11-01', 'expiry_date' => '2026-11-20'])->assertCreated();

        // 100 of L to the printer, due back on the 6th; 60 sent.
        $sc = $this->as()->postJson('/api/v1/sales-shipment/sc-programmes', ['so_no' => $this->so, 'party_id' => $printer, 'sc_date' => '2026-11-02', 'target_date' => '2026-11-06', 'process' => 'Printing',
            'lines' => [['size_id' => $l, 'qty' => 100, 'rate' => 0.5]]])->assertCreated()->json('data.sc_no');
        $pl = $this->as()->getJson("/api/v1/sales-shipment/sc-deliveries/source/{$sc}")->json('data.lines.0.programme_line_id');
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', ['sc_no' => $sc, 'customer_id' => $printer, 'chalan_date' => '2026-11-04', 'store_id' => $store, 'delivery_target_date' => '2026-11-06',
            'mode_of_shipment' => 'By Van', 'carrier_name' => 'Karim Transport', 'lines' => [['programme_line_id' => $pl, 'qty' => 60]]])->assertCreated();
        $this->act('sc-deliveries', 'SCD-20261104-0001', 'deliver');
    }

    public function test_the_report_set_reads_its_own_boxes_values_in_bdt_and_pending_counted_once(): void
    {
        $opt = $this->as()->getJson('/api/v1/sales-shipment/reports/options')->assertOk()->json('data');
        $this->assertCount(21, $opt['reports']);
        $this->assertEquals(['store', 'item'], collect($opt['reports'])->firstWhere('kind', 'sales-and-delivery')['params']);
        $this->assertEquals([['value' => 'ST-77', 'label' => 'ST-77']], $opt['styles']);

        $sum = $this->run_('sales-order-summary');
        $this->assertEquals([800.0, 176000.0, 260.0, 200.0, 540.0, 118800.0], [$sum['rows'][0]['order_qty'], $sum['rows'][0]['order_value'], $sum['rows'][0]['net_delivered'], $sum['rows'][0]['shipped'], $sum['rows'][0]['pending_qty'], $sum['rows'][0]['pending_value']]);
        $this->assertEquals(540.0, $sum['totals']['pending_qty']);

        $all = $this->run_('sales-shipment-summary');
        $this->assertEquals(['Poly Bag', 260.0, 57200.0, 200.0, 44000.0], [$all['rows'][0]['particular'], $all['rows'][0]['delivered_qty'], $all['rows'][0]['delivered_value'], $all['rows'][0]['shipment_qty'], $all['rows'][0]['shipment_value']]);

        $pend = collect($this->run_('delivery-pending')['rows'])->keyBy('size');
        $this->assertEquals([240.0, 12], [$pend['M']['pending_qty'], $pend['M']['days']]);
        $this->assertEquals(300.0, $pend['L']['pending_qty']);
        $ship = collect($this->run_('shipment-pending')['rows'])->keyBy('size');
        $this->assertEquals([300.0, 300.0, 50.0], [$ship['M']['pending_qty'], $ship['M']['available'], $ship['L']['planned']]);

        $ch = $this->run_('challan-statement')['rows'][0];
        $this->assertEquals(['DC-20261105-0001', 300.0, 40.0, 260.0, 57200.0], [$ch['chalan_no'], $ch['qty'], $ch['returned'], $ch['net_qty'], $ch['value']]);
        $this->assertCount(0, $this->run_('challan-statement', ['from' => '2026-11-06'])['rows']);

        $sc = $this->run_('sc-balance-summary')['rows'][0];
        $this->assertEquals([100.0, 60.0, 60.0, 40.0], [$sc['contract_qty'], $sc['delivered'], $sc['with_party'], $sc['to_deliver']]);
        $st = collect($this->run_('shipment-status')['rows'])->keyBy('shipment_no');
        $this->assertEquals([200.0, 200.0], [$st['SHP-2026-000001']['planned'], $st['SHP-2026-000001']['shipped']]);
        $this->assertEquals(0.0, $st['SHP-2026-000002']['shipped']);

        // A status only on the reports that read its kind; a box the report does not read is dropped.
        $this->assertCount(2, $this->run_('sales-order-report', ['status' => 'delivery:PARTIAL'])['rows']);
        $this->assertCount(0, $this->run_('sales-order-report', ['status' => 'delivery:DONE'])['rows']);
        $this->as()->postJson('/api/v1/sales-shipment/reports/run', ['kind' => 'sales-order-report', 'status' => 'challan:DRAFT'])->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.sr_status', ['report' => __('salesshipment.sr.kind.sales-order-report'), 'kinds' => 'Approval, Order, Delivery, Shipment'])]);
        $this->assertCount(1, $this->run_('sales-order-summary', ['status' => 'challan:DRAFT'])['rows']);
        $this->as()->postJson('/api/v1/sales-shipment/reports/run', ['kind' => 'sales-order-summary', 'from' => '2026-11-08', 'to' => '2026-11-01'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sr_range')]);

        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson('/api/v1/sales-shipment/reports/options')->assertOk();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->postJson('/api/v1/sales-shipment/reports/run', ['kind' => 'sales-order-summary'])->assertForbidden();
    }

    public function test_order_tracking_lists_every_event_in_order_with_the_balances(): void
    {
        $this->assertSame($this->so, $this->as()->getJson('/api/v1/sales-shipment/order-tracking')->assertOk()->json('data.0.value'));
        $t = $this->as()->getJson("/api/v1/sales-shipment/order-tracking/{$this->so}")->assertOk()->json('data');
        $this->assertEquals([800.0, 260.0, 200.0, 540.0, 600.0, 600.0], [$t['figures']['order_qty'], $t['figures']['net_delivered'], $t['figures']['shipped'], $t['figures']['delivery_balance'], $t['figures']['shipment_balance'], $t['figures']['available']]);
        $stages = array_column($t['events'], 'stage');
        $this->assertSame(__('salesshipment.ot.stage.order'), $stages[0]);
        foreach (['approval', 'sc_programme', 'challan', 'return', 'sc_delivery', 'shipment', 'confirmation'] as $s) {
            $this->assertContains(__("salesshipment.ot.stage.{$s}"), $stages, $s);
        }
        $dates = array_column($t['events'], 'date');
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates);
        $challan = collect($t['events'])->firstWhere('reference', 'DC-20261105-0001');
        $this->assertEquals([300.0, 66000.0], [$challan['qty'], $challan['value']]);
        $this->as()->getJson('/api/v1/sales-shipment/order-tracking/SO-NOPE')->assertNotFound();
    }

    public function test_alerts_are_worked_out_live_from_the_papers(): void
    {
        $rows = collect($this->as()->getJson('/api/v1/sales-shipment/alerts')->assertOk()->json('data.rows'));
        $kinds = $rows->pluck('kind')->all();
        sort($kinds);
        $this->assertEquals(['BOOKING_PENDING', 'CERTIFICATE_EXPIRING', 'DOCUMENT_PENDING', 'SHIPMENT_DUE', 'SUBCONTRACT_OVERDUE'], $kinds);
        $sub = $rows->firstWhere('kind', 'SUBCONTRACT_OVERDUE');
        $this->assertEquals(['critical', -2, 'sc-pending'], [$sub['severity'], $sub['days'], $sub['screen']]);
        $this->assertEquals([4, 'shipment-confirmation'], [$rows->firstWhere('kind', 'SHIPMENT_DUE')['days'], $rows->firstWhere('kind', 'SHIPMENT_DUE')['screen']]);
        $this->assertSame(12, $rows->firstWhere('kind', 'CERTIFICATE_EXPIRING')['days']);
        $this->assertSame('critical', $rows->first()['severity']);
        $summary = collect($this->as()->getJson('/api/v1/sales-shipment/alerts')->json('data.summary'))->keyBy('kind');
        $this->assertEquals([1, 1, 0], [$summary['SUBCONTRACT_OVERDUE']['count'], $summary['SUBCONTRACT_OVERDUE']['critical'], $summary['DELIVERY_DUE']['count']]);

        // Filing the EXP and the B/L clears the document alert; the delivery window opens a week ahead.
        foreach ([['export', 'EXP', 'EXP-1'], ['blawb', 'BILL_OF_LADING', 'BL-1']] as [$fam, $type, $no]) {
            $this->as()->postJson("/api/v1/sales-shipment/export-docs/{$fam}", ['shipment_no' => 'SHP-2026-000002', 'doc_type' => $type, 'document_no' => $no, 'document_date' => '2026-11-08'])->assertCreated();
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-14 10:00:00', 'Asia/Dhaka'));
        $rows = collect($this->as()->getJson('/api/v1/sales-shipment/alerts')->json('data.rows'));
        $this->assertNull($rows->firstWhere('kind', 'DOCUMENT_PENDING'));
        $this->assertEquals([6, 'warning'], [$rows->firstWhere('kind', 'DELIVERY_DUE')['days'], $rows->firstWhere('kind', 'DELIVERY_DUE')['severity']]);
        $this->assertEquals([-2, 'critical'], [$rows->firstWhere('kind', 'SHIPMENT_OVERDUE')['days'], $rows->firstWhere('kind', 'SHIPMENT_OVERDUE')['severity']]);
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/sales-shipment/alerts')->assertForbidden();
    }

    private function run_(string $kind, array $params = []): array
    {
        return $this->as()->postJson('/api/v1/sales-shipment/reports/run', ['kind' => $kind] + $params)->assertOk()->json('data');
    }

    private function act(string $kind, string $no, string $action): TestResponse
    {
        return $this->as()->postJson("/api/v1/sales-shipment/{$kind}/{$no}/{$action}")->assertOk();
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
