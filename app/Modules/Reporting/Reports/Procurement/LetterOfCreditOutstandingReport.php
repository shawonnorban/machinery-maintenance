<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\LetterOfCredit;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Letters of credit the bank still has open (Procurement spec §17).
 *
 * Sorted by expiry, because that is the deadline that costs money: an
 * amendment carries a bank charge, but letting an LC lapse costs the
 * whole opening. The variance against the order is carried here too —
 * a gap either way has to be amended before shipment, and finding that
 * out a week before expiry is finding it out too late.
 */
class LetterOfCreditOutstandingReport extends Report
{
    /** Opened, and not yet closed or cancelled. */
    private const OPEN_STATUSES = ['DRAFT', 'OPENED', 'SHIPPED'];

    public function key(): string
    {
        return 'procurement_lc_outstanding';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.import.view_any';
    }

    public function filters(): array
    {
        return ['search'];
    }

    public function columns(): array
    {
        return [
            'lc_no' => ['label' => 'report.columns.lc_no'],
            'lc_date' => ['label' => 'report.columns.lc_date'],
            'bank_name' => ['label' => 'report.columns.bank'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'po_no' => ['label' => 'report.columns.po_no'],
            'amount' => ['label' => 'report.columns.lc_amount', 'numeric' => true],
            'order_value' => ['label' => 'report.columns.order_value', 'numeric' => true],
            'variance' => ['label' => 'report.columns.variance', 'numeric' => true],
            'expiry_date' => ['label' => 'report.columns.expiry'],
            'days_to_expiry' => ['label' => 'report.columns.days_to_expiry', 'numeric' => true],
            'amendments' => ['label' => 'report.columns.amendments', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $letter) {
            yield [
                'lc_no' => $letter->lc_no,
                'lc_date' => $letter->lc_date?->toDateString(),
                'bank_name' => $letter->bank_name,
                'vendor' => $letter->vendor?->name,
                'po_no' => $letter->purchaseOrder?->po_no,
                'amount' => (string) $letter->amount,
                'order_value' => (string) ($letter->purchaseOrder?->subtotal ?? '0'),
                'variance' => $letter->varianceAgainstOrder(),
                'expiry_date' => $letter->expiry_date?->toDateString(),
                'days_to_expiry' => $letter->daysToExpiry(),
                'amendments' => $letter->amendments_count ?? 0,
                'status' => __("procurement.lc_statuses.{$letter->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = LetterOfCredit::query()
            ->with(['vendor:id,name', 'purchaseOrder:id,po_no,subtotal'])
            ->withCount('amendments')
            ->whereIn('status', self::OPEN_STATUSES);

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(lc_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(bank_name) LIKE ?', [$needle]));
        }

        // Nearest deadline first; undated last, since they have none.
        return $builder->orderByRaw('expiry_date IS NULL, expiry_date ASC');
    }
}
