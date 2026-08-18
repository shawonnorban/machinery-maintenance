<?php

declare(strict_types=1);

namespace App\Modules\Asset\Policies;

use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Permission grants the ability; this restricts the instance (API 34,
 * Handbook 2.6 rule 2). Both run on every request.
 *
 * The tenant scope already makes another company's assets invisible, so this
 * policy is about factory reach and asset state, not about tenancy.
 */
class AssetPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('asset.asset.view_any');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('asset.asset.view')
            && $this->withinFactoryReach($asset);
    }

    public function create(User $user): bool
    {
        return $user->can('asset.asset.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        // A scrapped asset is history. Editing it would rewrite the record of
        // what was actually on the floor.
        return $user->can('asset.asset.update')
            && $this->withinFactoryReach($asset)
            && $asset->status !== 'SCRAPPED';
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('asset.asset.delete')
            && $this->withinFactoryReach($asset);
    }

    public function changeStatus(User $user, Asset $asset): bool
    {
        return $user->can('asset.status.update')
            && $this->withinFactoryReach($asset);
    }

    public function transfer(User $user, Asset $asset): bool
    {
        return $user->can('asset.transfer.create')
            && $this->withinFactoryReach($asset)
            && ! $asset->isTerminal();
    }

    public function approveTransfer(User $user, Asset $asset): bool
    {
        return $user->can('asset.transfer.approve')
            && $this->withinFactoryReach($asset);
    }

    public function viewFinancial(User $user, Asset $asset): bool
    {
        // Separate from view: a technician needs the machine record, not its
        // purchase price (Handbook 2.1).
        return $user->can('asset.financial.view')
            && $this->withinFactoryReach($asset);
    }

    public function regenerateQr(User $user, Asset $asset): bool
    {
        return $user->can('asset.qr.regenerate')
            && $this->withinFactoryReach($asset);
    }

    /**
     * A factory-scoped role reaches only its own factories. A company-wide
     * role reaches all of them, which the resolver expresses by returning
     * every factory id.
     */
    private function withinFactoryReach(Asset $asset): bool
    {
        return $this->context->canAccessFactory($asset->current_factory_id);
    }
}
