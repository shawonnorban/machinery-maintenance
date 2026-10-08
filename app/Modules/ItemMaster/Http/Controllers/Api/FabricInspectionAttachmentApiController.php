<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\ItemMaster\Models\FabricInspection;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo evidence attached to one four-point sheet (Inventory spec §4.3) —
 * closing the gap the spec's §4.3 as-built note left open ("Photo আপলোড
 * এখনো নেই — শুধু লিখিত Remarks").
 *
 * Attached to the sheet rather than to a roll: a shade dispute or a knot
 * the inspector wants on record belongs to the lot's evidence file the
 * same way the pooled score does, and splitting photos across every
 * individual roll would scatter the one thing a later claim actually
 * needs — everything this pass found, in one place.
 */
class FabricInspectionAttachmentApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(FabricInspection $inspection): JsonResponse
    {
        $this->allow(FabricInspectionApiController::VIEW);
        $this->assertReachable($inspection);

        $attachments = FileAttachment::where('attachable_type', 'fabric_inspection')
            ->where('attachable_id', $inspection->id)
            ->latest()
            ->get();

        return ApiResponse::ok($attachments->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function store(Request $request, FabricInspection $inspection, StoreFileAttachment $files): JsonResponse
    {
        $this->allow(FabricInspectionApiController::VIEW);
        $this->allow(FabricInspectionApiController::INSPECT);
        $this->assertReachable($inspection);

        $request->validate(['file' => ['required', 'file']]);

        $attachment = $files->handle(
            $request->file('file'),
            'fabric_inspection',
            $inspection->id,
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($attachment->toApiSummary());
    }

    private function assertReachable(FabricInspection $inspection): void
    {
        if (! $this->context->canAccessFactory((string) $inspection->factory_id)) {
            abort(404);
        }
    }
}
