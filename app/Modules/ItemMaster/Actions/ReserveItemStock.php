<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Services\ItemLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The whole reservation lifecycle for the item library (Inventory spec §7).
 *
 * Ported from ReserveStock (spare parts) rather than shared with it: the keys
 * differ — this one reserves against (item, bin, lot) or one named fabric
 * roll — and it has to do something the spare-parts side never needed, which
 * is hold a claim on stock that has not arrived yet.
 *
 * Nothing here writes a ledger row. A reservation moves no stock; it raises
 * `quantity_reserved` on the balance, which is what makes the stock invisible
 * to the next requisition while leaving it exactly where it is. Putting a
 * RESERVATION row in the ledger would mean replaying the ledger no longer
 * reproduces what is on the shelf.
 */
class ReserveItemStock
{
    private const SCALE = 4;

    public function __construct(private readonly ItemLedger $ledger) {}

    /**
     * Holds a quantity of an item in one bin.
     *
     * @param  array<string, mixed>  $attributes  reservation_type, reference_type,
     *         reference_id, lot_no, reserved_by, expires_at
     */
    public function reserve(Item $item, Bin $bin, string $quantity, array $attributes = []): ItemReservation
    {
        $this->assertPositive($quantity);

        if ($item->usesRollLedger()) {
            // A roll-tracked item has no balance row to encumber; reserving
            // it means naming a roll.
            throw ValidationException::withMessages([
                'item_id' => __('itemmaster.reserve_roll_by_roll', ['item' => $item->item_code]),
            ]);
        }

        $lotNo = $item->isLotTracked()
            ? trim((string) ($attributes['lot_no'] ?? ''))
            : ItemStockBalance::NO_LOT;

        return DB::transaction(function () use ($item, $bin, $lotNo, $quantity, $attributes): ItemReservation {
            // Locked for the same reason an issue is: two concurrent
            // reservations of the last unit must not both succeed.
            $balance = $this->ledger->lockBalance($item, $bin, $lotNo);

            $available = bcsub(
                (string) $balance->quantity_on_hand,
                (string) $balance->quantity_reserved,
                self::SCALE,
            );

            if (bccomp($available, $quantity, self::SCALE) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('itemmaster.insufficient_available', [
                        'item' => $item->name,
                        'available' => $this->trim($available),
                    ]),
                ])->status(409);
            }

            $reservation = $this->write($item, $quantity, 'ACTIVE', $attributes, [
                'bin_id' => $bin->id,
                'lot_no' => $lotNo,
            ]);

            $this->encumber($balance, $quantity);

            return $reservation->fresh();
        });
    }

    /**
     * Locks one named roll to a claim — the hard allocation of spec §7.1.
     * The roll's own status carries the encumbrance, since ROLL-tracked items
     * keep no balance row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function allocateRoll(FabricRoll $roll, array $attributes = []): ItemReservation
    {
        if (! $roll->canTransitionTo('RESERVED')) {
            throw ValidationException::withMessages([
                'fabric_roll_id' => __('itemmaster.roll_not_reservable', [
                    'roll' => $roll->roll_no,
                    'status' => $roll->status,
                ]),
            ])->status(409);
        }

        $item = Item::findOrFail($roll->item_id);

        return DB::transaction(function () use ($roll, $item, $attributes): ItemReservation {
            $reservation = $this->write($item, '1', 'ACTIVE', $attributes, [
                'bin_id' => $roll->bin_id,
                'lot_no' => (string) ($roll->lot_no ?? ItemStockBalance::NO_LOT),
                'fabric_roll_id' => $roll->id,
            ]);

            $roll->forceFill(['status' => 'RESERVED'])->save();

            return $reservation->fresh();
        });
    }

    /**
     * Stakes a claim on stock that is not there yet (spec §5).
     *
     * A floor requisition that could only be part-filled raises one of these
     * per short line, alongside the automatic purchase requisition. It holds
     * nothing — there is nothing to hold — but it is what makes the arriving
     * goods receipt land in the right hands instead of being issued to
     * whoever asked next.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function earmark(Item $item, string $quantity, array $attributes = []): ItemReservation
    {
        $this->assertPositive($quantity);

        return $this->write($item, $quantity, 'PENDING_ARRIVAL', $attributes, [
            'bin_id' => null,
            'lot_no' => ItemStockBalance::NO_LOT,
        ])->fresh();
    }

    /**
     * Turns earmarks into real reservations when the stock lands.
     *
     * Called by goods receipt completion, once per posted line. Oldest claim
     * first: whoever waited longest gets the stock, not whoever happens to be
     * standing at the counter. A claim larger than what arrived is filled as
     * far as it goes and the remainder stays pending, so a drip-fed purchase
     * order keeps working.
     *
     * ROLL-tracked items are not filled here — allocating a roll means
     * choosing one, which is a person's decision (§7.1). Their earmark stays
     * pending and the caller notifies the store instead.
     *
     * @return Collection<int, ItemReservation> the reservations this filled
     */
    public function fillPendingEarmarks(Item $item, Bin $bin, string $arrivedQuantity, ?string $lotNo = null): Collection
    {
        if ($item->usesRollLedger() || bccomp($arrivedQuantity, '0', self::SCALE) !== 1) {
            return collect();
        }

        $lotKey = $item->isLotTracked()
            ? trim((string) $lotNo)
            : ItemStockBalance::NO_LOT;

        return DB::transaction(function () use ($item, $bin, $arrivedQuantity, $lotKey): Collection {
            $balance = $this->ledger->lockBalance($item, $bin, $lotKey);

            $free = bcsub(
                (string) $balance->quantity_on_hand,
                (string) $balance->quantity_reserved,
                self::SCALE,
            );

            // Never more than arrived, and never more than is actually free:
            // an earlier earmark may already have taken part of this bin.
            $fillable = bccomp($free, $arrivedQuantity, self::SCALE) < 0 ? $free : $arrivedQuantity;

            if (bccomp($fillable, '0', self::SCALE) !== 1) {
                return collect();
            }

            $earmarks = ItemReservation::where('item_id', $item->id)
                ->where('status', 'PENDING_ARRIVAL')
                ->orderBy('reserved_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $filled = collect();

            foreach ($earmarks as $earmark) {
                if (bccomp($fillable, '0', self::SCALE) !== 1) {
                    break;
                }

                $outstanding = $earmark->outstanding();

                if (bccomp($outstanding, '0', self::SCALE) !== 1) {
                    continue;
                }

                $take = bccomp($outstanding, $fillable, self::SCALE) > 0 ? $fillable : $outstanding;

                // Part-filled: the row becomes a real reservation for what
                // arrived and a fresh earmark carries the rest, so the line
                // that raised it keeps waiting for the remainder.
                if (bccomp($take, $outstanding, self::SCALE) < 0) {
                    $this->write($item, bcsub($outstanding, $take, self::SCALE), 'PENDING_ARRIVAL', [
                        'reservation_type' => $earmark->reservation_type,
                        'reference_type' => $earmark->reference_type,
                        'reference_id' => $earmark->reference_id,
                        'reserved_by' => $earmark->reserved_by,
                        'expires_at' => $earmark->expires_at,
                    ], ['bin_id' => null, 'lot_no' => ItemStockBalance::NO_LOT]);
                }

                $earmark->forceFill([
                    'bin_id' => $bin->id,
                    'lot_no' => $lotKey,
                    'quantity' => $take,
                    'status' => 'ACTIVE',
                ])->save();

                $this->encumber($balance, $take);

                $fillable = bcsub($fillable, $take, self::SCALE);
                $filled->push($earmark->fresh());
            }

            return $filled;
        });
    }

    /**
     * Records that part of a reservation has been issued.
     *
     * Called by the issue action *before* its ledger post: the encumbrance
     * comes off first, then the stock moves, so the two never double-count
     * the same units. The post that follows carries `against_reservation`,
     * which is what tells the ledger to check against what is on hand rather
     * than against what is unreserved.
     */
    public function consume(ItemReservation $reservation, string $quantity): ItemReservation
    {
        $this->assertPositive($quantity);

        if (! $reservation->isHolding()) {
            throw ValidationException::withMessages([
                'reservation_id' => __('itemmaster.reservation_not_holding'),
            ])->status(409);
        }

        $outstanding = $reservation->outstanding();

        if (bccomp($quantity, $outstanding, self::SCALE) > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('itemmaster.issue_exceeds_reservation'),
            ]);
        }

        return DB::transaction(function () use ($reservation, $quantity): ItemReservation {
            if ($reservation->bin_id !== null && ! $reservation->isHardAllocation()) {
                $item = Item::findOrFail($reservation->item_id);
                $bin = Bin::findOrFail($reservation->bin_id);

                $balance = $this->ledger->lockBalance($item, $bin, (string) $reservation->lot_no);
                $this->encumber($balance, '-'.$quantity);
            }

            $issued = bcadd((string) $reservation->quantity_issued, $quantity, self::SCALE);
            $remaining = bcsub((string) $reservation->quantity, bcadd($issued, (string) $reservation->quantity_released, self::SCALE), self::SCALE);

            $reservation->forceFill([
                'quantity_issued' => $issued,
                'status' => bccomp($remaining, '0', self::SCALE) === 1 ? 'PARTIALLY_ISSUED' : 'ISSUED',
            ])->save();

            return $reservation->fresh();
        });
    }

    /**
     * Gives back whatever the reservation still holds.
     *
     * The row is closed, never deleted: "why was this fabric held for a week"
     * is a question somebody asks, and a deleted row cannot answer it. When a
     * store manager takes earmarked stock for something more urgent, the
     * reason they typed is kept on the row (§5).
     */
    public function release(
        ItemReservation $reservation,
        ?string $quantity = null,
        ?string $userId = null,
        string $status = 'RELEASED',
        ?string $reason = null,
    ): ItemReservation {
        if (! $reservation->isOpen()) {
            throw ValidationException::withMessages([
                'reservation_id' => __('itemmaster.reservation_not_holding'),
            ])->status(409);
        }

        $outstanding = $reservation->outstanding();
        $quantity ??= $outstanding;

        if (bccomp($quantity, $outstanding, self::SCALE) > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('itemmaster.release_exceeds_reservation'),
            ]);
        }

        return DB::transaction(function () use ($reservation, $quantity, $userId, $status, $reason): ItemReservation {
            // An earmark holds nothing, so there is nothing to give back.
            if ($reservation->isHolding() && $reservation->bin_id !== null && ! $reservation->isHardAllocation()) {
                $item = Item::findOrFail($reservation->item_id);
                $bin = Bin::findOrFail($reservation->bin_id);

                $balance = $this->ledger->lockBalance($item, $bin, (string) $reservation->lot_no);
                $this->encumber($balance, '-'.$quantity);
            }

            if ($reservation->isHardAllocation()) {
                $roll = FabricRoll::find($reservation->fabric_roll_id);

                if ($roll !== null && $roll->status === 'RESERVED') {
                    $roll->forceFill(['status' => 'AVAILABLE'])->save();
                }
            }

            $released = bcadd((string) $reservation->quantity_released, $quantity, self::SCALE);

            $reservation->forceFill([
                'quantity_released' => $released,
                'status' => $this->statusAfterRelease($reservation, $released, $status),
                'released_by' => $userId,
                'released_at' => CarbonImmutable::now(),
                'override_reason' => $reason ?? $reservation->override_reason,
            ])->save();

            return $reservation->fresh();
        });
    }

    /**
     * Releases everything held for one document — a cancelled or closed
     * requisition should not keep stock invisible to the rest of the factory.
     *
     * @return int the number of reservations released
     */
    public function releaseFor(string $referenceType, string $referenceId, ?string $userId = null, string $status = 'RELEASED'): int
    {
        $reservations = ItemReservation::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereIn('status', ItemReservation::OPEN_STATUSES)
            ->get();

        foreach ($reservations as $reservation) {
            $this->release($reservation, null, $userId, $status);
        }

        return $reservations->count();
    }

    /**
     * Closes claims that outlived their date. Stock held for a job nobody
     * started is stock the rest of the factory cannot use and cannot see the
     * reason for; the spare-parts side has the same column and never swept
     * it, which is how reservations quietly become permanent.
     *
     * @return int the number of reservations expired
     */
    public function expireOverdue(): int
    {
        $overdue = ItemReservation::whereIn('status', ItemReservation::OPEN_STATUSES)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->get();

        foreach ($overdue as $reservation) {
            $this->release($reservation, null, null, 'EXPIRED');
        }

        return $overdue->count();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $placement
     */
    private function write(Item $item, string $quantity, string $status, array $attributes, array $placement): ItemReservation
    {
        return ItemReservation::create([
            'item_id' => $item->id,
            'bin_id' => $placement['bin_id'] ?? null,
            'lot_no' => $placement['lot_no'] ?? ItemStockBalance::NO_LOT,
            'fabric_roll_id' => $placement['fabric_roll_id'] ?? null,
            'reservation_type' => $attributes['reservation_type'] ?? 'MANUAL',
            'reference_type' => $attributes['reference_type'] ?? null,
            'reference_id' => $attributes['reference_id'] ?? null,
            'quantity' => $quantity,
            'status' => $status,
            'reserved_by' => $attributes['reserved_by'] ?? null,
            'reserved_at' => $attributes['reserved_at'] ?? CarbonImmutable::now(),
            'expires_at' => $attributes['expires_at'] ?? null,
            'override_reason' => $attributes['override_reason'] ?? null,
        ]);
    }

    /**
     * Moves the encumbrance on a locked balance. Floored at zero: a negative
     * encumbrance would make available stock look larger than what is
     * physically there.
     */
    private function encumber(ItemStockBalance $balance, string $delta): void
    {
        $reserved = bcadd((string) $balance->quantity_reserved, $delta, self::SCALE);

        if (bccomp($reserved, '0', self::SCALE) < 0) {
            $reserved = '0.0000';
        }

        $balance->forceFill([
            'quantity_reserved' => $reserved,
            'version' => $balance->version + 1,
        ])->save();
    }

    /**
     * A reservation partly issued and then released is ISSUED rather than
     * RELEASED: most of it did reach the floor, and reporting it as released
     * would lose that.
     */
    private function statusAfterRelease(ItemReservation $reservation, string $released, string $requestedStatus): string
    {
        $accounted = bcadd((string) $reservation->quantity_issued, $released, self::SCALE);

        if (bccomp($accounted, (string) $reservation->quantity, self::SCALE) < 0) {
            return $reservation->status;
        }

        return bccomp((string) $reservation->quantity_issued, '0', self::SCALE) > 0
            ? 'ISSUED'
            : $requestedStatus;
    }

    private function assertPositive(string $quantity): void
    {
        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.quantity_must_be_positive'),
            ]);
        }
    }

    private function trim(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
