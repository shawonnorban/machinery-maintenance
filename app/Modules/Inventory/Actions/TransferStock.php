<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryTransfer;
use App\Modules\Inventory\Models\InventoryTransferItem;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock moving between factories (SRS 21).
 *
 * Requested, approved, dispatched, received. Quantities move only at dispatch
 * and receipt: a transfer that decrements stock when somebody asks for it
 * leaves the source factory short of parts it still physically has.
 *
 * Between the two, the stock sits in an in-transit bin. That bin is the whole
 * reason the design works — without it, dispatched stock is either counted in
 * both factories or in neither, and a week-long road journey becomes a hole in
 * the valuation.
 */
class TransferStock
{
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly NumberSequenceGenerator $numbers,
    ) {}

    /**
     * @param  list<array{spare_part_id: string, from_bin_id: string, quantity: string, to_bin_id?: string|null}>  $items
     */
    public function request(
        Factory $from,
        Factory $to,
        array $items,
        ?string $userId = null,
        ?string $notes = null,
    ): InventoryTransfer {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_factory_id' => __('inventory.transfer_same_factory'),
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('inventory.transfer_needs_items'),
            ]);
        }

        return DB::transaction(function () use ($from, $to, $items, $userId, $notes): InventoryTransfer {
            $transfer = InventoryTransfer::create([
                'transfer_number' => $this->numbers->next('INVENTORY_TRANSFER', $from),
                'from_factory_id' => $from->id,
                'to_factory_id' => $to->id,
                'status' => 'REQUESTED',
                'requested_by' => $userId,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $quantity = (string) $item['quantity'];

                if (bccomp($quantity, '0', 4) !== 1) {
                    throw ValidationException::withMessages([
                        'items' => __('inventory.quantity_must_be_positive'),
                    ]);
                }

                InventoryTransferItem::create([
                    'inventory_transfer_id' => $transfer->id,
                    'spare_part_id' => $item['spare_part_id'],
                    'from_bin_id' => $item['from_bin_id'],
                    'to_bin_id' => $item['to_bin_id'] ?? null,
                    'quantity_requested' => $quantity,
                ]);
            }

            return $transfer->fresh();
        });
    }

    public function approve(InventoryTransfer $transfer, ?string $userId = null): InventoryTransfer
    {
        $this->assertTransition($transfer, 'APPROVED');

        // Nothing moves here. Approval is a decision, not a movement.
        $transfer->forceFill([
            'status' => 'APPROVED',
            'approved_by' => $userId,
            'approved_at' => CarbonImmutable::now(),
        ])->save();

        return $transfer->fresh();
    }

    public function reject(InventoryTransfer $transfer, string $reason, ?string $userId = null): InventoryTransfer
    {
        $this->assertTransition($transfer, 'REJECTED');

        $transfer->forceFill([
            'status' => 'REJECTED',
            'rejected_by' => $userId,
            'rejected_at' => CarbonImmutable::now(),
            'notes' => $reason,
        ])->save();

        return $transfer->fresh();
    }

    /**
     * Stock leaves the source bin for the in-transit bin.
     *
     * @param  array<string, string>  $quantities  item id => quantity actually sent
     */
    public function dispatch(
        InventoryTransfer $transfer,
        array $quantities = [],
        ?string $userId = null,
    ): InventoryTransfer {
        $this->assertTransition($transfer, 'IN_TRANSIT');

        $inTransit = $this->inTransitBinFor($transfer);

        return DB::transaction(function () use ($transfer, $quantities, $userId, $inTransit): InventoryTransfer {
            foreach ($transfer->items as $item) {
                // What was actually put on the truck, which is not always what
                // was asked for.
                $quantity = (string) ($quantities[$item->id] ?? $item->quantity_requested);

                if (bccomp($quantity, '0', 4) !== 1) {
                    continue;
                }

                $part = SparePart::findOrFail($item->spare_part_id);
                $fromBin = Bin::findOrFail($item->from_bin_id);

                $out = $this->ledger->post($part, $fromBin, 'TRANSFER_OUT', $quantity, null, [
                    'inventory_transfer_id' => $transfer->id,
                    'reference_type' => 'inventory_transfer',
                    'reference_id' => $transfer->id,
                    'performed_by' => $userId,
                ]);

                // Straight into the in-transit bin at the cost it left at, so
                // the stock is never in two places and never nowhere.
                $this->ledger->post($part, $inTransit, 'TRANSFER_IN', $quantity, (string) $out->unit_cost, [
                    'inventory_transfer_id' => $transfer->id,
                    'reference_type' => 'inventory_transfer',
                    'reference_id' => $transfer->id,
                    'performed_by' => $userId,
                ]);

                $item->forceFill([
                    'quantity_dispatched' => $quantity,
                    'unit_cost_at_dispatch' => $out->unit_cost,
                ])->save();
            }

            $transfer->forceFill([
                'status' => 'IN_TRANSIT',
                'in_transit_bin_id' => $inTransit->id,
                'dispatched_by' => $userId,
                'dispatched_at' => CarbonImmutable::now(),
            ])->save();

            return $transfer->fresh();
        });
    }

    /**
     * Stock leaves the in-transit bin for the destination.
     *
     * @param  array<string, string>  $quantities  item id => quantity actually received
     * @param  array<string, string>  $destinationBins  item id => bin id
     */
    public function receive(
        InventoryTransfer $transfer,
        array $quantities = [],
        array $destinationBins = [],
        ?string $userId = null,
    ): InventoryTransfer {
        $this->assertTransition($transfer, 'RECEIVED');

        $inTransit = Bin::findOrFail($transfer->in_transit_bin_id);

        return DB::transaction(function () use ($transfer, $quantities, $destinationBins, $userId, $inTransit): InventoryTransfer {
            foreach ($transfer->items as $item) {
                $dispatched = (string) $item->quantity_dispatched;
                $received = (string) ($quantities[$item->id] ?? $dispatched);

                if (bccomp($received, $dispatched, 4) > 0) {
                    throw ValidationException::withMessages([
                        'quantity_received' => __('inventory.receive_exceeds_dispatched'),
                    ])->status(422);
                }

                $toBinId = $destinationBins[$item->id] ?? $item->to_bin_id;

                if ($toBinId === null) {
                    throw ValidationException::withMessages([
                        'to_bin_id' => __('inventory.receive_needs_destination'),
                    ]);
                }

                $toBin = Bin::findOrFail($toBinId);
                $part = SparePart::findOrFail($item->spare_part_id);

                if (bccomp($received, '0', 4) === 1) {
                    $this->ledger->post($part, $inTransit, 'TRANSFER_OUT', $received, null, [
                        'inventory_transfer_id' => $transfer->id,
                        'reference_type' => 'inventory_transfer',
                        'reference_id' => $transfer->id,
                        'performed_by' => $userId,
                    ]);

                    $this->ledger->post(
                        $part, $toBin, 'TRANSFER_IN', $received,
                        (string) ($item->unit_cost_at_dispatch ?? '0'),
                        [
                            'inventory_transfer_id' => $transfer->id,
                            'reference_type' => 'inventory_transfer',
                            'reference_id' => $transfer->id,
                            'performed_by' => $userId,
                        ],
                    );
                }

                // A short receipt leaves the difference sitting in the
                // in-transit bin. That is deliberate: the stock left one
                // factory and did not arrive, and it stays visible until
                // somebody explains where it went (ERD Section 13 rule 4).
                $item->forceFill([
                    'quantity_received' => $received,
                    'to_bin_id' => $toBin->id,
                    'quantity_variance' => bcsub($dispatched, $received, 4),
                ])->save();
            }

            $transfer->forceFill([
                'status' => 'RECEIVED',
                'received_by' => $userId,
                'received_at' => CarbonImmutable::now(),
            ])->save();

            return $transfer->fresh();
        });
    }

    /**
     * The in-transit bin for the source factory, created on first use. It is a
     * system location: it holds real stock but nobody picks from it.
     */
    private function inTransitBinFor(InventoryTransfer $transfer): Bin
    {
        $existing = Bin::where('is_in_transit', true)
            ->whereHas('store.warehouse', fn ($q) => $q->where('factory_id', $transfer->from_factory_id))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $store = Store::query()
            ->whereHas('warehouse', fn ($q) => $q->where('factory_id', $transfer->from_factory_id))
            ->first();

        if ($store === null) {
            throw ValidationException::withMessages([
                'from_factory_id' => __('inventory.transfer_needs_in_transit_bin'),
            ])->status(409);
        }

        $factory = Factory::findOrFail($transfer->from_factory_id);

        return Bin::create([
            'store_id' => $store->id,
            'name' => __('inventory.in_transit'),
            'code' => $factory->code.'-TRANSIT',
            'is_in_transit' => true,
        ]);
    }

    private function assertTransition(InventoryTransfer $transfer, string $to): void
    {
        if (! $transfer->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('inventory.transfer_invalid_transition', [
                    'from' => $transfer->status,
                    'to' => $to,
                ]),
            ])->status(409);
        }
    }
}
