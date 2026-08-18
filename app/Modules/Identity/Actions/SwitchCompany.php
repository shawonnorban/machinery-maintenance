<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Switches the active company for a multi-company user (API 3).
 *
 * The membership check is the whole point: a company id arriving from a client
 * only selects among memberships the user already has (SRS 4).
 */
class SwitchCompany
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
    ) {}

    public function handle(User $user, string $companyId): Company
    {
        if (! $user->belongsToCompany($companyId)) {
            throw ValidationException::withMessages([
                'company_id' => __('auth.tenant_access_denied'),
            ])->status(403);
        }

        $company = Company::findOrFail($companyId);

        // Permissions are per company. A cached set from the previous company
        // would grant the wrong abilities here.
        $this->permissions->flush();

        $this->context->forget();
        $this->context->set(
            $company->id,
            $this->permissions->accessibleFactoryIds($user, $company->id),
        );

        if (request()->hasSession()) {
            $session = request()->session();
            $session->put(ResolveTenantContext::SESSION_KEY, $company->id);
            // The factory filter belongs to the previous company. Carrying it
            // over would either leak a foreign id or silently filter to nothing.
            $session->forget(ResolveTenantContext::FACTORY_SCOPE_KEY);
        }

        return $company;
    }
}
