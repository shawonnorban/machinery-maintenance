<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Http\Controllers\Api;

use App\Modules\Breakdown\Models\Breakdown;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Evidence attached to a breakdown (SRS 13.4) — mirrors
 * `WorkOrderAttachmentApiController` exactly. The first photo, if the
 * reporter attached one, arrives inline with `POST /breakdowns` instead
 * (`BreakdownApiController::store()`'s own `photo_base64` field) so the
 * offline queue can send report-plus-photo as one atomic, idempotent write —
 * this endpoint is for anything added afterwards, online, the same way a
 * work order's attachments tab works.
 */
class BreakdownAttachmentApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Breakdown $breakdown): JsonResponse
    {
        $this->allow('breakdown.breakdown.view');
        $this->assertReachable($breakdown);

        $attachments = FileAttachment::where('attachable_type', 'breakdown')
            ->where('attachable_id', $breakdown->id)
            ->latest()
            ->get();

        return ApiResponse::ok($attachments->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function store(Request $request, Breakdown $breakdown, StoreFileAttachment $files): JsonResponse
    {
        // Same pairing as WorkOrder's own attachment upload: seeing the
        // breakdown plus being one of the people actively working it,
        // not a separate "manage breakdown" permission that doesn't exist.
        $this->allow('breakdown.breakdown.view');
        $this->allow('breakdown.breakdown.repair');
        $this->assertReachable($breakdown);

        $request->validate(['file' => ['required', 'file']]);

        $attachment = $files->handle(
            $request->file('file'),
            'breakdown',
            $breakdown->id,
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($attachment->toApiSummary());
    }

    private function assertReachable(Breakdown $breakdown): void
    {
        if (! $this->context->canAccessFactory((string) $breakdown->factory_id)) {
            abort(404);
        }
    }
}
