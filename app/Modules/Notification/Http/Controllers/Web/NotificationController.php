<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers\Web;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A person's own notifications.
 *
 * No permission check beyond being signed in: these are addressed to the
 * reader by name, and the query is scoped to them. A permission would be the
 * wrong control — the thing that must not happen is reading somebody else's,
 * and that is prevented by the scope rather than by a role.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter', 'UNREAD');

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->when($filter === 'UNREAD', fn ($q) => $q->whereNull('read_at'))
            ->with('source:id,created_at')
            ->orderByDesc('created_at')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('notification::notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unread' => $this->dispatcher->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        $this->assertOwn($request, $notification);

        $this->dispatcher->markRead($notification);

        return back()->with('status', __('notification.marked_read'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->dispatcher->markAllRead($request->user());

        return back()->with('status', __('notification.marked_read'));
    }

    /**
     * Saying "I have this" — the act that stops an escalation, as distinct
     * from having opened the list.
     */
    public function acknowledge(Request $request, Notification $notification): RedirectResponse
    {
        $this->assertOwn($request, $notification);

        $this->dispatcher->acknowledge($notification, $request->user()->id);

        return back()->with('status', __('notification.acknowledged'));
    }

    public function preferences(Request $request): View
    {
        $existing = NotificationPreference::where('user_id', $request->user()->id)
            ->get()
            ->keyBy('event_type');

        $preferences = collect(Notification::EVENT_TYPES)
            ->mapWithKeys(fn (string $event) => [
                $event => $existing->get($event) ?? $this->dispatcher->preferenceFor($request->user(), $event),
            ]);

        return view('notification::notifications.preferences', [
            'preferences' => $preferences,
            'events' => Notification::EVENT_TYPES,
        ]);
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.email' => ['sometimes', 'boolean'],
            'preferences.*.sms' => ['sometimes', 'boolean'],
            'preferences.*.whatsapp' => ['sometimes', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $eventType => $channels) {
            if (! in_array($eventType, Notification::EVENT_TYPES, true)) {
                continue;
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'event_type' => $eventType],
                [
                    'company_id' => $this->context->companyId(),
                    // Not switchable. The record of what happened is part of
                    // the audit trail rather than a preference.
                    'in_app' => true,
                    'email' => (bool) ($channels['email'] ?? false),
                    'sms' => (bool) ($channels['sms'] ?? false),
                    'whatsapp' => (bool) ($channels['whatsapp'] ?? false),
                ],
            );
        }

        return back()->with('status', __('notification.preferences_saved'));
    }

    /**
     * A notification addressed to somebody else is not this person's to read
     * or acknowledge, even inside the same company.
     */
    private function assertOwn(Request $request, Notification $notification): void
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
    }
}
