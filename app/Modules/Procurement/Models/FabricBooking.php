<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fabric booked with a supplier before a purchase order exists
 * (Procurement spec §3).
 *
 * The step between "the buyer confirmed the order" and "we have committed
 * to buy": the mill is told to hold the yardage while price and terms are
 * still being settled. It is not a purchase — nothing is owed and nothing
 * will arrive on its own — but it *is* a commitment the factory plans
 * against, which is why confirming one stakes a claim on the incoming
 * stock (Inventory spec §7.1) and why the material requirement subtracts
 * it from what still needs buying (§4).
 */
class FabricBooking extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'CONFIRMED', 'CONVERTED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['CONVERTED', 'CANCELLED'],
        // Converted is the end of the booking's own life: the purchase
        // requisition it produced carries on from there.
        'CONVERTED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'fabric_bookings';

    protected $fillable = [
        'company_id', 'factory_id', 'booking_no', 'booking_date', 'vendor_id',
        'production_order_id', 'style_id', 'required_date', 'status', 'currency',
        'notes', 'created_by', 'confirmed_at', 'cancelled_at', 'converted_at',
        'converted_pr_id',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'required_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'converted_at' => 'datetime',
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

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'production_order_id');
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'converted_pr_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FabricBookingLine::class)->orderBy('created_at')->orderBy('id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** Holding stock away from the requirement calculation. */
    public function isHolding(): bool
    {
        return $this->status === 'CONFIRMED';
    }
}
