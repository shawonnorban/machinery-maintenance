<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric booking and its reservation sync (Procurement spec §3, Inventory
 * spec §7.1; Phase 3B).
 *
 * A booking is not a purchase — nothing is owed and nothing arrives on its
 * own — but a confirmed one is planned against. These tests pin what that
 * means: confirming stakes a claim on stock that has not shipped,
 * cancelling gives it back, and booking above the bill of materials warns
 * without refusing.
 */
class FabricBookingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $storeManager;

    private User $storekeeper;

    private Vendor $mill;

    private Item $fabric;

    private Style $style;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Dhaka Fabrics Ltd', 'code' => 'DF2',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);

        $order = Order::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id,
            'order_no' => 'HM-4471-23', 'buyer' => 'H&M', 'order_qty' => '1000',
            'order_date' => '2026-09-01', 'status' => 'CONFIRMED',
        ]);

        $this->style = Style::create([
            'company_id' => $this->delta->id, 'production_order_id' => $order->id,
            'style_no' => 'ST-100', 'quantity' => '1000',
        ]);

        // 1.85 m/pc + 8% wastage on 1000 pieces = 1998 m required.
        StyleBomLine::create([
            'company_id' => $this->delta->id, 'style_id' => $this->style->id,
            'item_id' => $this->fabric->id, 'item_code' => $this->fabric->item_code,
            'item_name' => $this->fabric->name, 'uom_id' => $this->fabric->uom_id,
            'consumption_per_unit' => '1.85', 'wastage_percent' => '8',
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @return array<string, mixed>
     */
    private function draftBooking(string $token, string $quantity = '1900'): array
    {
        return $this->withToken($token)->postJson('/api/v1/fabric-bookings', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'style_id' => $this->style->id,
            'required_date' => '2026-10-20',
            'lines' => [[
                'item_id' => $this->fabric->id,
                'booked_qty' => $quantity,
                'tentative_rate' => '250',
            ]],
        ])->assertCreated()->json('data');
    }

    public function test_confirming_a_booking_stakes_a_claim_on_stock_that_has_not_shipped(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $booking = $this->draftBooking($token);

        $this->assertStringStartsWith('FB-DHK-', $booking['booking_no']);
        $this->assertSame('DRAFT', $booking['status']);
        $this->assertSame('ST-100', $booking['style']['style_no']);
        // The order comes along with the style rather than being typed twice.
        $this->assertSame('HM-4471-23', $booking['production_order']['order_no']);
        $this->assertSame('475000.0000', $booking['lines'][0]['line_total']);

        // Nothing is claimed while it is a draft.
        $this->assertSame(0, ItemReservation::count());

        $confirmed = $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/confirm")
            ->assertOk()->json('data');

        $this->assertSame('CONFIRMED', $confirmed['status']);
        $this->assertSame([], $confirmed['warnings']);

        $claim = ItemReservation::firstOrFail();
        $this->assertSame('PENDING_ARRIVAL', $claim->status);
        $this->assertSame('BOOKING', $claim->reservation_type);
        $this->assertSame('fabric_booking_line', $claim->reference_type);
        $this->assertSame($booking['lines'][0]['id'], $claim->reference_id);
        $this->assertSame('1900.0000', (string) $claim->quantity);
        // It holds nothing yet: the fabric is at the mill, not on a shelf.
        $this->assertNull($claim->bin_id);

        // A confirmed booking is no longer editable.
        $this->withToken($token)->patchJson("/api/v1/fabric-bookings/{$booking['id']}", [
            'lines' => [['item_id' => $this->fabric->id, 'booked_qty' => '10']],
        ])->assertStatus(409);
    }

    public function test_booking_above_the_bill_of_materials_warns_but_does_not_refuse(): void
    {
        $token = $this->tokenFor($this->storeManager);

        // The style needs 1998 m; this books 2500.
        $booking = $this->draftBooking($token, '2500');

        $response = $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/confirm")
            ->assertOk()->json('data');

        $this->assertSame('CONFIRMED', $response['status']);
        $this->assertCount(1, $response['warnings']);
        $this->assertStringContainsString('2500', $response['warnings'][0]['message']);
        $this->assertStringContainsString('1998', $response['warnings'][0]['message']);

        // A second booking counts what the first already took.
        $second = $this->draftBooking($token, '100');
        $again = $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$second['id']}/confirm")
            ->assertOk()->json('data');

        $this->assertCount(1, $again['warnings']);
    }

    public function test_an_item_outside_the_styles_bill_of_materials_is_flagged(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $trim = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');

        $booking = $this->withToken($token)->postJson('/api/v1/fabric-bookings', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'style_id' => $this->style->id,
            'lines' => [['item_id' => $trim->id, 'booked_qty' => '5000']],
        ])->assertCreated()->json('data');

        $confirmed = $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/confirm")
            ->assertOk()->json('data');

        $this->assertCount(1, $confirmed['warnings']);
        $this->assertStringContainsString('ST-100', $confirmed['warnings'][0]['message']);
    }

    public function test_cancelling_gives_the_claim_back(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $booking = $this->draftBooking($token);

        $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/confirm")->assertOk();
        $this->assertSame(1, ItemReservation::whereIn('status', ItemReservation::OPEN_STATUSES)->count());

        $cancelled = $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/cancel", [
            'reason' => 'Buyer pulled the order',
        ])->assertOk()->json('data');

        $this->assertSame('CANCELLED', $cancelled['status']);
        // Closed, not deleted — the claim is still the record of what was held.
        $this->assertSame(0, ItemReservation::whereIn('status', ItemReservation::OPEN_STATUSES)->count());
        $this->assertSame('CANCELLED', ItemReservation::firstOrFail()->status);

        $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/convert")
            ->assertStatus(409);
    }

    public function test_converting_raises_a_draft_purchase_requisition(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $booking = $this->draftBooking($token);

        $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/confirm")->assertOk();

        $converted = $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/convert")
            ->assertOk()->json('data');

        $this->assertSame('CONVERTED', $converted['status']);

        $requisition = PurchaseRequisition::firstOrFail();
        $this->assertSame('DRAFT', $requisition->status);
        $this->assertSame('AUTO', $requisition->pr_type);
        $this->assertSame($booking['id'], $requisition->source_reference);
        $this->assertSame('1900.0000', (string) $requisition->lines()->firstOrFail()->requested_qty);
        $this->assertSame('250.0000', (string) $requisition->lines()->firstOrFail()->estimated_rate);
        $this->assertSame($requisition->id, $converted['purchase_requisition']['id']);

        // The claim stays open — the fabric still has not arrived.
        $this->assertSame('PENDING_ARRIVAL', ItemReservation::firstOrFail()->status);

        // And a converted booking is the end of its own life.
        $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/convert")
            ->assertStatus(409);
    }

    public function test_a_storekeeper_cannot_book_fabric(): void
    {
        $this->withToken($this->tokenFor($this->storekeeper))->postJson('/api/v1/fabric-bookings', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'lines' => [['item_id' => $this->fabric->id, 'booked_qty' => '10']],
        ])->assertForbidden();
    }
}
