<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Reporting\Reports\Inventory\Concerns\ScopesItemStock;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the item store holds and what it is worth (Inventory spec §14).
 *
 * The spare-parts valuation report next door answers the same question for
 * the maintenance catalogue; this one reads the item library's own ledger
 * (`item_stock_balances`), which is a separate set of books for a separate
 * set of goods — fabric, yarn, trims, chemicals.
 *
 * Valued at weighted average, because that is the basis the ledger
 * maintains. Any other basis would produce a figure that cannot be
 * reconciled against the movements that created it.
 *
 * A stock position is a present fact, so the period is ignored: answering
 * "what was it worth last March" from today's balances would be a lie with
 * a date on it.
 */
class ItemStockValuationReport extends Report
{
    use ScopesItemStock;

    public function key(): string
    {
        return 'item_stock_valuation';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function permission(): string
    {
        // The permission that already reads "view stock balances and
        // valuation" — a technician holding `itemmaster.item.view_any` can
        // look an item up, but not export what the store is worth.
        return 'inventory.stock.view';
    }

    public function filters(): array
    {
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'department' => ['label' => 'report.columns.item_department'],
            'bin' => ['label' => 'report.columns.bin'],
            'lot_no' => ['label' => 'report.columns.lot_no'],
            'unit' => ['label' => 'report.columns.unit'],
            'on_hand' => ['label' => 'report.columns.on_hand', 'numeric' => true],
            'reserved' => ['label' => 'report.columns.reserved', 'numeric' => true],
            'available' => ['label' => 'report.columns.available', 'numeric' => true],
            'unit_cost' => ['label' => 'report.columns.wac', 'numeric' => true],
            'value' => ['label' => 'report.columns.stock_value', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $balance) {
            yield [
                'item_code' => $balance->item?->item_code,
                'item_name' => $balance->item?->name,
                'department' => $balance->item?->itemDepartment?->name,
                'bin' => $balance->bin?->code,
                'lot_no' => $balance->lot_no === '' ? null : $balance->lot_no,
                'unit' => $balance->item?->uom?->code,
                'on_hand' => (string) $balance->quantity_on_hand,
                'reserved' => (string) $balance->quantity_reserved,
                // Reserved stock is on the shelf but spoken for. Showing
                // only on-hand is how two jobs get promised the same roll.
                'available' => bcsub(
                    (string) $balance->quantity_on_hand,
                    (string) $balance->quantity_reserved,
                    4,
                ),
                'unit_cost' => (string) $balance->weighted_average_cost,
                'value' => bcmul(
                    (string) $balance->quantity_on_hand,
                    (string) $balance->weighted_average_cost,
                    4,
                ),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = ItemStockBalance::query()
            ->with(['item.uom:id,code', 'item.itemDepartment:id,name', 'bin:id,code'])
            ->whereHas('item');

        $builder = $this->scopeToFactory($builder, $query);
        $builder = $this->scopeToSearch($builder, $query);

        return $builder->orderBy('item_id')->orderBy('bin_id')->orderBy('lot_no');
    }
}
