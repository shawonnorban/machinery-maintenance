<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\LandedCostVoucher;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What freight, duty and clearing added to the goods (Procurement §17).
 *
 * The uplift percentage is the number worth reading: a consignment whose
 * landed cost ran to a fifth of its value was either badly quoted or
 * badly cleared, and neither shows up in the purchase rate anybody
 * negotiated.
 *
 * Draft vouchers are included and marked. A charge sitting unposted is
 * cost the factory has incurred and not yet put on its stock, which is
 * precisely what a summary should surface rather than wait for.
 */
class LandedCostSummaryReport extends Report
{
    public function key(): string
    {
        return 'procurement_landed_cost_summary';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.landed_cost.view_any';
    }

    public function filters(): array
    {
        return ['search', 'period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'voucher_no' => ['label' => 'report.columns.voucher_no'],
            'voucher_date' => ['label' => 'report.columns.voucher_date'],
            'factory' => ['label' => 'report.columns.factory'],
            'basis' => ['label' => 'report.columns.basis'],
            'receipt_count' => ['label' => 'report.columns.receipts', 'numeric' => true],
            'goods_value' => ['label' => 'report.columns.goods_value', 'numeric' => true],
            'total_charges' => ['label' => 'report.columns.total_charges', 'numeric' => true],
            'uplift_percent' => ['label' => 'report.columns.uplift_percent', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $voucher) {
            $goods = $voucher->allocations->reduce(
                fn (string $sum, $row): string => bcadd($sum, (string) $row->basis_value, 4),
                '0.0000',
            );

            $charges = (string) $voucher->total_charges;

            yield [
                'voucher_no' => $voucher->voucher_no,
                'voucher_date' => $voucher->voucher_date?->toDateString(),
                'factory' => $voucher->factory?->name,
                'basis' => __("procurement.bases.{$voucher->basis}"),
                'receipt_count' => $voucher->receipts_count ?? 0,
                'goods_value' => $goods,
                'total_charges' => $charges,
                // Null before posting: nothing has been allocated yet, so
                // there is no base to express the uplift against.
                'uplift_percent' => bccomp($goods, '0', 4) === 1
                    ? bcmul(bcdiv($charges, $goods, 6), '100', 2)
                    : null,
                'status' => __("procurement.landed_cost_statuses.{$voucher->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = LandedCostVoucher::query()
            ->with(['factory:id,name', 'allocations:id,landed_cost_voucher_id,basis_value'])
            ->withCount('receipts')
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('voucher_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereRaw('LOWER(voucher_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        return $builder->orderByDesc('voucher_date');
    }
}
