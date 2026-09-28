<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * The inspection register (Inventory spec §4, §14).
 *
 * Knit and woven in one register rather than two: the spec names both, but
 * the difference between them is the points system a grader applies, not
 * the shape of the record — roll, date, grade, points, what was found.
 * Two tables holding the same columns would only guarantee that half the
 * mills' cloth was missing from whichever one somebody opened.
 *
 * Unlike the roll ledger this is a **period** report: it answers "what did
 * we inspect last month and how did it grade", so it reads inspection
 * dates rather than current status. A roll inspected in March and issued
 * in April still belongs in March's register.
 */
class RollInspectionRegisterReport extends Report
{
    public function key(): string
    {
        return 'fabric_inspection_register';
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
        return ['search', 'period'];
    }

    public function columns(): array
    {
        return [
            'inspected_at' => ['label' => 'report.columns.inspected_at'],
            'inspection_no' => ['label' => 'report.columns.inspection_no'],
            'roll_no' => ['label' => 'report.columns.roll_no'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'shade' => ['label' => 'report.columns.shade'],
            'quantity' => ['label' => 'report.columns.measured_quantity', 'numeric' => true],
            'grade' => ['label' => 'report.columns.grade'],
            'points' => ['label' => 'report.columns.inspection_points', 'numeric' => true],
            // The figure the accept/reject decision is actually made on;
            // raw points mean nothing without the yardage behind them.
            'penalty_percent' => ['label' => 'report.columns.penalty_percent', 'numeric' => true],
            'shade_match' => ['label' => 'report.columns.shade_match'],
            'defect_count' => ['label' => 'report.columns.defect_count', 'numeric' => true],
            'outcome' => ['label' => 'report.columns.status'],
            'inspected_by' => ['label' => 'report.columns.inspected_by'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $roll) {
            yield [
                'inspected_at' => $roll->inspected_at?->toDateString(),
                'inspection_no' => $roll->inspection?->inspection_no,
                'roll_no' => $roll->roll_no,
                'item_code' => $roll->item?->item_code,
                'item_name' => $roll->item?->name,
                'shade' => $roll->shade,
                'quantity' => $roll->measuredQuantity(),
                'grade' => $roll->inspection_grade,
                'points' => $roll->inspection_points,
                'penalty_percent' => $roll->penalty_percent,
                'shade_match' => $roll->shade_match,
                'defect_count' => $roll->defects_count ?? 0,
                // What the grade resolved to: passed onto the shelf, or
                // sent back / written off.
                'outcome' => __("itemmaster.statuses.{$roll->status}"),
                'inspected_by' => $roll->inspector?->name,
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
            ->with(['item:id,item_code,name', 'inspector:id,name', 'inspection:id,inspection_no'])
            ->withCount('defects')
            ->whereNotNull('inspected_at')
            ->whereBetween('inspected_at', [$query->from, $query->to]);

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->where(fn ($q) => $q
                ->where('roll_no', 'ilike', "%{$search}%")
                ->orWhere('shade', 'ilike', "%{$search}%")
                ->orWhereHas('item', fn ($i) => $i
                    ->where('item_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")));
        }

        return $builder->orderByDesc('inspected_at')->orderBy('roll_no');
    }
}
