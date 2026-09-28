<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Putting technicians on a work order, over the wire (API 11; mirrors the
 * web `WorkOrderTransitionController::assign/unassign`, which this
 * delegates to unchanged per ADR-003 — `AssignTechnicians` carries every
 * capacity, factory-match and terminal-state rule).
 */
class WorkOrderAssignmentApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, WorkOrder $workOrder, AssignTechnicians $action): JsonResponse
    {
        $this->allow('work_order.work_order.assign');
        $this->assertReachable($workOrder);

        $data = $request->validate([
            'technician_ids' => ['required', 'array', 'min:1'],
            'technician_ids.*' => ['string', 'size:26'],
        ]);

        try {
            $updated = $action->handle($workOrder, $data['technician_ids'], $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::ok($this->summary($updated));
    }

    public function destroy(Request $request, WorkOrder $workOrder, AssignTechnicians $action): JsonResponse
    {
        $this->allow('work_order.work_order.assign');
        $this->assertReachable($workOrder);

        $data = $request->validate(['technician_id' => ['required', 'string', 'size:26']]);

        try {
            $updated = $action->unassign($workOrder, $data['technician_id'], $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::ok($this->summary($updated));
    }

    private function translate(ValidationException $e): ApiException
    {
        $status = $e->status ?? 422;
        $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

        return ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
    }

    private function assertReachable(WorkOrder $workOrder): void
    {
        if (! $this->context->canAccessFactory((string) $workOrder->factory_id)) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkOrder $workOrder): array
    {
        $workOrder->load('activeAssignments.technician:id,name,employee_id');

        return [
            'id' => $workOrder->id,
            'status' => $workOrder->status,
            'version' => $workOrder->version,
            'assignments' => $workOrder->activeAssignments->map(fn ($a): array => [
                'technician_id' => $a->technician_id,
                'technician_name' => $a->technician?->name,
                'assigned_at' => $a->assigned_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
