<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Finance\Models\PaymentType;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money paid to a supplier before the goods arrive (Procurement spec §13).
 *
 * A mill will not start weaving on a promise, so a share of the order is
 * paid up front. Until the bills come in that money is an asset the factory
 * is owed in goods, and the number everybody actually wants is the one this
 * table exists to answer: **how much of what we have paid out is still
 * unaccounted for**.
 *
 * Adjustments are kept as their own rows rather than by decrementing a
 * balance. A balance that only goes down cannot say which bill consumed it,
 * and an advance adjusted against the wrong receipt is a mistake somebody
 * has to be able to find and reverse.
 */
class SupplierAdvance extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'PARTIALLY_ADJUSTED', 'ADJUSTED', 'REFUNDED', 'CANCELLED'];

    protected $table = 'supplier_advances';

    protected $fillable = [
        'company_id', 'factory_id', 'advance_no', 'advance_date', 'vendor_id',
        'purchase_order_id', 'payment_type_id', 'reference_no', 'amount',
        'adjusted_amount', 'refunded_amount', 'currency', 'status', 'notes',
        'created_by', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'advance_date' => 'date',
            'amount' => MoneyCast::class,
            'adjusted_amount' => MoneyCast::class,
            'refunded_amount' => MoneyCast::class,
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SupplierAdvanceAdjustment::class)->orderBy('created_at')->orderBy('id');
    }

    /** What is still sitting with the supplier, unaccounted for. */
    public function outstanding(): string
    {
        $used = bcadd((string) $this->adjusted_amount, (string) $this->refunded_amount, 4);
        $outstanding = bcsub((string) $this->amount, $used, 4);

        return bccomp($outstanding, '0', 4) === 1 ? $outstanding : '0.0000';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['OPEN', 'PARTIALLY_ADJUSTED'], true);
    }
}
