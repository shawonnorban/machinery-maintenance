<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Actions\ManageCompanyUser;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\PasswordChange;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Who works here and what they may do (API 4.1, SRS 5).
 *
 * A user account is not owned by a company, so everything here is about
 * membership: this person's place in *this* company. The business rules —
 * inviting, updating, suspending, the keyholder guard that stops a company
 * locking itself out — live in ManageCompanyUser (ADR-003), the same action
 * the settings screen calls.
 */
class UserApiController extends ApiController
{
    private const FILTERS = ['search'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('admin.user.manage');

        $companyId = $this->context->companyId();

        $query = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('company_id', $companyId))
            ->with([
                'memberships' => fn ($q) => $q->where('company_id', $companyId),
                'roleAssignments.role:id,name,description,scope',
                'roleAssignments.factory:id,name',
            ])
            ->when($request->filled('status'), fn ($q) => $q->whereHas(
                'memberships',
                fn ($m) => $m->where('company_id', $companyId)->where('status', $request->query('status')),
            ))
            ->when($request->filled('role_id'), fn ($q) => $q->whereHas(
                'roleAssignments',
                fn ($r) => $r->where('company_id', $companyId)->where('role_id', $request->query('role_id')),
            ))
            ->when($request->filled('factory_id'), fn ($q) => $q->whereHas(
                'roleAssignments',
                fn ($r) => $r->where('company_id', $companyId)->where('factory_id', $request->query('factory_id')),
            ));

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, ['name', 'email'], 'name', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (User $user): array => $this->summary($user),
        );
    }

    public function store(Request $request, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');

        $data = $this->validated($request, null) + ['changed_by' => $this->caller()->auditUserId()];

        $result = $action->invite($data, $data['roles'], $data['factory_id'] ?? null);

        // Shown once, in the response of the request that created it, and
        // never again — the same rule the settings screen follows.
        return ApiResponse::created($this->detail($result['user']) + [
            'password' => $result['password'],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        return ApiResponse::ok($this->detail($user));
    }

    public function update(Request $request, User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $data = $this->validated($request, $user);

        $action->update($user, $data, $data['roles'], $data['factory_id'] ?? null);

        return ApiResponse::ok($this->detail($user->fresh()));
    }

    public function deactivate(User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $action->setMembershipStatus($user, 'SUSPENDED');

        return ApiResponse::ok($this->detail($user->fresh()));
    }

    public function activate(User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $action->setMembershipStatus($user, 'ACTIVE');

        return ApiResponse::ok($this->detail($user->fresh()));
    }

    /**
     * A fresh password for somebody who has lost theirs — shown once, in
     * this response, the same rule `store()`'s own password follows.
     */
    public function resetPassword(Request $request, User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $data = $request->validate(['memorable_word' => ['nullable', 'string', 'max:255']]);

        $password = $action->resetPassword($user, $data['memorable_word'] ?? null, $this->caller()->auditUserId());

        return ApiResponse::ok(['password' => $password]);
    }

    /**
     * Access Control → Password Change for somebody else (AnnotechERP 02/01):
     * the administrator types the new password instead of being issued one.
     */
    public function setPassword(Request $request, User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(10)->uncompromised()],
            'force_change' => ['sometimes', 'boolean'],
            'memorable_word' => ['nullable', 'string', 'max:255'],
        ]);

        $action->setPassword($user, $data['password'], (bool) ($data['force_change'] ?? false), $data['memorable_word'] ?? null, $this->caller()->auditUserId());

        return ApiResponse::ok($this->detail($user->fresh()));
    }

    /** Password Change's Records: who changed whose password, and when — never the password. */
    public function passwordChanges(): JsonResponse
    {
        $this->allow('admin.user.manage');

        $members = CompanyUser::where('company_id', $this->context->companyId())->pluck('user_id');
        $changes = PasswordChange::query()
            ->whereIn('user_id', $members)
            ->with(['user:id,name,email,username', 'changedBy:id,name'])
            ->latest('changed_at')
            ->limit(200)
            ->get();

        return ApiResponse::ok($changes->map(fn (PasswordChange $change): array => [
            'id' => $change->id,
            'account' => $change->user?->username ?? $change->user?->email,
            'name' => $change->user?->name,
            'change_type' => $change->change_type,
            'forced_change_next_login' => $change->forced_change_next_login,
            'changed_at' => $change->changed_at?->toIso8601String(),
            'changed_by' => $change->changedBy?->name,
        ])->all());
    }

    /**
     * Ends this company's membership; the account and everything it ever
     * signed off stay (ManageCompanyUser::remove — the keyholder guard
     * lives there, not here, so the API refuses the last administrator
     * exactly as the web screen it mirrors does).
     */
    public function destroy(User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $action->remove($user);

        return ApiResponse::noContent();
    }

    public function roles(User $user): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $assignments = UserRole::where('user_id', $user->id)
            ->with(['role:id,name,description,scope', 'factory:id,name'])
            ->get();

        return ApiResponse::ok($assignments->map(fn (UserRole $a): array => $this->assignment($a))->values()->all());
    }

    public function assignRole(Request $request, User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $data = $request->validate([
            'role_id' => ['required', 'integer'],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        $assignment = $action->addRole($user, (string) $data['role_id'], $data['factory_id'] ?? null);

        return ApiResponse::created($this->assignment($assignment->load(['role:id,name,description,scope', 'factory:id,name'])));
    }

    public function removeRoleAssignment(User $user, UserRole $assignment, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $action->removeRoleAssignment($user, $assignment);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        /** @var CompanyUser|null $membership */
        $membership = $user->memberships->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'status' => $membership?->status,
            // Brickland's User Account Registration columns (AnnotechERP 02/01).
            'username' => $user->username,
            'user_type' => $user->user_type,
            'employee_code' => $user->employee_code,
            'two_factor_method' => $user->two_factor_method,
            'account_status' => $user->status,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            // What User Account Registration's Edit dialog starts from.
            'department_id' => $user->department_id,
            'production_line_id' => $user->production_line_id,
            'role_ids' => $user->roleAssignments->pluck('role_id')->unique()->values()->all(),
            'factory_id' => $user->roleAssignments->firstWhere('factory_id', '!=', null)?->factory_id,
            // One row per factory the role applies to, so the same role
            // name repeats here for anyone assigned it at more than one
            // factory (`role_assignments`, on `detail()`, is where each of
            // those individual assignments is actually shown) — deduplicated
            // for this badge list, which is naming what the user holds, not
            // enumerating every scope it's held in.
            'roles' => $user->roleAssignments->map(fn (UserRole $a): ?string => $a->role?->description)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(User $user): array
    {
        $user->loadMissing([
            'memberships' => fn ($q) => $q->where('company_id', $this->context->companyId()),
            'roleAssignments.role:id,name,description,scope',
            'roleAssignments.factory:id,name',
        ]);

        $user->loadMissing(['department:id,name', 'productionLine:id,name']);

        return $this->summary($user) + [
            'account_expires_at' => $user->account_expires_at?->format('Y-m-d'),
            'must_change_password' => (bool) $user->must_change_password,
            'cannot_change_password' => (bool) $user->cannot_change_password,
            'password_never_expires' => (bool) $user->password_never_expires,
            'has_memorable_word' => $user->memorable_word !== null,
            'justification' => $user->justification,
            'department_id' => $user->department_id,
            'department' => $user->department?->name,
            'production_line_id' => $user->production_line_id,
            'production_line' => $user->productionLine?->name,
            'role_assignments' => $user->roleAssignments
                ->map(fn (UserRole $a): array => $this->assignment($a))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignment(UserRole $assignment): array
    {
        return [
            'id' => $assignment->id,
            'role_id' => $assignment->role_id,
            'role' => $assignment->role?->description,
            'scope' => $assignment->role?->scope,
            'factory_id' => $assignment->factory_id,
            'factory' => $assignment->factory?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user): array
    {
        $email = $user === null
            ? ['required', 'email', 'max:255']
            : ['nullable'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $email,
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', Rule::in(['en', 'bn'])],
            'roles' => ['required', 'array', 'min:1'],
            // Spatie role ids — bigint, not this schema's usual ULID.
            'roles.*' => ['integer'],
            'factory_id' => ['nullable', 'string', 'size:26'],
            // Only meaningful for a role like Line Chief, which reports
            // breakdowns without a technician row of its own — see
            // `BreakdownScopeGuard`. Harmless to hold for anyone else.
            'department_id' => ['nullable', 'string', 'size:26'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            // Brickland's User Account Registration (AnnotechERP 02/01).
            'username' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'user_type' => ['sometimes', Rule::in(User::USER_TYPES)],
            'employee_code' => ['nullable', 'string', 'max:64'],
            'account_status' => ['sometimes', 'nullable', Rule::in(User::STATUSES)],
            'account_expires_at' => ['nullable', 'date'],
            'must_change_password' => ['sometimes', 'boolean'],
            'cannot_change_password' => ['sometimes', 'boolean'],
            'password_never_expires' => ['sometimes', 'boolean'],
            'two_factor_method' => ['sometimes', Rule::in(User::TWO_FACTOR_METHODS)],
            'justification' => ['nullable', 'string', 'max:2000'],
            // Set only on create; an existing account's password changes
            // through Password Change.
            'password' => $user === null ? ['nullable', 'confirmed', PasswordRule::min(10)->uncompromised()] : ['prohibited'],
            'memorable_word' => ['nullable', 'string', 'min:4', 'max:255', 'confirmed'],
        ]);
    }

    /**
     * Somebody who is not a member of this company is not this company's to
     * see, let alone edit — 404 rather than 403, because whether that
     * account exists at all is none of the caller's business.
     */
    private function assertMember(User $user): void
    {
        $exists = CompanyUser::where('user_id', $user->id)
            ->where('company_id', $this->context->companyId())
            ->exists();

        if (! $exists) {
            abort(404);
        }
    }
}
