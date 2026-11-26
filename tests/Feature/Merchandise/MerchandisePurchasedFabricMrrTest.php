<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\ProcPostingSetup;
use App\Modules\Merchandise\Models\PurchasedFabricLedger;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Purchased Fabric Receipt Report (AnnotechERP 06/06):
 * the Receive List of fabric orders still open; picking one fills the batches
 * it was placed for; each batch received, part rejected, the rest accepted —
 * never beyond what the order leaves open; the bill and each batch's landed
 * share; numbered MRR-IMF-2026100001; finalising puts the accepted fabric on
 * the purchased-fabric stock ledger and moves the order to Partial.
 */
class MerchandisePurchasedFabricMrrTest extends TestCase
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
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finished Fabric Store', 'code' => 'ST-FAB', 'active' => true])->id;
    }

    public function test_a_fabric_receipt_accepts_what_arrived_against_its_order_and_puts_it_on_stock(): void
    {
        [$star, $poNo, $pr] = $this->fabricOrder();

        $options = $this->as()->getJson('/api/v1/merchandise/pfab-mrrs/options')->assertOk()->json('data');
        $this->assertSame([[$poNo, $star]], array_map(fn ($p) => [$p['po_no'], $p['supplier_id']], $options['receivable']));
        $fill = $this->as()->getJson("/api/v1/merchandise/pfab-mrrs/receivables/{$poNo}")->assertOk()->json('data');
        $this->assertSame([$star, 'LC', 'LOCAL'], [$fill['supplier_id'], $fill['payment_method'], $fill['source_type']]);
        $this->assertEquals([['Poplin 40s', 'YDS', 1000, 0, 1000], ['Cuff rib', 'KG', 80, 0, 80]], array_map(fn ($l) => [$l['description'], $l['uom'], $l['po_qty'], $l['prev_received'], $l['receive_qty']], $fill['lines']));
        [$popLine, $ribLine] = array_column($fill['lines'], 'po_line_id');

        $this->as()->postJson('/api/v1/merchandise/pfab-mrrs', ['lines' => [], 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'store_id', 'mrr_date', 'challan_no', 'challan_date']);
        $head = ['unit_company_id' => $this->delta->id, 'supplier_id' => $star, 'store_id' => $this->store, 'po_id' => $fill['po_id'], 'mrr_date' => '2026-10-20', 'payment_method' => 'LC', 'source_type' => 'IMPORT',
            'challan_no' => ' CH-7781 ', 'challan_date' => '2026-10-19', 'currency' => 'BDT', 'lc_no' => 'LC-0042', 'lc_date' => '2026-10-01', 'bill_entry_no' => 'C-5512', 'bill_entry_date' => '2026-10-18', 'vat_pct' => 5, 'tds_pct' => 2];
        $this->as()->postJson('/api/v1/merchandise/pfab-mrrs', [...$head, 'lines' => [], 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors(['lines' => 'A receipt with no lines is not a receipt.']);
        // A row left half-filled, a reject larger than the delivery and an acceptance beyond the order are each named by row.
        $this->as()->postJson('/api/v1/merchandise/pfab-mrrs', [...$head, 'lines' => [['batch_no' => 'B-1'], ['po_line_id' => $popLine, 'receive_qty' => 10, 'reject_qty' => 11], ['po_line_id' => $ribLine, 'receive_qty' => 90, 'reject_qty' => 5]], 'costs' => []])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'lines.0.po_line_id' => 'Row 1: pick the PO line it receives.',
                'lines.1.reject_qty' => 'Row 2: Reject Qty cannot be more than Receive Qty.', 'lines.2.receive_qty' => "Row 3: only 80 is left to receive on PO {$poNo}.",
            ]);

        $carriage = ProcCostHead::query()->where('code', 'CARRIAGE')->value('id');
        $cnf = ProcCostHead::query()->where('code', 'CNF')->value('id');
        $lines = [['po_line_id' => $popLine, 'batch_no' => 'PB-01', 'rolls' => 24, 'receive_qty' => 620, 'reject_qty' => 20, 'rate' => 2.5, 'reject_reason' => 'Shade variation'], ['po_line_id' => $ribLine, 'batch_no' => 'RB-01', 'rolls' => 4, 'receive_qty' => 80, 'rate' => 4]];
        $saved = $this->as()->postJson('/api/v1/merchandise/pfab-mrrs', [...$head, 'lines' => $lines, 'costs' => [['cost_head_id' => $carriage, 'amount' => 100], ['cost_head_id' => $cnf, 'amount' => 50]]])->assertCreated()->json('data');
        // 600 × 2.5 + 80 × 4 = 1,820; landed 1,820 + 150 = 1,970; VAT base 1,820 + C&F 50 = 1,870 → VAT 93.50; TDS 2% of 1,970 = 39.40; payable 2,024.10.
        $this->assertEquals(['MRR-IMF-2026100001', 'CH-7781', 'LC-0042', 'C-5512', 1820, 150, 1970, 93.5, 39.4, 1970, 2024.1], [$saved['mrr_no'], $saved['challan_no'], $saved['lc_no'], $saved['bill_entry_no'], $saved['goods_value'], $saved['other_cost'], $saved['landed_cost'], $saved['vat'], $saved['tds'], $saved['net_amount'], $saved['net_payable']]);
        // Each batch carries its PO line's fabric; the landed cost is shared by value.
        $this->assertEquals([['Poplin 40s', 'PB-01', 24, 620, 20, 600, 1500, 1623.63, 'Shade variation'], ['Cuff rib', 'RB-01', 4, 80, 0, 80, 320, 346.37, null]], array_map(fn ($l) => [$l['description'], $l['batch_no'], $l['rolls'], $l['receive_qty'], $l['reject_qty'], $l['accept_qty'], $l['amount'], $l['landed_amount'], $l['reject_reason']], $saved['lines']));

        // 400 yards stay open on the order; the cuff rib is fully taken.
        $this->assertEquals([['Poplin 40s', 400]], array_map(fn ($l) => [$l['description'], $l['receive_qty']], $this->as()->getJson('/api/v1/merchandise/pfab-mrrs/options')->json('data.open_lines')));
        // Received against, the purchase order is locked.
        $this->as()->deleteJson("/api/v1/merchandise/pfab-pos/{$poNo}")->assertUnprocessable();

        $this->as()->postJson('/api/v1/merchandise/pfab-mrrs/MRR-IMF-2026100001/finalize')->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame(0, PurchasedFabricLedger::query()->count());
        $this->postingSetup();
        $final = $this->as()->postJson('/api/v1/merchandise/pfab-mrrs/MRR-IMF-2026100001/finalize')->assertOk()->json('data');
        $this->assertSame([false, 'FINALIZED'], [$final['editable'], $final['status']]);
        $this->assertEquals([['MRR', 'PB-01', 'Poplin 40s', 600, 1623.63], ['MRR', 'RB-01', 'Cuff rib', 80, 346.37]], PurchasedFabricLedger::query()->orderBy('batch_no')->get()->map(fn ($l) => [$l->source, $l->batch_no, $l->fabric_item, $l->qty, $l->cost_bdt])->all());
        $this->as()->putJson('/api/v1/merchandise/pfab-mrrs/MRR-IMF-2026100001', [...$head, 'lines' => $lines, 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->as()->deleteJson('/api/v1/merchandise/pfab-mrrs/MRR-IMF-2026100001')->assertUnprocessable();

        $records = $this->as()->getJson('/api/v1/merchandise/pfab-mrrs')->assertOk()->json('data');
        $this->assertEquals([['MRR-IMF-2026100001', true, false, $pr, $poNo, 'Star Fabrics', 'Finished Fabric Store', 'IMPORT', 'LC-0042', 700, 0, 1970, 2024.1]], array_map(fn ($r) => [$r['mrr_no'], $r['finalized'], $r['accounting'], $r['pr'], $r['po'], $r['supplier'], $r['store'], $r['source_type'], $r['lc_no'], $r['rcv_qty'], $r['ret_qty'], $r['net_amount'], $r['net_payable']], $records));
        $this->assertSame('PARTIAL', collect($this->as()->getJson('/api/v1/merchandise/pfab-pos')->json('data'))->firstWhere('po_no', $poNo)['receive_status']);

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/pfab-mrrs')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/pfab-mrrs', [...$head, 'lines' => $lines, 'costs' => []])->assertForbidden();
    }

    /** An approved indent for style 637PK and the order placed on it: 1,000 yards of poplin and 80 kg of cuff rib from Star Fabrics. */
    private function fabricOrder(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($order)->update(['status' => 'CONFIRMED']);

        $poplin = FabricType::query()->create(['code' => 'POP', 'name' => 'Poplin', 'short_name' => 'POP', 'category' => 'WOVEN', 'construction' => 'FLAT', 'active' => true])->id;
        $rib = FabricType::query()->create(['code' => 'RIB', 'name' => '1x1 Rib', 'short_name' => 'RIB', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Fabrics', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $pr = $this->as()->postJson('/api/v1/merchandise/pfab-indents', ['req_date' => '2026-10-17', 'order_id' => $order, 'supplier_id' => $star, 'lines' => [
            ['fabric_type_id' => $poplin, 'description' => 'Poplin 40s', 'qty' => 1000, 'uom' => 'YDS', 'gsm' => '110', 'dia' => '58'], ['fabric_type_id' => $rib, 'description' => 'Cuff rib', 'qty' => 80, 'uom' => 'KG'],
        ]])->assertCreated()->json('data.req_no');
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$pr}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$pr}/approve")->assertOk();
        $fill = $this->as()->getJson("/api/v1/merchandise/pfab-pos/requisitions/{$pr}")->assertOk()->json('data');
        [$popLine, $ribLine] = array_column($fill['lines'], 'indent_line_id');
        $poNo = $this->as()->postJson('/api/v1/merchandise/pfab-pos', ['supplier_id' => $star, 'po_date' => '2026-10-18', 'source' => 'LOCAL', 'indent_id' => $fill['indent_id'], 'payment_method' => 'LC', 'currency' => 'BDT', 'payment_term' => 'AT_SIGHT',
            'mode_of_shipment' => 'ROAD', 'delivery_date' => '2026-11-15', 'lines' => [['indent_line_id' => $popLine, 'qty' => 1000, 'rate' => 2.5], ['indent_line_id' => $ribLine, 'qty' => 80, 'rate' => 4]], 'costs' => []])
            ->assertCreated()->json('data.po_no');

        return [$star, $poNo, $pr];
    }

    /** The company's posting setup: a journal voucher on five ledgers. */
    private function postingSetup(): void
    {
        $ledger = fn (string $code, string $name, string $type) => AccAccount::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'type' => $type, 'postable' => true, 'active' => true])->id;
        ProcPostingSetup::query()->create(['company_id' => $this->delta->id, 'unit_company_id' => $this->delta->id, 'voucher_type' => 'JV', 'inventory_ledger_id' => $ledger('1130', 'Inventory — Fabric', 'ASSET'),
            'vat_ledger_id' => $ledger('1210', 'VAT Receivable', 'ASSET'), 'payable_ledger_id' => $ledger('2020201', 'Accounts Payable', 'LIABILITY'), 'tds_ledger_id' => $ledger('2030101', 'TDS Payable', 'LIABILITY'),
            'vds_ledger_id' => $ledger('2030102', 'VDS Payable', 'LIABILITY'), 'active' => true]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
