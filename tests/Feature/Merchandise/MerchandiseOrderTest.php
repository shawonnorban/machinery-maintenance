<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Style & Order → Order Register (AnnotechERP 06/07): an
 * order numbered by its buyer and year, filled from a picked style, with its
 * total price and lead time worked out; and its colour × size breakdown,
 * whose lines decide the order's quantity.
 */
class MerchandiseOrderTest extends TestCase
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

    public function test_an_order_is_numbered_by_its_buyer_and_filled_from_its_style(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        [$other] = $this->buyer('NIS', 'Nishimatsuya');
        $style = $this->style($lpp, $office, '637PK');

        // Without a style, the style no and buyer are needed; the job and quantity always are.
        $this->as()->postJson('/api/v1/erp/merch-orders', ['total_qty' => 100])->assertUnprocessable()->assertJsonValidationErrors(['job_no', 'style_no', 'buyer_id']);

        $first = $this->as()->postJson('/api/v1/erp/merch-orders', [
            'job_no' => '0036-R26-03', 'style_id' => $style, 'order_date' => '2026-10-01', 'conf_date' => '2026-10-05', 'ship_date' => '2026-12-04',
            'total_qty' => 1200, 'unit_price' => 2.5, 'currency' => 'usd',
        ])->assertCreated()->json('data');
        $this->assertSame(['LPP-'.now()->format('Y').'-000001', '637PK', $lpp, $office], [$first['order_no'], $first['style_no'], $first['buyer_id'], $first['buyer_office_id']]);
        $this->assertSame(['BULK_NEW', 'PCS', 'USD', 'CONFIRMED'], [$first['order_type'], $first['unit'], $first['currency'], $first['status']]);
        $this->assertEquals(3000, $first['total_price']);
        $this->assertSame(60, $first['lead_time']);

        $second = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J2', 'style_no' => 'X1', 'buyer_id' => $lpp, 'total_qty' => 10])->assertCreated()->json('data.order_no');
        $this->assertSame('LPP-'.now()->format('Y').'-000002', $second);
        $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J3', 'style_no' => 'N1', 'buyer_id' => $other, 'total_qty' => 10])->assertCreated()->assertJsonPath('data.order_no', 'NIS-'.now()->format('Y').'-000001');

        // A typed number stays the order's own; a taken one, another buyer's style or a ship date before the order are refused.
        $this->as()->postJson('/api/v1/erp/merch-orders', ['order_no' => 'PO-2026-145', 'job_no' => 'J4', 'style_no' => 'X1', 'buyer_id' => $lpp, 'total_qty' => 5])->assertCreated()->assertJsonPath('data.order_no', 'PO-2026-145');
        $this->as()->postJson('/api/v1/erp/merch-orders', ['order_no' => 'PO-2026-145', 'job_no' => 'J5', 'style_no' => 'X1', 'buyer_id' => $lpp, 'total_qty' => 5])->assertUnprocessable()->assertJsonValidationErrors('order_no');
        $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J6', 'style_id' => $style, 'buyer_id' => $other, 'total_qty' => 5])->assertUnprocessable()->assertJsonValidationErrors('style_id');
        $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J7', 'style_no' => 'X1', 'buyer_id' => $lpp, 'total_qty' => 5, 'order_date' => '2026-10-10', 'ship_date' => '2026-10-01'])->assertUnprocessable()->assertJsonValidationErrors('ship_date');

        $this->as()->patchJson("/api/v1/erp/merch-orders/{$first['id']}", ['status' => 'IN_PRODUCTION', 'unit_price' => 3])->assertOk()->assertJsonPath('data.status', 'IN_PRODUCTION')->assertJsonPath('data.order_no', $first['order_no']);
        $this->assertCount(4, $this->as()->getJson('/api/v1/erp/merch-orders')->assertOk()->json('data'));
        $this->assertContains('637PK — Hoodie', array_column($this->as()->getJson('/api/v1/merchandise/orders/lookups')->assertOk()->json('data.styles'), 'label'));
    }

    public function test_the_size_breakdown_decides_the_order_quantity(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($lpp, $office, '529');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1000])->assertCreated()->json('data.id');

        $empty = $this->as()->getJson("/api/v1/merchandise/orders/{$order}/sizes")->assertOk()->json('data');
        $this->assertSame([], $empty['rows']);
        $this->assertSame(['M'], $empty['suggest']['sizes']);

        // Every line names its colour, size and quantity, once.
        $this->as()->putJson("/api/v1/merchandise/orders/{$order}/sizes", ['rows' => [['colour' => 'White', 'size' => 'M', 'qty' => 0]]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/merchandise/orders/{$order}/sizes", ['rows' => [['colour' => 'White', 'size' => 'M', 'qty' => 5], ['colour' => 'white ', 'size' => 'm', 'qty' => 6]]])->assertUnprocessable()->assertJsonValidationErrors('rows');

        $saved = $this->as()->putJson("/api/v1/merchandise/orders/{$order}/sizes", ['rows' => [['colour' => 'WHITE', 'size' => 'S', 'qty' => 426], ['colour' => 'WHITE', 'size' => 'M', 'qty' => 426], ['colour' => 'WHITE', 'size' => 'L', 'qty' => 142]]])->assertOk()->json('data');
        $this->assertSame(994, $saved['order']['total_qty']);
        $this->assertSame(['S', 'M', 'L'], array_column($saved['rows'], 'size'));

        // A quantity typed on the order gives way to its breakdown.
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$order}", ['total_qty' => 5000])->assertOk()->assertJsonPath('data.total_qty', 994)->assertJsonPath('data.size_lines', 3);

        // Clearing the breakdown leaves the last quantity; deleting the order takes its lines along.
        $this->as()->putJson("/api/v1/merchandise/orders/{$order}/sizes", ['rows' => []])->assertOk()->assertJsonPath('data.order.total_qty', 994);
        $this->as()->deleteJson("/api/v1/erp/merch-orders/{$order}")->assertSuccessful();
        $this->as()->getJson("/api/v1/merchandise/orders/{$order}/sizes")->assertNotFound();
    }

    public function test_approved_styles_are_confirmed_as_orders_in_one_entry(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $approved = $this->style($lpp, $office, '529', 'STYLE_APPROVED');
        $set = $this->style($lpp, $office, 'SUMMER', 'STYLE_APPROVED', 'SET');
        $draft = $this->style($lpp, $office, '637PK');
        $this->as()->postJson("/api/v1/merchandise/styles/{$approved}/items", ['for_style_ids' => [$approved], 'item_group' => 'KNIT_TOP', 'item_type' => 'T-Shirt', 'division' => 'MENS', 'short_name' => 'TS', 'full_name' => 'TOP T-SHIRT', 'status' => 'ACTIVE', 'nature_of_work' => 'CUT_MAKE'])->assertCreated();

        // Only the buyer's approved styles are offered, with what an order would inherit.
        $styles = $this->as()->getJson("/api/v1/merchandise/order-confirmation/styles?buyer={$lpp}")->assertOk()->json('data');
        $this->assertSame(['529', 'SUMMER'], array_column($styles, 'style_no'));
        $this->assertSame(['TOP T-SHIRT', 1, 0], [$styles[0]['garment_type'], $styles[0]['inherit']['items'], $styles[0]['inherit']['fabrics']]);

        $entry = ['buyer_id' => $lpp, 'po_no' => 'PO-2026-145', 'order_date' => '2026-10-12', 'ship_date' => '2026-12-01', 'currency' => 'USD'];
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', ['po_no' => ''] + $entry + ['lines' => [['style_id' => $approved, 'qty' => 10]]])->assertUnprocessable()
            ->assertJsonPath('errors.po_no.0', "Enter the buyer's PO / order reference.");
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $draft, 'qty' => 10]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.style_id');
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $approved, 'qty' => null]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');

        $orders = $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $approved, 'qty' => 1562, 'unit_price' => 2.5], ['style_id' => $set, 'qty' => 300]]])->assertCreated()->json('data');
        $year = now()->format('Y');
        $this->assertSame(["LPP-{$year}-000001", "LPP-{$year}-000002"], array_column($orders, 'order_no'));
        $this->assertSame(['PO-2026-145', 'TOP T-SHIRT', 'BULK_NEW', 'CONFIRMED', 'PCS'], [$orders[0]['po_no'], $orders[0]['item'], $orders[0]['order_type'], $orders[0]['status'], $orders[0]['unit']]);
        $this->assertEquals(3905, $orders[0]['total_price']);
        $this->assertSame(['SET', 3], [$orders[1]['unit'], $orders[1]['pcs_per_unit']]);

        // A style confirmed again is a repeat order.
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $approved, 'qty' => 100]]])->assertCreated()->assertJsonPath('data.0.order_type', 'REPEAT');
        $this->assertSame(2, $this->as()->getJson("/api/v1/merchandise/order-confirmation/styles?buyer={$lpp}")->json('data.0.orders'));
    }

    public function test_the_style_sheet_confirms_and_rewrites_an_order_of_its_style(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($lpp, $office, '637PK');
        $country = OriginCountry::query()->where('code', '1000')->value('id');

        $empty = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/orders")->assertOk()->json('data');
        $this->assertSame([], $empty['rows']);
        $this->assertSame(['637PK', 'PCS'], [$empty['style']['style_no'], $empty['style']['qty_unit']]);
        $this->assertSame('Delta Apparels Ltd', $empty['options']['company']);

        $order = ['order_type' => 'BULK_NEW', 'job_no' => '637PK-01', 'po_no' => '4500123', 'sub_style' => '637PK', 'conf_date' => '2026-10-12', 'ship_date' => '2026-12-11',
            'total_qty' => 2400, 'unit' => 'PCS', 'pcs_per_unit' => 1, 'unit_price' => 3.25, 'currency' => 'USD', 'exchange_rate' => 122, 'status' => 'CONFIRMED', 'ship_country_id' => $country, 'projection_only' => true];
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/orders", ['ship_date' => '2026-10-01'] + $order)->assertUnprocessable()->assertJsonValidationErrors('ship_date');
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/orders", ['ship_country_id' => null, 'exchange_rate' => null] + $order)->assertUnprocessable()->assertJsonValidationErrors(['ship_country_id', 'exchange_rate']);

        $rows = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/orders", $order)->assertCreated()->json('data');
        $this->assertSame(['LPP-'.now()->format('Y').'-000001', '637PK-01', '4500123', '637PK', $lpp, 60], [$rows[0]['order_no'], $rows[0]['job_no'], $rows[0]['po_no'], $rows[0]['style_no'], $rows[0]['buyer_id'], $rows[0]['lead_time']]);
        $this->assertTrue($rows[0]['projection_only']);
        $this->assertEquals(7800, $rows[0]['total_price']);

        // Rewriting keeps the number; a breakdown still decides the quantity.
        $this->as()->putJson("/api/v1/merchandise/orders/{$rows[0]['id']}/sizes", ['rows' => [['colour' => 'BLACK', 'size' => 'M', 'qty' => 2000]]])->assertOk();
        $updated = $this->as()->putJson("/api/v1/merchandise/style-orders/{$rows[0]['id']}", ['order_type' => 'REPEAT', 'status' => 'IN_PRODUCTION'] + $order)->assertOk()->json('data.0');
        $this->assertSame([$rows[0]['order_no'], 'REPEAT', 'IN_PRODUCTION', 2000], [$updated['order_no'], $updated['order_type'], $updated['status'], $updated['total_qty']]);
    }

    public function test_the_dashboard_and_progress_board_count_styles_and_orders(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $approved = $this->style($lpp, $office, '529', 'STYLE_APPROVED');
        $this->style($lpp, $office, '637PK');
        $entry = ['buyer_id' => $lpp, 'po_no' => 'PO-1', 'order_date' => '2026-10-12', 'ship_date' => '2026-12-01'];
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $approved, 'qty' => 1000]]])->assertCreated();
        $second = $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $approved, 'qty' => 562]]])->assertCreated()->json('data.0.id');
        $cancelled = $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['lines' => [['style_id' => $approved, 'qty' => 99]]])->assertCreated()->json('data.0.id');
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$second}", ['status' => 'IN_PRODUCTION'])->assertOk();
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$cancelled}", ['status' => 'CANCELLED'])->assertOk();

        $dash = $this->as()->getJson('/api/v1/merchandise/style-order/dashboard')->assertOk()->json('data');
        $this->assertSame(['styles' => 2, 'approved' => 1, 'orders' => 2, 'in_production' => 1], $dash['counts']);
        $this->assertCount(2, $dash['styles']);
        $this->assertCount(3, $dash['orders']);

        // A cancelled order counts nowhere on the board.
        $board = collect($this->as()->getJson('/api/v1/merchandise/style-order/progress')->assertOk()->json('data'))->keyBy('style_no');
        $this->assertSame([2, 1562, 0], [$board['529']['orders'], $board['529']['order_qty'], $board['529']['samples']]);
        $this->assertSame([0, 0], [$board['637PK']['orders'], $board['637PK']['order_qty']]);
    }

    public function test_the_follow_up_lists_each_order_against_its_shipment(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($lpp, $office, '531', 'STYLE_APPROVED');
        $entry = ['buyer_id' => $lpp, 'po_no' => 'PO-1', 'order_date' => '2026-10-01'];
        $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['ship_date' => '2026-12-01', 'lines' => [['style_id' => $style, 'qty' => 4200, 'unit_price' => 2.8]]])->assertCreated();
        $closed = $this->as()->postJson('/api/v1/merchandise/order-confirmation', $entry + ['ship_date' => '2026-11-01', 'lines' => [['style_id' => $style, 'qty' => 10]]])->assertCreated()->json('data.0.id');
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$closed}", ['status' => 'CLOSED'])->assertOk();

        $rows = $this->as()->getJson('/api/v1/merchandise/style-order/follow-up')->assertOk()->json('data');
        // Earliest shipment first; production is nothing yet.
        $this->assertSame([false, true], array_column($rows, 'open'));
        $this->assertSame('Hoodie', $rows[1]['style_desc']);
        $this->assertEquals(11760, $rows[1]['order_value']);
        $this->assertSame([0, 0, false], [$rows[1]['cutting'], $rows[1]['shipment'], $rows[1]['verified']]);
        $this->assertStringContainsString('(531)', $rows[1]['order_style']);
    }

    public function test_call_offs_are_drawn_against_a_projection_within_its_quantity(): void
    {
        [$lpp, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($lpp, $office, '927');
        $projection = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'PRJ-1', 'style_id' => $style, 'order_type' => 'PROJECTION', 'total_qty' => 10000, 'unit_price' => 2.35, 'ship_date' => '2027-03-01'])->assertCreated()->json('data');
        $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'B-1', 'style_id' => $style, 'total_qty' => 50])->assertCreated();

        $list = $this->as()->getJson('/api/v1/merchandise/projections')->assertOk()->json('data');
        $this->assertSame([[$projection['order_no'], 10000, 0, 10000]], array_map(fn ($p) => [$p['order_no'], $p['total_qty'], $p['called_off'], $p['balance']], $list));

        $call = ['job_no' => 'CO-1', 'po_no' => '4500999', 'conf_date' => '2026-10-12', 'ship_date' => '2026-12-15', 'total_qty' => 6000];
        $this->as()->postJson("/api/v1/merchandise/projections/{$projection['id']}/call-offs", ['ship_date' => '2026-10-01'] + $call)->assertUnprocessable()->assertJsonValidationErrors('ship_date');
        $first = $this->as()->postJson("/api/v1/merchandise/projections/{$projection['id']}/call-offs", $call)->assertCreated()->json('data');
        $this->assertSame([6000, 4000], [$first['projection']['called_off'], $first['projection']['balance']]);
        $drawn = $first['call_offs'][0];
        $this->assertSame(['927', 'BULK_NEW', 'CONFIRMED', $projection['id']], [$drawn['style_no'], $drawn['order_type'], $drawn['status'], $drawn['projection_id']]);
        $this->assertEquals(2.35, $drawn['unit_price']);

        // Never past the balance — neither drawing, nor rewriting a call-off, nor shrinking or retyping the projection.
        $this->as()->postJson("/api/v1/merchandise/projections/{$projection['id']}/call-offs", ['total_qty' => 4001] + $call)->assertUnprocessable()->assertJsonValidationErrors('total_qty');
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$drawn['id']}", ['total_qty' => 10001])->assertUnprocessable()->assertJsonValidationErrors('total_qty');
        $this->as()->putJson("/api/v1/merchandise/orders/{$drawn['id']}/sizes", ['rows' => [['colour' => 'NAVY', 'size' => 'M', 'qty' => 10001]]])->assertUnprocessable();
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$projection['id']}", ['total_qty' => 5999])->assertUnprocessable()->assertJsonValidationErrors('total_qty');
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$projection['id']}", ['order_type' => 'BULK_NEW'])->assertUnprocessable()->assertJsonValidationErrors('order_type');
        $this->as()->deleteJson("/api/v1/erp/merch-orders/{$projection['id']}")->assertStatus(422);

        // A cancelled call-off gives its quantity back.
        $this->as()->patchJson("/api/v1/erp/merch-orders/{$drawn['id']}", ['status' => 'CANCELLED'])->assertOk();
        $this->as()->postJson("/api/v1/merchandise/projections/{$projection['id']}/call-offs", ['total_qty' => 10000] + $call)->assertCreated()->assertJsonPath('data.projection.balance', 0);
    }

    public function test_the_order_book_is_closed_to_a_technician_and_read_only_to_a_manager(): void
    {
        [$lpp] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/erp/merch-orders')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/erp/merch-orders')->assertOk();
        $this->as($manager)->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J', 'style_no' => 'X', 'buyer_id' => $lpp, 'total_qty' => 1])->assertForbidden();
    }

    /** @return array{0: string, 1: string} the buyer and its office */
    private function buyer(string $code, string $name): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => "{$name} Office", 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => $code, 'name' => $name, 'short_name' => $code, 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');

        return [$buyer, $office];
    }

    private function style(string $buyer, string $office, string $ref, string $status = 'DEVELOPMENT', string $unit = 'PCS'): string
    {
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id')
            ?? Size::query()->where('code', 'M')->value('id');

        return $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => $ref, 'description' => 'Hoodie', 'qty_unit' => $unit, 'pcs_per_unit' => $unit === 'SET' ? 3 : 1, 'status' => $status, 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
