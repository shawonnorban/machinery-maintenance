<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Support\Facades\DB;

/**
 * Which parts are being consumed, and against what (SRS 32).
 *
 * Consumption is measured from the ledger rather than from work order lines,
 * because the ledger is what actually moved stock. A part reserved and never
 * consumed is not consumption, and counting it would overstate usage and
 * trigger reordering that is not needed.
 *
 * Reversals are subtracted rather than listed. Unlike the cost report, this one
 * answers "how much did we use", and a reversed issue was not used.
 */
class PartsConsumptionReport extends Report
{
    public function key(): string
    {
        return 'parts_consumption';
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
        return ['period'];
    }

    public function columns(): array
    {
        return [
            'part_number' => ['label' => 'report.columns.part_number'],
            'part_name' => ['label' => 'report.columns.name'],
            'category' => ['label' => 'report.columns.category'],
            'unit' => ['label' => 'report.columns.unit'],
            'quantity' => ['label' => 'report.columns.quantity_consumed', 'numeric' => true],
            'value' => ['label' => 'report.columns.value_consumed', 'numeric' => true],
            'issues' => ['label' => 'report.columns.issue_count', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $rows = InventoryTransaction::query()
            ->join('spare_parts', 'inventory_transactions.spare_part_id', '=', 'spare_parts.id')
            ->leftJoin('spare_part_categories', 'spare_parts.category_id', '=', 'spare_part_categories.id')
            ->whereBetween('inventory_transactions.transaction_at', [$query->from, $query->to])
            ->whereIn('inventory_transactions.transaction_type', ['ISSUE', 'CONSUME'])
            ->groupBy('spare_parts.part_number', 'spare_parts.name', 'spare_parts.unit', 'spare_part_categories.name')
            ->orderByDesc(DB::raw('abs(sum(inventory_transactions.quantity))'))
            ->select([
                'spare_parts.part_number',
                'spare_parts.name as part_name',
                'spare_parts.unit',
                DB::raw('spare_part_categories.name as category'),
                // Outbound quantities are stored negative, and a reversal of an
                // issue is positive, so the sum nets correctly on its own.
                DB::raw('abs(sum(inventory_transactions.quantity)) as quantity'),
                DB::raw('abs(sum(inventory_transactions.base_total_cost)) as value'),
                DB::raw('count(*) as issues'),
            ])
            ->get();

        foreach ($rows as $row) {
            yield [
                'part_number' => $row->part_number,
                'part_name' => $row->part_name,
                'category' => $row->category,
                'unit' => $row->unit,
                'quantity' => $row->quantity,
                'value' => $row->value,
                'issues' => (int) $row->issues,
            ];
        }
    }

    public function estimatedRows(ReportQuery $query): int
    {
        // One row per part, bounded by the catalogue.
        return InventoryTransaction::query()
            ->whereBetween('transaction_at', [$query->from, $query->to])
            ->whereIn('transaction_type', ['ISSUE', 'CONSUME'])
            ->distinct()
            ->count('spare_part_id');
    }
}
