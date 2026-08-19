<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Vendor;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\WarrantyClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * How vendors actually behave (SRS 32, SRS 26).
 *
 * Measured from what the system already recorded rather than from anybody's
 * impression: claims filed against them, how many they honoured, and how long
 * they took to answer. A factory renegotiating an AMC has nothing to argue with
 * otherwise.
 *
 * Claim settlement rate counts settled against decided, not against filed. A
 * claim still sitting with the vendor is neither a success nor a failure yet,
 * and counting it as failure would punish a vendor for a slow week.
 */
class VendorPerformanceReport extends Report
{
    public function key(): string
    {
        return 'vendor_performance';
    }

    public function group(): string
    {
        return 'vendor';
    }

    public function permission(): string
    {
        return 'vendor.vendor.view_any';
    }

    public function filters(): array
    {
        return ['period'];
    }

    public function columns(): array
    {
        return [
            'vendor' => ['label' => 'report.columns.vendor'],
            'code' => ['label' => 'report.columns.code'],
            'type' => ['label' => 'report.columns.vendor_type'],
            'contracts' => ['label' => 'report.columns.contracts', 'numeric' => true],
            'contract_value' => ['label' => 'report.columns.contract_value', 'numeric' => true],
            'claims' => ['label' => 'report.columns.claims_filed', 'numeric' => true],
            'claims_settled' => ['label' => 'report.columns.claims_settled', 'numeric' => true],
            'claims_rejected' => ['label' => 'report.columns.claims_rejected', 'numeric' => true],
            'settlement_rate' => ['label' => 'report.columns.settlement_rate', 'numeric' => true],
            'settled_value' => ['label' => 'report.columns.settled_value', 'numeric' => true],
            'avg_response_days' => ['label' => 'report.columns.avg_claim_response', 'numeric' => true],
            'parts_spend' => ['label' => 'report.columns.parts_spend', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base()->lazy() as $vendor) {
            $claims = WarrantyClaim::query()
                ->whereBetween('claim_date', [$query->from, $query->to])
                ->whereHas('warranty', fn ($q) => $q->where('vendor_id', $vendor->id))
                ->get();

            $decided = $claims->whereIn('status', ['SETTLED', 'REJECTED', 'APPROVED']);
            $settled = $claims->where('status', 'SETTLED');
            $rejected = $claims->where('status', 'REJECTED');

            $answered = $claims->filter(fn (WarrantyClaim $c) => $c->resolved_at !== null);

            $contracts = ServiceContract::query()
                ->where('vendor_id', $vendor->id)
                ->where('start_date', '<=', $query->to)
                ->where('end_date', '>=', $query->from)
                ->get();

            $spend = DB::table('cost_entries')
                ->where('company_id', $vendor->company_id)
                ->where('vendor_id', $vendor->id)
                ->whereBetween('occurred_at', [$query->from, $query->to])
                ->sum('base_amount');

            yield [
                'vendor' => $vendor->name,
                'code' => $vendor->code,
                'type' => __('vendor.type_'.strtolower($vendor->vendor_type)),
                'contracts' => $contracts->count(),
                'contract_value' => $contracts->sum(fn (ServiceContract $c) => (float) ($c->value ?? 0)),
                'claims' => $claims->count(),
                'claims_settled' => $settled->count(),
                'claims_rejected' => $rejected->count(),
                // Against decided claims: one still with the vendor is neither
                // a success nor a failure yet.
                'settlement_rate' => $decided->isEmpty()
                    ? null
                    : round($settled->count() / $decided->count() * 100, 1),
                'settled_value' => $settled->sum(fn (WarrantyClaim $c) => (float) ($c->settled_amount ?? 0)),
                'avg_response_days' => $answered->isEmpty()
                    ? null
                    : round($answered->avg(
                        fn (WarrantyClaim $c) => $c->claim_date->diffInDays($c->resolved_at, absolute: true),
                    ), 1),
                'parts_spend' => $spend,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base();
    }

    private function base(): Builder
    {
        return Vendor::query()->orderBy('name');
    }
}
