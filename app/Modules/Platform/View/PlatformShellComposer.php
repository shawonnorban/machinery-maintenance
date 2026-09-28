<?php

declare(strict_types=1);

namespace App\Modules\Platform\View;

use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * What the platform shell needs, on every platform screen.
 *
 * A separate composer from AppShellComposer rather than a branch inside it.
 * That one reads the tenant context for almost everything it supplies, and
 * platform staff have no tenant context — so nearly every line of it would
 * have to be guarded, and the guard that got missed would throw on a screen
 * nobody thought to open.
 *
 * Every query here is explicitly unscoped. These rows belong to nobody's
 * company, which is exactly the case a tenant scope refuses to match.
 */
class PlatformShellComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'unreadNotifications' => $this->unread(),
            'recentNotifications' => $this->recent(),

            // On the shell rather than only on the customer's page: "is
            // somebody inside a customer right now" should be answerable from
            // any screen, not by remembering to go and look.
            'openGrantCount' => SupportGrant::withoutGlobalScope(TenantScope::class)
                ->whereNull('ended_at')
                ->where('expires_at', '>', now())
                ->count(),

            'openTicketCount' => SupportTicket::withoutGlobalScope(TenantScope::class)
                ->whereIn('status', SupportTicket::OPEN_STATUSES)
                ->count(),
        ]);
    }

    private function unread(): int
    {
        if (auth()->guest()) {
            return 0;
        }

        // A count, not the rows. The shell renders on every screen.
        return Notification::withoutGlobalScope(TenantScope::class)
            ->whereNull('company_id')
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return Collection<int, Notification>
     */
    private function recent(): Collection
    {
        if (auth()->guest()) {
            return collect();
        }

        // Read ones included: a dropdown that empties the moment somebody
        // glances at it is a dropdown that looks broken.
        return Notification::withoutGlobalScope(TenantScope::class)
            ->whereNull('company_id')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }
}
