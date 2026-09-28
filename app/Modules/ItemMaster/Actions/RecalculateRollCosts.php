<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\FabricRoll;
use Illuminate\Support\Facades\DB;

/**
 * Re-pricing a receipt's rolls, and following them wherever they went
 * (Inventory spec §10).
 *
 * A roll is created at a **provisional** cost — the purchase order's rate —
 * because the freight, duty and clearing that make up the real landed cost
 * arrive weeks later and the store cannot wait to put the cloth away (§10.2).
 * When the landed cost is finally posted, this walks the same receipt's
 * rolls and writes the corrected figure.
 *
 * The part that is easy to get wrong is that by then the rolls may not be
 * the rolls any more. One has been cut into four; three have been merged
 * into one. So the correction cascades: a child is re-priced with its
 * parent's new rate, and a merged roll is re-weighted from whatever its
 * sources now cost. A roll that has already been issued is still corrected,
 * and flagged — the cost of goods that left the store last month did change,
 * and a report that quietly restates it without saying so is worse than one
 * that admits it (§10.5).
 *
 * The landed cost posting itself is Phase 5; this is the half the roll
 * ledger owns, and it is callable on its own.
 */
class RecalculateRollCosts
{
    private const SCALE = 4;

    /**
     * @param  array<string, string>  $unitCostByItem  item id => corrected unit cost
     * @return int how many rolls were re-priced, cascade included
     */
    public function forGoodsReceipt(string $goodsReceiptId, array $unitCostByItem): int
    {
        if ($unitCostByItem === []) {
            return 0;
        }

        return DB::transaction(function () use ($goodsReceiptId, $unitCostByItem): int {
            $touched = 0;

            $rolls = FabricRoll::where('goods_receipt_id', $goodsReceiptId)
                ->whereIn('item_id', array_keys($unitCostByItem))
                ->get();

            foreach ($rolls as $roll) {
                $touched += $this->apply($roll, $unitCostByItem[$roll->item_id]);
            }

            return $touched;
        });
    }

    /**
     * Writes one roll's cost and follows it to whatever it became.
     *
     * @return int rolls touched, this one included
     */
    public function apply(FabricRoll $roll, string $unitCost): int
    {
        $unitCost = $this->scale($unitCost);

        if (bccomp($unitCost, (string) $roll->unit_cost, self::SCALE) === 0) {
            // Nothing moved; do not stamp the "adjusted after issue" flag on
            // a roll whose price did not actually change.
            return 0;
        }

        $roll->forceFill([
            'unit_cost' => $unitCost,
            // §10.5: the correction is allowed, and it is declared.
            'cost_adjusted_after_issue' => $roll->cost_adjusted_after_issue
                || in_array($roll->status, ['ISSUED', 'SPLIT', 'MERGED', 'CLOSED'], true),
        ])->save();

        $touched = 1;

        // A split child was priced per unit from its parent, so it takes the
        // parent's new rate unchanged.
        foreach (FabricRoll::where('parent_roll_id', $roll->id)->get() as $child) {
            $touched += $this->apply($child, $unitCost);
        }

        // A merged roll is re-weighted from all of its sources, not from
        // this one alone — the others may not have moved at all.
        if ($roll->merged_into_roll_id !== null) {
            $merged = FabricRoll::find($roll->merged_into_roll_id);

            if ($merged !== null) {
                $touched += $this->apply($merged, $this->weightedAverageOfSources($merged));
            }
        }

        return $touched;
    }

    /**
     * What a merged roll now costs, from what its sources now cost.
     */
    private function weightedAverageOfSources(FabricRoll $merged): string
    {
        $sources = FabricRoll::where('merged_into_roll_id', $merged->id)->get();

        if ($sources->isEmpty()) {
            return (string) $merged->unit_cost;
        }

        $quantity = '0.0000';
        $value = '0.0000';

        foreach ($sources as $source) {
            $sourceQuantity = $this->scale($source->measuredQuantity());
            $quantity = bcadd($quantity, $sourceQuantity, self::SCALE);
            $value = bcadd($value, bcmul($sourceQuantity, (string) $source->unit_cost, self::SCALE), self::SCALE);
        }

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            return (string) $merged->unit_cost;
        }

        return bcdiv($value, $quantity, self::SCALE);
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}
