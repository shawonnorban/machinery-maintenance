<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Holding item stock without moving it (Inventory spec §5, §7; Phase 3A).
 *
 * `quantity_reserved` has been read since Phase 1 and written by nothing.
 * These tests pin down what writes it: a reservation encumbers a balance
 * and posts no ledger row, an earmark encumbers nothing until the goods
 * arrive, and a fabric roll is held by name rather than by quantity.
 */
class ItemReservationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private Item $item;

    private User $storekeeper;

    private ItemLedger $ledger;

    private ReserveItemStock $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->item = ItemFixture::item($this->delta);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->ledger = app(ItemLedger::class);
        $this->reservations = app(ReserveItemStock::class);
    }

    private function balance(string $lot = ''): ItemStockBalance
    {
        return ItemStockBalance::where('item_id', $this->item->id)
            ->where('bin_id', $this->bin->id)
            ->where('lot_no', $lot)
            ->firstOrFail();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    public function test_a_reservation_encumbers_the_balance_and_writes_no_ledger_row(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '100');

        $reservation = $this->reservations->reserve($this->item, $this->bin, '4', [
            'reservation_type' => 'FLOOR_REQUISITION',
            'reference_type' => 'floor_requisition',
            'reference_id' => $this->item->id,
            'reserved_by' => $this->storekeeper->id,
        ]);

        // Nothing moved: the stock is still on the shelf, just spoken for.
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('4.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('6.0000', $this->ledger->available($this->item, $this->bin));
        $this->assertSame('6.0000', $this->item->fresh()->totalAvailable());
        $this->assertSame(1, ItemStockTransaction::where('item_id', $this->item->id)->count());

        $this->assertSame('ACTIVE', $reservation->status);
        $this->assertSame('4.0000', $reservation->outstanding());
    }

    public function test_reserving_more_than_is_free_is_refused(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '100');
        $this->reservations->reserve($this->item, $this->bin, '7');

        try {
            $this->reservations->reserve($this->item, $this->bin, '4');
            $this->fail('Reserved stock that was already held for somebody else');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        // The refusal left the first hold exactly as it was.
        $this->assertSame('7.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame(1, ItemReservation::where('item_id', $this->item->id)->count());
    }

    public function test_an_issue_against_a_reservation_draws_down_both(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '100');
        $reservation = $this->reservations->reserve($this->item, $this->bin, '6');

        // The encumbrance comes off first, then the stock moves — the order
        // the issue action uses, so the two never double-count the same units.
        $this->reservations->consume($reservation, '4');
        $this->ledger->post($this->item, $this->bin, 'ISSUE', '4', null, ['against_reservation' => true]);

        $this->assertSame('6.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('2.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('PARTIALLY_ISSUED', $reservation->fresh()->status);

        // Giving back the rest closes it as ISSUED, not RELEASED: most of it
        // did reach the floor.
        $closed = $this->reservations->release($reservation->fresh(), null, $this->storekeeper->id);

        $this->assertSame('ISSUED', $closed->status);
        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('6.0000', $this->ledger->available($this->item, $this->bin));
    }

    public function test_releasing_a_hold_keeps_the_row_and_the_reason(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '100');
        $reservation = $this->reservations->reserve($this->item, $this->bin, '5');

        $released = $this->reservations->release(
            $reservation, null, $this->storekeeper->id, 'RELEASED', 'Urgent order needed it',
        );

        $this->assertSame('RELEASED', $released->status);
        $this->assertSame('5.0000', (string) $released->quantity_released);
        $this->assertSame('Urgent order needed it', $released->override_reason);
        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);
        // Closed, never deleted.
        $this->assertSame(1, ItemReservation::where('item_id', $this->item->id)->count());

        try {
            $this->reservations->release($released);
            $this->fail('Released a reservation that was already closed');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_an_earmark_holds_nothing_until_the_stock_arrives(): void
    {
        $earmark = $this->reservations->earmark($this->item, '5', [
            'reservation_type' => 'FLOOR_REQUISITION',
            'reference_type' => 'floor_requisition_line',
            'reference_id' => $this->item->id,
            'reserved_by' => $this->storekeeper->id,
        ]);

        $this->assertSame('PENDING_ARRIVAL', $earmark->status);
        $this->assertNull($earmark->bin_id);
        $this->assertSame('0.0000', $this->item->fresh()->totalAvailable());

        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '8', '100');
        $filled = $this->reservations->fillPendingEarmarks($this->item, $this->bin, '8');

        $this->assertCount(1, $filled);
        $this->assertSame('ACTIVE', $earmark->fresh()->status);
        $this->assertSame($this->bin->id, $earmark->fresh()->bin_id);
        // The claim is now a real hold: 8 on hand, 5 of them spoken for.
        $this->assertSame('5.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('3.0000', $this->ledger->available($this->item, $this->bin));
    }

    public function test_arriving_stock_fills_the_oldest_claim_first_and_splits_the_remainder(): void
    {
        $first = $this->reservations->earmark($this->item, '5', [
            'reserved_at' => CarbonImmutable::now()->subDay(),
            'reference_type' => 'floor_requisition_line', 'reference_id' => $this->item->id,
        ]);
        $second = $this->reservations->earmark($this->item, '10', [
            'reserved_at' => CarbonImmutable::now(),
            'reference_type' => 'floor_requisition_line', 'reference_id' => $this->item->id,
        ]);

        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '8', '100');
        $filled = $this->reservations->fillPendingEarmarks($this->item, $this->bin, '8');

        $this->assertCount(2, $filled);
        // Whoever waited longest is filled in full before anybody else.
        $this->assertSame('ACTIVE', $first->fresh()->status);
        $this->assertSame('5.0000', (string) $first->fresh()->quantity);

        // The second claim takes what is left and keeps waiting for the rest.
        $this->assertSame('ACTIVE', $second->fresh()->status);
        $this->assertSame('3.0000', (string) $second->fresh()->quantity);

        $remainder = ItemReservation::where('item_id', $this->item->id)
            ->where('status', 'PENDING_ARRIVAL')
            ->get();

        $this->assertCount(1, $remainder);
        $this->assertSame('7.0000', (string) $remainder->first()->quantity);

        $this->assertSame('8.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('0.0000', $this->ledger->available($this->item, $this->bin));
    }

    public function test_an_overdue_hold_is_swept_and_the_stock_comes_back(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '100');

        $this->reservations->reserve($this->item, $this->bin, '6', [
            'expires_at' => CarbonImmutable::now()->subHour(),
        ]);
        $kept = $this->reservations->reserve($this->item, $this->bin, '2', [
            'expires_at' => CarbonImmutable::now()->addWeek(),
        ]);

        $this->assertSame('8.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame(1, $this->reservations->expireOverdue());

        $this->assertSame('2.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('ACTIVE', $kept->fresh()->status);
        $this->assertSame(1, ItemReservation::where('status', 'EXPIRED')->count());
    }

    public function test_a_fabric_roll_is_held_by_name_and_refuses_a_second_claim(): void
    {
        $roll = $this->availableRoll();

        $reservation = $this->reservations->allocateRoll($roll, [
            'reservation_type' => 'MANUAL', 'reserved_by' => $this->storekeeper->id,
        ]);

        $this->assertSame('RESERVED', $roll->fresh()->status);
        $this->assertTrue($reservation->isHardAllocation());
        // A roll-tracked item keeps no balance row, so nothing was encumbered.
        $this->assertSame(0, ItemStockBalance::where('item_id', $roll->item_id)->count());

        try {
            $this->reservations->allocateRoll($roll->fresh());
            $this->fail('Held the same roll twice');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        // Releasing puts the roll back on the shelf.
        $this->reservations->release($reservation, null, $this->storekeeper->id);
        $this->assertSame('AVAILABLE', $roll->fresh()->status);
    }

    public function test_a_roll_tracked_item_cannot_be_reserved_by_quantity(): void
    {
        $fabric = Item::findOrFail($this->availableRoll()->item_id);

        $this->expectException(ValidationException::class);

        $this->reservations->reserve($fabric, $this->bin, '1');
    }

    public function test_the_api_holds_and_releases_stock_and_refuses_everybody_else(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '100');

        $token = $this->tokenFor($this->storekeeper);

        $created = $this->withToken($token)->postJson('/api/v1/item-reservations', [
            'item_id' => $this->item->id,
            'bin_id' => $this->bin->id,
            'quantity' => '4',
        ])->assertCreated()->json('data');

        $this->assertSame('ACTIVE', $created['status']);
        $this->assertSame('4.0000', $created['outstanding']);
        $this->assertSame('4.0000', (string) $this->balance()->quantity_reserved);

        $this->withToken($token)->getJson('/api/v1/item-reservations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);

        // The item's own page says who is holding what.
        $this->withToken($token)->getJson("/api/v1/items/{$this->item->id}")
            ->assertOk()
            ->assertJsonPath('data.reservations.0.id', $created['id']);

        $this->withToken($token)->postJson("/api/v1/item-reservations/{$created['id']}/release", [
            'reason' => 'No longer needed',
        ])->assertOk()->assertJsonPath('data.status', 'RELEASED');

        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->tokenFor($technician))->postJson('/api/v1/item-reservations', [
            'item_id' => $this->item->id,
            'bin_id' => $this->bin->id,
            'quantity' => '1',
        ])->assertForbidden();
    }

    /**
     * One inspected, passed roll — created the way a real one is, through a
     * goods receipt, because `fabric_rolls` cannot exist without one.
     */
    private function availableRoll(): FabricRoll
    {
        $vendor = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Dhaka Fabrics Ltd', 'code' => 'DF2',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $fabric->id,
            'fabric_type' => 'KNIT', 'gsm' => '180', 'width' => '72', 'composition' => '100% cotton',
        ]);

        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $vendor->id,
            'lines' => [['item_id' => $fabric->id, 'received_qty' => '60', 'rate' => '250']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => [[
                'roll_no' => 'R-0001', 'bin_id' => $this->bin->id, 'net_length' => '60', 'gross_length' => '61',
            ]]],
        ])->assertOk();

        $roll = FabricRoll::where('roll_no', 'R-0001')->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/inspect", [
            'grade' => 'A', 'shade_match' => true,
        ])->assertOk();

        return $roll->fresh();
    }
}
