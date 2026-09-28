<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * Fabric grouped by shade and shade band (Inventory spec §14).
 *
 * A garment cut from two shades of the same cloth is a rejected garment,
 * so the useful unit of fabric stock is not the item — it is the item **in
 * one shade**. This report is the one a merchandiser reads before
 * promising a cutting date: 900 yards of a fabric is not 900 usable yards
 * if it is spread across four dye lots.
 *
 * Only rolls that can still be cut are counted. Issued, split and merged
 * rolls are history — including them would add up to cloth the factory
 * does not have.
 */
class ShadeWiseStockReport extends Report
{
    /** Rolls that are still cloth on a rack. */
    private const ON_SHELF = ['AVAILABLE', 'RESERVED'];

    public function key(): string
    {
        return 'fabric_shade_stock';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function permission(): string
    {
        return 'itemmaster.roll.view_any';
    }

    public function filters(): array
    {
        return ['search'];
    }

    public function columns(): array
    {
        return [
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'shade' => ['label' => 'report.columns.shade'],
            'shade_band' => ['label' => 'report.columns.shade_band'],
            'lot_no' => ['label' => 'report.columns.lot_no'],
            'roll_count' => ['label' => 'report.columns.roll_count', 'numeric' => true],
            'quantity' => ['label' => 'report.columns.measured_quantity', 'numeric' => true],
            'reserved_rolls' => ['label' => 'report.columns.reserved_rolls', 'numeric' => true],
            'value' => ['label' => 'report.columns.stock_value', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $groups = [];

        // Grouped in PHP rather than SQL because the quantity is whichever
        // of length or weight the cloth is sold by — a decision the model
        // makes per roll (`measuredQuantity`), not something a SUM() can.
        foreach ($this->base($query)->lazy() as $roll) {
            $key = implode('|', [$roll->item_id, (string) $roll->shade, (string) $roll->lot_no]);

            $groups[$key] ??= [
                'item_code' => $roll->item?->item_code,
                'item_name' => $roll->item?->name,
                'shade' => $roll->shade,
                'shade_band' => $roll->shade_band,
                'lot_no' => $roll->lot_no,
                'roll_count' => 0,
                'quantity' => '0',
                'reserved_rolls' => 0,
                'value' => '0',
            ];

            $groups[$key]['roll_count']++;
            $groups[$key]['quantity'] = bcadd($groups[$key]['quantity'], $roll->measuredQuantity(), 4);
            $groups[$key]['value'] = bcadd($groups[$key]['value'], $roll->totalValue(), 4);

            if ($roll->status === 'RESERVED') {
                // On the rack but spoken for: the difference between what
                // is there and what can be promised.
                $groups[$key]['reserved_rolls']++;
            }
        }

        ksort($groups);

        yield from array_values($groups);
    }

    /**
     * Rolls in, not groups out. Grouping is the work, so counting the
     * groups would mean doing the report to size the report.
     */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    private function base(ReportQuery $query)
    {
        $builder = FabricRoll::query()
            ->with(['item:id,item_code,name'])
            ->whereIn('status', self::ON_SHELF);

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->where(fn ($q) => $q
                ->where('shade', 'ilike', "%{$search}%")
                ->orWhere('shade_band', 'ilike', "%{$search}%")
                ->orWhereHas('item', fn ($i) => $i
                    ->where('item_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")));
        }

        return $builder->orderBy('item_id')->orderBy('shade');
    }
}
