<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dyes and chemicals going off (Inventory spec §12, §14).
 *
 * The daily sweep tells the store team about a lot once; this is the
 * standing list, ordered the way `LotSuggestion` orders it — nearest
 * expiry first, because that is the drum to open next.
 *
 * Lots with nothing left are dropped. A lot that has been fully consumed
 * cannot go off, and a list padded with them is one people learn to
 * scroll past — the same reasoning the alert command already follows.
 * Lots already past their date stay in, flagged: one that disappears from
 * the screen is one nobody writes off either.
 */
class LotExpiryReport extends Report
{
    public function key(): string
    {
        return 'item_lot_expiry';
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
        return ['search'];
    }

    public function columns(): array
    {
        return [
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'lot_no' => ['label' => 'report.columns.lot_no'],
            'supplier_lot_no' => ['label' => 'report.columns.supplier_lot_no'],
            'manufactured_date' => ['label' => 'report.columns.manufactured'],
            'expiry_date' => ['label' => 'report.columns.expiry'],
            'days_to_expiry' => ['label' => 'report.columns.days_to_expiry', 'numeric' => true],
            'on_hand' => ['label' => 'report.columns.on_hand', 'numeric' => true],
            'value' => ['label' => 'report.columns.stock_value', 'numeric' => true],
            'state' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();
        $stock = $this->stockByLot();

        foreach ($this->base($query)->get() as $lot) {
            $held = $stock[$lot->item_id.'|'.$lot->lot_no] ?? null;

            if ($held === null) {
                // Nothing left of it; it cannot go off.
                continue;
            }

            $days = $lot->expiry_date === null
                ? null
                : (int) $today->diffInDays($lot->expiry_date->startOfDay(), false);

            yield [
                'item_code' => $lot->item?->item_code,
                'item_name' => $lot->item?->name,
                'lot_no' => $lot->lot_no,
                'supplier_lot_no' => $lot->supplier_lot_no,
                'manufactured_date' => $lot->manufactured_date?->toDateString(),
                'expiry_date' => $lot->expiry_date?->toDateString(),
                'days_to_expiry' => $days,
                'on_hand' => $held['quantity'],
                'value' => $held['value'],
                'state' => match (true) {
                    $days === null => __('report.lot_states.no_expiry'),
                    $days < 0 => __('report.lot_states.expired'),
                    default => __('report.lot_states.live'),
                },
            ];
        }
    }

    /** The ceiling: lots with a date. How many still hold stock is the report. */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    /**
     * What is left of each lot, summed across every bin holding it.
     *
     * @return array<string, array{quantity: string, value: string}>
     */
    private function stockByLot(): array
    {
        $totals = [];

        $balances = ItemStockBalance::query()
            ->where('lot_no', '!=', ItemStockBalance::NO_LOT)
            ->where('quantity_on_hand', '>', 0)
            ->get(['item_id', 'lot_no', 'quantity_on_hand', 'weighted_average_cost']);

        foreach ($balances as $balance) {
            $key = $balance->item_id.'|'.$balance->lot_no;
            $quantity = (string) $balance->quantity_on_hand;

            $totals[$key] = [
                'quantity' => bcadd($totals[$key]['quantity'] ?? '0', $quantity, 4),
                'value' => bcadd(
                    $totals[$key]['value'] ?? '0',
                    bcmul($quantity, (string) $balance->weighted_average_cost, 4),
                    4,
                ),
            ];
        }

        return $totals;
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = ItemLot::query()
            ->with(['item:id,item_code,name'])
            ->whereHas('item');

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->where(fn ($q) => $q
                ->where('lot_no', 'ilike', "%{$search}%")
                ->orWhereHas('item', fn ($i) => $i
                    ->where('item_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")));
        }

        // Nearest expiry first, and lots without a date last: they are the
        // ones with no deadline to plan around.
        return $builder->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('lot_no');
    }
}
