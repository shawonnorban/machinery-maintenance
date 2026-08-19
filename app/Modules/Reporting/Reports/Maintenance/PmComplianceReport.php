<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Maintenance;

use App\Modules\Analytics\Services\KpiCalculator;
use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * Preventive maintenance compliance per machine (SRS 32, SRS 31.1).
 *
 * The percentage comes from KpiCalculator, not from arithmetic repeated here.
 * A compliance figure that differs between the dashboard and the report is
 * worse than having neither, because somebody will quote the flattering one
 * (SRS 31.2 rule 7).
 *
 * Machines with nothing due in the period are left out entirely rather than
 * shown at 0% or 100%. Neither is true of a machine that was not scheduled for
 * anything.
 */
class PmComplianceReport extends Report
{
    public function __construct(private readonly KpiCalculator $kpi) {}

    public function key(): string
    {
        return 'pm_compliance';
    }

    public function group(): string
    {
        return 'maintenance';
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
            'due' => ['label' => 'report.columns.pm_due', 'numeric' => true],
            'completed' => ['label' => 'report.columns.pm_completed', 'numeric' => true],
            'on_time' => ['label' => 'report.columns.pm_on_time', 'numeric' => true],
            'overdue' => ['label' => 'report.columns.pm_overdue', 'numeric' => true],
            'compliance_percent' => ['label' => 'report.columns.compliance', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $assetIds = MaintenanceSchedule::query()
            ->whereBetween('due_at', [$query->from, $query->to])
            ->whereNotIn('status', ['SKIPPED', 'CANCELLED'])
            ->distinct()
            ->pluck('asset_id');

        $assets = Asset::query()
            ->with('factory')
            ->whereIn('id', $assetIds)
            ->when($query->factoryId !== null, fn ($q) => $q->where('current_factory_id', $query->factoryId))
            ->orderBy('asset_code')
            ->get();

        foreach ($assets as $asset) {
            $schedules = MaintenanceSchedule::query()
                ->where('asset_id', $asset->id)
                ->whereBetween('due_at', [$query->from, $query->to])
                ->whereNotIn('status', ['SKIPPED', 'CANCELLED'])
                ->get();

            $completed = $schedules->whereNotNull('completed_at');

            $onTime = $completed->filter(
                fn (MaintenanceSchedule $s) => $s->completed_at->lessThanOrEqualTo($s->grace_until ?? $s->due_at),
            );

            yield [
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->name,
                'factory' => $asset->factory?->name,
                'due' => $schedules->count(),
                'completed' => $completed->count(),
                'on_time' => $onTime->count(),
                'overdue' => $schedules->where('status', 'OVERDUE')->count(),
                'compliance_percent' => $this->kpi->pmCompliance(
                    $query->from, $query->to, $query->factoryId, $asset->id,
                ),
            ];
        }
    }

    public function estimatedRows(ReportQuery $query): int
    {
        return MaintenanceSchedule::query()
            ->whereBetween('due_at', [$query->from, $query->to])
            ->whereNotIn('status', ['SKIPPED', 'CANCELLED'])
            ->distinct()
            ->count('asset_id');
    }
}
