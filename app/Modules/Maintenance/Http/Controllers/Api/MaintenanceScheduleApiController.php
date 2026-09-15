<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Api;

use App\Modules\Maintenance\Actions\CompleteSchedule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Maintenance schedules, over the wire (API 9; mirrors the web
 * `ScheduleController`, which this delegates every write to unchanged per
 * ADR-003) — one concrete occurrence of a plan.
 */
class MaintenanceScheduleApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allow('maintenance.schedule.view_any');

        $query = MaintenanceSchedule::query()
            ->with(['asset:id,asset_code,name,current_factory_id', 'plan:id,name,priority']);

        if (is_string($factoryId = $request->query('factory_id')) && $factoryId !== '') {
            $query->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId));
        }

        $status = $request->query('status');

        if ($status === 'OVERDUE') {
            // Overdue means past the grace period, not merely past the due
            // date (SRS 31.1). Reporting a plan late on day one of a
            // two-day grace would make compliance figures meaningless.
            $query->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                ->where(fn ($w) => $w->whereNotNull('grace_until')->where('grace_until', '<', now()));
        } elseif (is_string($status) && $status !== '') {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', MaintenanceSchedule::OPEN_STATUSES);
        }

        if (is_string($dueFrom = $request->query('due_from')) && $dueFrom !== '') {
            $query->where('due_at', '>=', CarbonImmutable::parse($dueFrom));
        }

        if (is_string($dueTo = $request->query('due_to')) && $dueTo !== '') {
            $query->where('due_at', '<=', CarbonImmutable::parse($dueTo));
        }

        $query = $this->applyFilters($query, $request, ['asset_id', 'maintenance_plan_id']);
        $query = $this->applySort($query, $request, ['due_at', 'status'], 'due_at', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (MaintenanceSchedule $schedule): array => $this->summary($schedule),
        );
    }

    /**
     * The KPI strip `ScheduleController::index()`'s Blade view shows above
     * its own status pills — dropped when this list was first ported and
     * restored here rather than folded into `index()`'s response, the same
     * reasoning as `WorkOrderApiController::counts()`.
     *
     * @return array<string, int>
     */
    public function counts(): JsonResponse
    {
        $this->allow('maintenance.schedule.view_any');

        return ApiResponse::ok([
            'due' => MaintenanceSchedule::whereIn('status', ['DUE'])->count(),
            'overdue' => MaintenanceSchedule::whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                ->whereNotNull('grace_until')->where('grace_until', '<', now())->count(),
            'planned' => MaintenanceSchedule::where('status', 'PLANNED')->count(),
        ]);
    }

    public function show(MaintenanceSchedule $schedule): JsonResponse
    {
        $this->allow('maintenance.schedule.view_any');

        $schedule->load(['asset:id,asset_code,name', 'plan:id,name,priority']);

        return ApiResponse::ok($this->summary($schedule));
    }

    public function complete(MaintenanceSchedule $schedule, CompleteSchedule $action): JsonResponse
    {
        $this->allow('maintenance.schedule.view_any');

        return ApiResponse::ok($this->summary(
            $action->handle($schedule, CarbonImmutable::now(), $this->caller()->auditUserId()),
        ));
    }

    public function skip(Request $request, MaintenanceSchedule $schedule, CompleteSchedule $action): JsonResponse
    {
        $this->allow('maintenance.schedule.skip');

        $data = $request->validate([
            'skipped_reason' => ['required', 'string', 'max:255'],
        ]);

        return ApiResponse::ok($this->summary(
            $action->skip($schedule, $data['skipped_reason'], $this->caller()->auditUserId()),
        ));
    }

    public function reschedule(Request $request, MaintenanceSchedule $schedule, CompleteSchedule $action): JsonResponse
    {
        $this->allow('maintenance.schedule.reschedule');

        $data = $request->validate([
            'due_at' => ['required', 'date'],
            'rescheduled_reason' => ['required', 'string', 'max:255'],
        ]);

        return ApiResponse::ok($this->summary($action->reschedule(
            $schedule,
            CarbonImmutable::parse($data['due_at']),
            $data['rescheduled_reason'],
            $this->caller()->auditUserId(),
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MaintenanceSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'maintenance_plan_id' => $schedule->maintenance_plan_id,
            'plan_name' => $schedule->plan?->name,
            'asset' => $schedule->asset === null ? null : [
                'id' => $schedule->asset->id,
                'asset_code' => $schedule->asset->asset_code,
                'name' => $schedule->asset->name,
            ],
            'status' => $schedule->status,
            'is_overdue' => $schedule->isOverdue(),
            'due_at' => $schedule->due_at?->toIso8601String(),
            // Mirrors `plans/show.blade.php`'s occurrences table exactly —
            // a meter-triggered occurrence's target reading, and what fired
            // it (the plan's own schedule vs. a meter reading crossing the
            // threshold), neither of which anything had asked this endpoint
            // for before.
            'due_meter' => $schedule->due_meter,
            'triggered_by' => $schedule->triggered_by,
            'grace_until' => $schedule->grace_until?->toIso8601String(),
            'completed_at' => $schedule->completed_at?->toIso8601String(),
            'rescheduled_from_due_at' => $schedule->rescheduled_from_due_at?->toIso8601String(),
            'rescheduled_reason' => $schedule->rescheduled_reason,
            'skipped_reason' => $schedule->skipped_reason,
            'work_order_id' => $schedule->work_order_id,
        ];
    }
}
