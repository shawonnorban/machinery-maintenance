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
use App\Modules\Reporting\Reports\Compliance\BondUtilisationReport;
use App\Modules\Reporting\Reports\Compliance\DutyExemptionSummaryReport;
use App\Modules\Reporting\Reports\Compliance\UtilisationReconciliationReport;
use App\Modules\Reporting\Reports\Cost\LifecycleCostReport;
use App\Modules\Reporting\Reports\Cost\MaintenanceCostReport;
use App\Modules\Reporting\Reports\Inventory\InventoryValuationReport;
use App\Modules\Reporting\Reports\Inventory\ItemStockAgeingReport;
use App\Modules\Reporting\Reports\Inventory\ItemStockValuationReport;
use App\Modules\Reporting\Reports\Inventory\LotExpiryReport;
use App\Modules\Reporting\Reports\Inventory\PartsConsumptionReport;
use App\Modules\Reporting\Reports\Inventory\ReservedVsAvailableReport;
use App\Modules\Reporting\Reports\Inventory\RollInspectionRegisterReport;
use App\Modules\Reporting\Reports\Inventory\RollLedgerReport;
use App\Modules\Reporting\Reports\Inventory\RollLineageReport;
use App\Modules\Reporting\Reports\Inventory\ShadeWiseStockReport;
use App\Modules\Reporting\Reports\Inventory\SlowMovingItemReport;
use App\Modules\Reporting\Reports\Procurement\AdvanceOutstandingReport;
use App\Modules\Reporting\Reports\Procurement\ClaimRegisterReport;
use App\Modules\Reporting\Reports\Procurement\FabricBookingStatusReport;
use App\Modules\Reporting\Reports\Procurement\LandedCostSummaryReport;
use App\Modules\Reporting\Reports\Procurement\LetterOfCreditOutstandingReport;
use App\Modules\Reporting\Reports\Procurement\PendingOrdersReport;
use App\Modules\Reporting\Reports\Procurement\PendingRequisitionsReport;
use App\Modules\Reporting\Reports\Procurement\PurchaseReturnRegisterReport;
use App\Modules\Reporting\Reports\Procurement\ShipmentEtaReport;
use App\Modules\Reporting\Reports\Procurement\SupplierScorecardReport;
use App\Modules\Reporting\Reports\Requisition\BorrowedItemReport;
use App\Modules\Reporting\Reports\Requisition\FloorRequisitionRegisterReport;
use App\Modules\Reporting\Reports\Requisition\PendingFloorRequisitionReport;
use App\Modules\Reporting\Reports\Requisition\RequisitionConsumptionReport;
use App\Modules\Reporting\Reports\Requisition\RequisitionTraceabilityReport;
use App\Modules\Reporting\Reports\Maintenance\MaintenanceHistoryReport;
use App\Modules\Reporting\Reports\Maintenance\OverdueMaintenanceReport;
use App\Modules\Reporting\Reports\Maintenance\PmComplianceReport;
use App\Modules\Reporting\Reports\People\TechnicianPerformanceReport;
use App\Modules\Reporting\Reports\Vendor\CoverageExpiryReport;
use App\Modules\Reporting\Reports\Vendor\VendorPerformanceReport;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Every report the product offers (SRS 32).
 *
 * A registry rather than a database table. Reports are code — each one is a
 * query and a set of columns — and a row saying a report exists when no class
 * implements it is a broken link on a screen somebody trusted.
 *
 * All eighteen reports named in SRS 32 are here. Vendor performance and
 * warranty/AMC expiry arrived last, with the vendor module that holds the data
 * they read.
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
        // The item library's own stock (Inventory spec §14). Separate books
        // from the spare-parts catalogue above — fabric, yarn, trims and
        // chemicals, with rolls tracked piece by piece rather than pooled —
        // which is why "valuation" appears twice: merging the two on one
        // screen would add up stock nobody holds together.
        ItemStockValuationReport::class,
        ReservedVsAvailableReport::class,
        ItemStockAgeingReport::class,
        SlowMovingItemReport::class,
        LotExpiryReport::class,
        RollLedgerReport::class,
        ShadeWiseStockReport::class,
        RollInspectionRegisterReport::class,
        RollLineageReport::class,
        // Buying, and the floor asking for it (Procurement spec §17,
        // Floor/Store Requisition spec §10). Their own groups rather than
        // `inventory`: these are about documents and who is waiting on
        // them, not about what is on a shelf.
        PendingRequisitionsReport::class,
        PendingOrdersReport::class,
        FabricBookingStatusReport::class,
        LetterOfCreditOutstandingReport::class,
        ShipmentEtaReport::class,
        LandedCostSummaryReport::class,
        PurchaseReturnRegisterReport::class,
        ClaimRegisterReport::class,
        AdvanceOutstandingReport::class,
        SupplierScorecardReport::class,
        FloorRequisitionRegisterReport::class,
        PendingFloorRequisitionReport::class,
        RequisitionTraceabilityReport::class,
        RequisitionConsumptionReport::class,
        BorrowedItemReport::class,
        // Bonded warehouse and UD/UP (Shared Systems spec §3.5). Their
        // own group: this is customs paperwork, not stock or buying.
        BondUtilisationReport::class,
        UtilisationReconciliationReport::class,
        DutyExemptionSummaryReport::class,
        TechnicianPerformanceReport::class,
        VendorPerformanceReport::class,
        CoverageExpiryReport::class,
    ];

    /** The order groups appear on the index. */
    public const GROUPS = [
        'asset', 'maintenance', 'breakdown', 'cost', 'inventory',
        'procurement', 'requisition', 'compliance', 'people', 'vendor',
    ];

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
