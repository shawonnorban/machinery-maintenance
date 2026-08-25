<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message to one person (SRS 27, ERD Section 17).
 *
 * Written before anything is sent, so a dropped websocket or a bounced email
 * loses a delivery attempt rather than the notification. The in-app record is
 * the source of truth; every other channel is a way of drawing attention to it.
 */
class Notification extends BaseModel
{
    use BelongsToTenant;

    public const SEVERITIES = ['INFO', 'WARNING', 'CRITICAL'];

    /**
     * The events the product actually raises (SRS 27).
     *
     * A fixed list rather than free text: a preference screen cannot offer a
     * switch for an event nobody enumerated, and a typo in an event name
     * silently disables the notification it was meant to send.
     */
    public const EVENT_TYPES = [
        'MAINTENANCE_DUE',
        'MAINTENANCE_OVERDUE',
        'BREAKDOWN_REPORTED',
        'BREAKDOWN_CRITICAL',
        'WORK_ORDER_ASSIGNED',
        'WORK_ORDER_COMPLETED',
        'APPROVAL_REQUESTED',
        'APPROVAL_DECIDED',
        'LOW_STOCK',
        'WARRANTY_EXPIRY',
        'AMC_EXPIRY',
        'WEBHOOK_DISABLED',
        // Platform support access to this company's data (SRS 5.4). A customer
        // is entitled to be told, in the product, that somebody outside their
        // company looked at their data.
        'SUPPORT_ACCESS',

        // A support ticket, told to the customer's own keyholders.
        'TICKET_REPLIED',
        'TICKET_RESOLVED',

        // Addressed to platform staff rather than to anybody inside a tenant,
        // and so carrying a null company_id. The customer has their own
        // SUPPORT_ACCESS notice above; this is the other half of the same
        // accountability, told to the people who can act on it.
        'PLATFORM_SUPPORT_OPENED',
        'PLATFORM_SUPPORT_CLOSED',
        'PLATFORM_TENANT_SUSPENDED',
        'PLATFORM_TENANT_CLOSED',
        'PLATFORM_TENANT_ERASED',
        'PLATFORM_TICKET_OPENED',
        'PLATFORM_TICKET_REPLIED',
    ];

    /** Those of the above that are addressed to the platform, not to a tenant. */
    public const PLATFORM_EVENT_TYPES = [
        'PLATFORM_SUPPORT_OPENED',
        'PLATFORM_SUPPORT_CLOSED',
        'PLATFORM_TENANT_SUSPENDED',
        'PLATFORM_TENANT_CLOSED',
        'PLATFORM_TENANT_ERASED',
        'PLATFORM_TICKET_OPENED',
        'PLATFORM_TICKET_REPLIED',
    ];

    protected $table = 'notifications';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'user_id', 'factory_id', 'event_type', 'title', 'body',
        'locale', 'data_json', 'entity_type', 'entity_id', 'action_url',
        'severity', 'read_at', 'acknowledged_at', 'escalation_level',
        'source_notification_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'expires_at' => 'datetime',
            'escalation_level' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /** The notification this one escalates, if any. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_notification_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(self::class, 'source_notification_id')->orderBy('escalation_level');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Acknowledged means somebody said "I have this", not that a list was
     * opened. Escalation stops on the former only.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function isEscalation(): bool
    {
        return $this->source_notification_id !== null;
    }

    /**
     * The head of the chain. An escalation of an escalation still counts
     * against the same original event, which is what keeps delay_minutes
     * measured from one fixed point.
     */
    public function rootId(): string
    {
        return $this->source_notification_id ?? $this->id;
    }
}
