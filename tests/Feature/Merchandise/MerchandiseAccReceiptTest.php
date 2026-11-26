<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\ProcPostingSetup;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Accessories Receipt Report (AnnotechERP 06/06): the
 * Receive List giving, supplier by supplier, the orders still to receive; a
 * receipt numbered MRR-ACC-2026100001 with its other costs and its bill
 * (discount, VAT on the VAT base, TDS and VDS capped at the VAT, landed cost
 * shared over the lines); nothing received beyond what was ordered; a
 * receipt locking its order; Finalize MRR moving the order to Partial and
 * Received and putting the goods on stock.
 */
class MerchandiseAccReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Trims Store', 'code' => 'ST-TRM', 'active' => true])->id;
    }

    public function test_a_receipt_bills_its_goods_and_costs_and_finalising_moves_its_order_and_stock(): void
    {
        $order = $this->order();
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'Pcs', 'active' => true])->id;
        $label = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-LBL-01', 'name' => 'Care Label', 'category' => 'Label', 'uom' => 'Pcs', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $other = MaterialSupplier::query()->create(['code' => 'SUP-02', 'name' => 'Moon Trims', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $poHead = ['supplier_id' => $star, 'po_date' => '2026-10-17', 'source' => 'LOCAL', 'payment_method' => 'LC', 'currency' => 'BDT', 'payment_term' => 'AT_SIGHT', 'mode_of_shipment' => 'ROAD', 'delivery_date' => '2026-11-10', 'delivery_store_id' => $this->store];
        $po = $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$poHead, 'lines' => [['item_id' => $button, 'qty' => 100, 'unit_price' => 0.85], ['item_id' => $label, 'qty' => 50, 'unit_price' => 0.2]]])->assertCreated()->json('data.po_no');

        // The cost heads a company starts with, as Brickland ships them.
        $options = $this->as()->getJson('/api/v1/merchandise/acc-receipts/options')->assertOk()->json('data');
        $this->assertSame([['Carriage Inward (CARRIAGE)', true, false], ['C&F Charges (CNF)', true, true], ['LC Charges (LC-CHARGE)', true, false], ['Insurance (INSURANCE)', true, false], ['Loading / Unloading (LOADING)', true, false]],
            array_map(fn ($h) => [$h['label'], $h['landed'], $h['vat_applicable']], $options['cost_heads']));
        $this->assertSame([$po], array_column($options['orders'], 'label'));

        $list = $this->as()->getJson('/api/v1/merchandise/acc-receipts/receivables')->assertOk()->json('data');
        $this->assertSame([['Star Accessories (SUP-01)', 1]], array_map(fn ($s) => [$s['label'], $s['count']], $list['suppliers']));
        $this->assertSame([], $list['orders'], 'Nothing is read until a supplier is picked.');
        $this->assertEquals([[$po, 'Trims Store', 150, 0, 150]], array_map(fn ($o) => [$o['po_no'], $o['store'], $o['ordered'], $o['received'], $o['left']], $this->as()->getJson("/api/v1/merchandise/acc-receipts/receivables?supplier={$star}")->json('data.orders')));

        $fill = $this->as()->getJson("/api/v1/merchandise/acc-receipts/receivables/{$po}")->assertOk()->json('data');
        $this->assertSame([$star, $this->store, 'LC', 'LOCAL', 'BDT'], [$fill['supplier_id'], $fill['store_id'], $fill['payment_method'], $fill['source_type'], $fill['currency']]);
        $this->assertEquals([[$button, 'Button 18L', 100, 0.85], [$label, 'Care Label', 50, 0.2]], array_map(fn ($l) => [$l['item_id'], $l['description'], $l['qty'], $l['rate']], $fill['lines']));
        [$btnLine, $lblLine] = array_column($fill['lines'], 'po_line_id');

        $this->as()->postJson('/api/v1/merchandise/acc-receipts', ['lines' => [], 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors([
            'unit_company_id' => 'Company is required.', 'supplier_id' => 'Supplier is required.', 'store_id' => 'Receiving Store is required.', 'mrr_date' => 'Date of Receive is required.',
            'payment_method' => 'Payment Method is required.', 'source_type' => 'Source Type is required.', 'challan_no' => 'Challan No is required.', 'challan_date' => 'Challan Date is required.',
            'currency' => 'Currency is required.', 'mrr_type' => 'MRR Type is required.',
        ]);
        $head = ['unit_company_id' => $this->delta->id, 'supplier_id' => $star, 'store_id' => $this->store, 'mrr_date' => '2026-10-20', 'payment_method' => 'LC', 'source_type' => 'LOCAL', 'challan_no' => ' CH-771 ', 'challan_date' => '2026-10-19', 'currency' => 'BDT', 'mrr_type' => 'PO', 'po_id' => $fill['po_id']];
        $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'challan_date' => '2026-10-21', 'lines' => [], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['challan_date' => 'Challan Date cannot be after the Date of Receive.']);
        $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'lines' => [], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'A receipt with no lines is not a receipt.']);
        $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'supplier_id' => $other, 'po_id' => null, 'lines' => [['item_id' => $button, 'po_line_id' => $btnLine, 'qty' => 1]], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.po_line_id' => "Row 1: PO {$po} is another supplier's order."]);
        $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'lines' => [['item_id' => $button, 'po_line_id' => $btnLine, 'qty' => 101]], 'costs' => [['amount' => 0]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Row 1: only 100 is left to receive on PO {$po}.", 'costs.0.cost_head_id' => 'Other cost 1: pick the cost head.', 'costs.0.amount' => 'Other cost 1: Amount must be above zero.']);

        $carriage = ProcCostHead::query()->where('code', 'CARRIAGE')->value('id');
        $cnf = ProcCostHead::query()->where('code', 'CNF')->value('id');
        $saved = $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'discount_pct' => 10, 'vat_pct' => 10, 'tds_pct' => 2, 'vds_pct' => 20,
            'lines' => [['item_id' => $button, 'po_line_id' => $btnLine, 'qty' => 60, 'rate' => 0.85, 'batch' => 'B-1'], ['item_id' => $label, 'po_line_id' => $lblLine, 'qty' => 50, 'rate' => 0.2]],
            'costs' => [['cost_head_id' => $carriage, 'amount' => 20], ['cost_head_id' => $cnf, 'amount' => 9, 'remarks' => 'Agent']],
        ])->assertCreated()->json('data');
        // Goods 51 + 10 = 61, less 10% = 54.9; landed 54.9 + 29 = 83.9; VAT base 54.9 + C&F 9 = 63.9, VAT 6.39;
        // TDS 2% of 83.9 = 1.68; VDS 20% of 63.9 = 12.78, capped at the VAT 6.39; net 54.9 + 29 + 6.39 − 1.68 − 6.39 = 82.22.
        $this->assertEquals(['MRR-ACC-2026100001', 'DRAFT', 'CH-771', 61, 29, 83.9, 63.9, 6.39, 1.68, 6.39, 82.22, null], [$saved['mrr_no'], $saved['status'], $saved['challan_no'], $saved['goods_value'], $saved['other_cost'], $saved['landed_cost'], $saved['vat_base'], $saved['vat'], $saved['tds'], $saved['vds'], $saved['net_payable'], $saved['voucher_no']]);
        $this->assertEquals([[51, 70.15, 'PCS', $po], [10, 13.75, 'PCS', $po]], array_map(fn ($l) => [$l['amount'], $l['landed_amount'], $l['uom'], $l['po_no']], $saved['lines']));
        $this->assertEquals([[true, false, 20], [true, true, 9]], array_map(fn ($c) => [$c['landed'], $c['vat_applicable'], $c['amount']], $saved['costs']));

        // A draft receipt already names the order's lines: the order is no longer rewritten, but still Pending.
        $this->as()->putJson("/api/v1/merchandise/acc-purchase-orders/{$po}", [...$poHead, 'lines' => [['item_id' => $button, 'qty' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'This order has a receipt against it — it can no longer be changed or removed here; revise it instead.']);
        $this->assertSame(['PENDING', false], [AccPurchaseOrder::query()->where('po_no', $po)->value('receive_status'), $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/{$po}")->json('data.editable')]);
        $this->assertEquals([[$po, 150, 110, 40]], array_map(fn ($o) => [$o['po_no'], $o['ordered'], $o['received'], $o['left']], $this->as()->getJson("/api/v1/merchandise/acc-receipts/receivables?supplier={$star}")->json('data.orders')));

        // A draft removed frees what it held.
        $draft = $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'lines' => [['item_id' => $button, 'po_line_id' => $btnLine, 'qty' => 41]], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Row 1: only 40 is left to receive on PO {$po}."]);
        $draft = $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'lines' => [['item_id' => $button, 'po_line_id' => $btnLine, 'qty' => 10]], 'costs' => []])->assertCreated()->json('data.mrr_no');
        $this->as()->deleteJson("/api/v1/merchandise/acc-receipts/{$draft}")->assertOk();
        $this->assertEquals([40], array_column($this->as()->getJson("/api/v1/merchandise/acc-receipts/receivables?supplier={$star}")->json('data.orders'), 'left'));

        // With no active posting setup for the company, finalising is refused rather than posted somewhere by guess.
        $this->as()->postJson('/api/v1/merchandise/acc-receipts/MRR-ACC-2026100001/finalize')->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Company Delta Apparels Ltd has no active procurement posting setup — the receipt is not finalised rather than posted somewhere by guess.']);
        $this->postingSetup();
        $final = $this->as()->postJson('/api/v1/merchandise/acc-receipts/MRR-ACC-2026100001/finalize')->assertOk()->json('data');
        $this->assertSame(['FINALIZED', false], [$final['status'], $final['editable']]);
        $this->assertSame('PARTIAL', AccPurchaseOrder::query()->where('po_no', $po)->value('receive_status'));
        $locked = 'This receipt is finalised — it can no longer be changed or removed.';
        $this->as()->putJson('/api/v1/merchandise/acc-receipts/MRR-ACC-2026100001', [...$head, 'lines' => [['item_id' => $button, 'qty' => 1]], 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors(['status' => $locked]);
        $this->as()->deleteJson('/api/v1/merchandise/acc-receipts/MRR-ACC-2026100001')->assertUnprocessable()->assertJsonValidationErrors(['status' => $locked]);
        $this->as()->postJson('/api/v1/merchandise/acc-receipts/MRR-ACC-2026100001/finalize')->assertUnprocessable();

        // The finalised goods are on stock: an indent for buttons now finds 60 in store.
        $pr = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-21', 'order_id' => $order['id'], 'lines' => [['item_id' => $button, 'required_qty' => 100]]])->assertCreated()->json('data.lines.0');
        $this->assertEquals([60, 40], [$pr['stock_qty'], $pr['short_qty']]);

        // The last 40, with a typed discount amount: the order is Received and leaves the Receive List.
        $second = $this->as()->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'challan_no' => 'CH-790', 'discount_amount' => 5, 'lines' => [['item_id' => $button, 'po_line_id' => $btnLine, 'qty' => 40, 'rate' => 0.85]], 'costs' => []])->assertCreated()->json('data');
        $this->assertEquals(['MRR-ACC-2026100002', 34, 29, 29], [$second['mrr_no'], $second['goods_value'], $second['landed_cost'], $second['net_payable']]);
        $this->as()->postJson('/api/v1/merchandise/acc-receipts/MRR-ACC-2026100002/finalize')->assertOk();
        $this->assertSame('RECEIVED', AccPurchaseOrder::query()->where('po_no', $po)->value('receive_status'));
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-receipts/receivables')->json('data.suppliers'));
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-receipts/options')->json('data.orders'));

        $records = $this->as()->getJson('/api/v1/merchandise/acc-receipts')->assertOk()->json('data');
        $this->assertEquals([['MRR-ACC-2026100002', 'CH-790', $po, 1, 29, 'FINALIZED'], ['MRR-ACC-2026100001', 'CH-771', $po, 2, 82.22, 'FINALIZED']], array_map(fn ($r) => [$r['mrr_no'], $r['challan_no'], $r['po_no'], $r['items'], $r['net_payable'], $r['status']], $records));

        // A store manager sees the receipts but does not record them.
        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/acc-receipts')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/acc-receipts', [...$head, 'lines' => [], 'costs' => []])->assertForbidden();
    }

    /** The company's posting setup: a journal voucher on five ledgers. */
    private function postingSetup(): void
    {
        $ledger = fn (string $code, string $name, string $type) => AccAccount::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'type' => $type, 'postable' => true, 'active' => true])->id;
        ProcPostingSetup::query()->create(['company_id' => $this->delta->id, 'unit_company_id' => $this->delta->id, 'voucher_type' => 'JV', 'inventory_ledger_id' => $ledger('1120', 'Inventory — Trims', 'ASSET'),
            'vat_ledger_id' => $ledger('1210', 'VAT Receivable', 'ASSET'), 'payable_ledger_id' => $ledger('2020201', 'Accounts Payable', 'LIABILITY'), 'tds_ledger_id' => $ledger('2030101', 'TDS Payable', 'LIABILITY'),
            'vds_ledger_id' => $ledger('2030102', 'VDS Payable', 'LIABILITY'), 'active' => true]);
    }

    /** A confirmed order of 100 pcs for style 637PK. */
    private function order(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data');
        Order::query()->whereKey($order['id'])->update(['status' => 'CONFIRMED']);

        return $order;
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
