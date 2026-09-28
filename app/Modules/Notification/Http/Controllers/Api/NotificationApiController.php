<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A person's own notifications, over the wire (API 19; mirrors the web
 * `NotificationController`, which this delegates every write to unchanged
 * per ADR-003).
 *
 * No permission check beyond being signed in, same as the web screen: these
 * are addressed to the caller by name and scoped to them, so the control
 * that matters is the scope, not a role. A machine caller has no inbox —
 * these events are addressed to a person.
 */
class NotificationApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->person();
        $filter = $request->query('filter', 'UNREAD');

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->when($filter === 'UNREAD', fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        // The web index reads this off `$this->dispatcher->unreadCount($user)`
        // for a bell-icon-style badge — a client has no other way to get the
        // same figure, since "ALL" filter's own `total` counts read ones too.
        return ApiResponse::ok(
            collect($paginator->items())->map(fn (Notification $n): array => $this->summary($n))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $this->dispatcher->unreadCount($user),
            ],
        );
    }

    public function markRead(Notification $notification): JsonResponse
    {
        $this->assertOwn($notification);

        return ApiResponse::ok($this->summary($this->dispatcher->markRead($notification)));
    }

    public function markAllRead(): JsonResponse
    {
        $this->dispatcher->markAllRead($this->person());

        return ApiResponse::noContent();
    }

    /**
     * Saying "I have this" — the act that stops an escalation, as distinct
     * from having opened the list.
     */
    public function acknowledge(Notification $notification): JsonResponse
    {
        $this->assertOwn($notification);

        return ApiResponse::ok($this->summary(
            $this->dispatcher->acknowledge($notification, $this->person()->id),
        ));
    }

    public function preferences(): JsonResponse
    {
        $user = $this->person();

        $existing = NotificationPreference::where('user_id', $user->id)->get()->keyBy('event_type');

        $preferences = collect(Notification::EVENT_TYPES)
            ->mapWithKeys(fn (string $event) => [
                $event => $this->preferenceSummary($existing->get($event) ?? $this->dispatcher->preferenceFor($user, $event)),
            ]);

        return ApiResponse::ok($preferences->all());
    }

    public function savePreferences(Request $request): JsonResponse
    {
        $user = $this->person();

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.email' => ['sometimes', 'boolean'],
            'preferences.*.sms' => ['sometimes', 'boolean'],
            'preferences.*.whatsapp' => ['sometimes', 'boolean'],
        ]);

        foreach ($data['preferences'] as $eventType => $channels) {
            if (! in_array($eventType, Notification::EVENT_TYPES, true)) {
                continue;
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'event_type' => $eventType],
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

        $existing = NotificationPreference::where('user_id', $user->id)->get()->keyBy('event_type');

        $preferences = collect(Notification::EVENT_TYPES)
            ->mapWithKeys(fn (string $event) => [
                $event => $this->preferenceSummary($existing->get($event) ?? $this->dispatcher->preferenceFor($user, $event)),
            ]);

        return ApiResponse::ok($preferences->all());
    }

    /**
     * A notification addressed to somebody else is not this person's to read
     * or acknowledge, even inside the same company.
     */
    private function assertOwn(Notification $notification): void
    {
        if ($notification->user_id !== $this->person()->id) {
            abort(404);
        }
    }

    private function person(): User
    {
        $user = $this->caller()->user;

        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'event_type' => $notification->event_type,
            'title' => $notification->title,
            'body' => $notification->body,
            'severity' => $notification->severity,
            'entity_type' => $notification->entity_type,
            'entity_id' => $notification->entity_id,
            'action_url' => $notification->action_url,
            'is_read' => $notification->isRead(),
            'is_acknowledged' => $notification->isAcknowledged(),
            // Says why it arrived — an escalation that looks like an
            // ordinary notification gives the reader no reason to treat it
            // differently, same as the web's own badge.
            'is_escalation' => $notification->isEscalation(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'acknowledged_at' => $notification->acknowledged_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function preferenceSummary(NotificationPreference $preference): array
    {
        return [
            'in_app' => true,
            'email' => $preference->email,
            'sms' => $preference->sms,
            'whatsapp' => $preference->whatsapp,
        ];
    }
}
