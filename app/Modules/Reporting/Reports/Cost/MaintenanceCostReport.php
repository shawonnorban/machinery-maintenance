<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Cost;

use App\Modules\Costing\Models\CostEntry;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * What maintenance cost, entry by entry (SRS 32, SRS 25).
 *
 * Reversals are shown, not netted away. The ledger is append-only precisely so
 * a corrected figure leaves both rows visible; a report that quietly collapses
 * them produces a number nobody can trace back to the entries behind it, which
 * is the first thing an auditor asks for.
 *
 * Both the entered amount and the base amount appear. A factory buying parts in
 * dollars and reporting in taka needs to see the rate that was used, not just
 * the converted total.
 */
class MaintenanceCostReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'maintenance_cost';
    }

    public function group(): string
    {
        return 'cost';
    }

    public function permission(): string
    {
        return 'cost.entry.view';
    }

    public function filters(): array
    {
        return ['period', 'factory', 'asset'];
    }

    public function columns(): array
    {
        return [
            'occurred_at' => ['label' => 'report.columns.occurred_at'],
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'work_order' => ['label' => 'report.columns.work_order'],
            'category' => ['label' => 'report.columns.cost_category'],
            'source' => ['label' => 'report.columns.source'],
            'description' => ['label' => 'report.columns.description'],
            'currency' => ['label' => 'report.columns.currency'],
            'amount' => ['label' => 'report.columns.amount', 'numeric' => true],
            'exchange_rate' => ['label' => 'report.columns.exchange_rate', 'numeric' => true],
            'base_amount' => ['label' => 'report.columns.base_amount', 'numeric' => true],
            'is_reversal' => ['label' => 'report.columns.reversal'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $entry) {
            yield [
                'occurred_at' => $this->timezone->format($entry->occurred_at),
                'asset_code' => $entry->asset?->asset_code,
                'work_order' => $entry->workOrder?->work_order_number,
                'category' => $entry->category?->name,
                'source' => $entry->source_type,
                'description' => $entry->description,
                'currency' => $entry->currency,
                'amount' => $entry->amount,
                'exchange_rate' => $entry->exchange_rate,
                'base_amount' => $entry->base_amount,
                'is_reversal' => $entry->is_reversal ? __('common.yes') : __('common.no'),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return CostEntry::query()
            ->with(['asset', 'workOrder', 'category'])
            ->whereBetween('occurred_at', [$query->from, $query->to])
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->when(
                $query->factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $query->factoryId)),
            )
            ->orderBy('occurred_at');
    }
}
