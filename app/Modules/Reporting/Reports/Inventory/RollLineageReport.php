<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Split and merge history (Inventory spec §14).
 *
 * A roll number that appears on an old issue note and nowhere on the rack
 * is the commonest way a fabric store loses an afternoon. This report is
 * the answer: every roll that came from another roll, or went into one,
 * with the piece it came from beside it.
 *
 * Read from the rolls themselves rather than from an event log, because
 * the lineage columns (`parent_roll_id`, `merged_into_roll_id`) are the
 * record — a separate history table could disagree with them, and then
 * there would be two answers to a question that has one.
 */
class RollLineageReport extends Report
{
    public function key(): string
    {
        return 'fabric_roll_lineage';
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
            'roll_no' => ['label' => 'report.columns.roll_no'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'shade' => ['label' => 'report.columns.shade'],
            'relation' => ['label' => 'report.columns.lineage'],
            'related_roll' => ['label' => 'report.columns.related_roll'],
            'quantity' => ['label' => 'report.columns.measured_quantity', 'numeric' => true],
            'unit_cost' => ['label' => 'report.columns.wac', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
            'received_date' => ['label' => 'report.columns.received_date'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $roll) {
            yield [
                'roll_no' => $roll->roll_no,
                'item_code' => $roll->item?->item_code,
                'shade' => $roll->shade,
                // A roll can be both: cut from a parent, then merged on.
                // The merge is the later fact, so it is the one shown.
                'relation' => $roll->merged_into_roll_id !== null
                    ? __('report.lineage.merged_into')
                    : __('report.lineage.split_from'),
                'related_roll' => $roll->merged_into_roll_id !== null
                    ? $roll->mergedInto?->roll_no
                    : $roll->parent?->roll_no,
                'quantity' => $roll->measuredQuantity(),
                'unit_cost' => (string) $roll->unit_cost,
                'status' => __("itemmaster.statuses.{$roll->status}"),
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
            ->with(['item:id,item_code,name', 'parent:id,roll_no', 'mergedInto:id,roll_no'])
            ->where(fn ($q) => $q->whereNotNull('parent_roll_id')->orWhereNotNull('merged_into_roll_id'));

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->where(fn ($q) => $q
                ->where('roll_no', 'ilike', "%{$search}%")
                ->orWhereHas('parent', fn ($p) => $p->where('roll_no', 'ilike', "%{$search}%"))
                ->orWhereHas('mergedInto', fn ($m) => $m->where('roll_no', 'ilike', "%{$search}%"))
                ->orWhereHas('item', fn ($i) => $i
                    ->where('item_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")));
        }

        return $builder->orderBy('item_id')->orderBy('roll_no');
    }
}
