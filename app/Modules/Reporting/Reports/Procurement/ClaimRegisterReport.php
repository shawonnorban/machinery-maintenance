<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\SupplierClaim;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money claimed from suppliers, and what came back (Procurement §17).
 *
 * A period report, because the question is "what did we claim this
 * quarter and how much of it did we recover". Outstanding is carried per
 * row rather than only as a total: one large claim nobody has settled is
 * a different conversation from twenty small ones.
 */
class ClaimRegisterReport extends Report
{
    public function key(): string
    {
        return 'procurement_claim_register';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.claim.view_any';
    }

    public function filters(): array
    {
        return ['search', 'period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'claim_no' => ['label' => 'report.columns.claim_no'],
            'claim_date' => ['label' => 'report.columns.claim_date'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'reason' => ['label' => 'report.columns.reason'],
            'grn_no' => ['label' => 'report.columns.grn_no'],
            'claim_amount' => ['label' => 'report.columns.claimed', 'numeric' => true],
            'settled_amount' => ['label' => 'report.columns.settled', 'numeric' => true],
            'outstanding' => ['label' => 'report.columns.outstanding', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $claim) {
            yield [
                'claim_no' => $claim->claim_no,
                'claim_date' => $claim->claim_date?->toDateString(),
                'vendor' => $claim->vendor?->name,
                'reason' => __("procurement.claim_reasons.{$claim->reason}"),
                'grn_no' => $claim->goodsReceipt?->grn_no,
                'claim_amount' => (string) $claim->claim_amount,
                'settled_amount' => (string) $claim->settled_amount,
                'outstanding' => $claim->outstanding(),
                'status' => __("procurement.claim_statuses.{$claim->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = SupplierClaim::query()
            ->with(['vendor:id,name', 'goodsReceipt:id,grn_no'])
            ->whereBetween('claim_date', [$query->from->toDateString(), $query->to->toDateString()]);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(claim_no) LIKE ?', [$needle])
                ->orWhereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$needle])));
        }

        return $builder->orderByDesc('claim_date');
    }
}
