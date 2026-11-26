<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\Identity\Actions\ManageRole;
use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\MenuModule;
use App\Modules\Identity\Models\MenuOverride;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Support\MenuOverlay;
use App\Modules\Identity\Support\MenuRegistry;
use App\Modules\Identity\Support\RoleBlueprint;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Access Control's menu tree (AnnotechERP 02/01): the caller's own sidebar,
 * the tree an admin edits, and a role's V/C/E/D/A/P/X grants over it.
 */
class MenuAccessApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MenuOverlay $overlay,
    ) {}

    /**
     * The sidebar for whoever is asking, in Brickland's shape: modules in
     * section order, each with its loose screens and folders interleaved by
     * order and a count of the screens shown. Only active entries whose View
     * permission the caller holds (a screen with none is open to every
     * signed-in user); empty folders and modules are dropped.
     */
    public function navigation(ApiCaller $caller): JsonResponse
    {
        $tree = $this->tree(activeOnly: true);
        $permissions = MenuAccessApiController::viewPermissions($tree);
        $gates = MenuAccessApiController::adminGates($tree);
        $held = array_flip($caller->permissionCodes(array_values(array_unique(array_merge(...array_values($permissions), ...array_values($gates), ...[['masterdata.manage']])))));

        // A setup screen (master data, settings, setup — RoleBlueprint) shows
        // only to one who may keep it: an admin-only permission of its own,
        // or — when it names none — an admin's own `masterdata.manage`.
        $visible = fn (Menu $menu): bool => $menu->in_sidebar
            && ($permissions[$menu->id] === [] || array_intersect_key(array_flip($permissions[$menu->id]), $held) !== [])
            && (! array_key_exists($menu->id, $gates) || array_intersect_key(array_flip($gates[$menu->id] ?: ['masterdata.manage']), $held) !== []);

        // Modules the platform has not given this company stay out (LicensedModules).
        $off = array_flip(\App\Modules\Platform\Support\LicensedModules::disabled($caller->companyId));
        $modules = [];
        foreach ($tree as $module) {
            if (isset($off[$module->code])) {
                continue;
            }
            [$entries, $count] = $this->entries($module->menus, null, $visible);
            if ($entries !== []) {
                $modules[] = [
                    'code' => $module->code, 'name' => $module->name, 'name_bn' => $module->name_bn, 'icon' => $module->icon,
                    'section' => $module->section, 'count' => $count, 'entries' => $entries,
                ];
            }
        }

        // Brickland's section order first, then the module's own.
        $sectionIndex = array_flip(MenuRegistry::SECTIONS);
        usort($modules, fn (array $a, array $b): int => ($sectionIndex[$a['section']] ?? 99) <=> ($sectionIndex[$b['section']] ?? 99));

        return ApiResponse::ok($modules);
    }

    /**
     * The setup screens (master data, settings, setup) this caller may not
     * keep: their URLs, so the app shows "not available" instead of the page
     * even when the address is typed in (user, 2026-11-22).
     */
    public function restricted(ApiCaller $caller): JsonResponse
    {
        $tree = $this->tree(activeOnly: true);
        $gates = MenuAccessApiController::adminGates($tree);
        $held = array_flip($caller->permissionCodes(array_values(array_unique(array_merge(['masterdata.manage'], ...array_values($gates))))));
        $open = [];
        $closed = [];
        foreach ($tree as $module) {
            foreach ($module->menus->where('is_leaf', true) as $menu) {
                if (! $menu->url) {
                    continue;
                }
                $shut = array_key_exists($menu->id, $gates) && array_intersect_key(array_flip($gates[$menu->id] ?: ['masterdata.manage']), $held) === [];
                $shut ? $closed[] = $menu->url : $open[] = $menu->url;
            }
        }

        return ApiResponse::ok(array_values(array_diff(array_unique($closed), $open)));
    }

    /**
     * The address prefixes of the modules the platform has not given this
     * company (LicensedModules): a typed-in address shows "not enabled".
     */
    public function locked(ApiCaller $caller): JsonResponse
    {
        return ApiResponse::ok(\App\Modules\Platform\Support\LicensedModules::lockedUrls($caller->companyId));
    }

    /**
     * The screens and folders under one parent, in order. A folder may hold
     * folders (Brickland's OPERATION → Style & Order …); it carries its own
     * entries, every screen beneath it as items, and shows only when one of
     * them does.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function entries(Collection $menus, ?string $parentId, callable $visible): array
    {
        $entries = [];
        $count = 0;
        foreach ($menus->where('parent_id', $parentId) as $menu) {
            if ($menu->is_leaf) {
                if ($visible($menu)) {
                    $entries[] = ['type' => 'screen'] + $this->items(collect([$menu]))[0];
                    $count++;
                }

                continue;
            }
            [$children, $n] = $this->entries($menus, $menu->id, $visible);
            if ($n > 0) {
                $screens = fn (array $list): array => array_merge(...array_map(fn (array $e): array => $e['type'] === 'folder' ? $e['items'] : [array_diff_key($e, ['type' => 1])], $list));
                $entries[] = ['type' => 'folder', 'code' => $menu->code, 'name' => $menu->name, 'name_bn' => $menu->name_bn, 'icon' => $menu->icon, 'items' => $screens($children), 'entries' => $children];
                $count += $n;
            }
        }

        return [$entries, $count];
    }

    /** The whole tree, for Menu Definition and the grant matrix. */
    public function menus(): JsonResponse
    {
        $this->allow('admin.role.manage');

        return ApiResponse::ok($this->tree(activeOnly: false)->map(fn (MenuModule $module): array => [
            'id' => $module->id,
            'code' => $module->code,
            'name' => $module->name,
            'name_bn' => $module->name_bn,
            'icon' => $module->icon,
            'sort_order' => $module->sort_order,
            'active' => $module->active,
            'menus' => $module->menus->map(fn (Menu $menu): array => $this->menuSummary($menu))->values()->all(),
        ])->values()->all());
    }

    /**
     * Rename, reorder or hide a menu for this company; only code adds or
     * removes a screen, and no company's edit reaches another's tree.
     */
    public function updateMenu(Request $request, Menu $menu): JsonResponse
    {
        $this->allow('admin.role.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'security_level' => ['sometimes', Rule::in(Menu::SECURITY_LEVELS)],
            'icon' => ['nullable', 'string', 'max:48'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'open_new_window' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $this->override(['menu_id' => $menu->id], $data);

        return ApiResponse::ok($this->menuSummary($this->overlay->menus(collect([$menu->fresh('permissions')]))->first()));
    }

    /** Module Registration's grid: every module with its menu count. */
    public function modules(): JsonResponse
    {
        $this->allow('admin.role.manage');

        $modules = $this->overlay->tree(MenuModule::query()->with('menus')->get());

        return ApiResponse::ok($modules->map(fn (MenuModule $module): array => $this->moduleSummary($module))->all());
    }

    /** Rename, reorder, re-icon or hide a module for this company. */
    public function updateModule(Request $request, MenuModule $module): JsonResponse
    {
        $this->allow('admin.role.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:48'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $this->override(['menu_module_id' => $module->id], $data);

        return ApiResponse::ok($this->moduleSummary($this->overlay->tree(collect([$module->load('menus')]))->first()));
    }

    /**
     * A role's matrix: every leaf, which flags it offers, and whether the role
     * holds all, some or none of each flag's permissions.
     */
    public function grants(Role $role): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        $held = array_flip($role->permissions()->pluck('name')->all());
        $leaves = $this->overlay->menus(Menu::query()->where('is_leaf', true)->with('permissions', 'module')->get())->where('active', true);

        return ApiResponse::ok([
            'role' => ['id' => $role->id, 'code' => $role->name, 'name' => $role->description, 'is_system' => $role->is_system, 'is_admin' => (bool) $role->is_admin],
            'menus' => $leaves->map(function (Menu $menu) use ($held): array {
                $flags = [];
                foreach ($menu->permissions->groupBy('flag') as $flag => $rows) {
                    $count = $rows->filter(fn ($row): bool => isset($held[$row->permission_name]))->count();
                    $flags[$flag] = $count === 0 ? 'none' : ($count === $rows->count() ? 'all' : 'some');
                }

                return [
                    'code' => $menu->code,
                    'module' => $menu->module?->code,
                    'name' => $menu->name,
                    'name_bn' => $menu->name_bn,
                    'flags' => $flags,
                ];
            })->values()->all(),
        ]);
    }

    public function saveGrants(Request $request, Role $role, ManageRole $roles): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        $data = $request->validate([
            'grants' => ['present', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => [Rule::in(Menu::FLAGS)],
        ]);

        $roles->grantMenus($role, $data['grants']);

        return $this->grants($role->fresh());
    }

    /**
     * Role Menu Assignment's Records: every role this company sees, with each
     * menu it holds at least one whole flag on.
     */
    public function grantRecords(): JsonResponse
    {
        $this->allow('admin.role.manage');

        $companyId = $this->context->companyId();
        $roles = Role::query()->availableTo($companyId)->with('permissions:id,name')->orderBy('name')->get();
        $leaves = $this->overlay->menus(Menu::query()->where('is_leaf', true)->with('permissions', 'module')->get())->where('active', true);

        $rows = [];
        foreach ($roles as $role) {
            $held = array_flip($role->permissions->pluck('name')->all());
            foreach ($leaves as $menu) {
                $flags = '';
                foreach ($menu->permissions->groupBy('flag') as $flag => $permissions) {
                    if ($permissions->every(fn ($p): bool => isset($held[$p->permission_name]))) {
                        $flags .= $flag;
                    }
                }
                if ($flags !== '') {
                    $rows[] = [
                        'id' => $role->id.'-'.$menu->id,
                        'role_id' => $role->id,
                        'role' => $role->description ?? $role->name,
                        'module' => $menu->module?->name,
                        'menu_id' => $menu->id,
                        'menu' => $menu->name,
                        'flags' => $flags,
                        'is_system' => (bool) $role->is_system || (bool) $role->is_admin,
                        'updated_at' => $role->updated_at?->toIso8601String(),
                    ];
                }
            }
        }

        return ApiResponse::ok($rows);
    }

    /** "New menu grant": one menu's flags for one role. */
    public function saveMenuGrant(Request $request, Role $role, Menu $menu, ManageRole $roles): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        $data = $request->validate(['flags' => ['present', 'array'], 'flags.*' => [Rule::in(Menu::FLAGS)]]);

        $roles->grantMenu($role, $menu, $data['flags']);

        return $this->grants($role->fresh());
    }

    /**
     * @return Collection<int, MenuModule>
     */
    private function tree(bool $activeOnly): Collection
    {
        // A shared row that is inactive is a screen the code retired (a
        // company's own hiding lives in its overrides), so it never shows;
        // the company's overlay then decides what is active for it.
        $tree = $this->overlay->tree(MenuModule::query()->with(['menus' => fn ($q) => $q->where('active', true)->with('permissions')])->get());

        if (! $activeOnly) {
            return $tree;
        }

        return $tree->where('active', true)->each(
            fn (MenuModule $module) => $module->setRelation('menus', $module->menus->where('active', true)->values()),
        )->values();
    }

    /**
     * Saves this company's override; a field sent back equal to the code's
     * own value is stored as null, so it follows the code again.
     *
     * @param  array{menu_id?: string, menu_module_id?: string}  $target
     * @param  array<string, mixed>  $data
     */
    private function override(array $target, array $data): void
    {
        $shared = isset($target['menu_id']) ? Menu::findOrFail($target['menu_id']) : MenuModule::findOrFail($target['menu_module_id']);
        $override = MenuOverride::query()->firstOrNew($target);

        foreach ($data as $field => $value) {
            $override->{$field} = $value == $shared->{$field} ? null : $value;
        }

        $override->save();
        $this->overlay->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleSummary(MenuModule $module): array
    {
        return [
            'id' => $module->id,
            'code' => $module->code,
            'name' => $module->name,
            'name_bn' => $module->name_bn,
            'slug' => $module->slug,
            'icon' => $module->icon,
            'sort_order' => $module->sort_order,
            'description' => $module->description,
            'active' => $module->active,
            'is_system' => $module->is_system,
            'menus_count' => $module->menus->where('is_leaf', true)->count(),
        ];
    }

    /**
     * Setup screens (RoleBlueprint::isAdminMenu) => the admin-only
     * permissions among the ones they name (empty: none of their own).
     *
     * @param  Collection<int, MenuModule>  $tree
     * @return array<string, list<string>>
     */
    private static function adminGates(Collection $tree): array
    {
        $out = [];
        foreach ($tree as $module) {
            $codes = $module->menus->pluck('code', 'id');
            foreach ($module->menus->where('is_leaf', true) as $menu) {
                if (RoleBlueprint::isAdminMenu($module->code, $menu->parent_id ? ($codes[$menu->parent_id] ?? null) : null)) {
                    $out[$menu->id] = $menu->permissions->pluck('permission_name')->filter(fn (string $p): bool => RoleBlueprint::adminOnly($p))->unique()->values()->all();
                }
            }
        }

        return $out;
    }

    /**
     * menu id => the View permission names gating it.
     *
     * @param  Collection<int, MenuModule>  $tree
     * @return array<string, list<string>>
     */
    private static function viewPermissions(Collection $tree): array
    {
        // A system screen opens by its own gate, not by every view verb its View grant carries.
        $gates = MenuRegistry::gates();
        $out = [];
        foreach ($tree as $module) {
            foreach ($module->menus as $menu) {
                $out[$menu->id] = $menu->is_system && array_key_exists((string) $menu->code, $gates)
                    ? $gates[$menu->code]
                    : $menu->permissions->where('flag', 'V')->pluck('permission_name')->values()->all();
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return list<array<string, mixed>>
     */
    private function items(Collection $menus): array
    {
        return $menus->map(fn (Menu $menu): array => [
            'code' => $menu->code,
            'name' => $menu->name,
            'name_bn' => $menu->name_bn,
            'url' => $menu->url,
            'icon' => $menu->icon,
            'open_new_window' => $menu->open_new_window,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function menuSummary(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'code' => $menu->code,
            'parent_id' => $menu->parent_id,
            'name' => $menu->name,
            'name_bn' => $menu->name_bn,
            'description' => $menu->description,
            'url' => $menu->url,
            'security_level' => $menu->security_level,
            'icon' => $menu->icon,
            'sort_order' => $menu->sort_order,
            'is_leaf' => $menu->is_leaf,
            'in_sidebar' => $menu->in_sidebar,
            'open_new_window' => $menu->open_new_window,
            'is_report' => $menu->is_report,
            'is_system' => $menu->is_system,
            'active' => $menu->active,
            'flags' => $menu->permissions->groupBy('flag')->map->count()->all(),
        ];
    }

    /** Another company's role is not reachable (404), the same as ManageRole. */
    private function assertVisible(Role $role): void
    {
        if ($role->company_id !== null && $role->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }
}
