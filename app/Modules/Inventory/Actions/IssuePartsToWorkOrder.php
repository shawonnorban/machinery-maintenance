<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parts moving between the store and a machine (SRS 19).
 *
 * Issue, consume and return are three separate facts, not one. "Four came out
 * of the store" and "four went into the machine" are different claims, and the
 * gap between them is stock sitting in somebody's toolbox that the system
 * believes is fitted. A work order cannot close while that gap is open.
 *
 * The cost of the issue is captured at issue time from the ledger's weighted
 * average and frozen onto the line. A purchase next month at a different price
 * must not rewrite what this repair cost.
 */
class IssuePartsToWorkOrder
{
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly ReserveStock $reservations,
    ) {}

    /**
     * Records that a part is wanted, without moving anything.
     */
    public function request(
        WorkOrder $workOrder,
        SparePart $part,
        string $quantity,
        ?SparePart $substituteFor = null,
    ): WorkOrderPart {
        $this->assertOpen($workOrder);

        return WorkOrderPart::create([
            'work_order_id' => $workOrder->id,
            'spare_part_id' => $part->id,
            // Recorded rather than glossed over: fitting a different part than
            // the one specified is exactly what a failure analysis needs later
            // (SRS 20).
            'substitute_for_spare_part_id' => $substituteFor?->id,
            'quantity_requested' => $quantity,
            'currency' => $workOrder->currency ?? 'BDT',
            'status' => 'REQUESTED',
        ]);
    }

    /**
     * Moves stock out of a bin and onto a work order.
     */
    public function issue(
        WorkOrder $workOrder,
        SparePart $part,
        Bin $bin,
        string $quantity,
        ?string $userId = null,
        ?SparePartReservation $reservation = null,
        ?WorkOrderPart $line = null,
        ?string $idempotencyKey = null,
    ): WorkOrderPart {
        $this->assertOpen($workOrder);

        if (bccomp($quantity, '0', 4) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.quantity_must_be_positive'),
            ]);
        }

        return DB::transaction(function () use (
            $workOrder, $part, $bin, $quantity, $userId, $reservation, $line, $idempotencyKey
        ): WorkOrderPart {
            // Consumed against its own reservation, so the stock is not counted
            // as unavailable twice over.
            if ($reservation !== null) {
                $this->consumeReservation($reservation, $quantity, $userId);
            }

            $transaction = $this->ledger->post($part, $bin, 'ISSUE', $quantity, null, [
                'work_order_id' => $workOrder->id,
                'reservation_id' => $reservation?->id,
                'reference_type' => 'work_order',
                'reference_id' => $workOrder->id,
                'performed_by' => $userId,
                'against_reservation' => $reservation !== null,
                'idempotency_key' => $idempotencyKey,
                'transaction_at' => CarbonImmutable::now(),
            ]);

            $line ??= WorkOrderPart::where('work_order_id', $workOrder->id)
                ->where('spare_part_id', $part->id)
                ->whereIn('status', ['REQUESTED', 'RESERVED'])
                ->first();

            $line ??= WorkOrderPart::create([
                'work_order_id' => $workOrder->id,
                'spare_part_id' => $part->id,
                'quantity_requested' => $quantity,
                'currency' => $workOrder->currency ?? 'BDT',
                'status' => 'REQUESTED',
            ]);

            $issued = bcadd((string) $line->quantity_issued, $quantity, 4);

            // Frozen at issue time. The unit cost on the line is the weighted
            // average the ledger charged, not whatever the part costs later.
            $unitCost = (string) $transaction->unit_cost;
            $totalCost = bcmul($issued, $unitCost, 4);

            $line->forceFill([
                'bin_id' => $bin->id,
                'quantity_issued' => $issued,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'base_total_cost' => $totalCost,
                'reservation_id' => $reservation?->id ?? $line->reservation_id,
                'status' => 'ISSUED',
            ])->save();

            return $line->fresh();
        });
    }

    /**
     * The part went into the machine. This is the point at which it becomes a
     * maintenance cost rather than stock in transit.
     */
    public function consume(WorkOrderPart $line, string $quantity, ?string $userId = null): WorkOrderPart
    {
        $outstanding = $line->outstandingQuantity();

        if (bccomp($quantity, $outstanding, 4) > 0) {
            // Rule 1: consumed plus returned may never exceed issued. Otherwise
            // a work order can consume stock that was never taken out.
            throw ValidationException::withMessages([
                'quantity' => __('inventory.consume_exceeds_issued', [
                    'outstanding' => rtrim(rtrim($outstanding, '0'), '.'),
                ]),
            ])->status(422);
        }

        return DB::transaction(function () use ($line, $quantity, $userId): WorkOrderPart {
            $consumed = bcadd((string) $line->quantity_consumed, $quantity, 4);

            $line->forceFill([
                'quantity_consumed' => $consumed,
                'status' => bccomp($consumed, (string) $line->quantity_issued, 4) >= 0
                    ? 'CONSUMED'
                    : 'PARTIALLY_CONSUMED',
            ])->save();

            // No ledger row: the stock left the store at issue time. Posting a
            // second movement here would double-count it out of the bin.
            unset($userId);

            return $line->fresh();
        });
    }

    /**
     * The part came back unused. It goes into the bin at the price it left at,
     * so a return cannot quietly revalue the shelf.
     */
    public function returnToStore(
        WorkOrderPart $line,
        string $quantity,
        ?string $userId = null,
        ?Bin $bin = null,
    ): WorkOrderPart {
        $outstanding = $line->outstandingQuantity();

        if (bccomp($quantity, $outstanding, 4) > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.return_exceeds_issued', [
                    'outstanding' => rtrim(rtrim($outstanding, '0'), '.'),
                ]),
            ])->status(422);
        }

        return DB::transaction(function () use ($line, $quantity, $userId, $bin): WorkOrderPart {
            $part = SparePart::findOrFail($line->spare_part_id);
            $bin ??= Bin::findOrFail($line->bin_id);

            $this->ledger->post($part, $bin, 'RETURN', $quantity, (string) $line->unit_cost, [
                'work_order_id' => $line->work_order_id,
                'reference_type' => 'work_order_part',
                'reference_id' => $line->id,
                'performed_by' => $userId,
                'transaction_at' => CarbonImmutable::now(),
            ]);

            $returned = bcadd((string) $line->quantity_returned, $quantity, 4);
            $chargeable = bcsub((string) $line->quantity_issued, $returned, 4);
            $totalCost = bcmul($chargeable, (string) $line->unit_cost, 4);

            $line->forceFill([
                'quantity_returned' => $returned,
                // The work order is charged for what it kept, not for what
                // passed through a technician's hands.
                'total_cost' => $totalCost,
                'base_total_cost' => $totalCost,
                'status' => bccomp($returned, (string) $line->quantity_issued, 4) >= 0
                    ? 'RETURNED'
                    : $line->status,
            ])->save();

            return $line->fresh();
        });
    }

    /**
     * Lines still holding stock that was neither fitted nor returned.
     *
     * @return Collection<int, WorkOrderPart>
     */
    public function unsettledLines(WorkOrder $workOrder): Collection
    {
        return WorkOrderPart::where('work_order_id', $workOrder->id)
            ->whereNotIn('status', ['CANCELLED'])
            ->get()
            ->filter(fn (WorkOrderPart $line) => ! $line->isSettled())
            ->values();
    }

    private function consumeReservation(SparePartReservation $reservation, string $quantity, ?string $userId): void
    {
        $outstanding = $reservation->outstanding();

        if (bccomp($quantity, $outstanding, 4) > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.issue_exceeds_reservation'),
            ])->status(422);
        }

        $part = SparePart::findOrFail($reservation->spare_part_id);
        $bin = Bin::findOrFail($reservation->bin_id);

        $balance = $this->ledger->lockBalance($part, $bin);

        $newReserved = bcsub((string) $balance->quantity_reserved, $quantity, 4);

        if (bccomp($newReserved, '0', 4) < 0) {
            $newReserved = '0.0000';
        }

        $balance->forceFill([
            'quantity_reserved' => $newReserved,
            'version' => $balance->version + 1,
        ])->save();

        $issued = bcadd((string) $reservation->quantity_issued, $quantity, 4);
        $accounted = bcadd($issued, (string) $reservation->quantity_released, 4);

        $reservation->forceFill([
            'quantity_issued' => $issued,
            'status' => bccomp($accounted, (string) $reservation->quantity, 4) >= 0
                ? 'ISSUED'
                : 'PARTIALLY_ISSUED',
            'released_by' => $userId,
        ])->save();
    }

    private function assertOpen(WorkOrder $workOrder): void
    {
        if ($workOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'work_order_id' => __('inventory.parts_after_close'),
            ])->status(409);
        }
    }
}
