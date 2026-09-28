<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A consignment on its way (Procurement spec §10).
 *
 * The question a merchandiser asks every morning is "where is it and when
 * does it land", so the ETA is the field this table exists for. It is kept
 * alongside the **original** ETA rather than overwritten, because the
 * difference between what the forwarder first promised and what actually
 * happened is the supplier's delivery record — and a field that is quietly
 * updated every week has no record in it at all.
 *
 * Document status is tracked separately from the goods: cloth that has
 * landed but whose documents are still at the bank cannot be cleared, and
 * "arrived" on its own would suggest it can be.
 */
class Shipment extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PLANNED', 'SHIPPED', 'IN_TRANSIT', 'ARRIVED', 'CLEARED', 'CANCELLED'];

    public const MODES = ['SEA', 'AIR', 'ROAD', 'COURIER'];

    /** Where the papers are — separate from where the goods are. */
    public const DOCUMENT_STATUSES = ['AWAITED', 'RECEIVED', 'AT_BANK', 'RELEASED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'PLANNED' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['IN_TRANSIT', 'ARRIVED', 'CANCELLED'],
        'IN_TRANSIT' => ['ARRIVED', 'CANCELLED'],
        // Landed, but not through customs until the papers are released.
        'ARRIVED' => ['CLEARED'],
        'CLEARED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'shipments';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'proforma_invoice_id', 'letter_of_credit_id',
        'vendor_id', 'shipment_no', 'mode', 'bl_awb_no', 'container_no', 'vessel_or_flight',
        'shipping_date', 'original_eta', 'eta', 'actual_arrival_date', 'cleared_date',
        'port_of_loading', 'port_of_discharge', 'status', 'document_status', 'notes',
        'created_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_date' => 'date',
            'original_eta' => 'date',
            'eta' => 'date',
            'actual_arrival_date' => 'date',
            'cleared_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Still coming. */
    public function isInFlight(): bool
    {
        return in_array($this->status, ['PLANNED', 'SHIPPED', 'IN_TRANSIT'], true);
    }

    /** Negative once the ETA is behind us. */
    public function daysToEta(?CarbonImmutable $at = null): ?int
    {
        if ($this->eta === null) {
            return null;
        }

        return (int) ($at ?? CarbonImmutable::today())->startOfDay()->diffInDays($this->eta->startOfDay(), false);
    }

    /** Past its ETA and still not here. */
    public function isOverdue(?CarbonImmutable $at = null): bool
    {
        return $this->isInFlight() && ($this->daysToEta($at) ?? 1) < 0;
    }

    /**
     * How late it ran against what was first promised — the number a
     * supplier scorecard is built from. Null until it lands.
     */
    public function delayDays(): ?int
    {
        if ($this->actual_arrival_date === null || $this->original_eta === null) {
            return null;
        }

        return (int) $this->original_eta->startOfDay()->diffInDays($this->actual_arrival_date->startOfDay(), false);
    }
}
