<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\MenuModule;
use App\Modules\Identity\Models\MenuPermission;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Support\MenuRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Writes MenuRegistry to the menu tables (AnnotechERP 02/01). Safe to run on
 * every deploy:
 *  - every module, folder and screen takes the registry's label, order,
 *    section and structure (url, parent, leaf) — a company's own names,
 *    order and hidden entries live in `menu_overrides`, never on these
 *    shared rows, so the code owns them outright;
 *  - a screen or module the code no longer has is deactivated, never deleted;
 *  - every system menu's flag → permission rows are rebuilt from the
 *    registry, because which permission a screen checks is a fact of code.
 */
class SyncMenus
{
    /**
     * @return array{modules: int, menus: int, permissions: int}
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $modules = [];
            foreach (MenuRegistry::modules() as $code => [$label, $icon, $order, $section]) {
                $modules[$code] = $this->upsertModule($code, $label, $icon, $order, $section);
            }

            // A folder with no order of its own sits just before its first screen.
            $firstLeaf = [];
            foreach (MenuRegistry::menus() as $index => $leaf) {
                if ($leaf[2] !== null && ! isset($firstLeaf[$leaf[2]])) {
                    $firstLeaf[$leaf[2]] = ($index + 1) * 10 - 5;
                }
            }

            // A folder holding only folders sits just before the first of them.
            $definitions = MenuRegistry::folders();
            $orders = [];
            foreach ($definitions as $code => $definition) {
                $orders[$code] = $definition[3] ?? $firstLeaf[$code] ?? null;
            }
            foreach ($definitions as $code => $definition) {
                if ($orders[$code] === null) {
                    $children = array_filter($orders, fn (?int $order, string $child): bool => $order !== null && ($definitions[$child][4] ?? null) === $code, ARRAY_FILTER_USE_BOTH);
                    $orders[$code] = $children === [] ? 0 : min($children) - 1;
                }
            }

            // Parents first, so a folder inside another finds it.
            uksort($definitions, fn (string $a, string $b): int => (isset($definitions[$a][4]) ? 1 : 0) <=> (isset($definitions[$b][4]) ? 1 : 0));
            $folders = [];
            foreach ($definitions as $code => $definition) {
                [$module, $label, $icon] = $definition;
                $parent = $definition[4] ?? null;
                $folders[$code] = $this->upsertMenu($code, [
                    'menu_module_id' => $modules[$module]->id,
                    'parent_id' => $parent !== null ? $folders[$parent]->id : null,
                    'url' => null,
                    'icon' => $icon,
                    'is_leaf' => false,
                    'in_sidebar' => true,
                ], $label, $orders[$code]);
            }

            $allPermissions = Permission::query()->pluck('name')->all();
            $seen = array_keys($folders);
            $permissionRows = 0;

            foreach (MenuRegistry::menus() as $index => [$code, $module, $folder, $label, $url, $icon, $view, $prefixes, $inSidebar]) {
                $menu = $this->upsertMenu($code, [
                    'menu_module_id' => $modules[$module]->id,
                    'parent_id' => $folder !== null ? $folders[$folder]->id : null,
                    'url' => $url,
                    'icon' => $icon,
                    'is_leaf' => true,
                    'in_sidebar' => $inSidebar,
                ], $label, ($index + 1) * 10);
                $seen[] = $code;

                MenuPermission::query()->where('menu_id', $menu->id)->delete();
                foreach ($this->flagsFor((array) $view, $prefixes, $allPermissions) as [$flag, $permission]) {
                    MenuPermission::create(['menu_id' => $menu->id, 'flag' => $flag, 'permission_name' => $permission]);
                    $permissionRows++;
                }
            }

            Menu::query()->where('is_system', true)->whereNotIn('code', $seen)->update(['active' => false]);
            // A module the registry dropped (the old project's, replaced by Brickland's) leaves the sidebar too.
            MenuModule::query()->where('is_system', true)->whereNotIn('code', array_keys($modules))->update(['active' => false]);

            return ['modules' => count($modules), 'menus' => count($seen), 'permissions' => $permissionRows];
        });
    }

    /**
     * V is exactly the screen's gate; the other flags come from every
     * permission in the screen's families, sorted by their verb.
     *
     * @param  list<string>  $view
     * @param  list<string>  $prefixes
     * @param  list<string>  $allPermissions
     * @return list<array{0: string, 1: string}>
     */
    private function flagsFor(array $view, array $prefixes, array $allPermissions): array
    {
        $rows = [];
        foreach (array_filter($view) as $permission) {
            $rows["V|{$permission}"] = ['V', $permission];
        }

        foreach ($allPermissions as $permission) {
            foreach ($prefixes as $prefix) {
                if ($permission !== $prefix && ! str_starts_with($permission, $prefix.'.')) {
                    continue;
                }
                $flag = MenuRegistry::flagFor($permission);
                // A family's view verbs (asset.asset.view, asset.financial.view) ride on the screen's View
                // grant, so the matrix can give them. The sidebar still opens the screen by its own gate only
                // (MenuRegistry::gates): ordersummary.view must not open Create New, which asks
                // ordersummary.create — it showed in the sidebar and 404'd on click (user, 2026-11-20).
                $rows["{$flag}|{$permission}"] = [$flag, $permission];
            }
        }

        return array_values($rows);
    }

    private function upsertModule(string $code, string $label, string $icon, int $order, string $section): MenuModule
    {
        $module = MenuModule::query()->firstOrNew(['code' => $code]);

        $module->fill([
            'name' => $this->label($label, 'en'),
            'name_bn' => $this->label($label, 'bn'),
            'sort_order' => $order,
            'section' => $section,
            'active' => true,
            'slug' => strtolower(str_replace('_', '-', $code)),
            'icon' => $icon,
            'is_system' => true,
        ])->save();

        return $module;
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    private function upsertMenu(string $code, array $structure, string $label, int $order): Menu
    {
        $menu = Menu::query()->firstOrNew(['code' => $code]);

        if (! $menu->exists) {
            $menu->security_level = 'INTERNAL';
        }

        $menu->fill($structure + [
            'name' => $this->label($label, 'en'),
            'name_bn' => $this->label($label, 'bn'),
            'sort_order' => $order,
            'active' => true,
            'is_system' => true,
        ])->save();

        return $menu;
    }

    private function label(string $key, string $locale): string
    {
        $text = __($key, [], $locale);

        return is_string($text) && $text !== $key ? $text : $key;
    }
}
