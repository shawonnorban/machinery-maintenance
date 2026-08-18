<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the effective permission set for a user within one company.
 *
 * Permission grants the ability; a policy restricts the instance. Both run on
 * every request (API 34, Handbook 2.6 rule 2). This class answers only the
 * first half.
 *
 * A role assignment scoped to a factory grants its permissions only while the
 * request concerns that factory, which is why factory ids are returned
 * alongside the codes rather than folded away.
 */
class PermissionResolver
{
    /** @var array<string, array{permissions: array<string, list<string|null>>, factories: list<string>}> */
    private array $cache = [];

    /**
     * Effective permission codes for the user in this company.
     *
     * @return list<string>
     */
    public function permissionsFor(User $user, string $companyId): array
    {
        return array_keys($this->resolve($user, $companyId)['permissions']);
    }

    /**
     * Does the user hold this permission anywhere in the company?
     *
     * When $factoryId is given, the grant must be company-wide or scoped to
     * that factory.
     */
    public function has(User $user, string $companyId, string $permission, ?string $factoryId = null): bool
    {
        $resolved = $this->resolve($user, $companyId);

        if (! isset($resolved['permissions'][$permission])) {
            return false;
        }

        $scopes = $resolved['permissions'][$permission];

        // A null scope is a company-wide grant and covers every factory.
        if (in_array(null, $scopes, true)) {
            return true;
        }

        if ($factoryId === null) {
            // No factory in question: holding the permission in any factory
            // is enough to reach the screen. The policy narrows the rows.
            return $scopes !== [];
        }

        return in_array($factoryId, $scopes, true);
    }

    /**
     * Factories the user can reach in this company. A company-wide role grants
     * all of them; a factory-scoped role grants only its own.
     *
     * @return list<string>
     */
    public function accessibleFactoryIds(User $user, string $companyId): array
    {
        return $this->resolve($user, $companyId)['factories'];
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @return array{permissions: array<string, list<string|null>>, factories: list<string>}
     */
    private function resolve(User $user, string $companyId): array
    {
        $key = $user->id.'|'.$companyId;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // Membership is the gate. Without an active membership the user holds
        // nothing here, whatever role rows happen to exist.
        if (! $user->belongsToCompany($companyId)) {
            return $this->cache[$key] = ['permissions' => [], 'factories' => []];
        }

        $rows = UserRole::withoutGlobalScope(TenantScope::class)
            ->where('user_roles.company_id', $companyId)
            ->where('user_roles.user_id', $user->id)
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->get(['permissions.code as code', 'user_roles.factory_id as factory_id']);

        $permissions = [];
        $hasCompanyWideRole = false;
        $factoryIds = [];

        foreach ($rows as $row) {
            $permissions[$row->code][] = $row->factory_id;

            if ($row->factory_id === null) {
                $hasCompanyWideRole = true;
            } else {
                $factoryIds[$row->factory_id] = true;
            }
        }

        foreach ($permissions as $code => $scopes) {
            $permissions[$code] = array_values(array_unique($scopes, SORT_REGULAR));
        }

        if ($hasCompanyWideRole) {
            $factories = DB::table('factories')
                ->where('company_id', $companyId)
                ->pluck('id')
                ->all();
        } else {
            $factories = array_keys($factoryIds);
        }

        return $this->cache[$key] = [
            'permissions' => $permissions,
            'factories' => array_values($factories),
        ];
    }
}
