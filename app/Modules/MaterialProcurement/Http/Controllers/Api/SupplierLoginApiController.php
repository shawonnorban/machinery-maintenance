<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\Identity\Actions\ManageCompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\SupplierLogin;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Material Procurement → Control → Supplier Portal Access (AnnotechERP 08/07):
 * portal logins for suppliers. A login is an external account in this
 * company holding only the Supplier role, linked to the supplier it quotes
 * for; the supplier signs in on the normal login page and sees only the
 * Supplier Portal. Active switches it on and off at once.
 */
class SupplierLoginApiController extends ApiController
{
    /** The role every portal login holds, and nothing else. */
    public const ROLE = 'SUPPLIER';

    public function index(): JsonResponse
    {
        $this->allow('material.portal_access.manage');
        $rows = SupplierLogin::query()->orderByDesc('created_at')->get();

        return ApiResponse::ok([
            'logins' => $this->present($rows),
            'active' => $rows->where('active', true)->count(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (MaterialSupplier $s): array => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
        ]);
    }

    /** Create login: the account, its membership of this company with the Supplier role, and its link to the supplier. */
    public function store(Request $request, ManageCompanyUser $users): JsonResponse
    {
        $this->allow('material.portal_access.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)->where('active', true)],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ], [
            'supplier_id.required' => __('materialprocurement.portal.supplier_required'), 'contact_name.required' => __('materialprocurement.portal.contact_required'),
            'email.required' => __('materialprocurement.portal.email_required'), 'email.unique' => __('materialprocurement.portal.email_taken'),
            'password.required' => __('materialprocurement.portal.password_required'),
        ]);
        $role = \App\Modules\Identity\Support\RoleProvisioner::resolve(self::ROLE, $this->caller()->companyId) ?? abort(500);
        $login = DB::transaction(function () use ($users, $data, $role): SupplierLogin {
            ['user' => $user] = $users->invite([
                'name' => $data['contact_name'], 'email' => $data['email'], 'password' => $data['password'], 'locale' => 'en',
                // The initial password is changed at the first sign-in.
                'user_type' => 'EXTERNAL', 'must_change_password' => true, 'changed_by' => $this->caller()->auditUserId(),
            ], [$role->id], null);

            return SupplierLogin::query()->create([
                'supplier_id' => $data['supplier_id'], 'user_id' => $user->id, 'contact_name' => $data['contact_name'], 'active' => true, 'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        $supplier = MaterialSupplier::query()->findOrFail($data['supplier_id']);
        Procurement::audit('CREATE', 'supplier-portal-access', $supplier->code, Procurement::en('materialprocurement.audit.login_created', ['email' => $data['email'], 'supplier' => $supplier->name]));

        return ApiResponse::created($this->present(collect([$login]))[0]);
    }

    /** The Active switch: off suspends the account's membership here, on brings it back. */
    public function setActive(Request $request, string $login, ManageCompanyUser $users): JsonResponse
    {
        $this->allow('material.portal_access.manage');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $row = SupplierLogin::query()->findOrFail($login);
        DB::transaction(function () use ($row, $data, $users): void {
            $row->update(['active' => $data['active']]);
            $users->setMembershipStatus(User::query()->findOrFail($row->user_id), $data['active'] ? 'ACTIVE' : 'SUSPENDED');
        });
        $supplier = MaterialSupplier::query()->findOrFail($row->supplier_id);
        Procurement::audit('UPDATE', 'supplier-portal-access', $supplier->code, Procurement::en($data['active'] ? 'materialprocurement.audit.login_enabled' : 'materialprocurement.audit.login_disabled', [
            'email' => User::query()->whereKey($row->user_id)->value('email'), 'supplier' => $supplier->name,
        ]));

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    /** The users of every active portal login: who hears of a new requirement. @return Collection<int, User> */
    public static function activeUsers(): Collection
    {
        return User::query()->whereIn('id', SupplierLogin::query()->where('active', true)->pluck('user_id'))->get();
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows): array
    {
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get(['id', 'email', 'last_login_at'])->keyBy('id');

        return $rows->map(fn (SupplierLogin $l): array => [
            'id' => $l->id, 'supplier_id' => $l->supplier_id, 'supplier' => $suppliers[$l->supplier_id]->name ?? null, 'supplier_code' => $suppliers[$l->supplier_id]->code ?? null,
            'contact_name' => $l->contact_name, 'email' => $users[$l->user_id]->email ?? null, 'created_at' => $l->created_at->toIso8601String(),
            'last_sign_in' => $users[$l->user_id]?->last_login_at?->toIso8601String(), 'active' => $l->active,
        ])->values()->all();
    }
}
