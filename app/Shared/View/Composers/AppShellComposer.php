<?php

declare(strict_types=1);

namespace App\Shared\View\Composers;

use App\Modules\Notification\Models\Notification;
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
            'unreadNotifications' => $this->unreadNotifications(),
            'recentNotifications' => $this->recentNotifications(),

            // Per-request context for window.App. Built here rather than
            // inline in Blade, and never compiled into the Vite bundle: it
            // varies per user and per company (Handbook 5.2).
            'appJs' => [
                'companyId' => $this->context->companyIdOrNull(),
                'locale' => app()->getLocale(),
                'csrf' => csrf_token(),

                // Which channels this page may subscribe to (SRS 29). Written
                // by the server from resolved context, never read from
                // anything the user can type — though the socket authorizes
                // every subscription again regardless, because this is
                // convenience and not security (ADR-018).
                'userId' => $user?->id,
                'factoryId' => session(ResolveTenantContext::FACTORY_SCOPE_KEY),
                'reverbKey' => config('broadcasting.connections.reverb.key'),
                'reverbHost' => config('broadcasting.connections.reverb.options.host'),
                'reverbPort' => (int) config('broadcasting.connections.reverb.options.port'),
                'reverbScheme' => config('broadcasting.connections.reverb.options.scheme'),

                // Strings the socket handlers need, translated server-side:
                // the bundle is shared by both languages.
                't' => [
                    'breakdown' => __('breakdown.breakdown'),
                    'assignedToYou' => __('notification.event_work_order_assigned'),

                    // What the offline queue says about itself (SRS 38). A
                    // technician must never wonder whether their work was
                    // recorded, and a queue that can only say so in English
                    // does not answer that for most of this factory floor.
                    'sync_live' => __('common.sync_live'),
                    'sync_pending' => __('common.sync_pending'),
                    'sync_failed' => __('common.sync_failed'),
                    'sync_offline' => __('common.sync_offline'),
                    'sync_refused' => __('common.sync_refused'),
                    'sync_will_send' => __('common.sync_will_send'),
                    'sync_discard' => __('common.sync_discard'),
                ],
            ],
        ]);
    }

    /**
     * The count for the header bell.
     *
     * A single count query rather than the rows: the header renders on every
     * screen, and loading a list nobody opened would add a query and a hundred
     * models to every request in the product.
     */
    private function unreadNotifications(): int
    {
        if (auth()->guest() || $this->context->companyIdOrNull() === null) {
            return 0;
        }

        return Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
    }

    /**
     * The handful the bell dropdown shows.
     *
     * Five, newest first, unread ones included whether or not they have been
     * read: a dropdown that empties the moment somebody glances at it is a
     * dropdown that looks broken. The full history is a page away.
     *
     * @return Collection<int, Notification>
     */
    private function recentNotifications(): Collection
    {
        if (auth()->guest() || $this->context->companyIdOrNull() === null) {
            return collect();
        }

        return Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
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
