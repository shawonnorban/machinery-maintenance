<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Metering\Models\MeterReading;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a machine that should never have been registered (SRS 8).
 *
 * Deliberately narrow. Deleting a machine is not how a factory disposes of
 * one — that is RETIRED and then SCRAPPED, which keeps its repair history,
 * its cost and the reason it left the floor. This exists for the other case:
 * the row someone typed twice this morning, which has no history to keep and
 * which retiring would leave in every list for ever.
 *
 * So a machine anything at all is filed against cannot be deleted, and the
 * screen says what is in the way. That check runs here rather than being left
 * to the foreign keys: the database would refuse too, but a constraint
 * violation tells the person nothing they can act on.
 */
class DeleteAsset
{
    /**
     * Everything that would be orphaned, as model => foreign key.
     *
     * @var array<class-string, string>
     */
    private const HISTORY = [
        WorkOrder::class => 'asset_id',
        Breakdown::class => 'asset_id',
        CostEntry::class => 'asset_id',
        MeterReading::class => 'asset_id',
        MaintenancePlan::class => 'asset_id',
    ];

    public function handle(Asset $asset): void
    {
        $history = $this->historyCount($asset);

        if ($history > 0) {
            throw ValidationException::withMessages([
                'asset' => __('asset.delete_blocked_by_history', ['count' => $history]),
            ])->status(409);
        }

        if ($asset->children()->exists()) {
            throw ValidationException::withMessages([
                'asset' => __('asset.delete_blocked_by_children'),
            ])->status(409);
        }

        DB::transaction(function () use ($asset): void {
            // The status trail is the asset's own record of itself and has no
            // meaning once the asset is gone, so it goes with it. Everything
            // that belongs to somebody else — work orders, costs — is what the
            // check above refuses to let us reach.
            $asset->statusHistories()->delete();

            $asset->delete();
        });
    }

    public function historyCount(Asset $asset): int
    {
        $total = 0;

        foreach (self::HISTORY as $model => $column) {
            $total += $model::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where($column, $asset->id)
                ->count();
        }

        return $total;
    }
}
