<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the store is holding and what it is worth (SRS 32, SRS 23).
 *
 * Valued at weighted average cost, which is the basis the ledger maintains. Any
 * other basis here would produce a figure that cannot be reconciled against the
 * transactions that created it.
 *
 * A stock position is a present fact, so this report ignores the period. Asking
 * "what was it worth last March" is a different question, and answering it from
 * today's balances would be a lie with a date on it.
 */
class InventoryValuationReport extends Report
{
    public function key(): string
    {
        return 'inventory_valuation';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function permission(): string
    {
        return 'inventory.stock.view';
    }

    public function filters(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            'part_number' => ['label' => 'report.columns.part_number'],
            'part_name' => ['label' => 'report.columns.name'],
            'bin' => ['label' => 'report.columns.bin'],
            'unit' => ['label' => 'report.columns.unit'],
            'on_hand' => ['label' => 'report.columns.on_hand', 'numeric' => true],
            'reserved' => ['label' => 'report.columns.reserved', 'numeric' => true],
            'available' => ['label' => 'report.columns.available', 'numeric' => true],
            'unit_cost' => ['label' => 'report.columns.wac', 'numeric' => true],
            'value' => ['label' => 'report.columns.stock_value', 'numeric' => true],
            'critical' => ['label' => 'report.columns.critical_spare'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $balance) {
            yield [
                'part_number' => $balance->sparePart?->part_number,
                'part_name' => $balance->sparePart?->name,
                'bin' => $balance->bin?->code,
                'unit' => $balance->sparePart?->unit,
                'on_hand' => $balance->quantity_on_hand,
                'reserved' => $balance->quantity_reserved,
                // Reserved stock is physically present but spoken for. Showing
                // only what is on hand is how two work orders get promised the
                // same part.
                'available' => bcsub(
                    (string) $balance->quantity_on_hand,
                    (string) $balance->quantity_reserved,
                    4,
                ),
                'unit_cost' => $balance->weighted_average_cost,
                'value' => $balance->totalValue(),
                'critical' => $balance->sparePart?->is_critical_spare ? __('common.yes') : __('common.no'),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return InventoryBalance::query()
            ->with(['sparePart', 'bin'])
            ->join('spare_parts', 'inventory_balances.spare_part_id', '=', 'spare_parts.id')
            ->orderBy('spare_parts.part_number')
            ->select('inventory_balances.*');
    }
}
