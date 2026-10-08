<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo evidence attached to one roll (Inventory spec §4.3) — the single-
 * roll inspect path's own evidence, independent of the four-point sheet's
 * own photos (`FabricInspectionAttachmentApiController`), because a
 * delivery nobody four-points never has a sheet to attach to at all.
 *
 * Mirrors `BreakdownAttachmentApiController` exactly: the roll already
 * exists from receiving, so a photo can be attached before, during or
 * after the inspect decision — a shade dispute is often settled by a
 * picture taken days later, not only at the moment of grading.
 */
class FabricRollAttachmentApiController extends ApiController
{
    public function index(FabricRoll $roll): JsonResponse
    {
        $this->allow(FabricRollApiController::VIEW);

        $attachments = FileAttachment::where('attachable_type', 'fabric_roll')
            ->where('attachable_id', $roll->id)
            ->latest()
            ->get();

        return ApiResponse::ok($attachments->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function store(Request $request, FabricRoll $roll, StoreFileAttachment $files): JsonResponse
    {
        $this->allow(FabricRollApiController::VIEW);
        $this->allow(FabricRollApiController::INSPECT);

        $request->validate(['file' => ['required', 'file']]);

        $attachment = $files->handle(
            $request->file('file'),
            'fabric_roll',
            $roll->id,
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($attachment->toApiSummary());
    }
}
