<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\ManageCompanyUser;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who works here and what they may do (SRS 5).
 *
 * A user account is not owned by a company, so everything on these screens is
 * about membership: this person's place in *this* company. Someone who works
 * for two companies in a group has one account and two memberships, and
 * removing them here ends one of those without touching the other or the work
 * they have already signed off.
 */
class UserController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeUsers($request);

        $companyId = $this->context->companyId();

        $users = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('company_id', $companyId))
            ->with([
                'memberships' => fn ($q) => $q->where('company_id', $companyId),
                'roleAssignments.role:id,name,code,scope',
                'roleAssignments.factory:id,name',
            ])
            ->when($request->string('search')->trim()->toString(), function ($q, string $term): void {
                $q->where(fn ($w) => $w->where('name', 'like', $term.'%')
                    ->orWhere('email', 'like', $term.'%'));
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('identity::users.index', ['users' => $users]);
    }

    public function create(Request $request): View
    {
        $this->authorizeUsers($request);

        return view('identity::users.form', $this->formOptions() + [
            'user' => null,
            'assignedRoleIds' => [],
            'assignedFactoryId' => null,
        ]);
    }

    public function store(Request $request, ManageCompanyUser $action): RedirectResponse
    {
        $this->authorizeUsers($request);

        $data = $this->validated($request, null);

        $result = $action->invite($data, $data['roles'], $data['factory_id'] ?? null);

        return redirect()
            ->route('app.settings.users')
            // Shown once, on the page the redirect lands on, and never again.
            ->with('user_password', $result['password'])
            ->with('user_password_for', $result['user']->email)
            ->with('status', __('user.created', ['name' => $result['user']->name]));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeUsers($request);
        $this->assertMember($user);

        return view('identity::users.form', $this->formOptions() + [
            'user' => $user,
            'assignedRoleIds' => $this->assignments($user)->pluck('role_id')->all(),
            'assignedFactoryId' => $this->assignments($user)->pluck('factory_id')->filter()->first(),
        ]);
    }

    public function update(Request $request, User $user, ManageCompanyUser $action): RedirectResponse
    {
        $this->authorizeUsers($request);
        $this->assertMember($user);

        $data = $this->validated($request, $user);

        $action->update($user, $data, $data['roles'], $data['factory_id'] ?? null);

        return redirect()
            ->route('app.settings.users')
            ->with('status', __('user.updated', ['name' => $user->name]));
    }

    public function toggle(Request $request, User $user, ManageCompanyUser $action): RedirectResponse
    {
        $this->authorizeUsers($request);
        $this->assertMember($user);

        $membership = $this->membership($user);

        $action->setMembershipStatus($user, $membership->status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE');

        return back()->with('status', __('user.updated', ['name' => $user->name]));
    }

    public function resetPassword(Request $request, User $user, ManageCompanyUser $action): RedirectResponse
    {
        $this->authorizeUsers($request);
        $this->assertMember($user);

        $password = $action->resetPassword($user);

        return redirect()
            ->route('app.settings.users')
            ->with('user_password', $password)
            ->with('user_password_for', $user->email)
            ->with('status', __('user.password_reset', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user, ManageCompanyUser $action): RedirectResponse
    {
        $this->authorizeUsers($request);
        $this->assertMember($user);

        $name = $user->name;

        $action->remove($user);

        return redirect()
            ->route('app.settings.users')
            ->with('status', __('user.removed', ['name' => $name]));
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
            'roles.*' => ['string', 'size:26'],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            // Platform roles are never offered: a company that could assign one
            // could grant itself the run of the platform.
            'roles' => Role::query()
                ->whereIn('scope', ['COMPANY', 'FACTORY'])
                ->with('permissions:id,code')
                ->orderBy('scope')
                ->orderBy('name')
                ->get(),
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())
                ->orderBy('name')
                ->get(),
        ];
    }

    private function assignments(User $user)
    {
        return UserRole::where('user_id', $user->id)->get();
    }

    private function membership(User $user): CompanyUser
    {
        return $this->membershipQuery($user)->first() ?? abort(404);
    }

    /**
     * Somebody who is not a member of this company is not this company's to
     * see, let alone edit — 404 rather than 403, because whether that account
     * exists at all is none of their business.
     */
    private function assertMember(User $user): void
    {
        if (! $this->membershipQuery($user)->exists()) {
            abort(404);
        }
    }

    /**
     * Scoped to the acting company by hand, and that is not an oversight
     * elsewhere: company_users is the table that says which companies a user
     * belongs to, so it is deliberately not tenant-scoped — a person working
     * for two companies in a group has one account and two memberships.
     *
     * Which means every query against it has to name the company itself.
     * Without that, "is this person a member" quietly becomes "does this
     * person exist", and every account on the platform is editable from here.
     */
    private function membershipQuery(User $user)
    {
        return CompanyUser::where('user_id', $user->id)
            ->where('company_id', $this->context->companyId());
    }

    private function authorizeUsers(Request $request): void
    {
        if (! $request->user()->can('admin.user.manage')) {
            abort(403);
        }
    }
}
