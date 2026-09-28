<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Requisition;

use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Floor shortage → the purchase requisition it raised (spec §10).
 *
 * The trail exists so a storekeeper chasing "when is my thread coming"
 * can follow it forward, and so anybody auditing an auto-raised PR can
 * follow it back. Only requisitions that actually raised one appear —
 * the rest have no trail to show.
 */
class RequisitionTraceabilityReport extends Report
{
    public function key(): string
    {
        return 'requisition_auto_pr_trace';
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
            'fr_no' => ['label' => 'report.columns.fr_no'],
            'fr_date' => ['label' => 'report.columns.requisition_date'],
            'department' => ['label' => 'report.columns.department'],
            'short_lines' => ['label' => 'report.columns.short_lines', 'numeric' => true],
            'pr_no' => ['label' => 'report.columns.pr_no'],
            'pr_status' => ['label' => 'report.columns.pr_status'],
            'pr_date' => ['label' => 'report.columns.pr_date'],
            'estimated_total' => ['label' => 'report.columns.estimated_total', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $requisition) {
            $pr = $requisition->shortagePurchaseRequisition;

            yield [
                'fr_no' => $requisition->fr_no,
                'fr_date' => $requisition->fr_date?->toDateString(),
                'department' => $requisition->requesterDepartment?->name,
                'short_lines' => $requisition->lines->where('status', 'SHORT')->count(),
                'pr_no' => $pr?->pr_no,
                'pr_status' => $pr === null ? null : __("procurement.statuses.{$pr->status}"),
                'pr_date' => $pr?->pr_date?->toDateString(),
                'estimated_total' => $pr === null ? null : (string) $pr->estimated_total,
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
                'requesterDepartment:id,name', 'lines:id,floor_requisition_id,status',
                'shortagePurchaseRequisition:id,pr_no,pr_date,status,estimated_total',
            ])
            // Only the ones that actually raised a purchase requisition.
            ->whereNotNull('shortage_pr_id')
            ->whereBetween('fr_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereRaw('LOWER(fr_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return $builder->orderByDesc('fr_date');
    }
}
