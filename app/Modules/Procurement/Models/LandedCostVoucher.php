<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a consignment really cost (Procurement spec §11).
 *
 * The purchase order's rate is only part of the price. Freight, duty, C&F,
 * insurance and bank charges arrive weeks later, on invoices of their own,
 * and until they are spread across the goods the stock is valued at a
 * number everybody knows is too low.
 *
 * One voucher covers **several goods receipts**, because one container's
 * freight bill does not respect the boundaries of the GRNs it was unloaded
 * into. The charges are distributed across every line of every receipt on
 * the voucher, by quantity or by value.
 *
 * Posting is one-way. A posted voucher has already moved the weighted
 * average and re-priced fabric rolls, some of which have since been cut,
 * merged or issued; unwinding that from a "cancel" button would be a
 * different and much larger feature than it looks. So a mistake is
 * corrected by a second voucher with the opposite charges, exactly as a
 * wrong ledger row is corrected by an opposing row rather than an eraser.
 */
class LandedCostVoucher extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'POSTED', 'CANCELLED'];

    /** How the charges are spread over the lines they apply to. */
    public const BASES = ['QTY', 'VALUE'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['POSTED', 'CANCELLED'],
        'POSTED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'landed_cost_vouchers';

    protected $fillable = [
        'company_id', 'factory_id', 'voucher_no', 'voucher_date', 'basis',
        'currency', 'status', 'notes', 'created_by', 'posted_by', 'posted_at',
        'cancelled_at', 'total_charges',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'total_charges' => MoneyCast::class,
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LandedCostCharge::class)->orderBy('created_at')->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(LandedCostReceipt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }
}
