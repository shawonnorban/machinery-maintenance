<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetTransfer;
use App\Shared\Scopes\TenantScope;
use Illuminate\Validation\ValidationException;

/**
 * Removes a location that should never have been created (ADR-052).
 *
 * The same narrow rule as everywhere else: a location with machines standing
 * in it, or named in a transfer that has already happened, is closed rather
 * than deleted. Closing takes it out of every picker and leaves the history —
 * where a machine used to stand — readable.
 *
 * What is left is the case this exists for: the row typed under the wrong
 * factory five minutes ago.
 */
class DeleteAssetLocation
{
    public function handle(AssetLocation $location): void
    {
        $used = $this->referenceCount($location);

        if ($used > 0) {
            throw ValidationException::withMessages([
                'code' => __('asset.location_in_use', ['count' => $used]),
            ])->status(409);
        }

        $location->delete();
    }

    public function referenceCount(AssetLocation $location): int
    {
        $assets = Asset::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('asset_location_id', $location->id)
            ->count();

        $transfers = AssetTransfer::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where(fn ($q) => $q->where('from_location_id', $location->id)
                ->orWhere('to_location_id', $location->id))
            ->count();

        return $assets + $transfers;
    }
}
