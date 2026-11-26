<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Billing\Services\EntitlementGuard;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Access Control → User Company Mapping (AnnotechERP 02/02): which of the
 * group's companies each of this company's users may work in, and which one
 * they land in at sign-in. A membership is company data, so the screen only
 * ever shows companies the administrator belongs to, and adds or removes a
 * membership only where they also administer users.
 */
class UserCompanyMappingApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
    ) {}

    public function index(): JsonResponse
    {
        $this->allow('admin.user.manage');

        $visible = $this->visibleCompanies();
        $users = $this->members();
        $rows = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->whereIn('user_id', $users->keys())
            ->whereIn('company_id', $visible->keys())
            ->get();

        return ApiResponse::ok($rows->map(fn (CompanyUser $m): array => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'user' => $users->get($m->user_id),
            'company_id' => $m->company_id,
            'company' => $visible->get($m->company_id),
            'is_default' => (bool) $m->is_default,
            'status' => $m->status,
            'locked' => ! $this->administers($m->company_id),
        ])->sortBy([['user', 'asc'], ['company', 'asc']])->values()->all());
    }

    public function options(): JsonResponse
    {
        $this->allow('admin.user.manage');

        return ApiResponse::ok([
            'users' => $this->members()->map(fn (string $label, string $id): array => ['value' => $id, 'label' => $label])->values()->all(),
            'companies' => $this->visibleCompanies()->filter(fn (string $name, string $id): bool => $this->administers($id))
                ->map(fn (string $name, string $id): array => ['value' => $id, 'label' => $name])->values()->all(),
        ]);
    }

    /** "New company mapping": joins a user to a company (or updates their default there). */
    public function store(Request $request, EntitlementGuard $entitlements): JsonResponse
    {
        $this->allow('admin.user.manage');

        $data = $request->validate([
            'user_id' => ['required', 'string', Rule::in($this->members()->keys()->all())],
            'company_id' => ['required', 'string', Rule::in($this->visibleCompanies()->keys()->all())],
            'is_default' => ['sometimes', 'boolean'],
        ], ['user_id.required' => __('access.mapping.subject_required.user')]);
        $this->assertAdministers($data['company_id']);

        $membership = DB::transaction(function () use ($data, $entitlements): CompanyUser {
            $membership = CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('user_id', $data['user_id'])->where('company_id', $data['company_id'])->first();

            if ($membership === null) {
                // A new seat in that company counts against that company's plan.
                $entitlements->assertCanAdd('ACTIVE_USERS', $data['company_id']);
                $membership = CompanyUser::withoutGlobalScope(TenantScope::class)->create([
                    'company_id' => $data['company_id'], 'user_id' => $data['user_id'], 'status' => 'ACTIVE', 'is_default' => false,
                ]);
            }

            if ((bool) ($data['is_default'] ?? false)) {
                $this->makeDefault($membership);
            }

            return $membership;
        });

        return ApiResponse::created(['id' => $membership->id]);
    }

    /** Edit: only the default flag changes. */
    public function update(Request $request, string $membership): JsonResponse
    {
        $this->allow('admin.user.manage');

        $row = $this->visibleMembership($membership);
        $this->assertAdministers($row->company_id);
        $data = $request->validate(['is_default' => ['required', 'boolean']]);

        DB::transaction(function () use ($row, $data): void {
            if ($data['is_default']) {
                $this->makeDefault($row);
            } else {
                $row->forceFill(['is_default' => false])->save();
            }
        });

        return ApiResponse::ok(['id' => $row->id]);
    }

    /** Removes a user from another group company; leaving this one is done from User Account Registration. */
    public function destroy(string $membership): JsonResponse
    {
        $this->allow('admin.user.manage');

        $row = $this->visibleMembership($membership);
        $this->assertAdministers($row->company_id);
        if ($row->company_id === $this->context->companyId()) {
            throw ApiException::of(ErrorCode::CONFLICT, __('access.mapping.current_company'));
        }

        $row->delete();

        return ApiResponse::noContent();
    }

    /** @return Collection<string, string> user id => label */
    private function members(): Collection
    {
        $ids = CompanyUser::query()->where('company_id', $this->context->companyId())->pluck('user_id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'username', 'email'])
            ->mapWithKeys(fn (User $u): array => [$u->id => $u->name.' ('.($u->username ?? $u->email).')']);
    }

    /** @return Collection<string, string> company id => name, the companies the caller belongs to */
    private function visibleCompanies(): Collection
    {
        $ids = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $this->caller()->user->id)->where('status', 'ACTIVE')->pluck('company_id');

        return Company::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id');
    }

    private function administers(string $companyId): bool
    {
        return $this->permissions->has($this->caller()->user, $companyId, 'admin.user.manage');
    }

    private function assertAdministers(string $companyId): void
    {
        if (! $this->administers($companyId)) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('access.mapping.not_administered'));
        }
    }

    private function visibleMembership(string $id): CompanyUser
    {
        $row = CompanyUser::withoutGlobalScope(TenantScope::class)->whereKey($id)->first();
        if ($row === null || ! $this->members()->has($row->user_id) || ! $this->visibleCompanies()->has($row->company_id)) {
            abort(404);
        }

        return $row;
    }

    private function makeDefault(CompanyUser $membership): void
    {
        CompanyUser::withoutGlobalScope(TenantScope::class)->where('user_id', $membership->user_id)
            ->whereKeyNot($membership->id)->update(['is_default' => false]);
        $membership->forceFill(['is_default' => true])->save();
    }
}
