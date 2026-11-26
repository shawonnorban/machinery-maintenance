<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\AccRequisition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Accessories Purchase Order (AnnotechERP 06/06): the
 * Requisition List giving, buyer by buyer, the approved requisitions still to
 * order and what is left of them; an order numbered PO-ACC-2026100001 with
 * its terms and priced lines, never ordering more than a requisition line has
 * left; each change a new revision; an order received against locked.
 */
class MerchandiseAccPurchaseOrderTest extends TestCase
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

    public function test_an_order_is_raised_from_what_an_approved_requisition_has_left_and_revised_until_received(): void
    {
        [$office, $order] = $this->order();
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'Pcs', 'active' => true])->id;
        $label = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-LBL-01', 'name' => 'Care Label', 'category' => 'Label', 'uom' => 'Pcs', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        // The requisition's supplier goes on the lines that name none.
        $pr = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-16', 'order_id' => $order['id'], 'supplier_id' => $star, 'lines' => [
            ['item_id' => $button, 'required_qty' => 108, 'required_date' => '2026-11-01'],
            ['description' => 'Hang tag string', 'uom' => 'Pcs', 'required_qty' => 110, 'required_date' => '2026-10-28'],
        ]])->assertCreated()->json('data');
        $this->assertSame([$star, $star], array_column($pr['lines'], 'supplier_id'));

        // Only approved requisitions are listed, or ordered against.
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-purchase-orders/requisitions')->assertOk()->json('data.buyers'));
        $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions/{$pr['req_no']}")->assertNotFound();
        $head = ['supplier_id' => $star, 'po_date' => '2026-10-17', 'source' => 'LOCAL', 'payment_method' => 'LC', 'currency' => 'BDT', 'payment_term' => 'AT_SIGHT', 'mode_of_shipment' => 'ROAD', 'delivery_date' => '2026-11-10'];
        $prId = AccRequisition::query()->where('req_no', $pr['req_no'])->value('id');
        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'requisition_id' => $prId, 'lines' => [['item_id' => $button, 'qty' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['requisition_id' => 'Only an approved requisition can be ordered against.']);
        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$pr['req_no']}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$pr['req_no']}/approve")->assertOk();

        $list = $this->as()->getJson('/api/v1/merchandise/acc-purchase-orders/requisitions')->assertOk()->json('data');
        $this->assertSame([[$office, 'LPP SPOLKA AKCYJNA OFFICE', 1]], array_map(fn ($b) => [$b['value'], $b['label'], $b['count']], $list['buyers']));
        $this->assertSame([], $list['requisitions'], 'Nothing is read until a buyer is picked.');
        $mine = $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions?buyer={$office}")->json('data.requisitions');
        $this->assertEquals([[$pr['req_no'], '529', 'Star Accessories', 'Button 18L, Hang tag string', 218]], array_map(fn ($r) => [$r['req_no'], $r['job_no'], $r['supplier'], $r['items'], $r['left']], $mine));

        // Taken from the list: the requisition's supplier, its earliest required date and what is left of each line.
        $fill = $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions/{$pr['req_no']}")->assertOk()->json('data');
        $this->assertSame([$star, '2026-10-28'], [$fill['supplier_id'], $fill['delivery_date']]);
        $this->assertEquals([[$button, null, 108], [null, 'Hang tag string', 110]], array_map(fn ($l) => [$l['item_id'], $l['specification'], $l['qty']], $fill['lines']));
        [$btnLine, $tagLine] = array_column($fill['lines'], 'requisition_line_id');

        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors([
            'supplier_id' => 'Supplier/Spinner is required.', 'po_date' => 'PO Date is required.', 'source' => 'Source is required.', 'payment_method' => 'Payment Method is required.',
            'currency' => 'Currency is required.', 'payment_term' => 'Payment Term is required.', 'mode_of_shipment' => 'Mode of Shipment is required.', 'delivery_date' => 'Delivery Date is required.',
        ]);
        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'delivery_date' => '2026-10-01', 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_date' => 'Delivery Date cannot be before the PO Date.']);
        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'A purchase order with no lines is not a purchase order.']);
        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'lines' => [['requisition_line_id' => $tagLine, 'qty' => 110]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.item_id' => 'Row 1: pick the item.']);
        // Two rows of the same requisition line count together.
        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'lines' => [['item_id' => $button, 'requisition_line_id' => $btnLine, 'qty' => 100], ['item_id' => $button, 'requisition_line_id' => $btnLine, 'qty' => 10]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.1.qty' => "Row 2: only 108 is left to order on requisition {$pr['req_no']}."]);

        $saved = $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'requisition_id' => $prId, 'currency' => 'USD', 'conv_rate' => 121.5, 'inco_term' => 'FOB', 'same_as_company' => true, 'beneficiary_company_id' => $this->delta->id, 'billed_to_company_id' => null, 'lines' => [
            ['item_id' => $button, 'requisition_line_id' => $btnLine, 'qty' => 100, 'unit_price' => 0.85, 'note' => ' rush '],
            ['item_id' => $label, 'requisition_line_id' => $tagLine, 'specification' => 'Hang tag string', 'qty' => 110, 'unit_price' => 0.1],
        ]])->assertCreated()->json('data');
        // "Same as Company" bills the beneficiary; the UOM falls back to the item's, as its master code.
        $this->assertEquals(['PO-ACC-2026100001', 0, 'PENDING', true, $this->delta->id, 121.5, 96, $pr['req_no'], 'PCS', 'rush'], [$saved['po_no'], $saved['revision'], $saved['receive_status'], $saved['editable'], $saved['billed_to_company_id'], $saved['conv_rate'], $saved['value'], $saved['pr_numbers'], $saved['lines'][0]['uom'], $saved['lines'][0]['note']]);

        // 8 buttons are left; a second order may take them, not 9.
        $this->assertEquals([[$pr['req_no'], 8]], array_map(fn ($r) => [$r['req_no'], $r['left']], $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions?buyer={$office}")->json('data.requisitions')));
        $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'lines' => [['item_id' => $button, 'requisition_line_id' => $btnLine, 'qty' => 9]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Row 1: only 8 is left to order on requisition {$pr['req_no']}."]);
        // An order's own quantity is not counted against itself: it may grow to the whole 108.
        $updated = $this->as()->putJson('/api/v1/merchandise/acc-purchase-orders/PO-ACC-2026100001', [...$head, 'lines' => [['item_id' => $button, 'requisition_line_id' => $btnLine, 'qty' => 108, 'unit_price' => 0.85]]])->assertOk()->json('data');
        $this->assertEquals([1, 91.8, 1.0, $pr['req_no']], [$updated['revision'], $updated['value'], $updated['conv_rate'], $updated['pr_numbers']]);

        $records = $this->as()->getJson('/api/v1/merchandise/acc-purchase-orders')->assertOk()->json('data');
        $this->assertEquals([['PO-ACC-2026100001', 'PENDING', 1, $pr['req_no'], 'Star Accessories', 'AT_SIGHT', 'LC', 91.8, 'BDT']], array_map(fn ($r) => [$r['po_no'], $r['receive_status'], $r['revision'], $r['pr_numbers'], $r['supplier'], $r['payment_term'], $r['payment_method'], $r['value'], $r['currency']], $records));
        // The tag line left the order, so its 110 is waiting again.
        $this->assertEquals([[$pr['req_no'], 110]], array_map(fn ($r) => [$r['req_no'], $r['left']], $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions?buyer={$office}")->json('data.requisitions')));
    }

    public function test_a_received_order_is_locked_and_a_removed_one_frees_its_requisition(): void
    {
        [$office, $order] = $this->order();
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'Pcs', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $no = $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-16', 'order_id' => $order['id'], 'lines' => [['item_id' => $button, 'required_qty' => 50]]])->json('data.req_no');
        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/acc-requisitions/{$no}/approve")->assertOk();
        $line = $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions/{$no}")->json('data.lines.0.requisition_line_id');
        $head = ['supplier_id' => $star, 'po_date' => '2026-10-17', 'source' => 'IMPORT', 'payment_method' => 'TT', 'currency' => 'BDT', 'payment_term' => 'ADVANCE', 'mode_of_shipment' => 'AIR', 'delivery_date' => '2026-10-20'];
        $po = $this->as()->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'lines' => [['item_id' => $button, 'requisition_line_id' => $line, 'qty' => 50, 'unit_price' => 1]]])->assertCreated()->json('data.po_no');
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-purchase-orders/requisitions')->json('data.buyers'));

        // A store manager sees the orders but does not place them.
        $store = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($store)->getJson('/api/v1/merchandise/acc-purchase-orders')->assertOk();
        $this->as($store)->postJson('/api/v1/merchandise/acc-purchase-orders', [...$head, 'lines' => []])->assertForbidden();

        AccPurchaseOrder::query()->where('po_no', $po)->update(['receive_status' => 'PARTIAL']);
        $locked = 'This order has been received against — it can no longer be changed or removed here; revise it instead.';
        $this->as()->putJson("/api/v1/merchandise/acc-purchase-orders/{$po}", [...$head, 'lines' => [['item_id' => $button, 'qty' => 1]]])->assertUnprocessable()->assertJsonValidationErrors(['status' => $locked]);
        $this->as()->deleteJson("/api/v1/merchandise/acc-purchase-orders/{$po}")->assertUnprocessable()->assertJsonValidationErrors(['status' => $locked]);
        $this->assertFalse($this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/{$po}")->json('data.editable'));

        AccPurchaseOrder::query()->where('po_no', $po)->update(['receive_status' => 'PENDING']);
        $this->as()->deleteJson("/api/v1/merchandise/acc-purchase-orders/{$po}")->assertOk();
        $this->assertEquals([[$no, 50]], array_map(fn ($r) => [$r['req_no'], $r['left']], $this->as()->getJson("/api/v1/merchandise/acc-purchase-orders/requisitions?buyer={$office}")->json('data.requisitions')));
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

        return [$office, $order];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
