<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Vendor\Models\Vendor;

/**
 * Vendors are company-wide, not factory-scoped: a supplier serves whichever
 * factories buy from them, so there is no factory reach to check here — only
 * the permission.
 */
class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendor.vendor.view_any');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->can('vendor.vendor.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('vendor.vendor.create');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->can('vendor.vendor.update');
    }
}
