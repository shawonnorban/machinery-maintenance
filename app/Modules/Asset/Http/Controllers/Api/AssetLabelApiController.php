<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Actions\RegenerateQrToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Services\QrCodeRenderer;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A machine's printed label, over the wire (API 6). QR encodes a direct
 * link into the Next.js scan landing page (`/scan/{code}`) rather than
 * Laravel's own `route('scan.asset', ...)` — that Blade route still exists
 * and still resolves (kept alive as a redirect for any already-printed
 * label), but a freshly generated or regenerated label has no reason to
 * take the extra hop.
 *
 * There is no barcode *renderer* anywhere in this codebase — `barcode` is a
 * plain text field a technician can key in by hand (Data Dictionary 5.5),
 * not a symbology anything here draws. The endpoint returns that stored
 * value rather than inventing an image capability the web side does not
 * have either.
 */
class AssetLabelApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The label sheet's data, over the wire — mirrors
     * `AssetLabelController::index()`'s own query (explicit `ids[]`, else
     * factory/status filters, 200-asset cap so a tenant with 20,000 assets
     * can't ask for a document no printer would accept) and reuses the
     * same `QrCodeRenderer`/`route('scan.asset', ...)` pairing, so a label
     * printed from either surface scans to the same place.
     */
    public function bulk(Request $request, QrCodeRenderer $renderer): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $selected = array_filter((array) $request->query('ids', []));

        $factoryId = $request->query('factory_id');
        $factoryId = is_string($factoryId) && $this->context->canAccessFactory($factoryId) ? $factoryId : null;

        $assets = Asset::query()
            ->with(['location:id,name'])
            ->when($selected !== [], fn ($q) => $q->whereIn('id', $selected))
            ->when($factoryId, fn ($q, $id) => $q->where('current_factory_id', $id))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('asset_code')
            ->limit(200)
            ->get();

        $labels = $assets->map(function (Asset $asset) use ($renderer): array {
            $scanUrl = $this->scanUrl($asset->qr_code);

            return [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'location' => $asset->location?->name,
                'qr_code' => $asset->qr_code,
                'scan_url' => $scanUrl,
                'svg' => $renderer->inlineSvg($scanUrl, 160),
            ];
        });

        return ApiResponse::ok([
            'labels' => $labels->all(),
            'truncated' => $assets->count() === 200,
        ]);
    }

    public function qr(Asset $asset, QrCodeRenderer $renderer): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        $scanUrl = $this->scanUrl($asset->qr_code);

        return ApiResponse::ok([
            'qr_code' => $asset->qr_code,
            'scan_url' => $scanUrl,
            'svg' => $renderer->inlineSvg($scanUrl),
        ]);
    }

    /**
     * Mirrors `AssetLabelController::regenerate` — invalidates the printed
     * label (Data Dictionary 5.5), so it's gated on the same
     * `asset.qr.regenerate` permission and reuses `RegenerateQrToken`
     * unchanged (ADR-003) so the audit trail it writes is identical
     * regardless of which surface triggered it.
     */
    public function regenerate(Asset $asset, RegenerateQrToken $action): JsonResponse
    {
        $this->allow('asset.qr.regenerate');
        $this->assertReachable($asset);

        $asset = $action->handle($asset, $this->caller()->auditUserId());

        return ApiResponse::ok(['qr_code' => $asset->qr_code]);
    }

    public function barcode(Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        return ApiResponse::ok(['barcode' => $asset->barcode]);
    }

    private function assertReachable(Asset $asset): void
    {
        if (! $this->context->canAccessFactory((string) $asset->current_factory_id)) {
            abort(404);
        }
    }

    private function scanUrl(string $qrCode): string
    {
        return rtrim((string) config('tenancy.frontend_url'), '/').'/scan/'.$qrCode;
    }
}
