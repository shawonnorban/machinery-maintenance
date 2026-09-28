<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Finance\Models\PaymentType;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A commitment to buy from one supplier (Procurement spec §7).
 *
 * Phase 1 is the local flow: draft, approve, receive against it, close. An
 * approved order is never edited in place — a revision snapshots what stood
 * before, so the supplier's original terms stay readable.
 */
class PurchaseOrder extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const PURCHASE_TYPES = ['LOCAL', 'IMPORT'];

    public const STATUSES = [
        'DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED',
        'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED', 'CANCELLED',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['PENDING_APPROVAL', 'APPROVED', 'CANCELLED'],
        'PENDING_APPROVAL' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['PENDING_APPROVAL', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED', 'CANCELLED'],
        'REJECTED' => ['DRAFT'],
        'PARTIALLY_RECEIVED' => ['RECEIVED', 'CLOSED'],
        'RECEIVED' => ['CLOSED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    /** Statuses a goods receipt may still be raised against. */
    public const RECEIVABLE = ['APPROVED', 'PARTIALLY_RECEIVED'];

    protected $table = 'purchase_orders';

    protected $fillable = [
        'company_id', 'factory_id', 'vendor_id', 'po_no', 'po_date', 'purchase_type',
        // Import against a customs bond (Shared Systems §3.3).
        'bond_applicable', 'bond_licence_id', 'duty_exemption_reference', 'currency',
        'payment_type_id', 'payment_terms', 'delivery_date', 'delivery_store_id', 'status',
        'approval_status', 'approval_request_id', 'revision_no', 'subtotal', 'approved_subtotal',
        'notes', 'created_by', 'submitted_at', 'approved_at', 'closed_at', 'cancelled_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'bond_applicable' => 'boolean',
            'po_date' => 'date',
            'delivery_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'revision_no' => 'integer',
            'subtotal' => MoneyCast::class,
            'approved_subtotal' => MoneyCast::class,
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function deliveryStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'delivery_store_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('created_at')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PurchaseOrderRevision::class)->orderByDesc('revision_no');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'po_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, self::RECEIVABLE, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalContext(): array
    {
        return [
            'cost' => number_format((float) $this->subtotal, 4, '.', ''),
            'currency' => $this->currency ?? 'BDT',
            'factory_id' => $this->factory_id,
            'vendor_id' => $this->vendor_id,
            'purchase_type' => $this->purchase_type,
        ];
    }

    /**
     * @return array{number: string, title: string, url: string, factory_id: ?string}
     */
    public function approvalSummary(): array
    {
        return [
            'number' => $this->po_no,
            'title' => __('procurement.po_approval_title', ['number' => $this->po_no]),
            'url' => config('tenancy.frontend_url').'/procurement/orders/'.$this->id,
            'factory_id' => $this->factory_id,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        $now = CarbonImmutable::now();

        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => in_array($this->status, ['DRAFT', 'PENDING_APPROVAL'], true)
                ? $this->forceFill([
                    'status' => 'APPROVED',
                    'approved_at' => $now,
                    'approved_subtotal' => $this->subtotal,
                ])->save()
                : null,
            'REJECTED' => $this->status === 'PENDING_APPROVAL'
                ? $this->forceFill(['status' => 'REJECTED', 'rejection_reason' => null])->save()
                : null,
            default => null,
        };
    }
}
