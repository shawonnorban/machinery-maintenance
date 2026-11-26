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
        // The Line Chief's/Engineer's turn in the extended sign-off chain
        // (SRS 15: repair -> verify -> submit -> approve).
        'BREAKDOWN_AWAITING_VERIFICATION',
        'BREAKDOWN_AWAITING_APPROVAL',
        'WORK_ORDER_ASSIGNED',
        'WORK_ORDER_COMPLETED',
        'APPROVAL_REQUESTED',
        'APPROVAL_DECIDED',
        // A request still waiting well past its due date, told to the type's
        // escalation contact (Approval Center, AnnotechERP 04/03).
        'APPROVAL_OVERDUE',
        'LOW_STOCK',
        'FLOOR_REQUISITION_SUBMITTED',
        // The store's side of the requisition: what was issued, what fell
        // short, and the arrival that lets a short line finally be filled.
        'FLOOR_REQUISITION_ISSUED',
        'FLOOR_REQUISITION_SHORTAGE',
        'EARMARK_STOCK_ARRIVED',
        // Chased by the daily sweep: stock lent out that has not come back,
        // and a shortage claim nobody has managed to fill.
        'RETURNABLE_OVERDUE',
        'EARMARK_WAITING',
        // A delivered lot that did not make the four-point bar
        // (Inventory spec §4.3).
        'FABRIC_INSPECTION_FAILED',
        // A chemical or dye lot approaching — or past — its expiry, while
        // there is still stock of it (Inventory spec §12).
        'LOT_EXPIRING',
        // Imports in the air (Procurement spec §10): what lands this week,
        // what should already be here, and the letter of credit that is
        // about to lapse with nothing shipped against it.
        // A count sheet left open: its frozen figures describe the shelf
        // a little less with every day that passes (Inventory spec §12).
        'STOCK_COUNT_STALE',
        'SHIPMENT_ARRIVING',
        'SHIPMENT_DELAYED',
        'LC_EXPIRING',
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

        // Access Control → Global Notification Broadcast (AnnotechERP 02/04).
        'GLOBAL_BROADCAST',

        // Prototype Desk → Sample Requisition (AnnotechERP 07/03): the sample
        // room person a sample is handed to.
        'SAMPLE_ASSIGNED',
        // Prototype Desk → Buyer Feedback: the action asked of the assignee, cleared once resolved.
        'SAMPLE_FEEDBACK_ACTION',

        // Material Procurement → Purchase Requisition (AnnotechERP 08/03): every
        // supplier portal login, when a requisition's lines open for quotation.
        'MP_REQUIREMENT_AVAILABLE',
        // Material Procurement → Price Review (08/05): the supplier's portal logins
        // hear what was decided on their quotation; the follow-up merchandiser
        // hears of the material handed to them.
        'MP_QUOTATION_NEGOTIATION',
        'MP_QUOTATION_REJECTED',
        'MP_QUOTATION_APPROVED',
        'MP_MATERIAL_ASSIGNED',
        // Approved Material & Follow-Up (08/06): the requisition's author and the
        // price's approver hear each delivery that comes in.
        'MP_MATERIAL_RECEIVED',
        // Supplier Portal (08/04), heard by whoever reviews prices: a first
        // quotation on a line, and each revision of it.
        'MP_QUOTATION_RECEIVED',
        'MP_QUOTATION_REVISED',
        // HR & Payroll → OT Request / OT Approval (24/05): the approver hears of
        // a request sent to them; the requester hears what was decided.
        'HR_OT_REQUESTED',
        'HR_OT_DECIDED',

        // Order Summary, Dev Sample Status and Pre-Budget (user, 2026-11-21):
        // a save told to the team at once, one notification per save, and
        // each morning the work that is due (Erp\Support\SheetNotices).
        'OS_ORDERS_CHANGED',
        'OS_PENDING_WORK',
        'DS_SAMPLES_CHANGED',
        'DS_DEADLINE_DUE',
        'PB_REQUISITIONS_CHANGED',
        'PB_BUDGET_CHANGED',
        'PB_STOCK_NEEDED',
        'PB_PENDING_WORK',
        'ES_STATEMENTS_CHANGED',
        'ES_PENDING_WORK',

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
        'PLATFORM_TICKET_SLA_BREACHED',
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
        'PLATFORM_TICKET_SLA_BREACHED',
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
