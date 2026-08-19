<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Breakdown;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every stoppage in the period, with its timestamp chain (SRS 32).
 *
 * The chain is the point. A breakdown is not one moment but seven, and a report
 * that shows only "down for four hours" cannot tell a slow maintenance team
 * from a slow reporting culture — the two need opposite responses.
 *
 * Duplicate reports are included and marked, not dropped. Somebody reading this
 * report is often trying to work out why a machine was reported three times,
 * and a filtered list hides the answer.
 */
class BreakdownAnalysisReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'breakdown_analysis';
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function permission(): string
    {
        return 'breakdown.breakdown.view_any';
    }

    public function filters(): array
    {
        return ['period', 'factory', 'asset'];
    }

    public function columns(): array
    {
        return [
            'breakdown_number' => ['label' => 'report.columns.breakdown'],
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'failure_at' => ['label' => 'report.columns.failure_at'],
            'reported_at' => ['label' => 'report.columns.reported_at'],
            'acknowledged_at' => ['label' => 'report.columns.acknowledged_at'],
            'repair_started_at' => ['label' => 'report.columns.repair_started_at'],
            'production_resumed_at' => ['label' => 'report.columns.production_resumed_at'],
            'severity' => ['label' => 'report.columns.severity'],
            'failure_code' => ['label' => 'report.columns.failure_code'],
            'root_cause' => ['label' => 'report.columns.root_cause'],
            'status' => ['label' => 'report.columns.status'],
            'is_duplicate' => ['label' => 'report.columns.duplicate_report'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $breakdown) {
            yield [
                'breakdown_number' => $breakdown->breakdown_number,
                'asset_code' => $breakdown->asset?->asset_code,
                'failure_at' => $this->timezone->format($breakdown->failure_at),
                'reported_at' => $this->timezone->format($breakdown->reported_at),
                'acknowledged_at' => $this->timezone->format($breakdown->acknowledged_at),
                'repair_started_at' => $this->timezone->format($breakdown->repair_started_at),
                'production_resumed_at' => $this->timezone->format($breakdown->production_resumed_at),
                'severity' => $breakdown->severity,
                'failure_code' => $breakdown->failureCode?->name,
                'root_cause' => $breakdown->rootCause?->name,
                'status' => __('breakdown.status_'.strtolower($breakdown->status)),
                'is_duplicate' => $breakdown->is_recurrence_of_breakdown_id !== null
                    ? __('common.yes')
                    : __('common.no'),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return Breakdown::query()
            ->with(['asset', 'failureCode', 'rootCause'])
            ->whereBetween('failure_at', [$query->from, $query->to])
            ->when($query->factoryId !== null, fn ($q) => $q->where('factory_id', $query->factoryId))
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->orderBy('failure_at');
    }
}
