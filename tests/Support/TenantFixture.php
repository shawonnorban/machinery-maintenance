<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Identity\Services\Totp;
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
        bool $withMfa = true,
    ): User {
        $user = User::create([
            'name' => ucfirst(strstr($email, '@', true) ?: 'User'),
            'email' => $email,
            'password' => $password,
            'status' => 'ACTIVE',
            'locale' => 'en',
        ]);

        // Roles for which SRS 50.3 makes a second factor compulsory arrive
        // holding one, exactly as they would in a real deployment.
        //
        // The alternative was a config switch turning the enforcement off in
        // the test environment, and that was refused deliberately: a flag whose
        // job is to disable a security control is a flag that eventually ships
        // enabled-off to production, where nothing would look wrong. Tests that
        // need an un-enrolled owner ask for one with $withMfa: false.
        if ($withMfa && in_array($roleCode, ['COMPANY_OWNER', 'PLATFORM_SUPER_ADMIN'], true)) {
            self::enrolMfa($user);
        }

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

    /**
     * A confirmed second factor, written directly.
     *
     * Not through ManageMfa: that would run the enrolment ceremony — generate a
     * secret, render a QR, verify a code — for every owner in every test, and
     * the ceremony has its own tests. What matters here is only that the
     * account holds one.
     */
    public static function enrolMfa(User $user): void
    {
        $user->forceFill([
            'mfa_secret' => app(Totp::class)->generateSecret(),
            'mfa_confirmed_at' => now(),
        ])->save();
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
