<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransfer;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Moving stock between factories over HTTP (SRS 23.4).
 *
 * Four steps with four different people behind them, and the part that is easy
 * to get wrong: between dispatch and receipt the stock is in neither factory.
 * It sits in an in-transit bin, so a valuation taken while the van is on the
 * road still balances.
 */
class TransferScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private Bin $dhakaBin;

    private Bin $gazipurBin;

    private SparePart $part;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        TenantFixture::actingAsTenant($this->delta);

        $this->dhakaBin = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A1');
        $this->gazipurBin = InventoryFixture::bin($this->delta, $this->gazipur, 'GAZ-A1');
        $this->part = InventoryFixture::part($this->delta);

        // Company-wide, so this person can act for both ends. The tests that
        // care about who may do what use their own users.
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        app(InventoryLedger::class)->post($this->part, $this->dhakaBin, 'RECEIPT', '20', '250');
    }

    /**
     * Normalised, because a bin with no balance row at all and a bin holding
     * zero mean the same thing here and should read the same.
     */
    private function onHand(Bin $bin): string
    {
        $quantity = InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $bin->id)
            ->value('quantity_on_hand');

        return bcadd((string) ($quantity ?? '0'), '0', 4);
    }

    private function requestTransfer(string $quantity = '5'): InventoryTransfer
    {
        $this->actingAs($this->owner)->post('/app/inventory/transfers', [
            'from_factory_id' => $this->dhaka->id,
            'to_factory_id' => $this->gazipur->id,
            'items' => [
                ['spare_part_id' => $this->part->id, 'from_bin_id' => $this->dhakaBin->id, 'quantity' => $quantity],
            ],
        ]);

        return InventoryTransfer::latest('created_at')->firstOrFail();
    }

    public function test_a_transfer_can_be_requested_and_moves_nothing_yet(): void
    {
        $transfer = $this->requestTransfer();

        $this->assertSame('REQUESTED', $transfer->status);
        $this->assertSame('5.0000', (string) $transfer->items->first()->quantity_requested);

        // Asking is not sending: the shelf is untouched until dispatch.
        $this->assertSame('20.0000', $this->onHand($this->dhakaBin));
    }

    public function test_a_transfer_to_the_same_factory_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/inventory/transfers')
            ->post('/app/inventory/transfers', [
                'from_factory_id' => $this->dhaka->id,
                'to_factory_id' => $this->dhaka->id,
                'items' => [
                    ['spare_part_id' => $this->part->id, 'from_bin_id' => $this->dhakaBin->id, 'quantity' => '5'],
                ],
            ])
            ->assertSessionHasErrors('to_factory_id');

        $this->assertSame(0, InventoryTransfer::count());
    }

    /**
     * The property the whole flow exists to preserve.
     */
    public function test_stock_in_transit_belongs_to_neither_factory(): void
    {
        $transfer = $this->requestTransfer();

        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/approve');
        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/dispatch');

        $transfer = $transfer->fresh();

        $this->assertSame('IN_TRANSIT', $transfer->status);

        // Off the sending shelf, not yet on the receiving one, and held
        // somewhere the ledger can still account for it.
        $this->assertSame('15.0000', $this->onHand($this->dhakaBin));
        $this->assertSame('0.0000', $this->onHand($this->gazipurBin));
        $this->assertNotNull($transfer->in_transit_bin_id);

        $inTransit = InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $transfer->in_transit_bin_id)
            ->value('quantity_on_hand');

        $this->assertSame('5.0000', (string) $inTransit);
    }

    public function test_receipt_puts_the_stock_on_the_far_shelf(): void
    {
        $transfer = $this->requestTransfer();

        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/approve');
        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/dispatch');
        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/receive', [
            'bins' => [$transfer->items->first()->id => $this->gazipurBin->id],
        ]);

        $this->assertSame('RECEIVED', $transfer->fresh()->status);
        $this->assertSame('15.0000', $this->onHand($this->dhakaBin));
        $this->assertSame('5.0000', $this->onHand($this->gazipurBin));

        // And the in-transit bin is empty again, so nothing is counted twice.
        $inTransit = InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $transfer->fresh()->in_transit_bin_id)
            ->value('quantity_on_hand');

        $this->assertSame('0.0000', (string) $inTransit);
    }

    public function test_a_rejected_transfer_moves_nothing(): void
    {
        $transfer = $this->requestTransfer();

        $this->actingAs($this->owner)
            ->post('/app/inventory/transfers/'.$transfer->id.'/reject', ['reason' => 'Needed here'])
            ->assertRedirect();

        $this->assertSame('REJECTED', $transfer->fresh()->status);
        $this->assertSame('20.0000', $this->onHand($this->dhakaBin));
    }

    /**
     * The rule that keeps stock from being marked as arrived while it is still
     * on the van.
     */
    public function test_only_the_receiving_factory_may_confirm_receipt(): void
    {
        $transfer = $this->requestTransfer();

        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/approve');
        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/dispatch');

        $sender = TenantFixture::user(
            $this->delta, 'STORE_MANAGER', 'dhaka-store@delta.test', factoryId: $this->dhaka->id,
        );
        TenantFixture::actingAsTenant($this->delta);

        $this->flushSession();

        $this->actingAs($sender)
            ->post('/app/inventory/transfers/'.$transfer->id.'/receive')
            ->assertForbidden();

        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
    }

    public function test_only_the_sending_factory_may_dispatch(): void
    {
        $transfer = $this->requestTransfer();

        $this->actingAs($this->owner)->post('/app/inventory/transfers/'.$transfer->id.'/approve');

        $receiver = TenantFixture::user(
            $this->delta, 'STORE_MANAGER', 'gazipur-store@delta.test', factoryId: $this->gazipur->id,
        );
        TenantFixture::actingAsTenant($this->delta);

        $this->flushSession();

        $this->actingAs($receiver)
            ->post('/app/inventory/transfers/'.$transfer->id.'/dispatch')
            ->assertForbidden();

        $this->assertSame('APPROVED', $transfer->fresh()->status);
    }

    public function test_both_ends_can_see_the_transfer(): void
    {
        $transfer = $this->requestTransfer();

        foreach ([$this->dhaka, $this->gazipur] as $index => $factory) {
            $user = TenantFixture::user(
                $this->delta, 'STORE_MANAGER', "store{$index}@delta.test", factoryId: $factory->id,
            );
            TenantFixture::actingAsTenant($this->delta);

            $this->flushSession();

            // The sending factory has to watch it leave and the receiving one
            // has to see it coming.
            $this->actingAs($user)
                ->get('/app/inventory/transfers')
                ->assertOk()
                ->assertSee($transfer->transfer_number);
        }
    }

    public function test_another_companys_transfer_is_not_listed(): void
    {
        $transfer = $this->requestTransfer();

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::factory($other, 'Their Unit', 'BTU');
        TenantFixture::actingAsTenant($other);
        $theirs = TenantFixture::user($other, 'STORE_MANAGER', 'store@btl.test');

        $this->flushSession();

        $this->actingAs($theirs)
            ->get('/app/inventory/transfers')
            ->assertOk()
            ->assertDontSee($transfer->transfer_number);
    }
}
