<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Taking on a customer (SRS 3.1 "SaaS tenant management").
 *
 * Until this existed, a company could only be created by a seeder or by hand
 * in the database — which meant the product could be sold and not delivered.
 *
 * One transaction, because the halves are useless apart. A company with no
 * owner is a tenant nobody can sign in to; an owner with no company is an
 * account that lands on a screen refusing to resolve a tenant. Either outcome
 * needs somebody with database access to finish the job, which is the position
 * this action exists to end.
 *
 * The first factory is created here too, for the same reason: almost nothing
 * in the product works without one — assets live in a factory, work orders are
 * numbered per factory, the calendar hangs off it — so a company handed over
 * without one is handed over broken.
 */
class OnboardTenant
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{company: Company, owner: User, factory: Factory, password: string}
     */
    public function handle(array $data, string $staffId): array
    {
        $this->assertCodeIsFree('companies', $data['code']);

        $existingUser = User::withTrashed()->where('email', $data['owner_email'])->first();

        if ($existingUser !== null) {
            // Joining an existing account to a new company is a legitimate
            // thing to want — the same person owning two mills in a group —
            // but it is a different operation with different consequences, and
            // guessing which one was meant is how somebody's password gets
            // reset without them asking.
            throw ValidationException::withMessages([
                'owner_email' => __('platform.owner_email_taken'),
            ]);
        }

        $password = $this->generatePassword();

        $result = DB::transaction(function () use ($data, $password): array {
            $company = Company::create([
                'name' => $data['name'],
                'code' => strtoupper(trim($data['code'])),
                'legal_name' => $data['legal_name'] ?? null,
                'base_currency' => $data['base_currency'] ?? 'BDT',
                'timezone' => $data['timezone'] ?? 'Asia/Dhaka',
                'default_locale' => $data['default_locale'] ?? 'bn',
                'status' => 'ACTIVE',
            ]);

            // Everything below writes tenant-owned rows, so the context has to
            // be the company being created rather than nothing.
            $this->context->set($company->id);

            $factory = Factory::create([
                'company_id' => $company->id,
                'name' => $data['factory_name'],
                'code' => strtoupper(trim($data['factory_code'])),
                'timezone' => $data['timezone'] ?? 'Asia/Dhaka',
                'status' => 'ACTIVE',
            ]);

            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $password,
                'status' => 'ACTIVE',
                'locale' => $data['default_locale'] ?? 'bn',
                'timezone' => $data['timezone'] ?? 'Asia/Dhaka',
            ]);

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'status' => 'ACTIVE',
                'is_default' => true,
            ]);

            $role = Role::withoutGlobalScope(TenantScope::class)
                ->whereNull('company_id')
                ->where('code', 'COMPANY_OWNER')
                ->firstOrFail();

            UserRole::withoutGlobalScope(TenantScope::class)->create([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'role_id' => $role->id,
                // Company-wide, not bound to the factory just created. The
                // owner of a company that will have six mills should not have
                // to be re-granted access to each one.
                'factory_id' => null,
            ]);

            return ['company' => $company, 'owner' => $owner, 'factory' => $factory];
        });

        $this->context->forget();
        $this->permissions->flush();

        $this->audit->event(
            'SECURITY_EVENT',
            [
                'reason' => 'TENANT_CREATED',
                'company_id' => $result['company']->id,
                'company_code' => $result['company']->code,
                'owner_email' => $result['owner']->email,
            ],
            userId: $staffId,
            label: 'TENANT_CREATED',
        );

        return $result + ['password' => $password];
    }

    /**
     * A code already taken by a soft-deleted company still collides: the unique
     * index is on (code, deleted_marker) and a deleted row keeps its code.
     * Saying so plainly beats a database error nobody can read.
     */
    private function assertCodeIsFree(string $table, string $code): void
    {
        $taken = Company::withTrashed()
            ->where('code', strtoupper(trim($code)))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => __('platform.code_taken'),
            ]);
        }
    }

    /**
     * Shown once to whoever is onboarding, for them to pass on.
     *
     * Not emailed from here: the address has not been verified, and a first
     * password sent to a mistyped address is a credential handed to a stranger.
     */
    private function generatePassword(): string
    {
        return Str::password(16, symbols: false);
    }
}
