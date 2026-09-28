<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Requisition;

use App\Modules\Requisition\Models\FloorRequisitionIssue;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Settings\Services\SettingsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Returnable stock that is still out (Requisition spec §10).
 *
 * Covers both reports the spec names — currently borrowed, and overdue —
 * because they are one list with a column: an item is overdue when it has
 * been out longer than the factory's own limit, and splitting them into
 * two screens means the person chasing has to open both.
 *
 * Consumed and not-yet-returned are kept apart, which the spec asks for
 * explicitly: a partly returned issue is still partly out, and rounding
 * it either way loses the half that matters.
 */
class BorrowedItemReport extends Report
{
    public function __construct(private readonly SettingsResolver $settings) {}

    public function key(): string
    {
        return 'requisition_borrowed_items';
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
            'issued_at' => ['label' => 'report.columns.issued_at'],
            'borrower' => ['label' => 'report.columns.borrowed_by'],
            'department' => ['label' => 'report.columns.department'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'issued_qty' => ['label' => 'report.columns.issued_qty', 'numeric' => true],
            'returned_qty' => ['label' => 'report.columns.returned_qty', 'numeric' => true],
            'outstanding_qty' => ['label' => 'report.columns.still_out', 'numeric' => true],
            'days_out' => ['label' => 'report.columns.days_out', 'numeric' => true],
            'overdue' => ['label' => 'report.columns.overdue'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();
        $limit = (int) $this->settings->get('requisition.returnable_overdue_days');

        foreach ($this->base($query)->lazy() as $issue) {
            $outstanding = bcsub((string) $issue->quantity, (string) ($issue->returned_qty ?? '0'), 4);

            if (bccomp($outstanding, '0', 4) !== 1) {
                // Fully back: not borrowed any more.
                continue;
            }

            $line = $issue->line;
            $requisition = $line?->requisition;
            $days = $issue->issued_at === null
                ? null
                : (int) $issue->issued_at->startOfDay()->diffInDays($today);

            yield [
                'fr_no' => $requisition?->fr_no,
                'issued_at' => $issue->issued_at?->toDateString(),
                'borrower' => $requisition?->requester?->name,
                'department' => $requisition?->requesterDepartment?->name,
                'item_code' => $line?->item_code,
                'item_name' => $line?->item_name,
                'issued_qty' => (string) $issue->quantity,
                'returned_qty' => (string) ($issue->returned_qty ?? '0'),
                'outstanding_qty' => $outstanding,
                'days_out' => $days,
                // Against the factory's own limit, not a number baked in
                // here: a torch and a trolley are owed back on different
                // timescales, and the setting is where that is decided.
                'overdue' => $days !== null && $limit > 0 && $days > $limit
                    ? __('common.yes')
                    : __('common.no'),
            ];
        }
    }

    /** Returnable issues, the ceiling; what is still out is the report. */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = FloorRequisitionIssue::query()
            ->with([
                'line:id,floor_requisition_id,item_id,item_code,item_name,status',
                'line.requisition:id,fr_no,factory_id,requested_by,requester_department_id',
                'line.requisition.requester:id,name',
                'line.requisition.requesterDepartment:id,name',
            ])
            // Only lines the store lent rather than issued for good.
            ->whereHas('line', fn ($q) => $q->whereIn('status', ['BORROWED', 'RETURNED']));

        if ($query->factoryId !== null) {
            $builder->whereHas(
                'line.requisition',
                fn ($q) => $q->where('factory_id', $query->factoryId),
            );
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->whereHas('line', fn ($q) => $q
                ->whereRaw('LOWER(item_code) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_name) LIKE ?', [$needle]));
        }

        return $builder->orderBy('issued_at');
    }
}
