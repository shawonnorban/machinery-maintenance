<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Policies;

use App\Modules\Identity\Models\User;

/**
 * Warranties and claims sit behind one permission.
 *
 * Reading a warranty is deliberately wider than managing one: a technician
 * standing at a broken machine has to be able to see that the repair is already
 * paid for, and gating that behind a management permission is how a factory
 * pays twice for the same fault.
 */
class WarrantyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('asset.asset.view_any');
    }

    public function view(User $user, mixed $record = null): bool
    {
        return $user->can('asset.asset.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('vendor.warranty.manage');
    }

    public function update(User $user, mixed $record = null): bool
    {
        return $user->can('vendor.warranty.manage');
    }
}
