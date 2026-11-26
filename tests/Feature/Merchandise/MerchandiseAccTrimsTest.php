<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Accessories & Trims (AnnotechERP 06/08): the confirmed
 * orders and the accessory bookings raised against them (ACB-00001); the
 * Booked Trims panel adding, adjusting and removing trim lines; the sourcing
 * line of each trim with a buyer-nominated supplier held; the trims dyeing
 * programmes (TDP-00001) and their items; the accessories purchase register.
 */
class MerchandiseAccTrimsTest extends TestCase
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

    public function test_accessories_are_booked_against_confirmed_orders_and_their_trims_kept_on_the_booking(): void
    {
        [$order, $draft] = $this->orders();
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'BTN-4H', 'name' => '4 Hole Button', 'category' => 'Button', 'uom' => 'PCS', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Trims', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        // Only confirmed orders are booked against: buyer, style, quantity, conf. date, shipment and lead time.
        $orders = $this->as()->getJson('/api/v1/merchandise/acc-bookings/orders')->assertOk()->json('data');
        $this->assertSame([['LPP-2026-000001', 'LPP-2026-000001 / 637PK', 'LPP', 'Hoodie', 'BULK_NEW', 1200, '2026-10-01', '2026-11-30', 60, 0]],
            array_map(fn ($o) => [$o['order_no'], $o['title'], $o['buyer'], $o['style_desc'], $o['order_type'], $o['qty'], $o['conf_date'], $o['ship_date'], $o['lead_time'], $o['bookings']], $orders));

        $this->as()->postJson("/api/v1/merchandise/acc-bookings/orders/{$order}", ['req_type' => 'BULK', 'po_date' => '2026-10-20', 'delivery_date' => '2026-10-10'])->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_date' => 'Del. Date cannot be before the PO Date.']);
        $this->as()->postJson("/api/v1/merchandise/acc-bookings/orders/{$order}", ['po_no' => ' PO-7781 ', 'subject' => 'Buttons for 637PK', 'supplier_id' => $star, 'req_type' => 'BULK', 'po_date' => '2026-10-20', 'delivery_date' => '2026-11-05'])
            ->assertCreated()->assertJsonPath('data.0.booking_no', 'ACB-00001');
        $bookings = $this->as()->postJson("/api/v1/merchandise/acc-bookings/orders/{$order}", ['req_type' => 'SAMPLE'])->assertCreated()->json('data');
        $this->assertSame([['ACB-00001', 'PO-7781', 'Star Trims', 'BULK', 0, 'DRAFT'], ['ACB-00002', null, null, 'SAMPLE', 0, 'DRAFT']], array_map(fn ($b) => [$b['booking_no'], $b['po_no'], $b['supplier'], $b['req_type'], $b['items'], $b['status']], $bookings));
        $this->assertSame(2, $this->as()->getJson('/api/v1/merchandise/acc-bookings/orders')->json('data.0.bookings'));
        $this->as()->postJson("/api/v1/merchandise/acc-bookings/orders/{$draft}", ['req_type' => 'BULK'])->assertCreated(); // any order by its number can still be looked up

        // Booked Trims: Add trim line keeps it at once; the grid adjusts and removes, kept on Save.
        $this->as()->postJson('/api/v1/merchandise/acc-bookings/ACB-00001/lines', ['qty' => 0])->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'Description is required.', 'qty' => 'Qty must be above zero.']);
        $this->as()->postJson('/api/v1/merchandise/acc-bookings/ACB-00001/lines', ['item_id' => $button, 'description' => '4 Hole Button 18L', 'colour' => 'Navy', 'size' => '18L', 'qty' => 4200, 'uom' => 'PCS', 'rate' => 0.35])->assertCreated();
        $booking = $this->as()->postJson('/api/v1/merchandise/acc-bookings/ACB-00001/lines', ['description' => 'Care Label', 'qty' => 1200])->assertCreated()->json('data');
        $this->assertEquals([['4 Hole Button', '4 Hole Button 18L', 'Navy', '18L', 4200, 'PCS', 0.35, 'DRAFT'], [null, 'Care Label', null, null, 1200, null, null, 'DRAFT']],
            array_map(fn ($l) => [$l['item'], $l['description'], $l['colour'], $l['size'], $l['qty'], $l['uom'], $l['rate'], $l['status']], $booking['lines']));
        [$btnLine, $labelLine] = array_column($booking['lines'], 'id');
        $this->as()->putJson('/api/v1/merchandise/acc-bookings/ACB-00001/lines', ['lines' => [['id' => $btnLine, 'description' => '', 'qty' => 0]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description' => 'Row 1: Description is required.', 'lines.0.qty' => 'Row 1: Qty must be above zero.']);
        $this->as()->putJson('/api/v1/merchandise/acc-bookings/ACB-00002/lines', ['lines' => [['id' => $btnLine, 'description' => 'x', 'qty' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.id' => 'Row 1: that line is not on this booking.']);
        $saved = $this->as()->putJson('/api/v1/merchandise/acc-bookings/ACB-00001/lines', ['lines' => [['id' => $btnLine, 'description' => '4 Hole Button 18L', 'colour' => 'Navy', 'size' => '18L', 'qty' => 4410, 'uom' => 'PCS', 'rate' => 0.33]]])->assertOk()->json('data');
        $this->assertEquals([['4 Hole Button', 4410, 0.33]], array_map(fn ($l) => [$l['item'], $l['qty'], $l['rate']], $saved['lines']));

        // Lookup: every booking with its order, PO, supplier, trim count and status.
        $lookup = $this->as()->getJson('/api/v1/merchandise/acc-bookings')->assertOk()->json('data');
        $this->assertSame([['ACB-00003', 0], ['ACB-00002', 0], ['ACB-00001', 1]], array_map(fn ($b) => [$b['booking_no'], $b['items']], $lookup));
        $this->assertSame(['LPP-2026-000001', 'LPP', 'Hoodie', '637PK', 'BULK_NEW', 1200, 'PO-7781', 'Buttons for 637PK', '2026-10-20', '2026-11-05', 'Star Trims'],
            array_values(array_intersect_key($lookup[2], array_flip(['buyer', 'style_desc', 'style_no', 'order_no', 'order_type', 'order_qty', 'po_no', 'subject', 'po_date', 'delivery_date', 'supplier']))));

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/acc-bookings')->assertOk();
        $this->as($storeManager)->postJson("/api/v1/merchandise/acc-bookings/orders/{$order}", ['req_type' => 'BULK'])->assertForbidden();
        $this->as($storeManager)->postJson('/api/v1/merchandise/acc-bookings/ACB-00001/lines', ['description' => 'x', 'qty' => 1])->assertForbidden();
    }

    public function test_trims_are_sourced_per_order_and_dyed_on_a_programme(): void
    {
        [$order] = $this->orders();
        $orderId = Order::query()->where('order_no', $order)->value('id');
        $tape = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TP-15', 'name' => '1.5 CM Twill Tape', 'category' => 'Tape', 'uom' => 'YDS', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Trims', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $moon = MaterialSupplier::query()->create(['code' => 'SUP-02', 'name' => 'Moon Labels', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        $this->as()->postJson('/api/v1/merchandise/trim-sourcings', [])->assertUnprocessable()->assertJsonValidationErrors(['order_id' => 'Order is required.', 'description' => 'Description is required.']);
        $this->as()->postJson('/api/v1/merchandise/trim-sourcings', ['order_id' => $orderId, 'description' => 'Main label', 'nominated' => true])->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id' => 'A buyer-nominated line needs its supplier.']);
        $lines = $this->as()->postJson('/api/v1/merchandise/trim-sourcings', ['order_id' => $orderId, 'item_id' => $tape, 'description' => 'Neck tape 1.5 cm', 'supplier_id' => $star, 'nominated' => true, 'remarks' => 'LPP list'])->assertCreated()->json('data');
        $this->assertSame([['1.5 CM Twill Tape', 'Neck tape 1.5 cm', 'Star Trims', true, 'DRAFT', 'LPP-2026-000001', 60]], array_map(fn ($l) => [$l['trim'], $l['description'], $l['supplier'], $l['nominated'], $l['status'], $l['order_no'], $l['lead_time']], $lines));
        $id = $lines[0]['id'];
        // The buyer's nominee is not swapped while the nomination stands; untick it first, then approve.
        $this->as()->putJson("/api/v1/merchandise/trim-sourcings/{$id}", ['item_id' => $tape, 'description' => 'Neck tape 1.5 cm', 'supplier_id' => $moon, 'nominated' => true])->assertUnprocessable()->assertJsonValidationErrors(['supplier_id']);
        $updated = $this->as()->putJson("/api/v1/merchandise/trim-sourcings/{$id}", ['item_id' => $tape, 'description' => 'Neck tape 1.5 cm', 'supplier_id' => $moon, 'nominated' => false, 'status' => 'APPROVED'])->assertOk()->json('data.0');
        $this->assertSame(['Moon Labels', false, 'APPROVED'], [$updated['supplier'], $updated['nominated'], $updated['status']]);
        $this->assertSame([[$order, 1]], array_map(fn ($o) => [$o['order_no'], $o['lines']], $this->as()->getJson('/api/v1/merchandise/trim-sourcings/orders')->json('data')));
        $this->assertSame(['Moon Labels'], array_column($this->as()->getJson('/api/v1/merchandise/trim-sourcings')->json('data'), 'supplier'));
        $this->as()->deleteJson("/api/v1/merchandise/trim-sourcings/{$id}")->assertOk()->assertJsonPath('data', []);

        // A dyeing programme: the order names its buyer; items keep their shade.
        $this->as()->postJson('/api/v1/merchandise/trim-dyeing', ['prod_type' => 'SAMPLE'])->assertUnprocessable()->assertJsonValidationErrors(['program_date' => 'Program Date is required.']);
        $program = $this->as()->postJson('/api/v1/merchandise/trim-dyeing', ['prod_type' => 'SAMPLE', 'order_id' => $orderId, 'program_date' => '2026-10-18', 'remarks' => ' Tape to match body '])->assertCreated()->json('data');
        $this->assertSame(['TDP-00001', 'SAMPLE', 'DRAFT', 'Tape to match body'], [$program['program_no'], $program['prod_type'], $program['status'], $program['remarks']]);
        $this->assertNotNull($program['buyer_id']);
        $navy = Colour::query()->create(['code' => 'NV-01', 'name' => 'Navy', 'colour_type' => 'SOLID', 'active' => true])->id;
        $this->as()->postJson('/api/v1/merchandise/trim-dyeing/TDP-00001/items', ['qty' => 5])->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'Description is required.', 'colour_name' => 'Colour Name is required.']);
        $items = $this->as()->postJson('/api/v1/merchandise/trim-dyeing/TDP-00001/items', ['item_id' => $tape, 'description' => 'Neck tape', 'colour_id' => $navy, 'colour_name' => 'Navy 19-4024', 'qty' => 800, 'uom' => 'YDS'])->assertCreated()->json('data.items');
        $this->assertEquals([['1.5 CM Twill Tape', 'Neck tape', 'Navy', 'Navy 19-4024', 800, 'YDS']], array_map(fn ($i) => [$i['trim'], $i['description'], $i['colour'], $i['colour_name'], $i['qty'], $i['uom']], $items));
        $this->as()->putJson('/api/v1/merchandise/trim-dyeing/TDP-00001', ['prod_type' => 'BULK', 'order_id' => $orderId, 'program_date' => '2026-10-19', 'status' => 'APPROVED'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $records = $this->as()->getJson('/api/v1/merchandise/trim-dyeing')->assertOk()->json('data');
        $this->assertSame([['TDP-00001', 'BULK', 'LPP', 'LPP-2026-000001 / 637PK', '2026-10-19', 1, 'APPROVED']], array_map(fn ($r) => [$r['program_no'], $r['prod_type'], $r['buyer'], $r['order'], $r['program_date'], $r['items'], $r['status']], $records));
        $this->as()->deleteJson("/api/v1/merchandise/trim-dyeing/TDP-00001/items/{$items[0]['id']}")->assertOk()->assertJsonPath('data.items', []);

        // The purchase register lists the accessory purchase requisitions (none yet).
        $this->as()->getJson('/api/v1/merchandise/acc-bookings/purchase-register')->assertOk()->assertJsonPath('data', []);
    }

    /** A confirmed order (LPP-2026-000001, style 637PK) and a draft one. */
    private function orders(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $confirmed = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1200, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        $draft = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '530', 'style_id' => $style, 'total_qty' => 50, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($confirmed)->update(['status' => 'CONFIRMED', 'order_type' => 'BULK_NEW', 'conf_date' => '2026-10-01', 'ship_date' => '2026-11-30', 'work_style_no' => '637PK']);
        Order::query()->whereKey($draft)->update(['status' => 'DRAFT']);

        return [Order::query()->whereKey($confirmed)->value('order_no'), Order::query()->whereKey($draft)->value('order_no')];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
