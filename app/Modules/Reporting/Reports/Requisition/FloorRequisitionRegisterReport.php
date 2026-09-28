<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Requisition;

use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every floor requisition in the period (Requisition spec §10).
 *
 * Department and requester are both columns rather than two separate
 * reports: the spec asks for department-wise and user-wise, and those are
 * the same rows grouped differently — which a spreadsheet does better
 * than a second query would.
 */
class FloorRequisitionRegisterReport extends Report
{
    public function key(): string
    {
        return 'requisition_register';
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
        return ['search', 'period', 'factory', 'status'];
    }

    public function columns(): array
    {
        return [
            'fr_no' => ['label' => 'report.columns.fr_no'],
            'fr_date' => ['label' => 'report.columns.requisition_date'],
            'department' => ['label' => 'report.columns.department'],
            'requested_by' => ['label' => 'report.columns.requested_by'],
            'line_count' => ['label' => 'report.columns.lines', 'numeric' => true],
            'issued_lines' => ['label' => 'report.columns.issued_lines', 'numeric' => true],
            'short_lines' => ['label' => 'report.columns.short_lines', 'numeric' => true],
            'shortage_pr' => ['label' => 'report.columns.shortage_pr'],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $requisition) {
            yield [
                'fr_no' => $requisition->fr_no,
                'fr_date' => $requisition->fr_date?->toDateString(),
                'department' => $requisition->requesterDepartment?->name,
                'requested_by' => $requisition->requester?->name,
                'line_count' => $requisition->lines->count(),
                'issued_lines' => $requisition->lines
                    ->whereIn('status', ['ISSUED', 'PARTIALLY_ISSUED', 'BORROWED', 'RETURNED'])->count(),
                'short_lines' => $requisition->lines->where('status', 'SHORT')->count(),
                'shortage_pr' => $requisition->shortagePurchaseRequisition?->pr_no,
                'status' => __("requisition.statuses.{$requisition->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = FloorRequisition::query()
            ->with([
                'requesterDepartment:id,name', 'requester:id,name',
                'lines:id,floor_requisition_id,status', 'shortagePurchaseRequisition:id,pr_no',
            ])
            ->whereBetween('fr_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($status = $query->get('status')) && $status !== '') {
            $builder->where('status', $status);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereRaw('LOWER(fr_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return $builder->orderByDesc('fr_date');
    }
}
