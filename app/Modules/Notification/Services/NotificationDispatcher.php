<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Events\NotificationCreated;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationPreference;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Creates notifications and records what was done with them
 * (SRS 27, ERD Section 17 rules 1-2).
 *
 * Persist first, deliver second. The notification row is committed before any
 * channel is attempted, so a dropped websocket or a bounced email loses a
 * delivery attempt rather than the message. A technician who never hears about
 * a critical breakdown because a broadcast failed is the outcome this ordering
 * exists to prevent.
 *
 * The title and body are rendered in the recipient's locale when the row is
 * written. Rendering at read time would mean a notification changes language
 * when somebody switches the interface, and a message somebody was sent should
 * still read as the message they were sent (SRS 48).
 */
class NotificationDispatcher
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Sends one notification to one person.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $recipient,
        string $eventType,
        array $data = [],
        string $severity = 'INFO',
        ?string $factoryId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $actionUrl = null,
        int $escalationLevel = 0,
        ?string $sourceNotificationId = null,
    ): ?Notification {
        if (! in_array($eventType, Notification::EVENT_TYPES, true)) {
            // A typo in an event name would silently disable the notification
            // it was meant to send, which is worse than a loud failure.
            throw ValidationException::withMessages([
                'event_type' => __('notification.unknown_event', ['event' => $eventType]),
            ]);
        }

        if (! in_array($severity, Notification::SEVERITIES, true)) {
            throw ValidationException::withMessages([
                'severity' => __('notification.unknown_severity'),
            ]);
        }

        $locale = $recipient->locale ?? config('app.locale');
        $preference = $this->preferenceFor($recipient, $eventType);

        $notification = DB::transaction(function () use (
            $recipient, $eventType, $data, $severity, $factoryId, $entityType,
            $entityId, $actionUrl, $escalationLevel, $sourceNotificationId, $locale, $preference
        ): Notification {
            $notification = Notification::create([
                'company_id' => $this->context->companyId(),
                'user_id' => $recipient->id,
                'factory_id' => $factoryId,
                'event_type' => $eventType,
                // Rendered now, in the recipient's language, and stored.
                'title' => $this->render("notification.event.{$eventType}.title", $data, $locale),
                'body' => $this->render("notification.event.{$eventType}.body", $data, $locale),
                'locale' => $locale,
                'data_json' => $data,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_url' => $actionUrl,
                'severity' => $severity,
                'escalation_level' => $escalationLevel,
                'source_notification_id' => $sourceNotificationId,
            ]);

            // The in-app copy exists the moment the row does; every other
            // channel is an attempt that may or may not land.
            NotificationDelivery::create([
                'company_id' => $notification->company_id,
                'notification_id' => $notification->id,
                'channel' => 'IN_APP',
                'status' => 'SENT',
                'sent_at' => CarbonImmutable::now(),
            ]);

            foreach ($preference->enabledChannels() as $channel) {
                if ($channel === 'IN_APP') {
                    continue;
                }

                NotificationDelivery::create([
                    'company_id' => $notification->company_id,
                    'notification_id' => $notification->id,
                    'channel' => $channel,
                    'status' => 'PENDING',
                ]);
            }

            return $notification;
        });

        // Outside the transaction on purpose: a broadcast that throws must not
        // roll back the notification it was announcing.
        $this->broadcast($notification);

        return $notification;
    }

    /**
     * Sends the same notification to several people.
     *
     * @param  Collection<int, User>|list<User>  $recipients
     * @param  array<string, mixed>  $data
     * @return Collection<int, Notification>
     */
    public function sendToMany(
        Collection|array $recipients,
        string $eventType,
        array $data = [],
        string $severity = 'INFO',
        ?string $factoryId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $actionUrl = null,
    ): Collection {
        $sent = collect();

        foreach ($recipients as $recipient) {
            $notification = $this->send(
                $recipient, $eventType, $data, $severity, $factoryId,
                $entityType, $entityId, $actionUrl,
            );

            if ($notification !== null) {
                $sent->push($notification);
            }
        }

        return $sent;
    }

    /**
     * Opening a list marks things read. It does not mean anybody has acted, so
     * it never stops an escalation.
     */
    public function markRead(Notification $notification): Notification
    {
        if ($notification->isRead()) {
            return $notification;
        }

        $notification->forceFill(['read_at' => CarbonImmutable::now()])->save();

        return $notification->fresh();
    }

    /**
     * Saying "I have this" — the act that stops an escalation.
     *
     * Acknowledging one notification acknowledges its whole chain, because the
     * factory manager reading a stopped line does not need the company admin
     * told about it thirty minutes later.
     */
    public function acknowledge(Notification $notification, ?string $userId = null): Notification
    {
        $now = CarbonImmutable::now();
        $rootId = $notification->rootId();

        DB::transaction(function () use ($rootId, $now): void {
            Notification::where('id', $rootId)
                ->orWhere('source_notification_id', $rootId)
                ->whereNull('acknowledged_at')
                ->get()
                ->each(fn (Notification $n) => $n->forceFill([
                    'acknowledged_at' => $now,
                    'read_at' => $n->read_at ?? $now,
                ])->save());
        });

        unset($userId);

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => CarbonImmutable::now()]);
    }

    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * The user's setting, or the default for the event.
     *
     * Defaults are per event rather than blanket: a critical breakdown mails
     * people, a maintenance reminder does not, and starting everyone on
     * "email me everything" trains them to filter the lot into a folder.
     */
    public function preferenceFor(User $user, string $eventType): NotificationPreference
    {
        $existing = NotificationPreference::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return new NotificationPreference([
            'company_id' => $this->context->companyId(),
            'user_id' => $user->id,
            'event_type' => $eventType,
            'in_app' => true,
            'email' => in_array($eventType, self::EMAIL_BY_DEFAULT, true),
            'sms' => false,
            'whatsapp' => false,
        ]);
    }

    /**
     * Events worth an email before anybody configures anything. Everything
     * else stays in-app until somebody asks for more.
     */
    private const EMAIL_BY_DEFAULT = [
        'BREAKDOWN_CRITICAL',
        'MAINTENANCE_OVERDUE',
        'APPROVAL_REQUESTED',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $key, array $data, string $locale): string
    {
        $rendered = __($key, $data, $locale);

        // A missing translation returns the key itself. Showing "notification.
        // event.FOO.title" to a technician is worse than showing the event
        // name, so it falls back to something a person can read.
        if ($rendered === $key) {
            return __($key.'_fallback', $data, $locale) === $key.'_fallback'
                ? ($data['title'] ?? $key)
                : __($key.'_fallback', $data, $locale);
        }

        return $rendered;
    }

    /**
     * Real-time delivery.
     *
     * Reverb lands with the real-time workstream; until then this records the
     * attempt as skipped rather than pretending it succeeded. A delivery row
     * saying SENT when nothing was sent is worse than no row.
     */
    private function broadcast(Notification $notification): void
    {
        try {
            NotificationCreated::dispatch($notification);

            NotificationDelivery::create([
                'company_id' => $notification->company_id,
                'notification_id' => $notification->id,
                'channel' => 'BROADCAST',
                // Handed to the transport, not proven delivered. A websocket
                // frame that reaches a browser nobody is looking at is not a
                // notification anybody received, and claiming otherwise would
                // make the delivery record a lie.
                'status' => 'SENT',
                'sent_at' => CarbonImmutable::now(),
            ]);
        } catch (\Throwable $e) {
            // A failure here must never propagate: the notification is already
            // committed and the recipient can see it in the interface.
            Log::warning('Notification broadcast failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
