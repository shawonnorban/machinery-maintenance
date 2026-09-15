<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Evidence attached directly to a work order (API 11, 19.1) — a photo of the
 * finished job, a vendor's service note — as opposed to a photo answering one
 * checklist item, which rides on `POST /work-orders/{workOrder}/checklist`
 * instead.
 */
class WorkOrderAttachmentApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $attachments = FileAttachment::where('attachable_type', 'work_order')
            ->where('attachable_id', $workOrder->id)
            ->latest()
            ->get();

        return ApiResponse::ok($attachments->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function store(Request $request, WorkOrder $workOrder, StoreFileAttachment $files): JsonResponse
    {
        // Attaching evidence to a work order is a change to its record, same
        // rule as any other edit made after it is opened.
        $this->allow('work_order.work_order.view');
        $this->allow('work_order.work_order.update');
        $this->assertReachable($workOrder);

        $request->validate(['file' => ['required', 'file']]);

        $attachment = $files->handle(
            $request->file('file'),
            'work_order',
            $workOrder->id,
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($attachment->toApiSummary());
    }

    private function assertReachable(WorkOrder $workOrder): void
    {
        if (! $this->context->canAccessFactory((string) $workOrder->factory_id)) {
            abort(404);
        }
    }
}
