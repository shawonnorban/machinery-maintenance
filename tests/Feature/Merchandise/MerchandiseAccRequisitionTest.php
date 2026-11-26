<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Accessories Purchase Indent (AnnotechERP 06/06): an
 * order's approved BOM filling the sheet (main fabric left out, the required
 * quantity from the order, consumption, allowance and conversion), the
 * requisition numbered PRQ-ACC-YYYYMM0001, an item raised twice for an order
 * only as an addition, and the Draft → Submitted → Approved / Rejected →
 * Reopened flow, a submitted one no longer changed.
 */
class MerchandiseAccRequisitionTest extends TestCase
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

    public function test_the_approved_bom_fills_the_sheet_and_a_requisition_is_numbered_and_guarded(): void
    {
        [$office, $order] = $this->order();
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'PCS', 'active' => true])->id;
        $supplier = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'short_name' => 'Star', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        // Before the BOM is approved the order shows none.
        $options = $this->as()->getJson('/api/v1/merchandise/acc-requisitions/options')->assertOk()->json('data');
        $this->assertSame([[$order['order_no'], null, $office]], array_map(fn ($o) => [$o['order_no'], $o['bom'], $o['buyer_office_id']], $options['orders']));
        $this->assertSame(['Button 18L (TR-BTN-01)'], array_column($options['items'], 'label'));
        $this->assertSame(['bom' => null, 'bom_version_id' => null, 'lines' => []], $this->as()->getJson("/api/v1/merchandise/acc-requisitions/bom?order_id={$order['id']}")->json('data'));

        $this->approvedBom($order['style_id']);
        $this->assertSame('BOM-T1 V2', $this->as()->getJson('/api/v1/merchandise/acc-requisitions/options')->json('data.orders.0.bom'));
        $sheet = $this->as()->getJson("/api/v1/merchandise/acc-requisitions/bom?order_id={$order['id']}")->assertOk()->json('data');
        // 100 pcs × 12/12 × (1 + 5% + 3% additional) = 108 buttons; 100 × 150 m ÷ 4000 m a cone = 3.75 cones.
        $this->assertEquals([
            ['Button 18L', $button, 'TR-BTN-01', 'PCS', 108, 0, 108, $supplier],
            ['Sewing Thread', null, 'TH-40', 'CONE', 3.75, 0, 3.75, null],
        ], array_map(fn ($l) => [$l['description'], $l['item_id'], $l['item_code'], $l['uom'], $l['required_qty'], $l['stock_qty'], $l['short_qty'], $l['supplier_id']], $sheet['lines']));

        $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['req_date' => 'Req. Date is required.', 'order_id' => 'Pick the order this requisition is for.']);
        $head = ['req_date' => '2026-10-16', 'order_id' => $order['id'], 'supplier_id' => $supplier, 'remarks' => ' First lot '];
        $this->as()->postJson('/api/v1/merchandise/acc-requisitions', $head + ['lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'Add at least one item.']);
        $this->as()->postJson('/api/v1/merchandise/acc-requisitions', $head + ['lines' => [['description' => 'Label', 'required_qty' => 0]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.required_qty' => 'Row 1: Required Qty must be a quantity above zero.']);

        $lines = array_map(fn ($l) => ['bom_line_id' => $l['bom_line_id'], 'item_id' => $l['item_id'], 'description' => $l['description'], 'uom' => $l['uom'], 'required_qty' => $l['required_qty'], 'supplier_id' => $l['supplier_id']], $sheet['lines']);
        $saved = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', $head + ['lines' => $lines])->assertCreated()->json('data');
        // The buyer account follows the order; the BOM it was raised from is kept.
        $this->assertSame(['PRQ-ACC-2026100001', 'DRAFT', true, $office, 'First lot', 'BOM-T1 V2', 2], [$saved['req_no'], $saved['status'], $saved['editable'], $saved['buyer_office_id'], $saved['remarks'], $saved['bom'], count($saved['lines'])]);

        // The same button again for the order is refused, unless raised as an addition.
        $this->as()->postJson('/api/v1/merchandise/acc-requisitions', $head + ['lines' => [$lines[0]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.item_id']);
        $extra = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', $head + ['addition_for' => 'Wastage', 'lines' => [['item_id' => $button, 'required_qty' => 12]]])->assertCreated()->json('data');
        $this->assertSame(['PRQ-ACC-2026100002', 'Button 18L', 'PCS'], [$extra['req_no'], $extra['lines'][0]['description'], $extra['lines'][0]['uom']]);

        $updated = $this->as()->putJson('/api/v1/merchandise/acc-requisitions/PRQ-ACC-2026100001', $head + ['lines' => [$lines[0]]])->assertOk()->json('data');
        $this->assertCount(1, $updated['lines']);

        $records = $this->as()->getJson('/api/v1/merchandise/acc-requisitions')->assertOk()->json('data');
        $this->assertEquals([
            ['PRQ-ACC-2026100002', 'Wastage', '529', 'LPP SPOLKA AKCYJNA OFFICE', 'Star Accessories', 'Button 18L', 12, 'DRAFT', '2026-10'],
            ['PRQ-ACC-2026100001', null, '529', 'LPP SPOLKA AKCYJNA OFFICE', 'Star Accessories', 'Button 18L', 108, 'DRAFT', '2026-10'],
        ], array_map(fn ($r) => [$r['req_no'], $r['addition_for'], $r['job_no'], $r['buyer_ac'], $r['supplier'], $r['items'], $r['qty'], $r['status'], $r['req_month']], $records));

        $this->as()->deleteJson('/api/v1/merchandise/acc-requisitions/PRQ-ACC-2026100002')->assertOk();
        $this->as()->getJson('/api/v1/merchandise/acc-requisitions/PRQ-ACC-2026100002')->assertNotFound();
    }

    public function test_a_requisition_is_submitted_then_approved_or_rejected_and_reopened(): void
    {
        [, $order] = $this->order();
        $no = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-16', 'order_id' => $order['id'], 'lines' => [['description' => 'Care label', 'uom' => 'PCS', 'required_qty' => 110]]])
            ->assertCreated()->json('data.req_no');

        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/approve")->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'A Draft requisition cannot be approved.']);
        $this->assertSame('SUBMITTED', $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/submit")->assertOk()->json('data.status'));

        // Submitted, it is no longer changed or removed.
        $this->as()->putJson("/api/v1/merchandise/acc-requisitions/{$no}", ['req_date' => '2026-10-16', 'order_id' => $order['id'], 'lines' => [['description' => 'Care label', 'required_qty' => 5]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['status' => 'This requisition is Submitted — it can no longer be changed or removed.']);
        $this->as()->deleteJson("/api/v1/merchandise/acc-requisitions/{$no}")->assertUnprocessable();

        // A store manager sees the indents but neither raises nor decides them.
        $store = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($store)->getJson('/api/v1/merchandise/acc-requisitions')->assertOk();
        $this->as($store)->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-16', 'order_id' => $order['id'], 'lines' => []])->assertForbidden();

        $rejected = $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/reject")->assertOk()->json('data');
        $this->assertSame(['REJECTED', true], [$rejected['status'], $rejected['editable']]);
        $this->assertSame('DRAFT', $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/reopen")->assertOk()->json('data.status'));
        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/submit")->assertOk();
        $approved = $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/approve")->assertOk()->json('data');
        $this->assertSame(['APPROVED', false], [$approved['status'], $approved['editable']]);
        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/reopen")->assertUnprocessable();
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

        return [$office, $order + ['style_id' => $style]];
    }

    /** BOM-T1: V1 still a draft, V2 approved with 3% additional — a main fabric, a button and a thread. */
    private function approvedBom(string $styleId): void
    {
        $bom = StyleBom::query()->create(['style_id' => $styleId, 'bom_no' => 'BOM-T1']);
        StyleBomVersion::query()->create(['bom_id' => $bom->id, 'version' => 1, 'status' => 'DRAFT', 'order_qty' => 100, 'addl_pct' => 0]);
        $v2 = StyleBomVersion::query()->create(['bom_id' => $bom->id, 'version' => 2, 'status' => StyleBomVersion::APPROVED, 'order_qty' => 100, 'addl_pct' => 3, 'approved_at' => now()]);
        $line = ['version_id' => $v2->id, 'cons_type' => 'FIXED', 'rate' => 0];
        StyleBomLine::query()->create($line + ['item_name' => 'Fleece 280 GSM', 'category' => 'MAIN_FABRIC', 'consumption' => 0.45, 'cons_unit' => 'KG', 'per_gmt' => 1, 'purchase_unit' => 'KG', 'conversion' => 1, 'apply_addl' => true, 'sort_order' => 1]);
        StyleBomLine::query()->create($line + ['item_name' => 'Button 18L', 'category' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'consumption' => 12, 'cons_unit' => 'PCS', 'per_gmt' => 12, 'purchase_unit' => 'PCS', 'conversion' => 1, 'allow_pct' => 5, 'apply_addl' => true, 'supplier' => 'Star', 'sort_order' => 2]);
        StyleBomLine::query()->create($line + ['item_name' => 'Sewing Thread', 'category' => 'TRIMS', 'item_code' => 'TH-40', 'consumption' => 150, 'cons_unit' => 'MTR', 'per_gmt' => 1, 'purchase_unit' => 'CONE', 'conversion' => 4000, 'apply_addl' => false, 'sort_order' => 3]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
