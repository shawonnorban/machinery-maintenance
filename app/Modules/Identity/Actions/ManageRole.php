<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\MenuPermission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * A company's own role, built from scratch or cloned from a seeded one (SRS
 * 5.3 rule 5, API 4.1).
 *
 * Nothing in the product created one before this — RoleController has always
 * been read-only, and "a tenant clones one to customize it" was documented
 * intent with no code behind it. Seeded roles (`is_system`, `company_id`
 * null) are never touched by any method here; every one of them either
 * refuses a seeded role outright or scopes its query to this company's own.
 *
 * Same code/label split as the permission catalog: `name` (Spatie's own
 * uniqueness key) is the machine code this role is addressed by everywhere
 * else — user_roles.role_id, the permission matrix — and `description`
 * carries the human-readable label a person actually reads on a screen.
 */
class ManageRole
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array{code: string, name: string, scope?: string, permissions?: list<string>}  $data
     */
    public function create(array $data): Role
    {
        $role = Role::create([
            'company_id' => $this->context->companyId(),
            'name' => $this->normalizeCode($data['code']),
            'guard_name' => 'web',
            'description' => $data['name'],
            'scope' => $data['scope'] ?? 'FACTORY',
            'is_system' => false,
        ] + $this->profile($data));

        $this->syncPermissions($role, $data['permissions'] ?? []);
        $this->applyAdmin($role);

        return $role->fresh('permissions');
    }

    /**
     * A seeded role's permission set, copied into a new role this company
     * owns and can edit — its own name from here, not the source's.
     *
     * @param  array{code: string, name: string, permissions?: list<string>}  $data
     */
    public function cloneFrom(Role $source, array $data): Role
    {
        $this->assertAvailable($source);

        $role = $this->create($data + ['scope' => $source->scope]);

        if (! array_key_exists('permissions', $data)) {
            $this->syncPermissions($role, $source->permissions()->pluck('name')->all());
        }

        return $role->fresh('permissions');
    }

    /**
     * @param  array{code?: string, name?: string, scope?: string, permissions?: list<string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        $this->assertEditable($role);

        $role->update([
            'name' => isset($data['code']) ? $this->normalizeCode($data['code']) : $role->name,
            'description' => $data['name'] ?? $role->description,
            'scope' => $data['scope'] ?? $role->scope,
        ] + $this->profile($data, $role));

        if (array_key_exists('permissions', $data)) {
            $this->syncPermissions($role, $data['permissions']);
        }

        $this->applyAdmin($role);

        return $role->fresh('permissions');
    }

    public function delete(Role $role): void
    {
        $this->assertEditable($role);

        if (UserRole::where('role_id', $role->id)->exists()) {
            // 409, not 422: nothing about the request was invalid, the role
            // simply cannot go while somebody is still holding it.
            throw ValidationException::withMessages([
                'name' => __('user.role_in_use'),
            ])->status(409);
        }

        $role->delete();
    }

    /**
     * Role Menu Assignment (AnnotechERP 02/01): `$grants` is menu code => the
     * V/C/E/D/A/P/X flags ticked for it, covering the whole matrix. The role
     * ends up with every permission those flags name, plus whatever it holds
     * that no menu names at all — the matrix only rewrites what it shows.
     *
     * @param  array<string, list<string>>  $grants
     */
    public function grantMenus(Role $role, array $grants): Role
    {
        $this->assertEditable($role);

        $mapped = MenuPermission::query()->pluck('permission_name')->unique()->all();
        $outside = $role->permissions()->pluck('name')->diff($mapped)->all();

        $menuIds = Menu::query()->whereIn('code', array_keys($grants))->pluck('id', 'code');
        $granted = [];
        foreach ($grants as $code => $flags) {
            if (! isset($menuIds[$code]) || $flags === []) {
                continue;
            }
            $granted = array_merge($granted, MenuPermission::query()
                ->where('menu_id', $menuIds[$code])
                ->whereIn('flag', array_intersect($flags, Menu::FLAGS))
                ->pluck('permission_name')
                ->all());
        }

        $this->syncPermissions($role, array_values(array_unique(array_merge($outside, $granted))));
        $this->applyAdmin($role);

        return $role->fresh('permissions');
    }

    /**
     * Role Menu Assignment's "New menu grant": one menu's flags for one role.
     * Unticking a flag drops its permissions — except those another menu
     * also names, which may still be what the role holds that other screen
     * by (one permission can gate several screens).
     *
     * @param  list<string>  $flags
     */
    public function grantMenu(Role $role, Menu $menu, array $flags): Role
    {
        $this->assertEditable($role);

        $rows = MenuPermission::query()->where('menu_id', $menu->id)->get();
        $add = $rows->whereIn('flag', array_intersect($flags, Menu::FLAGS))->pluck('permission_name')->unique();
        $drop = $rows->whereNotIn('flag', $flags)->pluck('permission_name')->unique()->diff($add);
        $shared = MenuPermission::query()->whereIn('permission_name', $drop)->where('menu_id', '!=', $menu->id)->pluck('permission_name')->unique();
        $drop = $drop->diff($shared);

        $current = $role->permissions()->pluck('name');
        $this->syncPermissions($role, $current->merge($add)->unique()->diff($drop)->values()->all());
        $this->applyAdmin($role);

        return $role->fresh('permissions');
    }

    /**
     * Brickland's Role Definition columns (AnnotechERP 02/01). On update a key
     * the caller did not send keeps its value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profile(array $data, ?Role $role = null): array
    {
        $out = [];
        foreach (['name_bn', 'short_name', 'rank', 'is_admin', 'active'] as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        return $out;
    }

    /** An admin role bypasses the menu matrix: it simply holds every permission. */
    private function applyAdmin(Role $role): void
    {
        if ($role->is_admin) {
            $this->syncPermissions($role, Permission::query()->pluck('name')->all());
        }
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function syncPermissions(Role $role, array $permissionCodes): void
    {
        $ids = Permission::whereIn('name', $permissionCodes)->pluck('id');

        $role->permissions()->sync($ids);
    }

    /**
     * A role this company may clone: its own, or a platform-seeded one.
     * Another company's is invisible here — 404, not 403, so its existence
     * is not confirmed to a company that has no business with it.
     */
    private function assertAvailable(Role $source): void
    {
        if ($source->company_id !== null && $source->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }

    /**
     * A role this company may change: its own, never a seeded one, and never
     * another company's (404 for the same reason as above).
     */
    private function assertEditable(Role $role): void
    {
        // is_system first: a seeded role's company_id is null, which is not
        // "belongs to another company" — checking ownership first would
        // 404 every seeded role instead of refusing the edit with a 403.
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => __('user.role_not_editable'),
            ])->status(403);
        }

        if ($role->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
