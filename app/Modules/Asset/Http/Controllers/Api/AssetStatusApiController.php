<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
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
 * Moves a machine through its status machine, over the wire (API 6; mirrors
 * the web `AssetStatusController`, which this delegates to unchanged per
 * ADR-003 — `ChangeAssetStatus` carries every transition and elevation
 * rule).
 */
class AssetStatusApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Asset $asset, ChangeAssetStatus $action): JsonResponse
    {
        $this->allow('asset.status.update');
        $this->assertReachable($asset);

        $data = $request->validate([
            'status' => ['required', Rule::in(Asset::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        // Duplicated from the web controller rather than moved into the
        // action, matching it exactly (ADR-003 mirrors behavior, not just
        // the happy path).
        if ((int) $data['version'] !== $asset->version) {
            throw ApiException::of(ErrorCode::CONFLICT, __('asset.version_conflict', [
                'current' => $asset->version,
                'submitted' => $data['version'],
            ]));
        }

        try {
            $updated = $action->handle(
                asset: $asset,
                toStatus: $data['status'],
                userId: $this->caller()->auditUserId(),
                reason: $data['reason'] ?? null,
                source: 'MANUAL',
                // Recommissioning a retired or lost asset is gated separately
                // (Data Dictionary 3.3), the same permission the web screen reuses.
                isElevated: $this->caller()->can('asset.qr.regenerate'),
            );
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = match ($status) {
                409 => ErrorCode::CONFLICT,
                403 => ErrorCode::FORBIDDEN,
                default => ErrorCode::VALIDATION_ERROR,
            };

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok([
            'id' => $updated->id,
            'status' => $updated->status,
            'version' => $updated->version,
        ]);
    }

    private function assertReachable(Asset $asset): void
    {
        if (! $this->context->canAccessFactory((string) $asset->current_factory_id)) {
            abort(404);
        }
    }
}
