<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Services;

/**
 * The events this system will send to somebody else's (SRS 43, ADR-035).
 *
 * A fixed catalogue rather than "whatever the application happens to dispatch".
 * A webhook is a published interface: once an ERP is parsing
 * `breakdown.reported`, its shape is a promise, and an event that appears
 * because somebody added a model observer is a promise nobody made.
 *
 * The names match the websocket event names on purpose. The same happening
 * should not have two names depending on which transport carried it.
 */
class WebhookEvents
{
    public const BREAKDOWN_REPORTED = 'breakdown.reported';

    public const ASSET_STATUS_CHANGED = 'asset.status-changed';

    public const WORK_ORDER_ASSIGNED = 'work-order.assigned';

    public const WORK_ORDER_UPDATED = 'work-order.updated';

    public const STOCK_CHANGED = 'stock.changed';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BREAKDOWN_REPORTED,
            self::ASSET_STATUS_CHANGED,
            self::WORK_ORDER_ASSIGNED,
            self::WORK_ORDER_UPDATED,
            self::STOCK_CHANGED,
        ];
    }

    public static function isKnown(string $eventType): bool
    {
        return in_array($eventType, self::all(), true);
    }

    /**
     * A translation key for the subscription screen.
     */
    public static function label(string $eventType): string
    {
        return 'webhook.events_list.'.str_replace(['.', '-'], ['_', '_'], $eventType);
    }
}
