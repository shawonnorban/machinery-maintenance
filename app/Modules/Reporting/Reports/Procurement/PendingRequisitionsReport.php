<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requisitions that have not become orders (Procurement spec §17).
 *
 * "Pending" covers two different waits and says which: a requisition
 * sitting on somebody's approval queue, and an approved one nobody has
 * raised an order against. They need different people to act, and a
 * single count of "pending" tells neither of them anything.
 *
 * Age is counted from when the requisition was raised rather than from
 * its last movement, because the question a store asks is "how long have
 * I been waiting", not "how long since somebody touched it".
 */
class PendingRequisitionsReport extends Report
{
    /** Raised, and not yet fully turned into orders. */
    private const OPEN_STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'PARTIALLY_CONVERTED'];

    public function key(): string
    {
        return 'procurement_pending_requisitions';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.requisition.view_any';
    }

    public function filters(): array
    {
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'pr_no' => ['label' => 'report.columns.pr_no'],
            'pr_date' => ['label' => 'report.columns.pr_date'],
            'pr_type' => ['label' => 'report.columns.pr_type'],
            'factory' => ['label' => 'report.columns.factory'],
            'requested_by' => ['label' => 'report.columns.requested_by'],
            'line_count' => ['label' => 'report.columns.lines', 'numeric' => true],
            'estimated_total' => ['label' => 'report.columns.estimated_total', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
            'waiting_on' => ['label' => 'report.columns.waiting_on'],
            'days_open' => ['label' => 'report.columns.days_open', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();

        foreach ($this->base($query)->lazy() as $requisition) {
            yield [
                'pr_no' => $requisition->pr_no,
                'pr_date' => $requisition->pr_date?->toDateString(),
                'pr_type' => __("procurement.pr_types.{$requisition->pr_type}"),
                'factory' => $requisition->factory?->name,
                'requested_by' => $requisition->requester?->name,
                'line_count' => $requisition->lines_count ?? 0,
                'estimated_total' => (string) $requisition->estimated_total,
                'status' => __("procurement.statuses.{$requisition->status}"),
                // The useful half: who has to move next.
                'waiting_on' => in_array($requisition->status, ['DRAFT', 'PENDING_APPROVAL'], true)
                    ? __('report.waiting.approval')
                    : __('report.waiting.ordering'),
                'days_open' => $requisition->pr_date === null
                    ? null
                    : (int) $requisition->pr_date->startOfDay()->diffInDays($today),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = PurchaseRequisition::query()
            ->with(['factory:id,name', 'requester:id,name'])
            ->withCount('lines')
            ->whereIn('status', self::OPEN_STATUSES);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereRaw('LOWER(pr_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return $builder->orderBy('pr_date');
    }
}
