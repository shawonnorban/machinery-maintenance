<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Vendor;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Warranty;
use Carbon\CarbonImmutable;

/**
 * What cover is about to run out (SRS 32, SRS 26).
 *
 * Warranties and contracts in one list, because the person who has to act on
 * them does not care which kind it is — they care what is unprotected next
 * quarter and what it will cost to keep covered.
 *
 * Sorted by what expires soonest and filtered to what is still in force.
 * Something that lapsed last year is filing, not a decision.
 */
class CoverageExpiryReport extends Report
{
    public function key(): string
    {
        return 'coverage_expiry';
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
            'kind' => ['label' => 'report.columns.cover_kind'],
            'reference' => ['label' => 'report.columns.reference'],
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'scope' => ['label' => 'report.columns.scope'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'start_date' => ['label' => 'report.columns.start_date'],
            'end_date' => ['label' => 'report.columns.end_date'],
            'days_remaining' => ['label' => 'report.columns.days_remaining', 'numeric' => true],
            'value' => ['label' => 'report.columns.value', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $rows = [];

        // The period is read as the window to look ahead over, not as history:
        // this report answers "what runs out between these dates".
        $warranties = Warranty::query()
            ->with(['asset', 'vendor'])
            ->whereBetween('end_date', [$query->from->toDateString(), $query->to->toDateString()])
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->get();

        foreach ($warranties as $warranty) {
            $rows[] = [
                'sort' => $warranty->end_date->toDateString(),
                'row' => [
                    'kind' => __('vendor.warranty'),
                    'reference' => $warranty->reference ?? '—',
                    'asset_code' => $warranty->asset?->asset_code,
                    'scope' => __('vendor.scope_asset'),
                    'vendor' => $warranty->vendor?->name ?? __('vendor.unnamed_vendor'),
                    'start_date' => $warranty->start_date->format('Y-m-d'),
                    'end_date' => $warranty->end_date->format('Y-m-d'),
                    'days_remaining' => $warranty->daysRemaining(),
                    'value' => null,
                    'status' => __('vendor.warranty_status_'.strtolower($warranty->status)),
                ],
            ];
        }

        $contracts = ServiceContract::query()
            ->with(['asset', 'vendor', 'factory'])
            ->whereBetween('end_date', [$query->from->toDateString(), $query->to->toDateString()])
            ->when($query->factoryId !== null, fn ($q) => $q->where('factory_id', $query->factoryId))
            ->get();

        foreach ($contracts as $contract) {
            $rows[] = [
                'sort' => $contract->end_date->toDateString(),
                'row' => [
                    'kind' => __('vendor.contract'),
                    'reference' => $contract->contract_number,
                    'asset_code' => $contract->asset?->asset_code,
                    'scope' => match (true) {
                        $contract->asset_id !== null => __('vendor.scope_asset'),
                        $contract->factory_id !== null => $contract->factory?->name ?? __('vendor.scope_factory'),
                        default => __('vendor.scope_list'),
                    },
                    'vendor' => $contract->vendor?->name ?? __('vendor.unnamed_vendor'),
                    'start_date' => $contract->start_date->format('Y-m-d'),
                    'end_date' => $contract->end_date->format('Y-m-d'),
                    'days_remaining' => $contract->daysRemaining(),
                    'value' => $contract->value,
                    'status' => __('vendor.contract_status_'.strtolower($contract->status)),
                ],
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        foreach ($rows as $row) {
            yield $row['row'];
        }
    }

    public function estimatedRows(ReportQuery $query): int
    {
        $from = $query->from->toDateString();
        $to = $query->to->toDateString();

        return Warranty::whereBetween('end_date', [$from, $to])->count()
            + ServiceContract::whereBetween('end_date', [$from, $to])->count();
    }

    /**
     * Looking forward by default: this report is about decisions still to be
     * made, so an unfiltered run covers the next year rather than the last
     * thirty days.
     */
    public function defaultPeriod(): array
    {
        $now = CarbonImmutable::now();

        return [$now, $now->addYear()];
    }
}
