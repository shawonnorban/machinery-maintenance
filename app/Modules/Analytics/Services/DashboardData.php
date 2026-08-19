<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The three dashboards (SRS 30).
 *
 * Three audiences asking different questions, so three sets of figures rather
 * than one screen with everything on it. A manager wants to know what the
 * fleet is costing; a maintenance lead wants to know what is waiting; a
 * storekeeper wants to know what is about to run out. A single dashboard
 * serving all three serves none of them.
 *
 * Every KPI comes from the snapshot reader rather than being recomputed here,
 * so the dashboard and a report cannot disagree (SRS 31.2 rule 7). The reader
 * answers from stored days where it can and scans live where it cannot, which
 * is a latency decision only — the arithmetic is the same either way.
 */
class DashboardData
{
    /**
     * KPI sets already computed in this request, keyed by scope and period.
     *
     * A manager sees the management and maintenance panels on one page, and
     * both want figures for the same window. Computing them twice is the same
     * answer at twice the cost.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $kpiCache = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly KpiSnapshotter $kpi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function kpis(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId): array
    {
        $key = $from->toIso8601String().'|'.$to->toIso8601String().'|'.($factoryId ?? 'all');

        return $this->kpiCache[$key] ??= $this->kpi->forPeriod($from, $to, ['factory_id' => $factoryId]);
    }

    /**
     * What the fleet is doing and what it is costing (SRS 30).
     *
     * @return array<string, mixed>
     */
    public function management(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId = null): array
    {
        $kpis = $this->kpis($from, $to, $factoryId);

        return [
            'kpis' => $kpis,
            'assets' => $this->assetStatusCounts($factoryId),
            'overdue_maintenance' => $this->overdueMaintenanceCount($factoryId),
            'cost' => $this->costBreakdown($from, $to, $factoryId),
            'period' => ['from' => $from, 'to' => $to],
        ];
    }

    /**
     * What is waiting, and who is carrying it.
     *
     * @return array<string, mixed>
     */
    public function maintenance(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId = null): array
    {
        $factoryIds = $factoryId !== null ? [$factoryId] : $this->context->accessibleFactoryIds();
        $today = CarbonImmutable::now()->startOfDay();

        return [
            'today' => MaintenanceSchedule::query()
                ->whereBetween('due_at', [$today, $today->endOfDay()])
                ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                ->count(),
            'due' => MaintenanceSchedule::where('status', 'DUE')->count(),
            // Past the grace period, not merely past the due date: a plan with
            // two days of grace is not late on day one (SRS 31.1).
            'overdue' => $this->overdueMaintenanceCount($factoryId),
            'open_work_orders' => WorkOrder::whereIn('factory_id', $factoryIds)
                ->whereIn('status', WorkOrder::OPEN_STATUSES)
                ->count(),
            'active_breakdowns' => Breakdown::whereIn('factory_id', $factoryIds)
                ->whereIn('status', Breakdown::OPEN_STATUSES)
                ->count(),
            'unacknowledged_breakdowns' => Breakdown::whereIn('factory_id', $factoryIds)
                ->where('status', 'REPORTED')
                ->count(),
            'workload' => $this->technicianWorkload($factoryIds),
            // Through the same reader as every other KPI, so the compliance
            // figure here and the one in a report are the same number.
            'pm_compliance_percent' => $this->kpis($from, $to, $factoryId)['pm_compliance_percent'],
        ];
    }

    /**
     * What the store is holding and what is about to run out.
     *
     * @return array<string, mixed>
     */
    public function store(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $balances = InventoryBalance::with('bin')->get();

        $value = '0.0000';
        $reserved = '0.0000';

        foreach ($balances as $balance) {
            $value = bcadd($value, $balance->totalValue(), 4);
            $reserved = bcadd($reserved, (string) $balance->quantity_reserved, 4);
        }

        $parts = SparePart::where('active', true)
            ->withSum('balances as on_hand', 'quantity_on_hand')
            ->get();

        return [
            'stock_value' => $value,
            'reserved_quantity' => $reserved,
            // Below the reorder level, which is the actionable signal. By the
            // time stock is out the lead time has already been lost.
            'low_stock' => $parts->filter(fn (SparePart $p) => bccomp(
                number_format((float) ($p->on_hand ?? 0), 4, '.', ''),
                (string) ($p->reorder_level ?? '0'),
                4,
            ) <= 0)->count(),
            'out_of_stock' => $parts->filter(fn (SparePart $p) => bccomp(
                number_format((float) ($p->on_hand ?? 0), 4, '.', ''),
                '0',
                4,
            ) <= 0)->count(),
            'critical_low' => $parts->filter(fn (SparePart $p) => $p->is_critical_spare && bccomp(
                number_format((float) ($p->on_hand ?? 0), 4, '.', ''),
                (string) ($p->reorder_level ?? '0'),
                4,
            ) <= 0)->count(),
            'active_reservations' => SparePartReservation::whereIn(
                'status', SparePartReservation::HOLDING_STATUSES,
            )->count(),
            'issued_value' => $this->partsCostInPeriod($from, $to),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function assetStatusCounts(?string $factoryId): array
    {
        $factoryIds = $factoryId !== null ? [$factoryId] : $this->context->accessibleFactoryIds();

        $counts = Asset::query()
            ->whereIn('current_factory_id', $factoryIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $tracked = ['RUNNING', 'IDLE', 'BREAKDOWN', 'UNDER_MAINTENANCE', 'UNDER_REPAIR'];

        $result = ['total' => 0];

        foreach ($tracked as $status) {
            $result[strtolower($status)] = (int) ($counts[$status] ?? 0);
        }

        // Retired and scrapped are excluded from the headline count: a fleet
        // total that keeps growing as machines are scrapped is not a fleet.
        foreach ($counts as $status => $total) {
            if (! in_array($status, ['RETIRED', 'SCRAPPED', 'LOST', 'DRAFT'], true)) {
                $result['total'] += (int) $total;
            }
        }

        return $result;
    }

    private function overdueMaintenanceCount(?string $factoryId): int
    {
        return MaintenanceSchedule::query()
            ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
            ->whereNotNull('grace_until')
            ->where('grace_until', '<', CarbonImmutable::now())
            ->when(
                $factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId)),
            )
            ->count();
    }

    /**
     * @return array<string, string>
     */
    private function costBreakdown(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId): array
    {
        $entries = CostEntry::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->when(
                $factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId)),
            )
            ->with('workOrder:id,breakdown_id')
            ->get();

        $maintenance = '0.0000';
        $breakdown = '0.0000';

        foreach ($entries as $entry) {
            $amount = (string) $entry->base_amount;

            // A cost tied to a breakdown is the cost of a failure; everything
            // else is the cost of keeping machines from failing. Reporting one
            // total hides which of the two the factory is actually paying for.
            if ($entry->breakdown_id !== null || $entry->workOrder?->breakdown_id !== null) {
                $breakdown = bcadd($breakdown, $amount, 4);
            } else {
                $maintenance = bcadd($maintenance, $amount, 4);
            }
        }

        return [
            'maintenance' => $maintenance,
            'breakdown' => $breakdown,
            'total' => bcadd($maintenance, $breakdown, 4),
        ];
    }

    /**
     * Open work per technician.
     *
     * @param  list<string>  $factoryIds
     * @return Collection<int, object>
     */
    private function technicianWorkload(array $factoryIds): Collection
    {
        $openIds = WorkOrder::whereIn('factory_id', $factoryIds)
            ->whereIn('status', WorkOrder::OPEN_STATUSES)
            ->pluck('id');

        $counts = WorkOrderAssignment::query()
            ->whereIn('work_order_id', $openIds)
            ->whereNull('unassigned_at')
            ->selectRaw('technician_id, COUNT(*) as open_count')
            ->groupBy('technician_id')
            ->pluck('open_count', 'technician_id');

        return Technician::whereIn('factory_id', $factoryIds)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'max_concurrent_work_orders'])
            ->map(fn (Technician $t) => (object) [
                'technician' => $t,
                'open_count' => (int) ($counts[$t->id] ?? 0),
                // Shown so a queue of twenty against one person is visible as
                // the planning fiction it is.
                'at_capacity' => $t->max_concurrent_work_orders !== null
                    && (int) ($counts[$t->id] ?? 0) >= $t->max_concurrent_work_orders,
            ]);
    }

    private function partsCostInPeriod(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return (string) number_format(
            (float) CostEntry::where('source_type', 'PARTS')
                ->whereBetween('occurred_at', [$from, $to])
                ->sum('base_amount'),
            4, '.', '',
        );
    }
}
