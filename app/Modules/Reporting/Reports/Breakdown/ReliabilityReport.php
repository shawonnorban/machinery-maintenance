<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Breakdown;

use App\Modules\Analytics\Services\KpiCalculator;
use App\Modules\Asset\Models\Asset;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * MTBF, MTTR and availability per machine (SRS 32, SRS 31.1).
 *
 * Every figure comes from KpiCalculator. The alternative — a second set of
 * formulas living in the reporting module — is how a factory ends up with a
 * dashboard and a report that disagree, and then with a meeting about which
 * one is right (SRS 31.2 rule 7).
 *
 * A machine with no failures shows an empty MTBF rather than 0. Zero would
 * rank the most reliable machine in the factory as the worst.
 */
class ReliabilityReport extends Report
{
    public function __construct(private readonly KpiCalculator $kpi) {}

    public function key(): string
    {
        return 'reliability';
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function permission(): string
    {
        return 'report.report.view';
    }

    public function filters(): array
    {
        return ['period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'asset_name' => ['label' => 'report.columns.name'],
            'factory' => ['label' => 'report.columns.factory'],
            'failures' => ['label' => 'report.columns.failures', 'numeric' => true],
            'downtime_minutes' => ['label' => 'report.columns.downtime_minutes', 'numeric' => true],
            'mtbf_minutes' => ['label' => 'report.columns.mtbf', 'numeric' => true],
            'mttr_minutes' => ['label' => 'report.columns.mttr', 'numeric' => true],
            'mtta_minutes' => ['label' => 'report.columns.mtta', 'numeric' => true],
            'availability_percent' => ['label' => 'report.columns.availability', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $assets = Asset::query()
            ->with('factory')
            // Rule 4: retired and scrapped machines are not part of a
            // reliability picture, and including them at 0 failures inflates
            // the fleet average.
            ->whereNotIn('status', ['RETIRED', 'SCRAPPED', 'LOST', 'DRAFT'])
            ->when($query->factoryId !== null, fn ($q) => $q->where('current_factory_id', $query->factoryId))
            ->orderBy('asset_code')
            ->lazy();

        foreach ($assets as $asset) {
            $kpis = $this->kpi->forPeriod($query->from, $query->to, [
                'factory_id' => $asset->current_factory_id,
                'asset_id' => $asset->id,
            ]);

            yield [
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->name,
                'factory' => $asset->factory?->name,
                'failures' => $kpis['failure_count'],
                'downtime_minutes' => $kpis['downtime_minutes'],
                'mtbf_minutes' => $kpis['mtbf_minutes'],
                'mttr_minutes' => $kpis['mttr_minutes'],
                'mtta_minutes' => $kpis['mtta_minutes'],
                'availability_percent' => $kpis['availability_percent'],
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return Asset::query()
            ->whereNotIn('status', ['RETIRED', 'SCRAPPED', 'LOST', 'DRAFT'])
            ->when($query->factoryId !== null, fn ($q) => $q->where('current_factory_id', $query->factoryId));
    }
}
