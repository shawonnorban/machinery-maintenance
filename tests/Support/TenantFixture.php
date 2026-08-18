<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;

/**
 * Builds realistic tenants for tests. Cross-tenant assertions need at least
 * two companies with overlapping-looking data, so this always makes it cheap
 * to create a second one.
 */
class TenantFixture
{
    public static function company(string $name, string $code): Company
    {
        return Company::create([
            'name' => $name,
            'code' => $code,
            'base_currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'default_locale' => 'en',
        ]);
    }

    public static function factory(Company $company, string $name, string $code): Factory
    {
        return Factory::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'timezone' => 'Asia/Dhaka',
        ]);
    }

    /**
     * Creates a user, their membership, and a role assignment.
     *
     * @param  string|null  $factoryId  null = company-wide role
     */
    public static function user(
        Company $company,
        string $roleCode,
        string $email,
        ?string $factoryId = null,
        string $password = 'correct-horse-battery',
    ): User {
        $user = User::create([
            'name' => ucfirst(strstr($email, '@', true) ?: 'User'),
            'email' => $email,
            'password' => $password,
            'status' => 'ACTIVE',
            'locale' => 'en',
        ]);

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        $role = Role::whereNull('company_id')->where('code', $roleCode)->firstOrFail();

        UserRole::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'factory_id' => $factoryId,
        ]);

        app(PermissionResolver::class)->flush();

        return $user;
    }

    public static function addMembership(User $user, Company $company, string $roleCode): void
    {
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ACTIVE',
            'is_default' => false,
        ]);

        $role = Role::whereNull('company_id')->where('code', $roleCode)->firstOrFail();

        UserRole::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'factory_id' => null,
        ]);

        app(PermissionResolver::class)->flush();
    }

    public static function actingAsTenant(Company $company): void
    {
        app(PermissionResolver::class)->flush();
        app(TenantContext::class)->forget();
        app(TenantContext::class)->set($company->id);
    }
}
