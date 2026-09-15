<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers\Api;

use App\Modules\Analytics\Services\DashboardData;
use App\Modules\Analytics\Services\KpiSnapshotter;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The three dashboards, over the wire (API 21; mirrors the web
 * `DashboardController`, which reads from the same `DashboardData` service
 * per ADR-003 — the dashboard and a report must never disagree, SRS 31.2
 * rule 7).
 *
 * Each panel sits behind its own permission, same as the web screen: a
 * storekeeper calling the management endpoint should not pay for a
 * fleet-wide availability scan it has no use for and cannot reach.
 */
class DashboardApiController extends ApiController
{
    /** Longest period offered. A year of raw scanning is a report, not a tile. */
    private const PERIODS = [7, 30, 90];

    public function __construct(
        private readonly TenantContext $context,
        private readonly DashboardData $data,
    ) {}

    public function management(Request $request): JsonResponse
    {
        $this->allow('dashboard.management.view');

        [$from, $to, $factoryId] = $this->window($request);

        return ApiResponse::ok($this->data->management($from, $to, $factoryId) + [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
        ]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $this->allow('dashboard.maintenance.view');

        [$from, $to, $factoryId] = $this->window($request);

        $panel = $this->data->maintenance($from, $to, $factoryId);
        $panel['workload'] = collect($panel['workload'])->map(fn (object $row): array => [
            'technician_id' => $row->technician->id,
            'name' => $row->technician->name,
            'employee_id' => $row->technician->employee_id,
            'open_count' => $row->open_count,
            'max_concurrent_work_orders' => $row->technician->max_concurrent_work_orders,
            'at_capacity' => $row->at_capacity,
        ])->values()->all();

        return ApiResponse::ok($panel);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('dashboard.store.view');

        [$from, $to] = $this->window($request);

        return ApiResponse::ok($this->data->store($from, $to));
    }

    /**
     * The daily breakdown/completion counts behind the trend chart every
     * panel shares. Gated the same way `kpis()` is — the shared component
     * behind all three dashboards, not a fourth audience of its own.
     */
    public function trend(Request $request): JsonResponse
    {
        if (! $this->caller()->can('dashboard.management.view')
            && ! $this->caller()->can('dashboard.maintenance.view')
            && ! $this->caller()->can('dashboard.store.view')) {
            abort(403);
        }

        [$from, $to, $factoryId] = $this->window($request);

        return ApiResponse::ok($this->data->trend($from, $to, $factoryId));
    }

    /**
     * The raw KPI set for the period, the same figures every panel and
     * every report reads (SRS 31.2 rule 7). Gated on whichever dashboard
     * permission the caller holds, since it is the shared component behind
     * all three rather than a fourth audience of its own.
     */
    public function kpis(Request $request, KpiSnapshotter $snapshotter): JsonResponse
    {
        if (! $this->caller()->can('dashboard.management.view')
            && ! $this->caller()->can('dashboard.maintenance.view')
            && ! $this->caller()->can('dashboard.store.view')) {
            abort(403);
        }

        [$from, $to, $factoryId] = $this->window($request);

        return ApiResponse::ok($snapshotter->forPeriod($from, $to, ['factory_id' => $factoryId]));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: ?string}
     */
    private function window(Request $request): array
    {
        $days = in_array((int) $request->query('days'), self::PERIODS, true)
            ? (int) $request->query('days')
            : 30;

        $to = CarbonImmutable::now();
        $from = $to->subDays($days)->startOfDay();

        $factoryId = $request->query('factory_id');

        if (is_string($factoryId) && $factoryId !== '' && $this->context->canAccessFactory($factoryId)) {
            return [$from, $to, $factoryId];
        }

        return [$from, $to, null];
    }
}
