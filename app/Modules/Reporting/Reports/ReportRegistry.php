<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Reports\Asset\AssetRegisterReport;
use App\Modules\Reporting\Reports\Asset\AssetStatusReport;
use App\Modules\Reporting\Reports\Asset\AssetTransferReport;
use App\Modules\Reporting\Reports\Breakdown\BreakdownAnalysisReport;
use App\Modules\Reporting\Reports\Breakdown\DowntimeReport;
use App\Modules\Reporting\Reports\Breakdown\ReliabilityReport;
use App\Modules\Reporting\Reports\Breakdown\RootCauseReport;
use App\Modules\Reporting\Reports\Cost\LifecycleCostReport;
use App\Modules\Reporting\Reports\Cost\MaintenanceCostReport;
use App\Modules\Reporting\Reports\Inventory\InventoryValuationReport;
use App\Modules\Reporting\Reports\Inventory\PartsConsumptionReport;
use App\Modules\Reporting\Reports\Maintenance\MaintenanceHistoryReport;
use App\Modules\Reporting\Reports\Maintenance\OverdueMaintenanceReport;
use App\Modules\Reporting\Reports\Maintenance\PmComplianceReport;
use App\Modules\Reporting\Reports\People\TechnicianPerformanceReport;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Every report the product offers (SRS 32).
 *
 * A registry rather than a database table. Reports are code — each one is a
 * query and a set of columns — and a row saying a report exists when no class
 * implements it is a broken link on a screen somebody trusted.
 *
 * Two reports from SRS 32 are absent: vendor performance and warranty/AMC
 * expiry. Both read vendor and contract data that does not exist yet, and
 * listing them now would offer an empty file rather than an answer.
 */
class ReportRegistry
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        AssetRegisterReport::class,
        AssetStatusReport::class,
        AssetTransferReport::class,
        MaintenanceHistoryReport::class,
        PmComplianceReport::class,
        OverdueMaintenanceReport::class,
        BreakdownAnalysisReport::class,
        RootCauseReport::class,
        DowntimeReport::class,
        ReliabilityReport::class,
        MaintenanceCostReport::class,
        LifecycleCostReport::class,
        PartsConsumptionReport::class,
        InventoryValuationReport::class,
        TechnicianPerformanceReport::class,
    ];

    /** The order groups appear on the index. */
    public const GROUPS = ['asset', 'maintenance', 'breakdown', 'cost', 'inventory', 'people'];

    /** @var array<string, Report>|null */
    private ?array $resolved = null;

    /**
     * @return array<string, Report>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $reports = [];

        foreach (self::REPORTS as $class) {
            $report = app($class);
            $reports[$report->key()] = $report;
        }

        return $this->resolved = $reports;
    }

    public function find(string $key): Report
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown report [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * What this person may run.
     *
     * Every report carries the permission of the data it exposes, not a blanket
     * reporting permission. A viewer who cannot see costs on a work order must
     * not be able to export the same figures as a spreadsheet (SRS 33).
     *
     * @return Collection<string, Report>
     */
    public function availableTo(User $user): Collection
    {
        return collect($this->all())
            ->filter(fn (Report $report) => $user->can('report.report.view') && $user->can($report->permission()));
    }

    /**
     * @return Collection<string, Collection<string, Report>>
     */
    public function groupedFor(User $user): Collection
    {
        return $this->availableTo($user)
            ->groupBy(fn (Report $report) => $report->group())
            ->sortBy(fn ($group, $key) => array_search($key, self::GROUPS, true));
    }
}
