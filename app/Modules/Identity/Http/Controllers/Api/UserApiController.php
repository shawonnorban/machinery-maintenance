<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Actions\ManageCompanyUser;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $data = $this->validated($request, null);

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
    public function resetPassword(User $user, ManageCompanyUser $action): JsonResponse
    {
        $this->allow('admin.user.manage');
        $this->assertMember($user);

        $password = $action->resetPassword($user);

        return ApiResponse::ok(['password' => $password]);
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
            'roles' => $user->roleAssignments->map(fn (UserRole $a): ?string => $a->role?->description)
                ->filter()
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
            'account_status' => $user->status,
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
