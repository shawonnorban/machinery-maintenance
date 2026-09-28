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
use App\Shared\Support\Sql;
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
        [$previousFrom, $previousTo] = $this->previousWindow($from, $to);
        $previous = $this->kpis($previousFrom, $previousTo, $factoryId);

        return [
            'kpis' => $kpis + [
                'availability_trend' => $this->percentChange($kpis['availability_percent'], $previous['availability_percent']),
                'mtbf_trend' => $this->percentChange($kpis['mtbf_minutes'], $previous['mtbf_minutes']),
                // Lower is better for both of these, so the arrow the frontend
                // draws from a positive/negative sign would read backwards —
                // the sign is inverted here so "trend > 0" always means
                // "improved," the same convention every other trend uses.
                'mttr_trend' => $this->percentChange($kpis['mttr_minutes'], $previous['mttr_minutes'], invert: true),
                'mtta_trend' => $this->percentChange($kpis['mtta_minutes'], $previous['mtta_minutes'], invert: true),
            ],
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
            'active_breakdowns_trend' => $this->percentChange(
                Breakdown::whereIn('factory_id', $factoryIds)->whereBetween('reported_at', [$from, $to])->count(),
                $this->countInWindow(Breakdown::class, 'reported_at', $this->previousWindow($from, $to), $factoryIds),
                invert: true,
            ),
            'completed_work_orders_trend' => $this->percentChange(
                WorkOrder::whereIn('factory_id', $factoryIds)->whereBetween('completed_at', [$from, $to])->count(),
                $this->countInWindow(WorkOrder::class, 'completed_at', $this->previousWindow($from, $to), $factoryIds),
            ),
        ];
    }

    /**
     * A daily count of breakdowns reported and work orders completed, for
     * the trend chart every dashboard shares — the same two figures the
     * stat cards above summarise for the whole period, broken out by day so
     * the shape of the period (a bad week buried in an otherwise fine
     * month) is visible rather than averaged away.
     *
     * @return list<array{date: string, breakdowns: int, completed_work_orders: int}>
     */
    public function trend(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId = null): array
    {
        $factoryIds = $factoryId !== null ? [$factoryId] : $this->context->accessibleFactoryIds();

        $breakdownsByDay = Breakdown::whereIn('factory_id', $factoryIds)
            ->whereBetween('reported_at', [$from, $to])
            ->selectRaw(Sql::dayBucket('reported_at').', COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $completedByDay = WorkOrder::whereIn('factory_id', $factoryIds)
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw(Sql::dayBucket('completed_at').', COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];
        $cursor = $from->startOfDay();
        $last = $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->toDateString();

            $days[] = [
                'date' => $key,
                'breakdowns' => (int) ($breakdownsByDay[$key] ?? 0),
                'completed_work_orders' => (int) ($completedByDay[$key] ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
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
            'total_parts' => $parts->count(),
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

    /**
     * The equal-length period immediately before this one, for "vs last
     * period" trend badges. A 30-day window compares against the 30 days
     * before it, not a calendar month — the two windows always match in
     * length, so a shorter reference period doesn't inflate the percentage.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousWindow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $length = $from->diffInSeconds($to);

        return [$from->subSeconds($length), $from];
    }

    /**
     * @param  class-string  $model
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window
     * @param  list<string>  $factoryIds
     */
    private function countInWindow(string $model, string $column, array $window, array $factoryIds): int
    {
        return $model::whereIn('factory_id', $factoryIds)
            ->whereBetween($column, $window)
            ->count();
    }

    /**
     * Percentage change from `$previous` to `$current`, or null when there
     * is nothing to compare against — a "+400%" badge off a previous value
     * of zero is noise, not a signal.
     *
     * @param  bool  $invert  set for a figure where lower is better (MTTR,
     *                        MTTA, breakdown count), so a positive trend
     *                        always means "improved" regardless of which
     *                        direction the raw number actually moved.
     */
    private function percentChange(?float $current, ?float $previous, bool $invert = false): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        $change = round((($current - $previous) / $previous) * 100, 1);

        return $invert ? -$change : $change;
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
