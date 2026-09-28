<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\WorkOrder\Actions\RecordLaborEntry;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Time spent on a work order, over the wire (API 11; mirrors the web
 * `LaborEntryController`, which this delegates to unchanged per ADR-003 —
 * `RecordLaborEntry` carries every overlap, terminal-state and duration
 * rule). Time only, never a rate or an amount (ADR-050) — a client that
 * sends one is not validated against, it is simply not read.
 */
class WorkOrderLaborApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $entries = $workOrder->laborEntries()->with('technician:id,name')->orderByDesc('started_at')->get();

        return ApiResponse::ok($entries->map(fn (WorkOrderLaborEntry $e): array => $this->summary($e))->all());
    }

    public function store(Request $request, WorkOrder $workOrder, RecordLaborEntry $action): JsonResponse
    {
        $this->allow('work_order.labor.manage');
        $this->assertReachable($workOrder);

        $data = $request->validate([
            'technician_id' => ['nullable', 'string', 'size:26'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $technician = $this->resolveTechnician($data['technician_id'] ?? null);

        try {
            $entry = $action->handle(
                workOrder: $workOrder,
                startedAt: CarbonImmutable::parse($data['started_at']),
                endedAt: CarbonImmutable::parse($data['ended_at']),
                technician: $technician,
                notes: $data['notes'] ?? null,
                userId: $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created($this->summary($entry->load('technician:id,name')));
    }

    public function destroy(WorkOrder $workOrder, string $entry, RecordLaborEntry $action): JsonResponse
    {
        $this->allow('work_order.labor.manage');
        $this->assertReachable($workOrder);

        $record = WorkOrderLaborEntry::where('work_order_id', $workOrder->id)
            ->where('id', $entry)
            ->firstOrFail();

        try {
            $action->delete($record);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::noContent();
    }

    private function assertReachable(WorkOrder $workOrder): void
    {
        if (! $this->context->canAccessFactory((string) $workOrder->factory_id)) {
            abort(404);
        }
    }

    /**
     * Whose hours this entry is logged against.
     *
     * Holding `work_order.work_order.assign` (a manager or engineer) may log
     * time on somebody else's behalf, so their submitted `technician_id` is
     * trusted as-is. Anyone else is logging their own hours: the technician
     * is derived from the account that is actually logged in and any
     * submitted value is ignored, so one shared login can no longer post
     * time under a different technician's history than the person typing.
     */
    private function resolveTechnician(?string $technicianId): ?Technician
    {
        if ($this->caller()->can('work_order.work_order.assign')) {
            return $technicianId === null ? null : Technician::find($technicianId);
        }

        $user = $this->caller()->user;
        $own = $user === null ? null : Technician::forUser($user);

        if ($own === null) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('work_order.labor_needs_technician'), [
                'technician_id' => [__('work_order.labor_needs_technician')],
            ]);
        }

        return $own;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkOrderLaborEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'technician' => $entry->relationLoaded('technician') && $entry->technician !== null
                ? ['id' => $entry->technician->id, 'name' => $entry->technician->name]
                : ['id' => $entry->technician_id],
            'started_at' => $entry->started_at?->toIso8601String(),
            'ended_at' => $entry->ended_at?->toIso8601String(),
            'minutes' => $entry->minutes,
            'notes' => $entry->notes,
        ];
    }
}
