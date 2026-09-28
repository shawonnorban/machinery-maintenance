<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Requisition;

use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Requisition\Models\FloorRequisitionIssue;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who drew what from the store (Requisition spec §10).
 *
 * The spec asks for this to detect misuse, which is worth being careful
 * about: the report shows what was issued and to whom, and says nothing
 * about whether it was justified. A person high on this list may run the
 * busiest line. It is a place to start asking, not an accusation, and the
 * columns are chosen so the obvious innocent explanation — more
 * requisitions, bigger ones — is visible beside the total.
 *
 * Returnable items that came back are netted off. Stock lent out and
 * returned was never consumed, and counting it would put a borrowed
 * torch on somebody's record as though they had used it up.
 */
class RequisitionConsumptionReport extends Report
{
    public function key(): string
    {
        return 'requisition_consumption';
    }

    public function group(): string
    {
        return 'requisition';
    }

    public function permission(): string
    {
        return 'requisition.floor.view_any';
    }

    public function filters(): array
    {
        return ['search', 'period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'requested_by' => ['label' => 'report.columns.requested_by'],
            'department' => ['label' => 'report.columns.department'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'requisitions' => ['label' => 'report.columns.requisitions', 'numeric' => true],
            'issued_qty' => ['label' => 'report.columns.issued_qty', 'numeric' => true],
            'returned_qty' => ['label' => 'report.columns.returned_qty', 'numeric' => true],
            'consumed_qty' => ['label' => 'report.columns.consumed_qty', 'numeric' => true],
            'value' => ['label' => 'report.columns.value', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $groups = [];

        foreach ($this->base($query)->lazy() as $issue) {
            $line = $issue->line;
            $requisition = $line?->requisition;

            if ($line === null || $requisition === null) {
                continue;
            }

            $key = ($requisition->requested_by ?? '').'|'.$line->item_id;

            $groups[$key] ??= [
                'requested_by' => $requisition->requester?->name,
                'department' => $requisition->requesterDepartment?->name,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'requisitions' => [],
                'issued_qty' => '0',
                'returned_qty' => '0',
                'consumed_qty' => '0',
                'value' => '0',
            ];

            $issued = (string) $issue->quantity;
            $returned = (string) ($issue->returned_qty ?? '0');
            $consumed = bcsub($issued, $returned, 4);

            $groups[$key]['requisitions'][$requisition->id] = true;
            $groups[$key]['issued_qty'] = bcadd($groups[$key]['issued_qty'], $issued, 4);
            $groups[$key]['returned_qty'] = bcadd($groups[$key]['returned_qty'], $returned, 4);
            $groups[$key]['consumed_qty'] = bcadd($groups[$key]['consumed_qty'], $consumed, 4);
            $groups[$key]['value'] = bcadd(
                $groups[$key]['value'],
                bcmul($consumed, (string) ($issue->unit_cost ?? '0'), 4),
                4,
            );
        }

        foreach ($groups as $row) {
            // The count of requisitions, not their ids — the obvious
            // innocent explanation for a large total, beside the total.
            $row['requisitions'] = count($row['requisitions']);

            yield $row;
        }
    }

    /** Issues in, groups out; grouping is the work. */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    private function base(ReportQuery $query): Builder
    {
        $requisitions = FloorRequisition::query()
            ->whereBetween('fr_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $requisitions->where('factory_id', $query->factoryId);
        }

        $builder = FloorRequisitionIssue::query()
            ->with([
                'line:id,floor_requisition_id,item_id,item_code,item_name',
                'line.requisition:id,fr_no,requested_by,requester_department_id',
                'line.requisition.requester:id,name',
                'line.requisition.requesterDepartment:id,name',
            ])
            ->whereHas('line', fn ($q) => $q->whereIn('floor_requisition_id', $requisitions->select('id')));

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->whereHas('line', fn ($q) => $q
                ->whereRaw('LOWER(item_code) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_name) LIKE ?', [$needle]));
        }

        return $builder;
    }
}
