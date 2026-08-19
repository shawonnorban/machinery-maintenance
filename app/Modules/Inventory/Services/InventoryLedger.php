<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Settings\Services\SettingsResolver;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The append-only stock ledger (ERD Section 13, SRS 19).
 *
 * Every movement is a row, and every row carries the balance and weighted
 * average cost that resulted from it. Nothing is ever updated or deleted; a
 * correction is a REVERSAL row pointing at what it undoes. That makes the
 * ledger self-auditing — replaying it must reproduce the current balance
 * exactly — and it means a storekeeper can be shown why the number is what it
 * is rather than being asked to trust it.
 *
 * Two properties this class exists to guarantee:
 *
 * 1. The ledger row and the balance update happen in one database transaction,
 *    under a row lock on the balance. Without the lock, two concurrent issues
 *    of the last item both read "1 available" and both succeed.
 *
 * 2. Weighted average cost moves only on inbound movements. An issue consumes
 *    at the current average and leaves it alone; letting an issue change the
 *    average would make the cost of a repair depend on how much was in the bin
 *    when it happened.
 */
class InventoryLedger
{
    /** Scale used for money and quantity arithmetic, matching DECIMAL(18,4). */
    private const SCALE = 4;

    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $settings,
    ) {}

    /**
     * Posts one movement.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function post(
        SparePart $part,
        Bin $bin,
        string $type,
        string $quantity,
        ?string $unitCost = null,
        array $attributes = [],
    ): InventoryTransaction {
        $this->assertKnownType($type);

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            // Zero moves nothing and a negative would invert the type's meaning.
            throw ValidationException::withMessages([
                'quantity' => __('inventory.quantity_must_be_positive'),
            ]);
        }

        $idempotencyKey = $attributes['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = InventoryTransaction::where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                // A retried request must not post the movement twice. Returning
                // the original is the whole point of the key (ADR-056).
                return $existing;
            }
        }

        return DB::transaction(function () use (
            $part, $bin, $type, $quantity, $unitCost, $attributes
        ): InventoryTransaction {
            $balance = $this->lockBalance($part, $bin);

            $onHand = $this->scale($balance->quantity_on_hand);
            $reserved = $this->scale($balance->quantity_reserved);
            $wac = $this->scale($balance->weighted_average_cost);

            $inbound = in_array($type, InventoryTransaction::INBOUND, true);

            // An outbound movement draws at the average in force at the moment
            // it happens, not at whatever the part costs later. A caller may
            // still name a price — a transfer receipt and a reversal both carry
            // the cost the stock left at.
            $effectiveUnitCost = $this->scale($unitCost ?? $wac);

            if ($inbound) {
                $newOnHand = bcadd($onHand, $quantity, self::SCALE);

                // Only inbound movements move the average. If an issue changed
                // it, the cost of a repair would depend on how much happened to
                // be in the bin that day.
                $newWac = in_array($type, InventoryTransaction::AFFECTS_WAC, true)
                    ? $this->weightedAverage($onHand, $wac, $quantity, $effectiveUnitCost)
                    : $wac;
            } else {
                $this->assertSufficientStock($part, $bin, $onHand, $reserved, $quantity, $type, $attributes);

                $newOnHand = bcsub($onHand, $quantity, self::SCALE);
                $newWac = $wac;
            }

            $totalCost = bcmul($quantity, $effectiveUnitCost, self::SCALE);
            $exchangeRate = $this->scale($attributes['exchange_rate'] ?? '1');

            $transaction = InventoryTransaction::create([
                'spare_part_id' => $part->id,
                'bin_id' => $bin->id,
                'transaction_type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $effectiveUnitCost,
                'total_cost' => $totalCost,
                'currency' => $attributes['currency'] ?? $balance->currency ?? 'BDT',
                'exchange_rate' => $exchangeRate,
                'base_total_cost' => bcmul($totalCost, $exchangeRate, self::SCALE),
                // Written here, not derived later: this is what makes the
                // ledger auditable against itself.
                'balance_after' => $newOnHand,
                'wac_after' => $newWac,
                'reference_type' => $attributes['reference_type'] ?? null,
                'reference_id' => $attributes['reference_id'] ?? null,
                'reservation_id' => $attributes['reservation_id'] ?? null,
                'inventory_transfer_id' => $attributes['inventory_transfer_id'] ?? null,
                'work_order_id' => $attributes['work_order_id'] ?? null,
                'reverses_transaction_id' => $attributes['reverses_transaction_id'] ?? null,
                'performed_by' => $attributes['performed_by'] ?? null,
                'transaction_at' => $attributes['transaction_at'] ?? CarbonImmutable::now(),
                'notes' => $attributes['notes'] ?? null,
                'idempotency_key' => $attributes['idempotency_key'] ?? null,
            ]);

            $balance->forceFill([
                'quantity_on_hand' => $newOnHand,
                'weighted_average_cost' => $newWac,
                'version' => $balance->version + 1,
            ])->save();

            return $transaction;
        });
    }

    /**
     * Undoes a posted movement with an opposing row.
     *
     * The original stays exactly as it was. Deleting it would leave the balance
     * right and the history wrong, which is the worse of the two failures: a
     * wrong number gets found, a missing row does not.
     */
    public function reverse(InventoryTransaction $original, ?string $userId = null, ?string $reason = null): InventoryTransaction
    {
        if (InventoryTransaction::where('reverses_transaction_id', $original->id)->exists()) {
            throw ValidationException::withMessages([
                'transaction_id' => __('inventory.already_reversed'),
            ])->status(409);
        }

        $part = SparePart::findOrFail($original->spare_part_id);
        $bin = Bin::findOrFail($original->bin_id);

        $opposite = $this->oppositeType($original->transaction_type);

        return $this->post($part, $bin, $opposite, $original->quantity, $original->unit_cost, [
            'reverses_transaction_id' => $original->id,
            'work_order_id' => $original->work_order_id,
            'reference_type' => $original->reference_type,
            'reference_id' => $original->reference_id,
            'performed_by' => $userId,
            'notes' => $reason ?? __('inventory.reversal_of', ['id' => $original->id]),
            'currency' => $original->currency,
        ]);
    }

    /**
     * Available means unencumbered: on hand minus what is already promised to
     * someone else's work order.
     */
    public function available(SparePart $part, Bin $bin): string
    {
        $balance = InventoryBalance::where('spare_part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->first();

        if ($balance === null) {
            return '0.0000';
        }

        return bcsub(
            $this->scale($balance->quantity_on_hand),
            $this->scale($balance->quantity_reserved),
            self::SCALE,
        );
    }

    /**
     * Replays every transaction for a part and bin and compares the result to
     * the stored balance.
     *
     * This is the promise the ledger design makes, so it is checkable rather
     * than assumed. A drift here means a movement was written outside this
     * class, and it should be found on the day it happens.
     *
     * @return array{balance: string, replayed: string, matches: bool, transactions: int}
     */
    public function verify(SparePart $part, Bin $bin): array
    {
        $transactions = InventoryTransaction::where('spare_part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get();

        $replayed = '0.0000';

        foreach ($transactions as $transaction) {
            $replayed = in_array($transaction->transaction_type, InventoryTransaction::INBOUND, true)
                ? bcadd($replayed, $this->scale($transaction->quantity), self::SCALE)
                : bcsub($replayed, $this->scale($transaction->quantity), self::SCALE);
        }

        $balance = InventoryBalance::where('spare_part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->first();

        $stored = $this->scale($balance?->quantity_on_hand ?? '0');

        return [
            'balance' => $stored,
            'replayed' => $replayed,
            'matches' => bccomp($stored, $replayed, self::SCALE) === 0,
            'transactions' => $transactions->count(),
        ];
    }

    /**
     * The balance row, created if absent, locked for update.
     *
     * lockForUpdate is what stops two concurrent issues of the last item from
     * both reading "1 available" and both succeeding.
     */
    public function lockBalance(SparePart $part, Bin $bin): InventoryBalance
    {
        $balance = InventoryBalance::where('spare_part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->lockForUpdate()
            ->first();

        if ($balance !== null) {
            return $balance;
        }

        InventoryBalance::create([
            'company_id' => $this->context->companyId(),
            'spare_part_id' => $part->id,
            'bin_id' => $bin->id,
            'quantity_on_hand' => '0',
            'quantity_reserved' => '0',
            'weighted_average_cost' => '0',
            'currency' => $part->currency ?? 'BDT',
        ]);

        return InventoryBalance::where('spare_part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * ((existing quantity x existing cost) + (incoming quantity x incoming
     * cost)) / total quantity.
     *
     * Computed with bcmath rather than floats: a valuation report that is a
     * few paisa out per row is a valuation report somebody has to reconcile by
     * hand (ADR-063).
     */
    private function weightedAverage(
        string $existingQuantity,
        string $existingCost,
        string $incomingQuantity,
        string $incomingCost,
    ): string {
        $totalQuantity = bcadd($existingQuantity, $incomingQuantity, self::SCALE);

        if (bccomp($totalQuantity, '0', self::SCALE) !== 1) {
            // Nothing to average against. The incoming price stands.
            return $incomingCost;
        }

        // A bin that went negative has no meaningful existing value to blend,
        // so the incoming price is taken as the new average rather than
        // producing an average pulled below zero by the deficit.
        if (bccomp($existingQuantity, '0', self::SCALE) !== 1) {
            return $incomingCost;
        }

        $existingValue = bcmul($existingQuantity, $existingCost, self::SCALE);
        $incomingValue = bcmul($incomingQuantity, $incomingCost, self::SCALE);

        return bcdiv(
            bcadd($existingValue, $incomingValue, self::SCALE),
            $totalQuantity,
            self::SCALE,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertSufficientStock(
        SparePart $part,
        Bin $bin,
        string $onHand,
        string $reserved,
        string $quantity,
        string $type,
        array $attributes,
    ): void {
        // A transfer or an adjustment out moves physical stock and is checked
        // against what is on hand. An issue is additionally checked against
        // what is unreserved, because reserved stock is already promised.
        $comparable = $type === 'ISSUE'
            ? bcsub($onHand, $reserved, self::SCALE)
            : $onHand;

        // Stock being issued against its own reservation is not double-counted:
        // the reservation is released as part of the same operation.
        if ($type === 'ISSUE' && ($attributes['against_reservation'] ?? false)) {
            $comparable = $onHand;
        }

        if (bccomp($comparable, $quantity, self::SCALE) >= 0) {
            return;
        }

        if ($this->allowsNegativeStock($bin)) {
            // Some factories run this way deliberately, reconciling later. It
            // is a setting rather than a silent default because a store that
            // can hand out what it does not have will eventually be asked how.
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => __('inventory.insufficient_stock', [
                'part' => $part->part_number,
                'available' => rtrim(rtrim($comparable, '0'), '.'),
                'requested' => rtrim(rtrim($quantity, '0'), '.'),
            ]),
        ])->status(409);
    }

    private function allowsNegativeStock(Bin $bin): bool
    {
        $factoryId = $bin->store?->warehouse?->factory_id;

        return (bool) $this->settings->get('inventory.allow_negative_stock', factoryId: $factoryId);
    }

    private function assertKnownType(string $type): void
    {
        if (! in_array($type, [...InventoryTransaction::INBOUND, ...InventoryTransaction::OUTBOUND], true)) {
            throw ValidationException::withMessages([
                'transaction_type' => __('inventory.unknown_transaction_type'),
            ]);
        }
    }

    private function oppositeType(string $type): string
    {
        return match ($type) {
            'RECEIPT', 'OPENING_BALANCE', 'ADJUSTMENT_IN' => 'ADJUSTMENT_OUT',
            'RETURN' => 'ISSUE',
            'TRANSFER_IN' => 'TRANSFER_OUT',
            'ISSUE' => 'RETURN',
            'CONSUME', 'SCRAP', 'ADJUSTMENT_OUT' => 'ADJUSTMENT_IN',
            'TRANSFER_OUT' => 'TRANSFER_IN',
            default => 'ADJUSTMENT_IN',
        };
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}
