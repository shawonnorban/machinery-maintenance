<?php

declare(strict_types=1);

namespace App\Shared\View\Composers;

use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Navigation\SidebarMenu;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Supplies the shell with everything the sidebar and header need.
 *
 * A composer rather than a base controller, so no controller has to remember
 * to pass shell data. A controller that forgets would render a layout with an
 * empty sidebar and no company switcher, which is the kind of bug that only
 * shows up on the one screen nobody tested.
 */
class AppShellComposer
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SidebarMenu $menu,
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        $view->with([
            'tenant' => $this->context,
            'menu' => $user ? $this->menu->build() : [],
            'companies' => $user ? $this->companies() : collect(),
            'factories' => $this->factories(),
            'scopedFactoryId' => session(ResolveTenantContext::FACTORY_SCOPE_KEY),

            // Per-request context for window.App. Built here rather than
            // inline in Blade, and never compiled into the Vite bundle: it
            // varies per user and per company (Handbook 5.2).
            'appJs' => [
                'companyId' => $this->context->companyIdOrNull(),
                'locale' => app()->getLocale(),
                'csrf' => csrf_token(),
            ],
        ]);
    }

    /**
     * @return Collection<int, Company>
     */
    private function companies(): Collection
    {
        return auth()->user()->accessibleCompanies();
    }

    /**
     * Only the factories this user can actually reach. A factory filter that
     * lists factories the user cannot see would leak the estate's shape.
     *
     * @return Collection<int, Factory>
     */
    private function factories(): Collection
    {
        if (! $this->context->hasContext()) {
            return collect();
        }

        $accessible = $this->context->accessibleFactoryIds();

        if ($accessible === []) {
            return collect();
        }

        return Factory::query()
            ->whereIn('id', $accessible)
            ->orderBy('name')
            ->get();
    }
}
