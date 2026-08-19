<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransfer;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Inter-factory transfers (SRS 21, ERD Section 13).
 *
 * The property that matters: stock is never in two places and never nowhere. A
 * transfer that decrements the source at dispatch and increments the
 * destination at receipt leaves a hole for the length of the road journey, and
 * a week-long hole in the valuation is what makes a storekeeper stop trusting
 * the figures.
 */
class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private Bin $dhakaBin;

    private Bin $gazipurBin;

    private SparePart $part;

    private InventoryLedger $ledger;

    private TransferStock $transfers;

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

        $this->ledger = app(InventoryLedger::class);
        $this->transfers = app(TransferStock::class);

        $this->ledger->post($this->part, $this->dhakaBin, 'RECEIPT', '30', '200');
    }

    private function onHand(Bin $bin): string
    {
        $balance = InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $bin->id)
            ->first();

        return (string) ($balance?->quantity_on_hand ?? '0.0000');
    }

    private function requested(string $quantity = '10'): InventoryTransfer
    {
        return $this->transfers->request($this->dhaka, $this->gazipur, [[
            'spare_part_id' => $this->part->id,
            'from_bin_id' => $this->dhakaBin->id,
            'to_bin_id' => $this->gazipurBin->id,
            'quantity' => $quantity,
        ]], 'user-a');
    }

    public function test_requesting_moves_no_stock(): void
    {
        $transfer = $this->requested();

        // A transfer that decrements stock when somebody asks for it leaves the
        // source factory short of parts it still physically has.
        $this->assertSame('REQUESTED', $transfer->status);
        $this->assertSame('30.0000', $this->onHand($this->dhakaBin));
        $this->assertCount(1, $transfer->items);
    }

    public function test_approving_moves_no_stock_either(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');

        $this->assertSame('APPROVED', $transfer->status);
        $this->assertSame('30.0000', $this->onHand($this->dhakaBin));
    }

    public function test_dispatch_puts_the_stock_in_transit_rather_than_nowhere(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');
        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');

        $inTransit = Bin::findOrFail($transfer->in_transit_bin_id);

        // Left Dhaka, has not reached Gazipur, and is visible in between.
        $this->assertSame('IN_TRANSIT', $transfer->status);
        $this->assertSame('20.0000', $this->onHand($this->dhakaBin));
        $this->assertSame('10.0000', $this->onHand($inTransit));
        $this->assertSame('0.0000', $this->onHand($this->gazipurBin));
    }

    public function test_the_total_across_all_bins_never_changes_during_a_transfer(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');

        $this->assertSame('30.0000', $this->part->fresh()->totalOnHand());

        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');
        $this->assertSame('30.0000', $this->part->fresh()->totalOnHand());

        $this->transfers->receive($transfer, [], [], 'user-d');
        $this->assertSame('30.0000', $this->part->fresh()->totalOnHand());
    }

    public function test_receiving_lands_the_stock_at_the_cost_it_left_at(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');
        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');
        $transfer = $this->transfers->receive($transfer, [], [], 'user-d');

        $balance = InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $this->gazipurBin->id)
            ->firstOrFail();

        $this->assertSame('RECEIVED', $transfer->status);
        $this->assertSame('10.0000', (string) $balance->quantity_on_hand);
        // Moving stock between factories is not a purchase, so it must not
        // revalue anything.
        $this->assertSame('200.0000', (string) $balance->weighted_average_cost);
    }

    public function test_a_short_receipt_leaves_the_difference_in_transit(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');
        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');

        $item = $transfer->items->first();
        $transfer = $this->transfers->receive($transfer, [$item->id => '7'], [], 'user-d');

        $inTransit = Bin::findOrFail($transfer->in_transit_bin_id);

        // Three left Dhaka and did not arrive. They stay visible in the
        // in-transit bin until somebody explains where they went, rather than
        // being written off silently (ERD Section 13 rule 4).
        $this->assertSame('7.0000', $this->onHand($this->gazipurBin));
        $this->assertSame('3.0000', $this->onHand($inTransit));
        $this->assertSame('3.0000', (string) $transfer->items->first()->quantity_variance);
        $this->assertTrue($transfer->items->first()->hasVariance());
    }

    public function test_receiving_more_than_was_dispatched_is_refused(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');
        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');

        $item = $transfer->items->first();

        $this->expectException(ValidationException::class);
        $this->transfers->receive($transfer, [$item->id => '15'], [], 'user-d');
    }

    public function test_dispatching_less_than_requested_is_recorded(): void
    {
        $transfer = $this->transfers->approve($this->requested('10'), 'user-b');

        $item = $transfer->items->first();
        $transfer = $this->transfers->dispatch($transfer, [$item->id => '6'], 'user-c');

        // What was asked for and what went on the truck are different facts.
        $this->assertSame('10.0000', (string) $transfer->items->first()->quantity_requested);
        $this->assertSame('6.0000', (string) $transfer->items->first()->quantity_dispatched);
        $this->assertSame('24.0000', $this->onHand($this->dhakaBin));
    }

    public function test_dispatching_more_than_is_in_stock_is_refused(): void
    {
        $transfer = $this->transfers->approve($this->requested('50'), 'user-b');

        try {
            $this->transfers->dispatch($transfer, [], 'user-c');
            $this->fail('Dispatching stock that is not there must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $this->assertSame('30.0000', $this->onHand($this->dhakaBin));
    }

    public function test_a_transfer_to_the_same_factory_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->transfers->request($this->dhaka, $this->dhaka, [[
            'spare_part_id' => $this->part->id,
            'from_bin_id' => $this->dhakaBin->id,
            'quantity' => '5',
        ]], 'user-a');
    }

    public function test_a_transfer_with_no_parts_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->transfers->request($this->dhaka, $this->gazipur, [], 'user-a');
    }

    public function test_the_state_machine_refuses_a_skipped_step(): void
    {
        $transfer = $this->requested();

        try {
            // Dispatching something nobody approved.
            $this->transfers->dispatch($transfer, [], 'user-c');
            $this->fail('Dispatching an unapproved transfer must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $this->assertSame('30.0000', $this->onHand($this->dhakaBin));
    }

    public function test_a_rejected_transfer_moves_nothing_and_is_terminal(): void
    {
        $transfer = $this->transfers->reject($this->requested(), 'Gazipur sourced locally', 'user-b');

        $this->assertSame('REJECTED', $transfer->status);
        $this->assertTrue($transfer->isTerminal());
        $this->assertSame('30.0000', $this->onHand($this->dhakaBin));
    }

    public function test_a_transfer_moves_many_parts_at_once(): void
    {
        $second = InventoryFixture::part($this->delta, 'JK-BELT-9000', 'Drive belt');
        $this->ledger->post($second, $this->dhakaBin, 'RECEIPT', '12', '80');

        // v1.0 modelled a transfer as one from/to bin pair, so it could only
        // ever move a single part. Real transfers move many.
        $transfer = $this->transfers->request($this->dhaka, $this->gazipur, [
            [
                'spare_part_id' => $this->part->id,
                'from_bin_id' => $this->dhakaBin->id,
                'to_bin_id' => $this->gazipurBin->id,
                'quantity' => '5',
            ],
            [
                'spare_part_id' => $second->id,
                'from_bin_id' => $this->dhakaBin->id,
                'to_bin_id' => $this->gazipurBin->id,
                'quantity' => '4',
            ],
        ], 'user-a');

        $transfer = $this->transfers->approve($transfer, 'user-b');
        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');
        $this->transfers->receive($transfer, [], [], 'user-d');

        $this->assertCount(2, $transfer->fresh()->items);
        $this->assertSame('25.0000', $this->onHand($this->dhakaBin));
        $this->assertSame('5.0000', $this->onHand($this->gazipurBin));
        $this->assertSame('12.0000', $second->fresh()->totalOnHand());
    }

    public function test_every_bin_still_replays_after_a_transfer(): void
    {
        $transfer = $this->transfers->approve($this->requested(), 'user-b');
        $transfer = $this->transfers->dispatch($transfer, [], 'user-c');
        $transfer = $this->transfers->receive($transfer, [], [], 'user-d');

        $inTransit = Bin::findOrFail($transfer->in_transit_bin_id);

        foreach ([$this->dhakaBin, $this->gazipurBin, $inTransit] as $bin) {
            $result = $this->ledger->verify($this->part, $bin);

            $this->assertTrue(
                $result['matches'],
                "Bin {$bin->code} no longer replays to its stored balance.",
            );
        }
    }
}
