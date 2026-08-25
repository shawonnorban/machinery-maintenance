<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The two platform screens that are not about one customer.
 *
 * Support access across every customer, because "who is inside somebody's data
 * right now" is a question about the platform rather than about a tenant, and
 * answering it by opening customers one at a time is not answering it.
 *
 * And the notification history, which is where the bell's dropdown stops.
 */
class PlatformDeskController extends Controller
{
    public function support(): View
    {
        $grants = SupportGrant::withoutGlobalScope(TenantScope::class)
            ->with(['holder:id,name', 'company:id,name,code'])
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();

        return view('platform::desk.support', [
            // Split rather than filtered in the view: open grants are the
            // reason to open this page, and the history is the reason to stay.
            'open' => $grants->filter(fn (SupportGrant $grant): bool => $grant->isActive())->values(),
            'past' => $grants->reject(fn (SupportGrant $grant): bool => $grant->isActive())->values(),
        ]);
    }

    public function tickets(): View
    {
        $tickets = SupportTicket::withoutGlobalScope(TenantScope::class)
            ->with(['company:id,name,code', 'opener:id,name', 'assignee:id,name'])
            ->orderByRaw("status = 'CLOSED'")
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get();

        return view('platform::desk.tickets', [
            'open' => $tickets->filter(fn (SupportTicket $t): bool => $t->isOpen())->values(),
            'closed' => $tickets->reject(fn (SupportTicket $t): bool => $t->isOpen())->values(),
        ]);
    }

    public function notifications(): View
    {
        return view('platform::desk.notifications', [
            'notifications' => $this->platformNotifications()
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function markRead(Request $request): RedirectResponse
    {
        $this->platformNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', __('notification.marked_read'));
    }

    /**
     * This person's platform notifications, and only those.
     *
     * `whereNull('company_id')` is the whole of what makes them platform rows,
     * and it is stated here once so no screen can accidentally show somebody
     * the notifications belonging to a customer they support.
     */
    private function platformNotifications(): Builder
    {
        return Notification::withoutGlobalScope(TenantScope::class)
            ->whereNull('company_id')
            ->where('user_id', auth()->id());
    }
}
