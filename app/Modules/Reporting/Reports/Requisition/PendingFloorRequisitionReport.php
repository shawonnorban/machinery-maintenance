<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Requisition;

use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Requisition\Models\FloorRequisitionLine;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the floor is still waiting for (Requisition spec §10).
 *
 * Per line, not per requisition: a requisition with four lines issued and
 * one short is not "pending", it is one item short, and the store needs
 * to know which. Lines already settled are dropped so the report is a
 * work queue rather than a history.
 *
 * Not a period report — a line nobody filled in March is exactly what
 * should still be showing in September.
 */
class PendingFloorRequisitionReport extends Report
{
    /** Lines with something still owed to the floor. */
    private const OPEN_LINE_STATUSES = ['PENDING', 'PARTIALLY_ISSUED', 'SHORT'];

    /** Requisitions that can still be filled. */
    private const OPEN_STATUSES = ['SUBMITTED', 'UNDER_REVIEW', 'PARTIALLY_FULFILLED'];

    public function key(): string
    {
        return 'requisition_pending';
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
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'fr_no' => ['label' => 'report.columns.fr_no'],
            'fr_date' => ['label' => 'report.columns.requisition_date'],
            'department' => ['label' => 'report.columns.department'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'requested_qty' => ['label' => 'report.columns.requested_qty', 'numeric' => true],
            'approved_qty' => ['label' => 'report.columns.approved_qty', 'numeric' => true],
            'issued_qty' => ['label' => 'report.columns.issued_qty', 'numeric' => true],
            'outstanding_qty' => ['label' => 'report.columns.outstanding_qty', 'numeric' => true],
            'line_status' => ['label' => 'report.columns.line_status'],
            'days_waiting' => ['label' => 'report.columns.days_waiting', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();

        foreach ($this->base($query)->lazy() as $line) {
            $wanted = (string) ($line->approved_qty ?? $line->requested_qty);
            $outstanding = bcsub($wanted, (string) $line->issued_qty, 4);

            if (bccomp($outstanding, '0', 4) !== 1) {
                continue;
            }

            $raised = $line->requisition?->fr_date;

            yield [
                'fr_no' => $line->requisition?->fr_no,
                'fr_date' => $raised?->toDateString(),
                'department' => $line->requisition?->requesterDepartment?->name,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'requested_qty' => (string) $line->requested_qty,
                'approved_qty' => $line->approved_qty === null ? null : (string) $line->approved_qty,
                'issued_qty' => (string) $line->issued_qty,
                'outstanding_qty' => $outstanding,
                'line_status' => __("requisition.line_statuses.{$line->status}"),
                'days_waiting' => $raised === null ? null : (int) $raised->startOfDay()->diffInDays($today),
            ];
        }
    }

    /** Open lines, the ceiling; which still owe something is the report. */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    private function base(ReportQuery $query): Builder
    {
        $requisitions = FloorRequisition::query()->whereIn('status', self::OPEN_STATUSES);

        if ($query->factoryId !== null) {
            $requisitions->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $requisitions->whereRaw('LOWER(fr_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return FloorRequisitionLine::query()
            ->with(['requisition:id,fr_no,fr_date,requester_department_id', 'requisition.requesterDepartment:id,name'])
            ->whereIn('floor_requisition_id', $requisitions->select('id'))
            ->whereIn('status', self::OPEN_LINE_STATUSES)
            ->orderBy('floor_requisition_id')
            ->orderBy('item_code');
    }
}
