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
            'top_failing_assets' => $this->topFailingAssets($from, $to, $factoryId),
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
            // The rows behind the counts above, so the dashboard can say which
            // machine is down and which plan is late, not only how many.
            'open_breakdown_list' => $this->openBreakdownList($factoryIds),
            'pm_queue' => $this->pmQueue($factoryId),
            'upcoming_week' => MaintenanceSchedule::query()
                ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                ->whereBetween('due_at', [CarbonImmutable::now(), CarbonImmutable::now()->addDays(7)->endOfDay()])
                ->when(
                    $factoryId !== null,
                    fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId)),
                )
                ->count(),
            // Through the same reader as every other KPI, so the compliance
            // figure here and the one in a report are the same number.
            'pm_compliance_percent' => $this->kpis($from, $to, $factoryId)['pm_compliance_percent'],
            'breakdowns_reported' => Breakdown::whereIn('factory_id', $factoryIds)->whereBetween('reported_at', [$from, $to])->count(),
            'active_breakdowns_trend' => $this->percentChange(
                Breakdown::whereIn('factory_id', $factoryIds)->whereBetween('reported_at', [$from, $to])->count(),
                $this->countInWindow(Breakdown::class, 'reported_at', $this->previousWindow($from, $to), $factoryIds),
                invert: true,
            ),
            'completed_work_orders' => WorkOrder::whereIn('factory_id', $factoryIds)->whereBetween('completed_at', [$from, $to])->count(),
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

        $onHand = fn (SparePart $p): string => number_format((float) ($p->on_hand ?? 0), 4, '.', '');

        // Out of stock first, then critical spares, then whoever is furthest
        // below their reorder level: the order a storekeeper should reorder in.
        $lowStockItems = $parts
            ->filter(fn (SparePart $p) => bccomp($onHand($p), (string) ($p->reorder_level ?? '0'), 4) <= 0)
            ->sortBy(fn (SparePart $p) => [
                bccomp($onHand($p), '0', 4) <= 0 ? 0 : 1,
                $p->is_critical_spare ? 0 : 1,
                (float) ($p->reorder_level ?? 0) > 0 ? (float) $onHand($p) / (float) $p->reorder_level : 1,
                $p->part_number,
            ])
            ->take(8)
            ->map(fn (SparePart $p): array => [
                'id' => $p->id,
                'part_number' => $p->part_number,
                'name' => $p->name,
                'unit' => $p->unit,
                'on_hand' => $onHand($p),
                'reorder_level' => (string) ($p->reorder_level ?? '0'),
                'is_critical_spare' => (bool) $p->is_critical_spare,
            ])
            ->values()
            ->all();

        return [
            'low_stock_items' => $lowStockItems,
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
     * The machines that failed most often in the period.
     *
     * @return list<array<string, mixed>>
     */
    private function topFailingAssets(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId): array
    {
        $factoryIds = $factoryId !== null ? [$factoryId] : $this->context->accessibleFactoryIds();

        $counts = Breakdown::whereIn('factory_id', $factoryIds)
            ->whereBetween('reported_at', [$from, $to])
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw('asset_id, COUNT(*) as failures, MAX(reported_at) as last_reported_at')
            ->groupBy('asset_id')
            ->orderByDesc('failures')
            ->orderByDesc('last_reported_at')
            ->limit(5)
            ->get();

        $assets = Asset::whereIn('id', $counts->pluck('asset_id'))->get(['id', 'asset_code', 'name', 'status'])->keyBy('id');

        return $counts
            ->filter(fn ($row) => $assets->has($row->asset_id))
            ->map(fn ($row): array => [
                'asset_id' => $row->asset_id,
                'asset_code' => $assets[$row->asset_id]->asset_code,
                'name' => $assets[$row->asset_id]->name,
                'status' => $assets[$row->asset_id]->status,
                'failures' => (int) $row->failures,
                'last_reported_at' => CarbonImmutable::parse($row->last_reported_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Open breakdowns, most urgent first: unacknowledged before acknowledged,
     * then by priority, then oldest.
     *
     * @param  list<string>  $factoryIds
     * @return list<array<string, mixed>>
     */
    private function openBreakdownList(array $factoryIds): array
    {
        $rank = function (Breakdown $b): array {
            $priority = array_search($b->priority, Breakdown::PRIORITIES, true);

            return [
                $b->status === 'REPORTED' ? 0 : 1,
                $priority === false ? 9 : $priority,
                $b->reported_at?->getTimestamp() ?? 0,
            ];
        };

        return Breakdown::whereIn('factory_id', $factoryIds)
            ->whereIn('status', Breakdown::OPEN_STATUSES)
            ->with(['asset:id,asset_code,name', 'productionLine:id,name'])
            ->get(['id', 'breakdown_number', 'asset_id', 'production_line_id', 'status', 'priority', 'reported_at'])
            ->sortBy($rank)
            ->take(6)
            ->map(fn (Breakdown $b): array => [
                'id' => $b->id,
                'breakdown_number' => $b->breakdown_number,
                'status' => $b->status,
                'priority' => $b->priority,
                'asset_code' => $b->asset?->asset_code,
                'asset_name' => $b->asset?->name,
                'line' => $b->productionLine?->name,
                'reported_at' => $b->reported_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Open schedules that are late or due within a week, earliest first.
     *
     * @return list<array<string, mixed>>
     */
    private function pmQueue(?string $factoryId): array
    {
        $now = CarbonImmutable::now();

        return MaintenanceSchedule::query()
            ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now->addDays(7)->endOfDay())
            ->when(
                $factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId)),
            )
            ->with(['asset:id,asset_code,name', 'plan:id,plan_number,name'])
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn (MaintenanceSchedule $s): array => [
                'id' => $s->id,
                'status' => $s->status,
                'plan_number' => $s->plan?->plan_number,
                'plan_name' => $s->plan?->name,
                'asset_code' => $s->asset?->asset_code,
                'asset_name' => $s->asset?->name,
                'due_at' => $s->due_at?->toIso8601String(),
                'overdue' => $s->grace_until !== null && $s->grace_until->lessThan($now),
            ])
            ->values()
            ->all();
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
