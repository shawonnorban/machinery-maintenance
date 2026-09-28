<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Actions\ManageRole;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A company's own roles, built from scratch or cloned from a seeded one (API
 * 4.1, SRS 5.3 rule 5).
 *
 * The rules live in ManageRole (ADR-003): a seeded role is visible here but
 * never editable, and every read is scoped to this company's own roles plus
 * the platform-seeded set — never another company's.
 */
class RoleApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('admin.role.manage');

        $query = Role::availableTo($this->context->companyId())
            ->with('permissions:id,name');

        $query = $this->applySort($query, $request, ['description', 'scope'], 'description', 'asc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        // One grouped query for the whole page, same as the web index's own
        // `$assigned` — an administrator handing roles out needs to see
        // which ones are actually in use, and `destroy()` already refuses
        // to remove one that is.
        $holders = UserRole::query()
            ->whereIn('role_id', collect($paginator->items())->pluck('id'))
            ->selectRaw('role_id, count(*) as holders')
            ->groupBy('role_id')
            ->pluck('holders', 'role_id');

        return ApiResponse::paginated(
            $paginator,
            fn (Role $role): array => $this->summary($role, (int) ($holders[$role->id] ?? 0)),
        );
    }

    public function store(Request $request, ManageRole $roles): JsonResponse
    {
        $this->allow('admin.role.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', Rule::in(['COMPANY', 'FACTORY'])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
            // Clone an existing role's permission set (a seeded one, or this
            // company's own) rather than building the list from scratch.
            'clone_from' => ['nullable', 'integer'],
        ]);

        $role = isset($data['clone_from'])
            ? $roles->cloneFrom(Role::findOrFail($data['clone_from']), $data)
            : $roles->create($data);

        return ApiResponse::created($this->detail($role));
    }

    public function show(Role $role): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        return ApiResponse::ok($this->detail($role->load('permissions:id,name')));
    }

    public function update(Request $request, Role $role, ManageRole $roles): JsonResponse
    {
        $this->allow('admin.role.manage');

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'name' => ['sometimes', 'string', 'max:255'],
            'scope' => ['sometimes', Rule::in(['COMPANY', 'FACTORY'])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = $roles->update($role, $data);

        return ApiResponse::ok($this->detail($role));
    }

    public function destroy(Role $role, ManageRole $roles): JsonResponse
    {
        $this->allow('admin.role.manage');

        $roles->delete($role);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Role $role, ?int $holdersCount = null): array
    {
        return [
            'id' => $role->id,
            'code' => $role->name,
            'name' => $role->description,
            'scope' => $role->scope,
            'is_system' => $role->is_system,
            'company_id' => $role->company_id,
            // Free off `index()`'s own `with('permissions:id,name')` — the
            // web's read-only role list shows the same count next to each
            // role, so a client building that same list needs it too.
            'permissions_count' => $role->relationLoaded('permissions') ? $role->permissions->count() : null,
            'holders_count' => $holdersCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Role $role): array
    {
        return $this->summary($role) + [
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];
    }

    /**
     * A role this company may see: its own, or a platform-seeded one — never
     * another company's. 404, not 403: the same non-disclosure the rest of
     * the API uses for a cross-tenant record.
     */
    private function assertVisible(Role $role): void
    {
        if ($role->company_id !== null && $role->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }
}
