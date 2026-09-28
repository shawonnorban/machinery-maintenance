<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\PurchaseReturn;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Goods sent back to suppliers (Procurement spec §17).
 *
 * Includes returns that have not left yet, and says so, because a return
 * approved and never dispatched is stock the store thinks it has got rid
 * of and the shelf still holds. `stock_moved` is the column that settles
 * the argument.
 */
class PurchaseReturnRegisterReport extends Report
{
    public function key(): string
    {
        return 'procurement_return_register';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.return.view_any';
    }

    public function filters(): array
    {
        return ['search', 'period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'return_no' => ['label' => 'report.columns.return_no'],
            'return_date' => ['label' => 'report.columns.return_date'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'grn_no' => ['label' => 'report.columns.grn_no'],
            'reason' => ['label' => 'report.columns.reason'],
            'line_count' => ['label' => 'report.columns.lines', 'numeric' => true],
            'debit_note_no' => ['label' => 'report.columns.debit_note_no'],
            'debit_note_amount' => ['label' => 'report.columns.debit_note_amount', 'numeric' => true],
            'stock_moved' => ['label' => 'report.columns.stock_moved'],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $return) {
            yield [
                'return_no' => $return->return_no,
                'return_date' => $return->return_date?->toDateString(),
                'vendor' => $return->vendor?->name,
                'grn_no' => $return->goodsReceipt?->grn_no,
                'reason' => __("procurement.return_reasons.{$return->reason}"),
                'line_count' => $return->lines_count ?? 0,
                'debit_note_no' => $return->debit_note_no,
                'debit_note_amount' => (string) $return->debit_note_amount,
                // Approved is not gone: the shelf only moves at dispatch.
                'stock_moved' => $return->status === 'DISPATCHED' ? __('common.yes') : __('common.no'),
                'status' => __("procurement.return_statuses.{$return->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = PurchaseReturn::query()
            ->with(['vendor:id,name', 'goodsReceipt:id,grn_no'])
            ->withCount('lines')
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('return_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(return_no) LIKE ?', [$needle])
                ->orWhereRaw("LOWER(COALESCE(debit_note_no, '')) LIKE ?", [$needle]));
        }

        return $builder->orderByDesc('return_date');
    }
}
