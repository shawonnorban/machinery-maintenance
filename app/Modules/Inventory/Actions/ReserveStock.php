<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Holds stock for a work order without moving it (ERD Section 13).
 *
 * A reservation writes nothing to the ledger. Nothing has physically moved, and
 * a ledger row here would mean replaying the ledger no longer reproduces what
 * is on the shelf. It only raises quantity_reserved, which makes the stock
 * unavailable to anyone else while leaving it exactly where it is.
 *
 * Reservations expire, because stock held indefinitely for a job nobody started
 * is stock the rest of the factory cannot use and cannot see the reason for.
 */
class ReserveStock
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    public function handle(
        SparePart $part,
        Bin $bin,
        WorkOrder $workOrder,
        string $quantity,
        ?string $userId = null,
        ?CarbonImmutable $expiresAt = null,
    ): SparePartReservation {
        if (bccomp($quantity, '0', 4) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.quantity_must_be_positive'),
            ]);
        }

        if ($workOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'work_order_id' => __('inventory.reserve_after_close'),
            ])->status(409);
        }

        return DB::transaction(function () use ($part, $bin, $workOrder, $quantity, $userId, $expiresAt): SparePartReservation {
            // Locked for the same reason an issue is: two concurrent
            // reservations of the last item must not both succeed.
            $balance = $this->ledger->lockBalance($part, $bin);

            $available = bcsub(
                (string) $balance->quantity_on_hand,
                (string) $balance->quantity_reserved,
                4,
            );

            if (bccomp($available, $quantity, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('inventory.insufficient_available', [
                        'part' => $part->part_number,
                        'available' => rtrim(rtrim($available, '0'), '.'),
                    ]),
                ])->status(409);
            }

            $reservation = SparePartReservation::create([
                'spare_part_id' => $part->id,
                'work_order_id' => $workOrder->id,
                'bin_id' => $bin->id,
                'quantity' => $quantity,
                'status' => 'ACTIVE',
                'reserved_by' => $userId,
                'reserved_at' => CarbonImmutable::now(),
                'expires_at' => $expiresAt,
            ]);

            $balance->forceFill([
                'quantity_reserved' => bcadd((string) $balance->quantity_reserved, $quantity, 4),
                'version' => $balance->version + 1,
            ])->save();

            return $reservation->fresh();
        });
    }

    /**
     * Gives back whatever the reservation still holds.
     *
     * The reservation is closed, never deleted: "why was this part held for two
     * days" is a question somebody asks, and a deleted row cannot answer it.
     */
    public function release(
        SparePartReservation $reservation,
        ?string $quantity = null,
        ?string $userId = null,
        string $status = 'RELEASED',
    ): SparePartReservation {
        if (! $reservation->isHolding()) {
            throw ValidationException::withMessages([
                'reservation_id' => __('inventory.reservation_not_holding'),
            ])->status(409);
        }

        $outstanding = $reservation->outstanding();
        $quantity ??= $outstanding;

        if (bccomp($quantity, $outstanding, 4) > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.release_exceeds_reservation'),
            ]);
        }

        return DB::transaction(function () use ($reservation, $quantity, $userId, $status): SparePartReservation {
            $part = SparePart::findOrFail($reservation->spare_part_id);
            $bin = Bin::findOrFail($reservation->bin_id);

            $balance = $this->ledger->lockBalance($part, $bin);

            // Floored at zero: an encumbrance that went negative would make
            // available stock look larger than what is physically there.
            $newReserved = bcsub((string) $balance->quantity_reserved, $quantity, 4);

            if (bccomp($newReserved, '0', 4) < 0) {
                $newReserved = '0.0000';
            }

            $balance->forceFill([
                'quantity_reserved' => $newReserved,
                'version' => $balance->version + 1,
            ])->save();

            $released = bcadd((string) $reservation->quantity_released, $quantity, 4);

            $reservation->forceFill([
                'quantity_released' => $released,
                'status' => $this->statusAfterRelease($reservation, $released, $status),
                'released_by' => $userId,
                'released_at' => CarbonImmutable::now(),
            ])->save();

            return $reservation->fresh();
        });
    }

    /**
     * Releases whatever a work order still holds. Called when the job is closed
     * or cancelled, so parts set aside for work that is over go back on the
     * shelf rather than staying invisible.
     *
     * @return int the number of reservations released
     */
    public function releaseForWorkOrder(WorkOrder $workOrder, ?string $userId = null, string $status = 'RELEASED'): int
    {
        $reservations = SparePartReservation::where('work_order_id', $workOrder->id)
            ->whereIn('status', SparePartReservation::HOLDING_STATUSES)
            ->get();

        foreach ($reservations as $reservation) {
            $this->release($reservation, null, $userId, $status);
        }

        return $reservations->count();
    }

    /**
     * A reservation partly issued and then released is ISSUED rather than
     * RELEASED: most of it did reach the machine, and reporting it as released
     * would lose that.
     */
    private function statusAfterRelease(
        SparePartReservation $reservation,
        string $released,
        string $requestedStatus,
    ): string {
        $accounted = bcadd((string) $reservation->quantity_issued, $released, 4);

        if (bccomp($accounted, (string) $reservation->quantity, 4) < 0) {
            return $reservation->status;
        }

        return bccomp((string) $reservation->quantity_issued, '0', 4) > 0
            ? 'ISSUED'
            : $requestedStatus;
    }
}
