<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock arriving into a bin (SRS 19).
 *
 * The received price is what sets the weighted average, so it is required
 * rather than defaulted. Receiving at zero would drag the average down and make
 * every subsequent issue look free, and a repair that appears to cost nothing
 * is worse than one with no cost at all — somebody will quote it.
 */
class ReceiveStock
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    public function handle(
        SparePart $part,
        Bin $bin,
        string $quantity,
        string $unitCost,
        ?string $userId = null,
        ?string $notes = null,
        string $type = 'RECEIPT',
        ?string $idempotencyKey = null,
    ): InventoryTransaction {
        if (! in_array($type, ['RECEIPT', 'OPENING_BALANCE', 'ADJUSTMENT_IN'], true)) {
            throw ValidationException::withMessages([
                'transaction_type' => __('inventory.unknown_transaction_type'),
            ]);
        }

        if (bccomp($unitCost, '0', 4) < 0) {
            throw ValidationException::withMessages([
                'unit_cost' => __('inventory.cost_must_not_be_negative'),
            ]);
        }

        return DB::transaction(function () use ($part, $bin, $quantity, $unitCost, $userId, $notes, $type, $idempotencyKey): InventoryTransaction {
            $transaction = $this->ledger->post($part, $bin, $type, $quantity, $unitCost, [
                'performed_by' => $userId,
                'notes' => $notes,
                'currency' => $part->currency ?? 'BDT',
                'idempotency_key' => $idempotencyKey,
                'transaction_at' => CarbonImmutable::now(),
            ]);

            // The last purchase price, kept for reference only. Costing always
            // uses the ledger's weighted average, never this.
            if ($type === 'RECEIPT') {
                $part->forceFill(['unit_cost' => $unitCost])->save();
            }

            return $transaction;
        });
    }

    /**
     * Stock leaving for a reason that is not a repair: damaged, expired, or
     * counted short.
     *
     * A reason is required. An adjustment with no reason is indistinguishable
     * from theft, and a store whose figures move without explanation is one
     * nobody defends at an audit.
     */
    public function adjustOut(
        SparePart $part,
        Bin $bin,
        string $quantity,
        string $reason,
        ?string $userId = null,
        string $type = 'ADJUSTMENT_OUT',
    ): InventoryTransaction {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'notes' => __('inventory.adjustment_needs_reason'),
            ]);
        }

        if (! in_array($type, ['ADJUSTMENT_OUT', 'SCRAP'], true)) {
            throw ValidationException::withMessages([
                'transaction_type' => __('inventory.unknown_transaction_type'),
            ]);
        }

        return $this->ledger->post($part, $bin, $type, $quantity, null, [
            'performed_by' => $userId,
            'notes' => $reason,
            'transaction_at' => CarbonImmutable::now(),
        ]);
    }
}
