<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every roll, and where it stands (Inventory spec §14).
 *
 * Fabric does not live in the quantity ledger: each roll is an
 * individually identified piece with its own shade, length and status, so
 * "how much fabric do we have" is a question about rows in this table
 * rather than a balance. That is why the valuation report cannot answer
 * it and this one exists.
 *
 * Closed rolls — split, merged, issued — stay in by default and are
 * filtered out by status when somebody wants only the shelf. A ledger that
 * quietly drops what has left is not a ledger; the roll that was cut in
 * half last month is exactly what somebody tracing a shortage is looking
 * for.
 */
class RollLedgerReport extends Report
{
    public function key(): string
    {
        return 'fabric_roll_ledger';
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
        return ['search', 'status'];
    }

    public function columns(): array
    {
        return [
            'roll_no' => ['label' => 'report.columns.roll_no'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'shade' => ['label' => 'report.columns.shade'],
            'shade_band' => ['label' => 'report.columns.shade_band'],
            'lot_no' => ['label' => 'report.columns.lot_no'],
            'bin' => ['label' => 'report.columns.bin'],
            'status' => ['label' => 'report.columns.status'],
            'grade' => ['label' => 'report.columns.grade'],
            'quantity' => ['label' => 'report.columns.measured_quantity', 'numeric' => true],
            'unit_cost' => ['label' => 'report.columns.wac', 'numeric' => true],
            'value' => ['label' => 'report.columns.stock_value', 'numeric' => true],
            'received_date' => ['label' => 'report.columns.received_date'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $roll) {
            yield [
                'roll_no' => $roll->roll_no,
                'item_code' => $roll->item?->item_code,
                'item_name' => $roll->item?->name,
                'shade' => $roll->shade,
                'shade_band' => $roll->shade_band,
                'lot_no' => $roll->lot_no,
                'bin' => $roll->bin?->code,
                'status' => __("itemmaster.statuses.{$roll->status}"),
                'grade' => $roll->inspection_grade,
                // Length or weight, whichever this cloth is sold by.
                'quantity' => $roll->measuredQuantity(),
                'unit_cost' => (string) $roll->unit_cost,
                'value' => $roll->totalValue(),
                'received_date' => $roll->received_date?->toDateString(),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = FabricRoll::query()
            ->with(['item:id,item_code,name', 'bin:id,code']);

        if (is_string($status = $query->get('status')) && $status !== '') {
            $builder->where('status', $status);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->where(fn ($q) => $q
                ->where('roll_no', 'ilike', "%{$search}%")
                ->orWhere('shade', 'ilike', "%{$search}%")
                ->orWhere('lot_no', 'ilike', "%{$search}%")
                ->orWhereHas('item', fn ($i) => $i
                    ->where('item_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")));
        }

        return $builder->orderBy('item_id')->orderBy('shade')->orderBy('roll_no');
    }
}
