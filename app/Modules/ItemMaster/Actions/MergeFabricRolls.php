<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\FabricRoll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Joining several rolls into one (Inventory spec §6).
 *
 * The mirror of a split, and the rule that matters is the cost: the new
 * roll's unit cost is the **weighted average** of what went into it, not
 * the average of the numbers. Two hundred metres at 250 and fifty at 300
 * make 260, not 275 — and taking the simple mean would quietly move value
 * every time a store tidied its racks.
 *
 * Only rolls that are genuinely interchangeable may be merged: same item,
 * shade, lot and bin. A merge across shades produces a roll whose shade is
 * a lie, and the lie is only found at the cutting table.
 */
class MergeFabricRolls
{
    private const SCALE = 2;

    /**
     * @param  list<string>  $rollIds
     * @param  array<string, mixed>  $data  roll_no, notes?
     */
    public function handle(array $rollIds, array $data, ?string $userId = null): FabricRoll
    {
        $ids = array_values(array_unique($rollIds));

        if (count($ids) < 2) {
            throw ValidationException::withMessages([
                'roll_ids' => __('inspection.merge_needs_two'),
            ]);
        }

        /** @var Collection<int, FabricRoll> $rolls */
        $rolls = FabricRoll::whereIn('id', $ids)->get();

        if ($rolls->count() !== count($ids)) {
            throw ValidationException::withMessages(['roll_ids' => __('requisition.unknown_roll')]);
        }

        $blocked = $rolls->firstWhere(fn (FabricRoll $roll): bool => $roll->status !== 'AVAILABLE');

        if ($blocked !== null) {
            // A held or issued roll is spoken for; merging it would move
            // somebody else's cloth into a roll they never asked for.
            throw ValidationException::withMessages([
                'roll_ids' => __('inspection.roll_not_mergeable', ['roll' => $blocked->roll_no, 'status' => $blocked->status]),
            ])->status(409);
        }

        $this->assertInterchangeable($rolls);

        $rollNo = trim((string) ($data['roll_no'] ?? ''));

        if ($rollNo === '') {
            throw ValidationException::withMessages(['roll_no' => __('validation.required', ['attribute' => 'roll_no'])]);
        }

        if (FabricRoll::where('roll_no', $rollNo)->exists()) {
            throw ValidationException::withMessages([
                'roll_no' => __('inspection.duplicate_roll_no', ['roll_no' => $rollNo]),
            ]);
        }

        $first = $rolls->first();
        $measure = $first->measure();

        $quantity = '0.00';
        $value = '0.0000';

        foreach ($rolls as $roll) {
            $rollQuantity = $this->scale($roll->{$measure});
            $quantity = bcadd($quantity, $rollQuantity, self::SCALE);
            $value = bcadd($value, bcmul($rollQuantity, (string) $roll->unit_cost, 4), 4);
        }

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['roll_ids' => __('inventory.quantity_must_be_positive')]);
        }

        $unitCost = bcdiv($value, $quantity, 4);

        return DB::transaction(function () use ($rolls, $first, $measure, $quantity, $unitCost, $rollNo, $data): FabricRoll {
            $merged = FabricRoll::create([
                'item_id' => $first->item_id,
                'goods_receipt_id' => $first->goods_receipt_id,
                'goods_receipt_line_id' => $first->goods_receipt_line_id,
                'bin_id' => $first->bin_id,
                'roll_no' => $rollNo,
                'lot_no' => $first->lot_no,
                'shade' => $first->shade,
                'shade_band' => $first->shade_band,
                'nominal_width' => $first->nominal_width,
                'actual_width' => $first->actual_width,
                $measure => $quantity,
                'gross_length' => $this->sum($rolls, 'gross_length'),
                'gross_weight' => $this->sum($rolls, 'gross_weight'),
                'status' => 'AVAILABLE',
                'unit_cost' => $unitCost,
                'currency' => $first->currency,
                // Sticky: if any parent's cost was corrected after it was
                // issued, the merged roll carries the same warning.
                'cost_adjusted_after_issue' => $rolls->contains(fn (FabricRoll $r): bool => (bool) $r->cost_adjusted_after_issue),
                'inspection_grade' => $first->inspection_grade,
                'shade_match' => $first->shade_match,
                'inspection_notes' => $data['notes'] ?? null,
                // The oldest receiving date wins, so FIFO still reaches the
                // cloth that has been in the store longest.
                'received_date' => $rolls->min('received_date'),
            ]);

            foreach ($rolls as $roll) {
                $roll->forceFill([
                    'status' => 'MERGED',
                    'merged_into_roll_id' => $merged->id,
                ])->save();
            }

            return $merged->fresh();
        });
    }

    /**
     * @param  Collection<int, FabricRoll>  $rolls
     */
    private function assertInterchangeable(Collection $rolls): void
    {
        foreach (['item_id' => 'item', 'bin_id' => 'bin', 'shade' => 'shade', 'lot_no' => 'lot'] as $column => $label) {
            if ($rolls->pluck($column)->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'roll_ids' => __('inspection.merge_mismatch', ['field' => $label]),
                ])->status(409);
            }
        }

        // One roll measured by length and another by weight cannot be added
        // together at all.
        if ($rolls->map(fn (FabricRoll $roll): string => $roll->measure())->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'roll_ids' => __('inspection.merge_mismatch', ['field' => 'measure']),
            ])->status(409);
        }
    }

    /**
     * @param  Collection<int, FabricRoll>  $rolls
     */
    private function sum(Collection $rolls, string $column): ?string
    {
        if ($rolls->every(fn (FabricRoll $roll): bool => $roll->{$column} === null)) {
            return null;
        }

        return $rolls->reduce(
            fn (string $sum, FabricRoll $roll): string => bcadd($sum, $this->scale($roll->{$column}), self::SCALE),
            '0.00',
        );
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}
