<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A machine's papers, over the wire (API 6, 19.1; mirrors the web
 * `AssetDocumentController`).
 *
 * Uploading is a separate permission from viewing the asset, same as the web
 * screen: a document attached to a machine is read by everybody who works on
 * it and changed by very few.
 */
class AssetDocumentApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        $documents = FileAttachment::where('attachable_type', 'asset')
            ->where('attachable_id', $asset->id)
            ->latest()
            ->get();

        return ApiResponse::ok($documents->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function store(Request $request, Asset $asset, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->allow('asset.document.manage');
        $this->assertReachable($asset);

        $request->validate(['file' => ['required', 'file']]);

        $document = $files->handle($request->file('file'), 'asset', $asset->id, $this->caller()->auditUserId());

        return ApiResponse::created($document->toApiSummary());
    }

    /**
     * A machine in a factory this caller cannot reach is a machine that does
     * not exist, as far as the answer goes (API 2).
     */
    private function assertReachable(Asset $asset): void
    {
        if (! $this->context->canAccessFactory((string) $asset->current_factory_id)) {
            abort(404);
        }
    }
}
