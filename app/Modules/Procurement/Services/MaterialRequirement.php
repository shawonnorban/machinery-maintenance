<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Procurement\Models\FabricBookingLine;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;

/**
 * What still has to be bought (Procurement spec §4).
 *
 *     Net Requirement = BOM required
 *                     − stock available
 *                     − already booked
 *                     − already ordered
 *
 * The spec writes the wastage allowance as a fourth term added back on.
 * Here it is already inside the BOM requirement (`StyleBomLine::requiredFor`
 * adds the planned percentage), which comes to the same number and keeps
 * the allowance next to the consumption it belongs to rather than floating
 * as a separate line nobody can trace.
 *
 * Two rules the spec is firm about, and both are easy to get wrong:
 *
 *  - **Stock available excludes what is reserved.** Fabric hard-allocated
 *    to another order is physically on the shelf and is not available; a
 *    requirement that counted it would under-buy and the shortage would only
 *    surface on the cutting table.
 *  - **The calculation is real-time.** Nothing here is stored. A requirement
 *    snapshot goes stale the moment a goods receipt lands, and a stale
 *    number that looks authoritative is worse than no number.
 */
class MaterialRequirement
{
    private const SCALE = 4;

    /**
     * The requirement for one cutting programme.
     *
     * @return list<array<string, mixed>>
     */
    public function forProgram(CuttingProgram $program): array
    {
        $style = Style::findOrFail($program->style_id);

        return $this->calculate($style, (string) $program->program_qty, (string) $program->factory_id);
    }

    /**
     * The requirement for a whole style — what the order needs in total,
     * before it is broken into cutting programmes.
     *
     * @return list<array<string, mixed>>
     */
    public function forStyle(Style $style, string $factoryId): array
    {
        return $this->calculate($style, (string) $style->quantity, $factoryId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calculate(Style $style, string $quantity, string $factoryId): array
    {
        $bom = StyleBomLine::where('style_id', $style->id)->with('uom:id,code,name')->get();

        if ($bom->isEmpty()) {
            return [];
        }

        $itemIds = $bom->pluck('item_id')->unique()->all();

        $available = $this->availableByItem($itemIds, $factoryId);
        $booked = $this->bookedByItem($itemIds, $style->id);
        $ordered = $this->orderedByItem($itemIds);

        return $bom->map(function (StyleBomLine $line) use ($quantity, $available, $booked, $ordered): array {
            $required = $line->requiredFor($quantity);

            $covered = bcadd(
                bcadd($available[$line->item_id] ?? '0.0000', $booked[$line->item_id] ?? '0.0000', self::SCALE),
                $ordered[$line->item_id] ?? '0.0000',
                self::SCALE,
            );

            $net = bcsub($required, $covered, self::SCALE);

            return [
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
                'uom_id' => $line->uom_id,
                'consumption_per_unit' => (string) $line->consumption_per_unit,
                'wastage_percent' => (string) $line->wastage_percent,
                'required_qty' => $required,
                'stock_available' => $available[$line->item_id] ?? '0.0000',
                'already_booked' => $booked[$line->item_id] ?? '0.0000',
                'already_ordered' => $ordered[$line->item_id] ?? '0.0000',
                // Floored: buying a negative quantity is not a thing, and a
                // surplus on one item does not fund a shortage on another.
                'net_requirement' => bccomp($net, '0', self::SCALE) === 1 ? $net : '0.0000',
            ];
        })->all();
    }

    /**
     * Free stock in this factory: on hand less what is already promised.
     *
     * @param  list<string>  $itemIds
     * @return array<string, string>
     */
    private function availableByItem(array $itemIds, string $factoryId): array
    {
        $binIds = Bin::whereHas('store.warehouse', fn ($q) => $q->where('factory_id', $factoryId))
            ->pluck('id')
            ->all();

        $balances = ItemStockBalance::whereIn('item_id', $itemIds)
            ->whereIn('bin_id', $binIds)
            ->get(['item_id', 'quantity_on_hand', 'quantity_reserved']);

        $totals = [];

        foreach ($balances as $balance) {
            $free = bcsub((string) $balance->quantity_on_hand, (string) $balance->quantity_reserved, self::SCALE);

            if (bccomp($free, '0', self::SCALE) !== 1) {
                continue;
            }

            $totals[$balance->item_id] = bcadd($totals[$balance->item_id] ?? '0.0000', $free, self::SCALE);
        }

        return $totals;
    }

    /**
     * Fabric a confirmed booking is holding for this style.
     *
     * Only this style's own bookings count. A booking for a different style
     * is somebody else's fabric, exactly as reserved stock is.
     *
     * @param  list<string>  $itemIds
     * @return array<string, string>
     */
    private function bookedByItem(array $itemIds, string $styleId): array
    {
        $lines = FabricBookingLine::whereIn('item_id', $itemIds)
            ->whereHas('booking', fn ($q) => $q->where('style_id', $styleId)->whereIn('status', ['CONFIRMED', 'CONVERTED']))
            ->with('reservation:id,status,quantity,quantity_issued,quantity_released')
            ->get();

        $totals = [];

        foreach ($lines as $line) {
            $claim = $line->reservation;

            // What the booking still holds, not what it was for: a claim
            // partly filled by a receipt has already become stock and would
            // otherwise be counted twice. A line with no claim at all —
            // released by hand, or confirmed before claims existed — still
            // represents fabric on its way, so the booked quantity stands.
            $outstanding = match (true) {
                $claim === null => (string) $line->booked_qty,
                $claim->isOpen() => $claim->outstanding(),
                default => '0.0000',
            };

            if (bccomp($outstanding, '0', self::SCALE) !== 1) {
                continue;
            }

            $totals[$line->item_id] = bcadd($totals[$line->item_id] ?? '0.0000', $outstanding, self::SCALE);
        }

        return $totals;
    }

    /**
     * Still to be delivered on live purchase orders — the same definition
     * the requisition screen already shows a requester (§5.2).
     *
     * @param  list<string>  $itemIds
     * @return array<string, string>
     */
    private function orderedByItem(array $itemIds): array
    {
        $lines = PurchaseOrderLine::whereIn('item_id', $itemIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['PENDING_APPROVAL', 'APPROVED', 'PARTIALLY_RECEIVED']))
            ->get();

        $totals = [];

        foreach ($lines as $line) {
            $pending = $line->pendingQty();

            if (bccomp($pending, '0', self::SCALE) !== 1) {
                continue;
            }

            $totals[$line->item_id] = bcadd($totals[$line->item_id] ?? '0.0000', $pending, self::SCALE);
        }

        return $totals;
    }

    /** True once anything on the requirement still needs buying. */
    public static function hasShortfall(array $requirement): bool
    {
        foreach ($requirement as $line) {
            if (bccomp($line['net_requirement'], '0', self::SCALE) === 1) {
                return true;
            }
        }

        return false;
    }
}
