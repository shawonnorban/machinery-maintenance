<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Billing\Services\EntitlementGuard;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PermissionResolver;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adding somebody to a company and deciding what they may do (SRS 5).
 *
 * A user is not owned by a company. The same person can work for two companies
 * in a group, so the account lives on its own and membership is a separate row
 * — which is why "removing" somebody here ends their membership and their
 * roles, and never touches the account or the work they signed off.
 *
 * Two rules exist to stop a company locking itself out, and both are enforced
 * here rather than on the screen: nobody may take away their own
 * administration, and the last person who can manage users cannot lose that
 * ability. A company with no administrator has no way back in except support.
 */
class ManageCompanyUser
{
    /** The permission that, if nobody holds it, locks the company out. */
    private const KEYHOLDER_PERMISSION = 'admin.user.manage';

    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
        private readonly EntitlementGuard $entitlements,
    ) {}

    /**
     * Add a person to this company, creating the account if they are new.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roleIds
     * @return array{user: User, password: ?string}
     */
    public function invite(array $data, array $roleIds, ?string $factoryId): array
    {
        $companyId = $this->context->companyId();

        // Only on invite. Reactivating somebody who is already on the books is
        // handled by setMembershipStatus, and blocking that would leave a
        // customer at their limit unable to bring back a person they had just
        // suspended by mistake.
        $this->entitlements->assertCanAdd('ACTIVE_USERS');

        return DB::transaction(function () use ($data, $roleIds, $factoryId, $companyId): array {
            $existing = User::where('email', $data['email'])->first();

            // An account that already exists is joined rather than duplicated:
            // the same person moving between two companies in a group keeps one
            // set of credentials.
            $password = $existing === null ? $this->generatePassword() : null;

            $user = $existing ?? User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $password,
                'status' => 'ACTIVE',
                'locale' => $data['locale'] ?? 'bn',
            ]);

            $membership = CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->first();

            if ($membership !== null) {
                throw ValidationException::withMessages([
                    'email' => __('user.already_a_member'),
                ]);
            }

            CompanyUser::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => 'ACTIVE',
                // Their first company becomes the one they land in at sign-in.
                'is_default' => $user->memberships()->count() === 0,
            ]);

            $this->syncRoles($user, $roleIds, $factoryId);

            return ['user' => $user->fresh(), 'password' => $password];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roleIds
     */
    public function update(User $user, array $data, array $roleIds, ?string $factoryId): User
    {
        $this->assertStillHasAKeyholder($user, $roleIds);

        return DB::transaction(function () use ($user, $data, $roleIds, $factoryId): User {
            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? $user->locale,
            ]);

            $this->syncRoles($user, $roleIds, $factoryId);

            return $user->fresh();
        });
    }

    /**
     * Suspend somebody's access to this company, or restore it.
     *
     * The membership is suspended, not the account: a contractor who leaves one
     * company in a group may still work for another.
     */
    public function setMembershipStatus(User $user, string $status): void
    {
        $this->assertNotSelf($user, __('user.cannot_suspend_yourself'));

        if ($status !== 'ACTIVE') {
            $this->assertStillHasAKeyholder($user, []);
        }

        $this->membership($user)->forceFill(['status' => $status])->save();

        $this->permissions->flush();
    }

    /**
     * End somebody's membership of this company.
     *
     * The account and everything they signed off stay exactly where they are;
     * a work order closed by this person still names them.
     */
    public function remove(User $user): void
    {
        $this->assertNotSelf($user, __('user.cannot_remove_yourself'));
        $this->assertStillHasAKeyholder($user, []);

        DB::transaction(function () use ($user): void {
            UserRole::where('user_id', $user->id)->delete();

            $this->membership($user)->delete();
        });

        $this->permissions->flush();
    }

    /**
     * Issue a new password, shown once.
     *
     * Most people on a factory floor have no working email address, so a reset
     * link is not a path everybody can take. The administrator is given the
     * password to hand over, and it is readable exactly once.
     */
    public function resetPassword(User $user): string
    {
        $password = $this->generatePassword();

        $user->forceFill(['password' => $password])->save();

        return $password;
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds, ?string $factoryId): void
    {
        $companyId = $this->context->companyId();

        $roles = Role::query()
            ->whereIn('id', $roleIds)
            // A tenant may only hand out company and factory roles. Platform
            // roles are the operator's, and a company that could assign one
            // could grant itself the run of the platform.
            ->whereIn('scope', ['COMPANY', 'FACTORY'])
            ->get();

        if ($roles->isEmpty()) {
            throw ValidationException::withMessages(['roles' => __('user.roles_required')]);
        }

        UserRole::where('user_id', $user->id)->delete();

        foreach ($roles as $role) {
            UserRole::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'role_id' => $role->id,
                // A factory-scoped role without a factory would grant nothing
                // anywhere, which looks like an assignment and behaves like
                // none.
                'factory_id' => $role->scope === 'FACTORY' ? $this->requiredFactory($factoryId) : null,
            ]);
        }

        $this->permissions->flush();
    }

    private function requiredFactory(?string $factoryId): string
    {
        if (! filled($factoryId) || ! $this->context->canAccessFactory((string) $factoryId)) {
            throw ValidationException::withMessages(['factory_id' => __('user.factory_required')]);
        }

        return (string) $factoryId;
    }

    /**
     * The acting company is named explicitly: company_users is the table that
     * says which companies a user belongs to, so it is deliberately not
     * tenant-scoped. Leaving the company out turns "their membership here"
     * into "any membership anywhere", and a status change would land on the
     * wrong company's row.
     */
    private function membership(User $user): CompanyUser
    {
        $membership = CompanyUser::where('user_id', $user->id)
            ->where('company_id', $this->context->companyId())
            ->first();

        return $membership ?? abort(404);
    }

    private function assertNotSelf(User $user, string $message): void
    {
        if (auth()->id() === $user->id) {
            throw ValidationException::withMessages(['user' => $message])->status(422);
        }
    }

    /**
     * Refuse a change that would leave nobody able to manage users.
     *
     * @param  list<string>  $intendedRoleIds  roles the user will hold afterwards
     */
    private function assertStillHasAKeyholder(User $user, array $intendedRoleIds): void
    {
        $companyId = $this->context->companyId();

        if (! $this->permissions->has($user, $companyId, self::KEYHOLDER_PERMISSION)) {
            // They are not a keyholder, so nothing they lose can be the last of
            // anything.
            return;
        }

        if ($intendedRoleIds !== [] && $this->rolesInclude($intendedRoleIds, self::KEYHOLDER_PERMISSION)) {
            return;
        }

        $others = User::whereHas('memberships', fn ($q) => $q->where('company_id', $companyId)
            ->where('status', 'ACTIVE'))
            ->where('id', '!=', $user->id)
            ->where('status', 'ACTIVE')
            ->get()
            ->filter(fn (User $other) => $this->permissions->has($other, $companyId, self::KEYHOLDER_PERMISSION));

        if ($others->isEmpty()) {
            throw ValidationException::withMessages([
                'roles' => __('user.last_administrator'),
            ])->status(422);
        }
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function rolesInclude(array $roleIds, string $permission): bool
    {
        return Role::whereIn('id', $roleIds)
            ->whereHas('permissions', fn ($q) => $q->where('code', $permission))
            ->exists();
    }

    /**
     * Long enough not to be guessed, short enough to be read down a phone line
     * once and typed in by somebody standing at a machine.
     */
    private function generatePassword(): string
    {
        return Str::password(14, symbols: false);
    }
}
