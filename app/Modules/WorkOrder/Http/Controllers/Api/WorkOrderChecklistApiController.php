<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\WorkOrder\Actions\RecordChecklistResult;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderChecklistResult;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A work order's executed checklist, over the wire (API 11; mirrors the web
 * `ChecklistExecutionController`, which this delegates to unchanged per
 * ADR-003 — `RecordChecklistResult` carries every fail-needs-note,
 * fail-needs-photo, tolerance and follow-up rule).
 */
class WorkOrderChecklistApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $items = $workOrder->template_version_id !== null
            ? (MaintenanceTemplateVersion::find($workOrder->template_version_id)?->items()->orderBy('sequence')->get() ?? collect())
            : collect();

        $results = $workOrder->checklistResults()->get()->keyBy('checklist_item_id');

        return ApiResponse::ok([
            'progress' => $workOrder->checklistProgress(),
            'items' => $items->map(fn (ChecklistItem $item): array => $this->itemSummary($item, $results->get($item->id)))->all(),
        ]);
    }

    public function store(Request $request, WorkOrder $workOrder, RecordChecklistResult $action, StoreFileAttachment $files): JsonResponse
    {
        // Whoever does the work answers the checklist, the same permission
        // as starting it (matches ChecklistExecutionController exactly).
        $this->allow('work_order.work_order.start');
        $this->assertReachable($workOrder);

        $data = $request->validate([
            'checklist_item_id' => ['required', 'string', 'size:26'],
            'result' => ['required', Rule::in(WorkOrderChecklistResult::RESULTS)],
            'numeric_value' => ['nullable', 'numeric'],
            'text_value' => ['nullable', 'string', 'max:2000'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'max:10240'],
        ]);

        // Uploaded first so the action can enforce photo-on-failure against a
        // stored file rather than a promise of one.
        $fileId = null;

        if ($request->hasFile('photo')) {
            $fileId = $files->handle(
                $request->file('photo'),
                'work_order',
                $workOrder->id,
                $this->caller()->auditUserId(),
            )->id;
        }

        try {
            $result = $action->handle($workOrder, [
                'checklist_item_id' => $data['checklist_item_id'],
                'result' => $data['result'],
                'numeric_value' => $data['numeric_value'] ?? null,
                'text_value' => $data['text_value'] ?? null,
                'observation' => $data['observation'] ?? null,
                'file_id' => $fileId,
            ], $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created([
            'id' => $result->id,
            'checklist_item_id' => $result->checklist_item_id,
            'result' => $result->result,
            'numeric_value' => $result->numeric_value,
            'text_value' => $result->text_value,
            'observation' => $result->observation,
            'is_within_tolerance' => $result->is_within_tolerance,
            'followup_work_order_id' => $result->followup_work_order_id,
            'completed_at' => $result->completed_at?->toIso8601String(),
        ]);
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
    private function itemSummary(ChecklistItem $item, ?WorkOrderChecklistResult $result): array
    {
        return [
            'id' => $item->id,
            'sequence' => $item->sequence,
            'label' => $item->label,
            'input_type' => $item->input_type,
            'unit' => $item->unit,
            'options' => $item->options_json,
            'required' => $item->required,
            'is_safety_item' => $item->is_safety_item,
            'result' => $result === null ? null : [
                'result' => $result->result,
                'numeric_value' => $result->numeric_value,
                'text_value' => $result->text_value,
                'observation' => $result->observation,
                'is_within_tolerance' => $result->is_within_tolerance,
                'completed_at' => $result->completed_at?->toIso8601String(),
            ],
        ];
    }
}
