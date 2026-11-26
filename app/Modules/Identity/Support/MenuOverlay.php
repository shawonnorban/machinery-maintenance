<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\MenuModule;
use App\Modules\Identity\Models\MenuOverride;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lays the calling company's own names, order, icons and visibility over the
 * shared menu tree (AnnotechERP 02/01, `menu_overrides`). Applied in memory
 * only: the shared rows are never written from a company's screen.
 */
class MenuOverlay
{
    /** @var array{modules: Collection<string, MenuOverride>, menus: Collection<string, MenuOverride>}|null */
    private ?array $overrides = null;

    /** Whose overrides are cached: a router keeps controller instances between requests. */
    private ?string $cachedFor = null;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Modules with their `menus` relation loaded, overlaid and re-sorted.
     *
     * @param  Collection<int, MenuModule>  $modules
     * @return Collection<int, MenuModule>
     */
    public function tree(Collection $modules): Collection
    {
        foreach ($modules as $module) {
            $this->apply($module, $this->overrides()['modules']->get($module->id));
            $module->setRelation('menus', $this->menus($module->menus));
        }

        return $modules->sortBy('sort_order')->values();
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    public function menus(Collection $menus): Collection
    {
        foreach ($menus as $menu) {
            $this->apply($menu, $this->overrides()['menus']->get($menu->id));
        }

        return $menus->sortBy('sort_order')->values();
    }

    public function module(MenuModule $module): MenuModule
    {
        $this->apply($module, $this->overrides()['modules']->get($module->id));

        return $module;
    }

    /** Forget what was read, after this request changed an override. */
    public function flush(): void
    {
        $this->overrides = null;
    }

    private function apply(Model $model, ?MenuOverride $override): void
    {
        if ($override === null) {
            return;
        }

        foreach (MenuOverride::FIELDS as $field) {
            if ($override->{$field} !== null && $model->isFillable($field)) {
                $model->setAttribute($field, $override->{$field});
            }
        }

        // Shown, never saved: nothing here is a change to the shared row.
        $model->syncOriginal();
    }

    /**
     * @return array{modules: Collection<string, MenuOverride>, menus: Collection<string, MenuOverride>}
     */
    private function overrides(): array
    {
        $companyId = $this->context->companyIdOrNull();

        if ($this->overrides !== null && $this->cachedFor === $companyId) {
            return $this->overrides;
        }

        $this->cachedFor = $companyId;
        $rows = $companyId !== null ? MenuOverride::query()->get() : collect();

        return $this->overrides = [
            'modules' => $rows->whereNotNull('menu_module_id')->keyBy('menu_module_id'),
            'menus' => $rows->whereNotNull('menu_id')->keyBy('menu_id'),
        ];
    }
}
