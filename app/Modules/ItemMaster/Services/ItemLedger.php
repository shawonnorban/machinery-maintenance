<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Services;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Notification\Models\Notification;
use App\Modules\Settings\Services\SettingsResolver;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The item library's append-only stock ledger (Inventory spec §16).
 *
 * A deliberate port of InventoryLedger rather than a subclass of it: the two
 * catalogues have different keys (item + lot vs spare part), and the rules
 * they share — one transaction per movement, a row lock on the balance,
 * weighted average cost moved only by inbound rows, corrections as opposing
 * rows — are short enough to hold in one file each. The spare-parts ledger
 * stays exactly as it was.
 *
 * Lots: a LOT-tracked item keeps one balance row per (bin, lot) and must be
 * received with a lot number. A QUANTITY-tracked item pools into a single
 * row per bin under the empty lot key, whatever the caller passes. ROLL
 * (fabric) and SERIAL items are refused; their per-unit ledgers come with
 * Phase 2.
 */
class ItemLedger
{
    /** Scale used for money and quantity arithmetic, matching DECIMAL(18,4). */
    private const SCALE = 4;

    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $settings,
        private readonly ItemNotifier $notifier,
    ) {}

    /**
     * Posts one movement.
     *
     * @param  array<string, mixed>  $attributes  lot_no, reference_type,
     *         reference_id, performed_by, notes, currency, exchange_rate,
     *         transaction_at, idempotency_key, reverses_transaction_id
     */
    public function post(
        Item $item,
        Bin $bin,
        string $type,
        string $quantity,
        ?string $unitCost = null,
        array $attributes = [],
    ): ItemStockTransaction {
        $this->assertKnownType($type);
        $this->assertLedgerable($item);

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.quantity_must_be_positive'),
            ]);
        }

        $lotNo = $this->lotKeyFor($item, $attributes['lot_no'] ?? null, required: true);

        $idempotencyKey = $attributes['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = ItemStockTransaction::where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                // A retried request must not post the movement twice.
                return $existing;
            }
        }

        $transaction = DB::transaction(function () use (
            $item, $bin, $lotNo, $type, $quantity, $unitCost, $attributes
        ): ItemStockTransaction {
            $balance = $this->lockBalance($item, $bin, $lotNo);

            $onHand = $this->scale($balance->quantity_on_hand);
            $reserved = $this->scale($balance->quantity_reserved);
            $wac = $this->scale($balance->weighted_average_cost);

            $inbound = in_array($type, ItemStockTransaction::INBOUND, true);

            // An outbound movement draws at the average in force at the
            // moment it happens. A caller may still name a price — a reversal
            // carries the cost the stock arrived at.
            $effectiveUnitCost = $this->scale($unitCost ?? $wac);

            if ($inbound) {
                $newOnHand = bcadd($onHand, $quantity, self::SCALE);
                $newWac = in_array($type, ItemStockTransaction::AFFECTS_WAC, true)
                    ? $this->weightedAverage($onHand, $wac, $quantity, $effectiveUnitCost)
                    : $wac;
            } else {
                $this->assertSufficientStock($item, $bin, $onHand, $reserved, $quantity, $type, $attributes);

                $newOnHand = bcsub($onHand, $quantity, self::SCALE);
                $newWac = $wac;
            }

            $totalCost = bcmul($quantity, $effectiveUnitCost, self::SCALE);
            $exchangeRate = $this->scale($attributes['exchange_rate'] ?? '1');

            $transaction = ItemStockTransaction::create([
                'item_id' => $item->id,
                'bin_id' => $bin->id,
                'lot_no' => $lotNo,
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

        // After the movement is committed. A notification that fails must
        // never roll back the stock movement it was announcing.
        $this->notifyIfLow($item, $bin, $type);

        return $transaction;
    }

    /**
     * Adds value to stock without moving any of it (Procurement spec §11).
     *
     * Landed cost — freight, duty, C&F, insurance — arrives weeks after the
     * goods do, and by then the shelf is right but the price is not. This
     * writes the correction as its own row carrying a **zero quantity**, so
     * replaying the ledger still reproduces exactly what is on the shelf,
     * and moves the weighted average to what the stock actually cost.
     *
     * When the stock has already been issued there is nothing left to carry
     * the value: the average is left alone and the row is still written, so
     * "why is this month's cost different from last month's" has an answer
     * (§10.5's "cost adjusted after issue", in the quantity ledger's terms).
     *
     * @param  array<string, mixed>  $attributes  lot_no, reference_type, reference_id, performed_by, notes, currency
     */
    public function revalue(Item $item, Bin $bin, string $addedValue, array $attributes = []): ItemStockTransaction
    {
        $this->assertLedgerable($item);

        $lotNo = $this->lotKeyFor($item, $attributes['lot_no'] ?? null);

        return DB::transaction(function () use ($item, $bin, $lotNo, $addedValue, $attributes): ItemStockTransaction {
            $balance = $this->lockBalance($item, $bin, $lotNo);

            $onHand = $this->scale($balance->quantity_on_hand);
            $wac = $this->scale($balance->weighted_average_cost);
            $added = $this->scale($addedValue);

            $carried = bccomp($onHand, '0', self::SCALE) === 1;

            // Spread over what is still there. Nothing there means the cost
            // left with the goods and cannot be recovered onto the balance.
            $newWac = $carried
                ? bcadd($wac, bcdiv($added, $onHand, self::SCALE), self::SCALE)
                : $wac;

            $transaction = ItemStockTransaction::create([
                'item_id' => $item->id,
                'bin_id' => $bin->id,
                'lot_no' => $lotNo,
                'transaction_type' => ItemStockTransaction::REVALUATION,
                // Zero, and deliberately so — see the docblock.
                'quantity' => '0',
                'unit_cost' => $carried ? bcdiv($added, $onHand, self::SCALE) : '0',
                'total_cost' => $added,
                'currency' => $attributes['currency'] ?? $balance->currency ?? 'BDT',
                'exchange_rate' => '1',
                'base_total_cost' => $added,
                'balance_after' => $onHand,
                'wac_after' => $newWac,
                'reference_type' => $attributes['reference_type'] ?? null,
                'reference_id' => $attributes['reference_id'] ?? null,
                'performed_by' => $attributes['performed_by'] ?? null,
                'transaction_at' => CarbonImmutable::now(),
                'notes' => $attributes['notes'] ?? null,
                'idempotency_key' => $attributes['idempotency_key'] ?? null,
            ]);

            if ($carried) {
                $balance->forceFill([
                    'weighted_average_cost' => $newWac,
                    'version' => $balance->version + 1,
                ])->save();
            }

            return $transaction;
        });
    }

    /**
     * Undoes a posted movement with an opposing row. The original stays
     * exactly as it was: a wrong number gets found, a missing row does not.
     */
    public function reverse(ItemStockTransaction $original, ?string $userId = null, ?string $reason = null): ItemStockTransaction
    {
        if (ItemStockTransaction::where('reverses_transaction_id', $original->id)->exists()) {
            throw ValidationException::withMessages([
                'transaction_id' => __('inventory.already_reversed'),
            ])->status(409);
        }

        $item = Item::findOrFail($original->item_id);
        $bin = Bin::findOrFail($original->bin_id);

        return $this->post($item, $bin, $this->oppositeType($original->transaction_type), $original->quantity, $original->unit_cost, [
            'lot_no' => $original->lot_no,
            'reverses_transaction_id' => $original->id,
            'reference_type' => $original->reference_type,
            'reference_id' => $original->reference_id,
            'performed_by' => $userId,
            'notes' => $reason ?? __('inventory.reversal_of', ['id' => $original->id]),
            'currency' => $original->currency,
        ]);
    }

    /** Available means unencumbered: on hand minus what is already promised. */
    public function available(Item $item, Bin $bin, ?string $lotNo = null): string
    {
        $balance = $this->balanceQuery($item, $bin, $this->lotKeyFor($item, $lotNo))->first();

        if ($balance === null) {
            return '0.0000';
        }

        return bcsub($this->scale($balance->quantity_on_hand), $this->scale($balance->quantity_reserved), self::SCALE);
    }

    /**
     * Replays every transaction for an item, bin and lot and compares the
     * result to the stored balance. A drift means a movement was written
     * outside this class.
     *
     * @return array{balance: string, replayed: string, matches: bool, transactions: int}
     */
    public function verify(Item $item, Bin $bin, ?string $lotNo = null): array
    {
        $lotKey = $this->lotKeyFor($item, $lotNo);

        $transactions = ItemStockTransaction::where('item_id', $item->id)
            ->where('bin_id', $bin->id)
            ->where('lot_no', $lotKey)
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get();

        $replayed = '0.0000';

        foreach ($transactions as $transaction) {
            $replayed = in_array($transaction->transaction_type, ItemStockTransaction::INBOUND, true)
                ? bcadd($replayed, $this->scale($transaction->quantity), self::SCALE)
                : bcsub($replayed, $this->scale($transaction->quantity), self::SCALE);
        }

        $stored = $this->scale($this->balanceQuery($item, $bin, $lotKey)->first()?->quantity_on_hand ?? '0');

        return [
            'balance' => $stored,
            'replayed' => $replayed,
            'matches' => bccomp($stored, $replayed, self::SCALE) === 0,
            'transactions' => $transactions->count(),
        ];
    }

    /**
     * The balance row, created if absent, locked for update. The lock is what
     * stops two concurrent issues of the last unit from both succeeding.
     */
    public function lockBalance(Item $item, Bin $bin, string $lotNo = ItemStockBalance::NO_LOT): ItemStockBalance
    {
        $balance = $this->balanceQuery($item, $bin, $lotNo)->lockForUpdate()->first();

        if ($balance !== null) {
            return $balance;
        }

        ItemStockBalance::create([
            'company_id' => $this->context->companyId(),
            'item_id' => $item->id,
            'bin_id' => $bin->id,
            'lot_no' => $lotNo,
            'quantity_on_hand' => '0',
            'quantity_reserved' => '0',
            'weighted_average_cost' => '0',
            'currency' => 'BDT',
        ]);

        return $this->balanceQuery($item, $bin, $lotNo)->lockForUpdate()->firstOrFail();
    }

    /**
     * The balance-row key for a lot. A LOT-tracked item must name one on
     * every movement; a pooled item ignores whatever was passed so a stray
     * lot number cannot split its balance in two.
     */
    private function lotKeyFor(Item $item, ?string $lotNo, bool $required = false): string
    {
        if (! $item->isLotTracked()) {
            return ItemStockBalance::NO_LOT;
        }

        $lotNo = trim((string) $lotNo);

        if ($lotNo === '' && $required) {
            throw ValidationException::withMessages([
                'lot_no' => __('itemmaster.lot_required', ['item' => $item->item_code]),
            ]);
        }

        return $lotNo;
    }

    private function assertLedgerable(Item $item): void
    {
        if ($item->usesRollLedger() || $item->tracking_mode === 'SERIAL') {
            throw ValidationException::withMessages([
                'item_id' => __('itemmaster.roll_ledger_not_available', ['item' => $item->item_code]),
            ]);
        }
    }

    private function balanceQuery(Item $item, Bin $bin, string $lotNo)
    {
        return ItemStockBalance::where('item_id', $item->id)
            ->where('bin_id', $bin->id)
            ->where('lot_no', $lotNo);
    }

    /**
     * Tells the store when an outbound movement takes an item to or below
     * its reorder level — on the crossing, not on every issue afterwards.
     */
    private function notifyIfLow(Item $item, Bin $bin, string $type): void
    {
        if (! in_array($type, ItemStockTransaction::OUTBOUND, true)) {
            return;
        }

        $item = $item->fresh();

        if (! $item->isBelowReorderLevel()) {
            return;
        }

        $alreadyWarned = Notification::query()
            ->where('event_type', 'LOW_STOCK')
            ->where('entity_type', 'item')
            ->where('entity_id', $item->id)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadyWarned) {
            return;
        }

        $this->notifier->lowStock($item, $item->totalOnHand(), $this->factoryIdOf($bin));
    }

    private function weightedAverage(string $existingQuantity, string $existingCost, string $incomingQuantity, string $incomingCost): string
    {
        $totalQuantity = bcadd($existingQuantity, $incomingQuantity, self::SCALE);

        // Nothing to average against, or a bin that went negative: the
        // incoming price stands rather than an average pulled below zero.
        if (bccomp($totalQuantity, '0', self::SCALE) !== 1 || bccomp($existingQuantity, '0', self::SCALE) !== 1) {
            return $incomingCost;
        }

        return bcdiv(
            bcadd(bcmul($existingQuantity, $existingCost, self::SCALE), bcmul($incomingQuantity, $incomingCost, self::SCALE), self::SCALE),
            $totalQuantity,
            self::SCALE,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertSufficientStock(Item $item, Bin $bin, string $onHand, string $reserved, string $quantity, string $type, array $attributes): void
    {
        // An issue is checked against unreserved stock; other outbound types
        // move physical stock and are checked against what is on hand.
        $comparable = $type === 'ISSUE' && ! ($attributes['against_reservation'] ?? false)
            ? bcsub($onHand, $reserved, self::SCALE)
            : $onHand;

        if (bccomp($comparable, $quantity, self::SCALE) >= 0) {
            return;
        }

        // Some factories run this way deliberately, reconciling later; the
        // same switch the spare-parts store uses.
        if ((bool) $this->settings->get('inventory.allow_negative_stock', factoryId: $this->factoryIdOf($bin))) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => __('itemmaster.insufficient_stock', [
                'item' => $item->item_code,
                'available' => rtrim(rtrim($comparable, '0'), '.'),
                'requested' => rtrim(rtrim($quantity, '0'), '.'),
            ]),
        ])->status(409);
    }

    private function factoryIdOf(Bin $bin): ?string
    {
        return $bin->store?->warehouse?->factory_id;
    }

    private function assertKnownType(string $type): void
    {
        if (! in_array($type, [...ItemStockTransaction::INBOUND, ...ItemStockTransaction::OUTBOUND], true)) {
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
