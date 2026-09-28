<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The letter of credit a bank opens against a proforma invoice
 * (Procurement spec §10).
 *
 * Two numbers have to agree, and the spec asks for the comparison by name:
 * what the bank committed against what the order is actually worth. They
 * drift for ordinary reasons — the order was revised, the PI came back with
 * a different price — and the drift matters because an LC short of the
 * order will not pay the whole shipment, while one over it has the
 * factory's credit line tied up for nothing.
 *
 * Amendments are their own rows. A bank charges for each one and the
 * history is what a trade-finance audit reads; folding them into the
 * current amount would lose both the fee and the reason.
 */
class LetterOfCredit extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'OPENED', 'SHIPPED', 'CLOSED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['OPENED', 'CANCELLED'],
        'OPENED' => ['SHIPPED', 'CLOSED', 'CANCELLED'],
        'SHIPPED' => ['CLOSED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'letters_of_credit';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'proforma_invoice_id', 'vendor_id',
        'lc_no', 'lc_date', 'bank_name', 'branch', 'amount', 'currency',
        'expiry_date', 'last_shipment_date', 'tenor_days', 'status', 'notes',
        'created_by', 'opened_at', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'lc_date' => 'date',
            'expiry_date' => 'date',
            'last_shipment_date' => 'date',
            'tenor_days' => 'integer',
            'amount' => MoneyCast::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(LetterOfCreditAmendment::class)->orderBy('amendment_no');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['OPENED', 'SHIPPED'], true);
    }

    /**
     * The gap between what the bank committed and what the order is worth.
     * Negative means the LC is short of the order.
     */
    public function varianceAgainstOrder(): ?string
    {
        $order = $this->purchaseOrder;

        if ($order === null) {
            return null;
        }

        return bcsub((string) $this->amount, (string) $order->subtotal, 4);
    }

    /** Expiring soon enough that somebody has to decide about it. */
    public function daysToExpiry(?CarbonImmutable $at = null): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) ($at ?? CarbonImmutable::today())->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }
}
