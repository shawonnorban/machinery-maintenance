<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\PurchasedFabricPo;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Purchased Fabric Purchase Order (AnnotechERP 06/06):
 * the Approved PR List and what an indent leaves open; each line ordering an
 * approved indent line, never beyond what is open; the other costs, discount,
 * VAT, TDS, VDS capped at the VAT and the adjustment giving the Net PO Amount;
 * numbered PO-FAB-2026100001, revised on each change, locked once received.
 */
class MerchandisePurchasedFabricPoTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_fabric_order_takes_what_an_approved_indent_left_open_and_reckons_its_net_amount(): void
    {
        [$office, $order] = $this->order();
        $poplin = FabricType::query()->create(['code' => 'POP', 'name' => 'Poplin', 'short_name' => 'POP', 'category' => 'WOVEN', 'construction' => 'FLAT', 'active' => true])->id;
        $rib = FabricType::query()->create(['code' => 'RIB', 'name' => '1x1 Rib', 'short_name' => 'RIB', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Fabrics', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $lines = [['fabric_type_id' => $poplin, 'description' => 'Poplin 40s', 'qty' => 1200, 'uom' => 'YDS', 'gsm' => '110', 'dia' => '58', 'required_date' => '2026-11-15'], ['fabric_type_id' => $rib, 'description' => 'Cuff rib', 'qty' => 80, 'uom' => 'KG']];
        $pr = $this->as()->postJson('/api/v1/merchandise/pfab-indents', ['req_date' => '2026-10-17', 'order_id' => $order, 'supplier_id' => $star, 'lines' => $lines])->assertCreated()->json('data.req_no');
        $this->as()->postJson('/api/v1/merchandise/pfab-indents', ['req_date' => '2026-10-17', 'order_id' => $order, 'lines' => [['description' => 'Draft only', 'qty' => 5, 'uom' => 'KG']]])->assertCreated();
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/pfab-pos/options')->json('data.approved'), 'Only approved indents are listed.');
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$pr}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$pr}/approve")->assertOk();

        $options = $this->as()->getJson('/api/v1/merchandise/pfab-pos/options')->assertOk()->json('data');
        $this->assertSame([[$pr, $star]], array_map(fn ($p) => [$p['req_no'], $p['supplier_id']], $options['approved']));
        $this->assertEquals([['Poplin 40s', 1200], ['Cuff rib', 80]], array_map(fn ($l) => [$l['description'], $l['qty']], $options['open_lines']));
        $list = $this->as()->getJson("/api/v1/merchandise/pfab-pos/requisitions?buyer={$office}")->assertOk()->json('data');
        $this->assertEquals([[$pr, 1280]], array_map(fn ($r) => [$r['req_no'], $r['left']], $list['requisitions']));

        $fill = $this->as()->getJson("/api/v1/merchandise/pfab-pos/requisitions/{$pr}")->assertOk()->json('data');
        $this->assertSame([$star, '2026-11-15'], [$fill['supplier_id'], $fill['delivery_date']]);
        [$popLine, $ribLine] = array_column($fill['lines'], 'indent_line_id');

        $this->as()->postJson('/api/v1/merchandise/pfab-pos', ['lines' => [], 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors([
            'supplier_id' => 'Supplier/Spinner is required.', 'po_date' => 'PO Date is required.', 'payment_term' => 'Payment Term is required.', 'delivery_date' => 'Delivery Date is required.',
        ]);
        $head = ['supplier_id' => $star, 'po_date' => '2026-10-18', 'source' => 'LOCAL', 'indent_id' => $fill['indent_id'], 'payment_method' => 'LC', 'currency' => 'BDT', 'payment_term' => 'AT_SIGHT', 'mode_of_shipment' => 'ROAD', 'delivery_date' => '2026-11-15', 'po_group' => ' SS27 '];
        $this->as()->postJson('/api/v1/merchandise/pfab-pos', [...$head, 'lines' => [], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'A purchase order with no lines is not a purchase order.']);
        $this->as()->postJson('/api/v1/merchandise/pfab-pos', [...$head, 'lines' => [['qty' => 1], ['indent_line_id' => $popLine, 'qty' => 1201]], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.indent_line_id' => 'Row 1: pick the approved requisition line it orders.', 'lines.1.qty' => "Row 2: only 1200 is left to order on requisition {$pr}."]);

        $carriage = ProcCostHead::query()->where('code', 'CARRIAGE')->value('id');
        $cnf = ProcCostHead::query()->where('code', 'CNF')->value('id');
        $saved = $this->as()->postJson('/api/v1/merchandise/pfab-pos', [...$head, 'discount_pct' => 5, 'vat_pct' => 10, 'tds_pct' => 2, 'vds_pct' => 20, 'adjustment' => -10, 'adjustment_note' => 'Short-width claim',
            'lines' => [['indent_line_id' => $popLine, 'qty' => 1000, 'rate' => 2.5], ['indent_line_id' => $ribLine, 'qty' => 80, 'rate' => 4]],
            'costs' => [['cost_head_id' => $carriage, 'amount' => 100], ['cost_head_id' => $cnf, 'amount' => 50]]])->assertCreated()->json('data');
        // 2,500 + 320 = 2,820, less 5% (141) = 2,679; VAT base 2,679 + C&F 50 = 2,729 → VAT 272.90; TDS 2% of 2,829 = 56.58;
        // VDS 20% of 2,729 = 545.80, capped at the VAT 272.90; net 2,820 − 141 + 150 + 272.90 − 56.58 − 272.90 − 10 = 2,762.42.
        $this->assertEquals(['PO-FAB-2026100001', 0, 'SS27', 2820, 150, 272.9, 56.58, 272.9, 2762.42, $pr], [$saved['po_no'], $saved['revision'], $saved['po_group'], $saved['po_value'], $saved['other_cost'], $saved['vat'], $saved['tds'], $saved['vds'], $saved['net_amount'], $saved['pr_numbers']]);
        // Each line carries its indent line's fabric; PR Qty is what it may still take.
        $this->assertEquals([[$poplin, 'Poplin 40s', 'YDS', '110', 1200, 2500], [$rib, 'Cuff rib', 'KG', null, 80, 320]], array_map(fn ($l) => [$l['fabric_type_id'], $l['description'], $l['uom'], $l['gsm'], $l['pr_qty'], $l['amount']], $saved['lines']));

        // 200 yards are left open; a second order may take them, not 201.
        $this->assertEquals([['Poplin 40s', 200]], array_map(fn ($l) => [$l['description'], $l['qty']], $this->as()->getJson('/api/v1/merchandise/pfab-pos/options')->json('data.open_lines')));
        $this->as()->postJson('/api/v1/merchandise/pfab-pos', [...$head, 'lines' => [['indent_line_id' => $popLine, 'qty' => 201]], 'costs' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Row 1: only 200 is left to order on requisition {$pr}."]);
        $updated = $this->as()->putJson('/api/v1/merchandise/pfab-pos/PO-FAB-2026100001', [...$head, 'lines' => [['indent_line_id' => $popLine, 'qty' => 1200, 'rate' => 2.5]], 'costs' => []])->assertOk()->json('data');
        $this->assertEquals([1, 3000, 3000], [$updated['revision'], $updated['po_value'], $updated['net_amount']]);
        $this->assertEquals([[$pr, 80]], array_map(fn ($r) => [$r['req_no'], $r['left']], $this->as()->getJson("/api/v1/merchandise/pfab-pos/requisitions?buyer={$office}")->json('data.requisitions')));

        $records = $this->as()->getJson('/api/v1/merchandise/pfab-pos')->assertOk()->json('data');
        $this->assertEquals([['PO-FAB-2026100001', 'PENDING', 1, $pr, 'Star Fabrics', 'SS27', 3000]], array_map(fn ($r) => [$r['po_no'], $r['receive_status'], $r['revision'], $r['pr_numbers'], $r['supplier'], $r['po_group'], $r['value']], $records));

        // Received against, the order is locked.
        PurchasedFabricPo::query()->where('po_no', 'PO-FAB-2026100001')->update(['receive_status' => 'PARTIAL']);
        $this->as()->putJson('/api/v1/merchandise/pfab-pos/PO-FAB-2026100001', [...$head, 'lines' => [['indent_line_id' => $popLine, 'qty' => 1]], 'costs' => []])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->as()->deleteJson('/api/v1/merchandise/pfab-pos/PO-FAB-2026100001')->assertUnprocessable();

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/pfab-pos')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/pfab-pos', [...$head, 'lines' => [], 'costs' => []])->assertForbidden();
    }

    /** A confirmed order for style 637PK. */
    private function order(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $id = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($id)->update(['status' => 'CONFIRMED']);

        return [$office, $id];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
