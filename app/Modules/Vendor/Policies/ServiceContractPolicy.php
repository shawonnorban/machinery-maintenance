<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Vendor\Models\ServiceContract;

/**
 * Contract value is commercial information, so reading the list needs the
 * vendor permission rather than the general asset one — unlike a warranty,
 * which a technician needs at the machine.
 */
class ServiceContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendor.vendor.view_any');
    }

    public function view(User $user, ServiceContract $contract): bool
    {
        return $user->can('vendor.vendor.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('vendor.contract.manage');
    }

    public function update(User $user, ServiceContract $contract): bool
    {
        return $user->can('vendor.contract.manage');
    }
}
