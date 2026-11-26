<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\StyleFabric;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Purchased Fabric Indent (AnnotechERP 06/06): an order
 * filling the buyer, ship date and the style's purchased fabrics; the indent
 * numbered PRQ-FAB-2026100001 with its fabric lines; a fabric requisitioned
 * once per order; the header's supplier on the lines naming none; Draft →
 * Submitted → Approved / Rejected → Reopened.
 */
class MerchandisePurchasedFabricIndentTest extends TestCase
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

    public function test_an_order_fills_the_purchased_fabrics_and_the_indent_is_kept_once_per_order(): void
    {
        [$office, $order] = $this->order();
        $sj = FabricType::query()->create(['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'active' => true])->id;
        $poplin = FabricType::query()->create(['code' => 'POP', 'name' => 'Poplin', 'short_name' => 'POP', 'category' => 'WOVEN', 'construction' => 'FLAT', 'active' => true])->id;
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Fabrics', 'short_name' => 'Star', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $moon = MaterialSupplier::query()->create(['code' => 'SUP-02', 'name' => 'Moon Textiles', 'supplier_type' => 'FOREIGN', 'active' => true])->id;
        StyleFabric::query()->create(['style_id' => $order['style_id'], 'fabric_code' => 'POP-40', 'fabric_category' => 'WOVEN', 'source' => 'PURCHASED', 'fabric_type_id' => $poplin, 'fiber_composition_id' => $cotton, 'weight_from' => 110, 'width' => 58, 'supplier' => 'star', 'description' => 'Poplin 40s', 'sort_order' => 1]);
        StyleFabric::query()->create(['style_id' => $order['style_id'], 'fabric_code' => 'SJ-160', 'fabric_category' => 'KNIT', 'source' => 'MANUFACTURED', 'fabric_type_id' => $sj, 'fiber_composition_id' => $cotton, 'weight_from' => 160, 'description' => 'Knitted in-house', 'sort_order' => 2]);

        $options = $this->as()->getJson('/api/v1/merchandise/pfab-indents/options')->assertOk()->json('data');
        $this->assertSame([[$order['order_no'], '2026-12-20']], array_map(fn ($o) => [$o['order_no'], $o['ship_date']], $options['orders']));
        $this->assertSame(['KG', 'YDS', 'MTR', 'LBS', 'PCS'], $options['uoms']);

        // Only the purchased fabric is filled — GSM, DIA and the supplier matched by its short name.
        $fill = $this->as()->getJson("/api/v1/merchandise/pfab-indents/fill?order_id={$order['id']}")->assertOk()->json('data');
        $this->assertSame([$office, '2026-12-20'], [$fill['buyer_office_id'], $fill['ship_date']]);
        $this->assertSame([[$poplin, 'Poplin 40s', 'POP-40', $cotton, '110', '58', $star, 'KG']], array_map(fn ($l) => [$l['fabric_type_id'], $l['description'], $l['construction'], $l['composition_id'], $l['gsm'], $l['dia'], $l['supplier_id'], $l['uom']], $fill['lines']));

        $this->as()->postJson('/api/v1/merchandise/pfab-indents', ['lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['req_date' => 'Req. Date is required.', 'order_id' => 'Pick the order this requisition is for.']);
        $head = ['req_date' => '2026-10-17', 'order_id' => $order['id'], 'supplier_id' => $moon, 'gsm' => ' 110 ', 'dia' => '58 inch'];
        $this->as()->postJson('/api/v1/merchandise/pfab-indents', [...$head, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'A requisition with no items is not a requisition.']);
        $poplinLine = [...$fill['lines'][0], 'qty' => 1200, 'uom' => 'YDS', 'required_date' => '2026-11-15'];
        $this->as()->postJson('/api/v1/merchandise/pfab-indents', [...$head, 'lines' => [['qty' => 0, 'uom' => null], $poplinLine, $poplinLine]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description' => 'Row 1: pick the fabric type or give a description.', 'lines.0.qty' => 'Row 1: Qty must be above zero.', 'lines.0.uom' => 'Row 1: pick the UOM.']);
        $this->as()->postJson('/api/v1/merchandise/pfab-indents', [...$head, 'lines' => [$poplinLine, $poplinLine]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.1.description' => 'Row 2: this fabric is already on the requisition.']);

        // The ship date follows the order; a line naming no supplier takes the header's.
        $saved = $this->as()->postJson('/api/v1/merchandise/pfab-indents', [...$head, 'lines' => [$poplinLine, ['fabric_type_id' => $sj, 'description' => 'Rib for collar', 'qty' => 80, 'uom' => 'KG', 'gsm' => '220']]])->assertCreated()->json('data');
        $this->assertSame(['PRQ-FAB-2026100001', 'DRAFT', $office, '2026-12-20', '110', '58 inch'], [$saved['req_no'], $saved['status'], $saved['buyer_office_id'], $saved['ship_date'], $saved['gsm'], $saved['dia']]);
        $this->assertEquals([[$star, 1200, 'YDS', '2026-11-15'], [$moon, 80, 'KG', null]], array_map(fn ($l) => [$l['supplier_id'], $l['qty'], $l['uom'], $l['required_date']], $saved['lines']));

        // The poplin is requisitioned for the order: a second indent may not take it again.
        $this->as()->postJson('/api/v1/merchandise/pfab-indents', [...$head, 'lines' => [$poplinLine]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description' => 'Row 1: this fabric is already requisitioned for the order on PRQ-FAB-2026100001.']);

        $records = $this->as()->getJson('/api/v1/merchandise/pfab-indents')->assertOk()->json('data');
        $this->assertEquals([['PRQ-FAB-2026100001', '2026-12-20', '110', '58 inch', 'LPP SPOLKA AKCYJNA OFFICE', '529', 'Moon Textiles', 'Poplin 40s, Rib for collar', 1280, 'DRAFT']],
            array_map(fn ($r) => [$r['req_no'], $r['ship_date'], $r['gsm'], $r['dia'], $r['buyer_ac'], $r['job_no'], $r['supplier'], $r['items'], $r['qty'], $r['status']], $records));

        // The workflow, each step only from the one before; a submitted indent is no longer changed.
        $no = 'PRQ-FAB-2026100001';
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$no}/approve")->assertUnprocessable()->assertJsonValidationErrors(['status' => 'A Draft requisition cannot be approved.']);
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/merchandise/pfab-indents/{$no}", [...$head, 'lines' => [$poplinLine]])->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'This requisition is Submitted — it can no longer be changed or removed.']);
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$no}/reject")->assertOk()->assertJsonPath('data.editable', true);
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$no}/reopen")->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->as()->putJson("/api/v1/merchandise/pfab-indents/{$no}", [...$head, 'remarks' => 'Collar rib dropped', 'lines' => [$poplinLine]])->assertOk()->assertJsonPath('data.remarks', 'Collar rib dropped');
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$no}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/pfab-indents/{$no}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->as()->deleteJson("/api/v1/merchandise/pfab-indents/{$no}")->assertUnprocessable();

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/pfab-indents')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/pfab-indents', [...$head, 'lines' => [$poplinLine]])->assertForbidden();
    }

    /** A confirmed order of 100 pcs for style 637PK, shipping 20 Dec 2026. */
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
        Order::query()->whereKey($order['id'])->update(['status' => 'CONFIRMED', 'ship_date' => '2026-12-20']);

        return [$office, $order + ['style_id' => $style]];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
