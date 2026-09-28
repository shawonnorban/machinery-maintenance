<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\SupplierAdvance;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money paid to suppliers and not yet worked off (Procurement §17, §13).
 *
 * Not a period report: an advance paid two years ago and never adjusted
 * is exactly what this exists to surface, and filtering to a quarter
 * would hide the oldest and worst of them. Age is shown instead, so the
 * ones that have been sitting longest read as such.
 */
class AdvanceOutstandingReport extends Report
{
    public function key(): string
    {
        return 'procurement_advance_outstanding';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.advance.view_any';
    }

    public function filters(): array
    {
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'advance_no' => ['label' => 'report.columns.advance_no'],
            'advance_date' => ['label' => 'report.columns.paid_on'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'po_no' => ['label' => 'report.columns.po_no'],
            'amount' => ['label' => 'report.columns.paid', 'numeric' => true],
            'adjusted_amount' => ['label' => 'report.columns.adjusted', 'numeric' => true],
            'refunded_amount' => ['label' => 'report.columns.refunded', 'numeric' => true],
            'outstanding' => ['label' => 'report.columns.outstanding', 'numeric' => true],
            'days_outstanding' => ['label' => 'report.columns.days_outstanding', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();

        foreach ($this->base($query)->lazy() as $advance) {
            yield [
                'advance_no' => $advance->advance_no,
                'advance_date' => $advance->advance_date?->toDateString(),
                'vendor' => $advance->vendor?->name,
                'po_no' => $advance->purchaseOrder?->po_no,
                'amount' => (string) $advance->amount,
                'adjusted_amount' => (string) $advance->adjusted_amount,
                'refunded_amount' => (string) $advance->refunded_amount,
                'outstanding' => $advance->outstanding(),
                // How long the factory has been out of pocket.
                'days_outstanding' => $advance->advance_date === null
                    ? null
                    : (int) $advance->advance_date->startOfDay()->diffInDays($today),
                'status' => __("procurement.advance_statuses.{$advance->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = SupplierAdvance::query()
            ->with(['vendor:id,name', 'purchaseOrder:id,po_no'])
            // Only what is still owed back in goods or cash.
            ->where('status', 'OPEN');

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(advance_no) LIKE ?', [$needle])
                ->orWhereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$needle])));
        }

        // Oldest first: the ones nobody has chased.
        return $builder->orderBy('advance_date');
    }
}
