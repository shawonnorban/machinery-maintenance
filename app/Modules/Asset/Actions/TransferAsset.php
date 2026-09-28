<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetTransfer;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an asset to another location, and possibly another factory.
 *
 * The asset's own location changes only when the transfer reaches RECEIVED
 * (ERD Section 4 rule 3). Updating it at request time would leave the asset
 * recorded somewhere it is not yet standing.
 *
 * Cross-tenant transfer is impossible by construction: both factories are
 * loaded through the tenant scope, so a foreign id resolves to nothing.
 */
class TransferAsset
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NumberSequenceGenerator $numbers,
    ) {}

    public function request(
        Asset $asset,
        string $toLocationId,
        string $reason,
        string $userId,
        ?string $notes = null,
        bool $autoReceive = false,
    ): AssetTransfer {
        if ($asset->isTerminal()) {
            throw ValidationException::withMessages([
                'asset_id' => "A {$asset->status} asset cannot be transferred.",
            ])->status(409);
        }

        // Tenant-scoped lookup. An id from another company resolves to null,
        // so the failure is "not found", never a cross-tenant move.
        $destination = AssetLocation::find($toLocationId);

        if ($destination === null) {
            throw ValidationException::withMessages([
                'to_location_id' => 'The destination location does not exist.',
            ]);
        }

        if ($destination->id === $asset->asset_location_id) {
            throw ValidationException::withMessages([
                'to_location_id' => 'The asset is already at this location.',
            ]);
        }

        if ($destination->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'to_location_id' => 'The destination location is not active.',
            ]);
        }

        $fromFactory = Factory::findOrFail($asset->current_factory_id);
        $toFactory = Factory::findOrFail($destination->factory_id);

        return DB::transaction(function () use (
            $asset, $destination, $fromFactory, $toFactory, $reason, $notes, $userId, $autoReceive
        ): AssetTransfer {
            $transfer = AssetTransfer::create([
                'asset_id' => $asset->id,
                'transfer_number' => $this->numbers->next('ASSET_TRANSFER', $fromFactory),
                'from_factory_id' => $asset->current_factory_id,
                'from_location_id' => $asset->asset_location_id,
                'to_factory_id' => $toFactory->id,
                'to_location_id' => $destination->id,
                'status' => 'REQUESTED',
                'reason' => $reason,
                'notes' => $notes,
                'requested_by' => $userId,
                'requested_at' => now(),
                'transfer_at' => now(),
            ]);

            // A move inside one factory needs no approval hop; a move between
            // factories does, because two custodians are involved.
            if ($autoReceive && $fromFactory->id === $toFactory->id) {
                return $this->receive($transfer, $userId);
            }

            return $transfer;
        });
    }

    public function approve(AssetTransfer $transfer, string $userId): AssetTransfer
    {
        $this->assertStatus($transfer, ['REQUESTED']);

        if ($transfer->requested_by === $userId) {
            throw ValidationException::withMessages([
                'approved_by' => 'The requester cannot approve their own transfer.',
            ])->status(403);
        }

        $transfer->forceFill(['status' => 'APPROVED', 'approved_by' => $userId, 'approved_at' => now()])->save();

        return $transfer;
    }

    public function reject(AssetTransfer $transfer, string $userId, string $reason): AssetTransfer
    {
        $this->assertStatus($transfer, ['REQUESTED', 'APPROVED']);

        $transfer->forceFill([
            'status' => 'REJECTED',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $transfer;
    }

    /**
     * The only point at which the asset actually moves.
     */
    public function receive(AssetTransfer $transfer, string $userId): AssetTransfer
    {
        $this->assertStatus($transfer, ['REQUESTED', 'APPROVED', 'IN_TRANSIT']);

        return DB::transaction(function () use ($transfer, $userId): AssetTransfer {
            $asset = Asset::findOrFail($transfer->asset_id);

            $asset->forceFill([
                'current_factory_id' => $transfer->to_factory_id,
                'asset_location_id' => $transfer->to_location_id,
                'updated_by' => $userId,
                'version' => $asset->version + 1,
            ])->save();

            $transfer->forceFill([
                'status' => 'RECEIVED',
                'received_by' => $userId,
                'received_at' => now(),
                'transfer_at' => now(),
            ])->save();

            return $transfer;
        });
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertStatus(AssetTransfer $transfer, array $allowed): void
    {
        if ($transfer->isImmutable()) {
            throw ValidationException::withMessages([
                'status' => 'A received transfer is immutable. Post a reversing transfer instead.',
            ])->status(409);
        }

        if (! in_array($transfer->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "A transfer in {$transfer->status} cannot make this transition.",
            ])->status(409);
        }
    }
}
