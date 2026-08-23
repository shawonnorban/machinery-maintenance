<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Work orders (API 11).
 *
 * The transitions are named endpoints rather than a status field a client
 * sets, and that is the important decision here. A work order's lifecycle
 * carries rules — a checklist that must be answered, parts that must be
 * reconciled, verification that must happen before closing — and a client
 * PATCHing `status: CLOSED` would either bypass them or fail with an error
 * that names a field rather than a rule. `POST /close` can answer
 * `PARTS_NOT_RECONCILED`, which tells the caller what to do next.
 */
class WorkOrderApiController extends ApiController
{
    private const FILTERS = [
        'status', 'priority', 'source', 'asset_id', 'factory_id',
        'maintenance_type_id', 'assigned_team_id',
    ];

    private const SORTS = ['work_order_number', 'status', 'priority', 'scheduled_start', 'created_at'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('work_order.work_order.view_any');

        $query = WorkOrder::query()
            ->with(['asset:id,asset_code,name', 'factory:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if ($request->query('open') === 'true') {
            $query->whereNotIn('status', WorkOrder::TERMINAL_STATUSES);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (WorkOrder $workOrder): array => $this->summary($workOrder),
        );
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $workOrder->load(['asset:id,asset_code,name', 'factory:id,name']);

        return ApiResponse::ok($this->detail($workOrder));
    }

    public function start(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.start',
            fn (string $userId) => $action->start($workOrder, $userId),
        );
    }

    public function hold(Request $request, WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        $data = $request->validate([
            // A reason code, not free text. "Why did this job stall" is a
            // question somebody asks of a hundred work orders at once, and
            // free text cannot be counted.
            'reason_code' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->step(
            $workOrder,
            'work_order.work_order.start',
            fn (string $userId) => $action->hold($workOrder, $data['reason_code'], $userId, $data['notes'] ?? null),
        );
    }

    public function resume(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.start',
            fn (string $userId) => $action->resume($workOrder, $userId),
        );
    }

    public function complete(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.complete',
            fn (string $userId) => $action->complete($workOrder, $userId),
        );
    }

    public function verify(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.verify',
            fn (string $userId) => $action->verify($workOrder, $userId),
        );
    }

    public function close(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.close',
            fn (string $userId) => $action->close($workOrder, $userId),
        );
    }

    public function cancel(Request $request, WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return $this->step(
            $workOrder,
            'work_order.work_order.cancel',
            fn (string $userId) => $action->cancel($workOrder, $userId, $data['reason']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkOrder $workOrder): array
    {
        return [
            'id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'title' => $workOrder->title,
            'status' => $workOrder->status,
            'priority' => $workOrder->priority,
            'source' => $workOrder->source,
            'asset' => [
                'id' => $workOrder->asset_id,
                'asset_code' => $workOrder->asset?->asset_code,
                'name' => $workOrder->asset?->name,
            ],
            'factory' => ['id' => $workOrder->factory_id, 'name' => $workOrder->factory?->name],
            'scheduled_start' => $workOrder->scheduled_start?->toIso8601String(),
            'scheduled_end' => $workOrder->scheduled_end?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(WorkOrder $workOrder): array
    {
        return $this->summary($workOrder) + [
            'description' => $workOrder->description,
            'requires_verification' => (bool) $workOrder->requires_verification,
            'requires_shutdown' => (bool) $workOrder->requires_shutdown,
            'approval_status' => $workOrder->approval_status,
            'breakdown_id' => $workOrder->breakdown_id,
            'maintenance_schedule_id' => $workOrder->maintenance_schedule_id,
            'created_at' => $workOrder->created_at?->toIso8601String(),
        ];
    }

    private function step(WorkOrder $workOrder, string $permission, callable $transition): JsonResponse
    {
        $this->allow($permission);
        $this->assertReachable($workOrder);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            // Every one of these lands in the work order's history as "who did
            // this". A machine client has no answer to that question, and
            // writing one down that is not true is worse than refusing.
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return ApiResponse::ok($this->detail($transition($userId)));
    }

    private function assertReachable(WorkOrder $workOrder): void
    {
        if (! $this->context->canAccessFactory((string) $workOrder->factory_id)) {
            abort(404);
        }
    }
}
