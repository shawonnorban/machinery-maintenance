<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Events\AssetStatusChanged;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an asset through the status machine in Data Dictionary 3.3.
 *
 * A transition not in the table is refused. The alternative, letting any
 * status be set directly, means a scrapped machine can silently reappear as
 * running and every metric derived from status becomes unreliable.
 */
class ChangeAssetStatus
{
    /**
     * @param  string  $source  MANUAL, BREAKDOWN, WORK_ORDER or SYSTEM.
     *                          System-driven transitions bypass the elevation
     *                          check because the driving record enforces its own.
     */
    public function handle(
        Asset $asset,
        string $toStatus,
        ?string $userId = null,
        ?string $reason = null,
        string $source = 'MANUAL',
        bool $isElevated = false,
    ): Asset {
        if (! in_array($toStatus, Asset::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => "Unknown status [{$toStatus}].",
            ]);
        }

        $from = $asset->status;

        if ($from === $toStatus) {
            return $asset;
        }

        if (! $asset->canTransitionTo($toStatus)) {
            throw ValidationException::withMessages([
                'status' => "An asset cannot move from {$from} to {$toStatus}.",
            ])->status(409);
        }

        if ($source === 'MANUAL' && $asset->transitionRequiresElevation($toStatus) && ! $isElevated) {
            throw ValidationException::withMessages([
                'status' => "Moving an asset from {$from} back to {$toStatus} requires elevated permission.",
            ])->status(403);
        }

        // Retiring or scrapping is the end of an asset's working life, so the
        // reason is part of the record rather than optional colour.
        if (in_array($toStatus, ['RETIRED', 'SCRAPPED', 'LOST'], true) && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => "A reason is required when moving an asset to {$toStatus}.",
            ]);
        }

        $changed = DB::transaction(function () use ($asset, $from, $toStatus, $userId, $reason, $source): Asset {
            $asset->status = $toStatus;
            $asset->updated_by = $userId;
            $asset->version++;

            if ($toStatus === 'RETIRED') {
                $asset->retired_at = now();
            }

            if ($toStatus === 'SCRAPPED') {
                $asset->scrapped_at = now();
            }

            $asset->save();

            AssetStatusHistory::create([
                'asset_id' => $asset->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'changed_by' => $userId,
                'changed_at' => now(),
                'reason' => $reason,
                'source' => $source,
            ]);

            return $asset;
        });

        // Outside the transaction, deliberately: a socket message announcing a
        // state the database never reached is worse than no message at all, and
        // a broadcast that throws must not undo the status change it was
        // announcing (SRS 29).
        AssetStatusChanged::dispatch($changed, $from, $toStatus);

        return $changed;
    }
}
