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
 * Trims & Accessories → Fabric Supplier Return Challan (AnnotechERP 06/06):
 * the MRR List of finalised fabric MRRs; picking one fills the batches it
 * accepted; nothing goes back beyond what was accepted, never dated before
 * the receipt; numbered RTN-FAB-2026100001; finalising locks the challan and
 * takes each batch off the purchased-fabric stock ledger at its landed cost.
 */
class MerchandiseFabricReturnTest extends TestCase
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

    public function test_a_fabric_return_sends_accepted_batches_back_and_takes_them_off_stock(): void
    {
        [$star, $mrrNo] = $this->finalisedReceipt();
        $this->as()->postJson('/api/v1/merchandise/pfab-mrrs', [...$this->mrrHead($star), 'lines' => [['po_line_id' => $this->as()->getJson('/api/v1/merchandise/pfab-mrrs/options')->json('data.open_lines.0.po_line_id'), 'receive_qty' => 10]], 'costs' => []])->assertCreated();

        // Only finalised MRRs are in the MRR List.
        $options = $this->as()->getJson('/api/v1/merchandise/pfab-returns/options')->assertOk()->json('data');
        $this->assertSame([[$mrrNo, 'CH-7781', $star]], array_map(fn ($r) => [$r['mrr_no'], $r['challan_no'], $r['supplier_id']], $options['receipts']));
        $fill = $this->as()->getJson("/api/v1/merchandise/pfab-returns/receipts/{$mrrNo}")->assertOk()->json('data');
        $this->assertSame([$star, $this->store], [$fill['supplier_id'], $fill['store_id']]);
        $this->assertEquals([['Poplin 40s · PB-01', 'YDS', 600], ['Cuff rib · RB-01', 'KG', 80]], array_map(fn ($l) => [$l['description'], $l['uom'], $l['qty']], $fill['lines']));
        $popLine = $fill['lines'][0]['mrr_line_id'];

        $this->as()->postJson('/api/v1/merchandise/pfab-returns', ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors(['unit_company_id', 'supplier_id', 'mrr_no', 'store_id', 'return_by']);
        $head = ['return_type' => 'OFF_SHADE', 'unit_company_id' => $this->delta->id, 'supplier_id' => $star, 'mrr_no' => $mrrNo, 'challan_date' => '2026-10-22', 'store_id' => $this->store, 'return_by' => $this->owner->id, 'gate_pass_no' => ' GP-19 '];
        $this->as()->postJson('/api/v1/merchandise/pfab-returns', [...$head, 'lines' => []])->assertUnprocessable()->assertJsonValidationErrors(['lines' => 'A return challan with no lines is not a return challan.']);
        $this->as()->postJson('/api/v1/merchandise/pfab-returns', [...$head, 'challan_date' => '2026-10-19', 'lines' => [['mrr_line_id' => $popLine, 'description' => 'Poplin', 'qty' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['challan_date' => 'Challan Date cannot be before the receipt (20-Oct-2026).']);
        $this->as()->postJson('/api/v1/merchandise/pfab-returns', [...$head, 'lines' => [['qty' => 2], ['mrr_line_id' => $popLine, 'description' => 'Poplin 40s · PB-01', 'qty' => 601]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description' => 'Row 1: Description is required.', 'lines.1.qty' => "Row 2: only 600 is left to return on receipt {$mrrNo}."]);

        $lines = [['mrr_line_id' => $popLine, 'description' => 'Poplin 40s · PB-01', 'uom' => 'YDS', 'qty' => 50, 'reason' => 'Off shade on 2 rolls'], ['description' => 'Sample swatches', 'uom' => 'KG', 'qty' => 2]];
        $saved = $this->as()->postJson('/api/v1/merchandise/pfab-returns', [...$head, 'lines' => $lines])->assertCreated()->json('data');
        $this->assertSame(['RTN-FAB-2026100001', 'OFF_SHADE', $mrrNo, 'GP-19', true], [$saved['challan_no'], $saved['return_type'], $saved['mrr_no'], $saved['gate_pass_no'], $saved['editable']]);
        $this->assertEquals([[$popLine, 50, 'Off shade on 2 rolls'], [null, 2, null]], array_map(fn ($l) => [$l['mrr_line_id'], $l['qty'], $l['reason']], $saved['lines']));
        // 550 yards are left to send back; the receipt shows 50 returned.
        $this->assertEquals([550, 80], array_column($this->as()->getJson("/api/v1/merchandise/pfab-returns/receipts/{$mrrNo}")->json('data.lines'), 'qty'));
        $this->assertEquals(50, collect($this->as()->getJson('/api/v1/merchandise/pfab-mrrs')->json('data'))->firstWhere('mrr_no', $mrrNo)['ret_qty']);

        // Finalised, the batch leaves stock at its landed cost: 1,623.63 for 600 yards → 135.30 for 50; the free line has no batch to take off.
        $this->as()->postJson('/api/v1/merchandise/pfab-returns/RTN-FAB-2026100001/finalize')->assertOk()->assertJsonPath('data.editable', false);
        $this->assertEquals([['RTN', 'PB-01', -50, -135.3]], PurchasedFabricLedger::query()->where('source', 'RTN')->get()->map(fn ($l) => [$l->source, $l->batch_no, $l->qty, $l->cost_bdt])->all());
        $this->assertEquals(550, PurchasedFabricLedger::query()->where('batch_no', 'PB-01')->sum('qty'));
        $this->as()->putJson('/api/v1/merchandise/pfab-returns/RTN-FAB-2026100001', [...$head, 'lines' => $lines])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->as()->deleteJson('/api/v1/merchandise/pfab-returns/RTN-FAB-2026100001')->assertUnprocessable();

        $records = $this->as()->getJson('/api/v1/merchandise/pfab-returns')->assertOk()->json('data');
        $this->assertEquals([['RTN-FAB-2026100001', true, $mrrNo, '2026-10-22', 'OFF_SHADE', 'Delta Apparels Ltd', 'Star Fabrics', 'Finished Fabric Store']], array_map(fn ($r) => [$r['challan_no'], $r['finalized'], $r['mrr_no'], $r['challan_date'], $r['return_type'], $r['company'], $r['supplier'], $r['store']], $records));

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/pfab-returns')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/pfab-returns', [...$head, 'lines' => $lines])->assertForbidden();
    }

    /** MRR-IMF-2026100001, finalised: 600 yards of poplin (batch PB-01, 20 rejected) and 80 kg of cuff rib (RB-01) received on the order. */
    private function finalisedReceipt(): array
    {
        [$star, $poNo] = $this->fabricOrder();
        $this->as()->getJson('/api/v1/merchandise/pfab-mrrs/options')->assertOk(); // seeds the cost heads
        [$popLine, $ribLine] = array_column($this->as()->getJson("/api/v1/merchandise/pfab-mrrs/receivables/{$poNo}")->assertOk()->json('data.lines'), 'po_line_id');
        $carriage = ProcCostHead::query()->where('code', 'CARRIAGE')->value('id');
        $cnf = ProcCostHead::query()->where('code', 'CNF')->value('id');
        $mrrNo = $this->as()->postJson('/api/v1/merchandise/pfab-mrrs', [...$this->mrrHead($star),
            'lines' => [['po_line_id' => $popLine, 'batch_no' => 'PB-01', 'receive_qty' => 620, 'reject_qty' => 20, 'rate' => 2.5], ['po_line_id' => $ribLine, 'batch_no' => 'RB-01', 'receive_qty' => 80, 'rate' => 4]],
            'costs' => [['cost_head_id' => $carriage, 'amount' => 100], ['cost_head_id' => $cnf, 'amount' => 50]]])->assertCreated()->json('data.mrr_no');
        $this->postingSetup();
        $this->as()->postJson("/api/v1/merchandise/pfab-mrrs/{$mrrNo}/finalize")->assertOk();

        return [$star, $mrrNo];
    }

    private function mrrHead(string $star): array
    {
        return ['unit_company_id' => $this->delta->id, 'supplier_id' => $star, 'store_id' => $this->store, 'mrr_date' => '2026-10-20', 'payment_method' => 'LC', 'source_type' => 'IMPORT', 'challan_no' => 'CH-7781', 'challan_date' => '2026-10-19', 'currency' => 'BDT'];
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
