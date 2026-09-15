<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Actions\TransferAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetTransfer;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Moving a machine between locations and factories, over the wire (API 6;
 * mirrors the web `AssetTransferController`, which this delegates to
 * unchanged per ADR-003 — `TransferAsset` carries every request/approve/
 * receive/reject rule, including the fact that the asset itself moves only
 * once a transfer reaches RECEIVED).
 */
class AssetTransferApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The pending queue: what is waiting on somebody. Mirrors
     * `AssetTransferController::index()` exactly — every REQUESTED/
     * APPROVED/IN_TRANSIT transfer company-wide, not scoped to the
     * caller's own factories, since tracking transfers *between*
     * factories is the point of this screen.
     */
    public function index(Request $request): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $transfers = AssetTransfer::query()
            ->with(['asset:id,asset_code,name', 'fromFactory:id,name', 'toFactory:id,name', 'toLocation:id,name'])
            ->whereIn('status', ['REQUESTED', 'APPROVED', 'IN_TRANSIT'])
            ->orderByDesc('requested_at');

        return ApiResponse::paginated(
            $transfers->paginate($this->perPage($request))->withQueryString(),
            fn (AssetTransfer $t): array => $this->summary($t),
        );
    }

    /**
     * One transfer's own detail — no equivalent page exists on the web
     * (everything happens from the index/history lists instead); this is a
     * genuinely new, more discoverable detail view for the same data,
     * mirroring `InventoryTransferApiController::show()`'s own reasoning.
     */
    public function show(AssetTransfer $transfer): JsonResponse
    {
        $asset = $this->assetFor($transfer);
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        return ApiResponse::ok($this->summary(
            $transfer->load(['asset:id,asset_code,name', 'fromFactory:id,name', 'toFactory:id,name', 'toLocation:id,name']),
        ));
    }

    public function history(Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        $transfers = $asset->transfers()
            ->with(['fromFactory:id,name', 'toFactory:id,name', 'toLocation:id,name'])
            ->limit(100)
            ->get();

        return ApiResponse::ok($transfers->map(fn (AssetTransfer $t): array => $this->summary($t))->all());
    }

    public function store(Request $request, Asset $asset, TransferAsset $action): JsonResponse
    {
        $this->allow('asset.transfer.create');
        $this->assertReachable($asset);

        $data = $request->validate([
            'to_location_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        if ((int) $data['version'] !== $asset->version) {
            throw ApiException::of(ErrorCode::CONFLICT, __('asset.version_conflict', [
                'current' => $asset->version,
                'submitted' => $data['version'],
            ]));
        }

        try {
            $destination = AssetLocation::findOrFail($data['to_location_id']);

            $transfer = $action->request(
                asset: $asset,
                toLocationId: $destination->id,
                reason: $data['reason'],
                userId: (string) $this->caller()->auditUserId(),
                notes: $data['notes'] ?? null,
                autoReceive: $destination->factory_id === $asset->current_factory_id,
            );
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created($this->summary($transfer->fresh(['fromFactory', 'toFactory', 'toLocation'])));
    }

    public function approve(AssetTransfer $transfer, TransferAsset $action): JsonResponse
    {
        $asset = $this->assetFor($transfer);
        $this->allow('asset.transfer.approve');
        $this->assertReachable($asset);

        try {
            $updated = $action->approve($transfer, (string) $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            throw $this->conflictOrForbidden($e);
        }

        return ApiResponse::ok($this->summary($updated->fresh(['fromFactory', 'toFactory', 'toLocation'])));
    }

    public function receive(AssetTransfer $transfer, TransferAsset $action): JsonResponse
    {
        $asset = $this->assetFor($transfer);
        $this->allow('asset.transfer.receive');

        // The far end confirms, not the end that sent it — the same reason
        // the web controller checks the destination factory rather than the
        // origin (`AssetTransferController::receive`).
        if (! $this->context->canAccessFactory((string) $transfer->to_factory_id)) {
            abort(404);
        }

        try {
            $updated = $action->receive($transfer, (string) $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            throw $this->conflictOrForbidden($e);
        }

        return ApiResponse::ok($this->summary($updated->fresh(['fromFactory', 'toFactory', 'toLocation'])));
    }

    public function reject(Request $request, AssetTransfer $transfer, TransferAsset $action): JsonResponse
    {
        $asset = $this->assetFor($transfer);
        $this->allow('asset.transfer.approve');
        $this->assertReachable($asset);

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        try {
            $updated = $action->reject($transfer, (string) $this->caller()->auditUserId(), $data['rejection_reason']);
        } catch (ValidationException $e) {
            throw $this->conflictOrForbidden($e);
        }

        return ApiResponse::ok($this->summary($updated->fresh(['fromFactory', 'toFactory', 'toLocation'])));
    }

    private function conflictOrForbidden(ValidationException $e): ApiException
    {
        $status = $e->status ?? 422;
        $code = match ($status) {
            409 => ErrorCode::CONFLICT,
            403 => ErrorCode::FORBIDDEN,
            default => ErrorCode::VALIDATION_ERROR,
        };

        return ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
    }

    private function assetFor(AssetTransfer $transfer): Asset
    {
        return Asset::findOrFail($transfer->asset_id);
    }

    private function assertReachable(Asset $asset): void
    {
        if (! $this->context->canAccessFactory((string) $asset->current_factory_id)) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(AssetTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'asset_id' => $transfer->asset_id,
            'asset' => $transfer->relationLoaded('asset') && $transfer->asset !== null
                ? ['id' => $transfer->asset->id, 'asset_code' => $transfer->asset->asset_code, 'name' => $transfer->asset->name]
                : null,
            'status' => $transfer->status,
            'from_factory' => $transfer->relationLoaded('fromFactory') && $transfer->fromFactory !== null
                ? ['id' => $transfer->fromFactory->id, 'name' => $transfer->fromFactory->name]
                : ['id' => $transfer->from_factory_id],
            'to_factory' => $transfer->relationLoaded('toFactory') && $transfer->toFactory !== null
                ? ['id' => $transfer->toFactory->id, 'name' => $transfer->toFactory->name]
                : ['id' => $transfer->to_factory_id],
            'to_location' => $transfer->relationLoaded('toLocation') && $transfer->toLocation !== null
                ? ['id' => $transfer->toLocation->id, 'name' => $transfer->toLocation->name]
                : ['id' => $transfer->to_location_id],
            'reason' => $transfer->reason,
            'notes' => $transfer->notes,
            'requested_at' => $transfer->requested_at?->toIso8601String(),
            'approved_at' => $transfer->approved_at?->toIso8601String(),
            'received_at' => $transfer->received_at?->toIso8601String(),
            'rejected_at' => $transfer->rejected_at?->toIso8601String(),
            'rejection_reason' => $transfer->rejection_reason,
        ];
    }
}
