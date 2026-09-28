<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fabric sent out for dyeing, printing or embroidery (Inventory spec §8).
 *
 * The cloth leaves the building and stays the factory's. That is the whole
 * difficulty: it is not an issue (nothing was consumed), not a sale
 * (nothing was sold), and not a transfer (no other store of ours has it) —
 * so it needs its own document and its own roll status,
 * `SENT_FOR_SUBCONTRACT`, which keeps it off the available shelf while
 * still counting it as ours.
 *
 * Returns are partial by nature: a dyer sends back three rolls this week
 * and two next, and some of the length comes back as shrinkage rather than
 * cloth. The challan therefore closes only when every roll is accounted
 * for, one way or another.
 */
class SubcontractChallan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SENT', 'PARTIALLY_RETURNED', 'CLOSED', 'CANCELLED'];

    public const PURPOSES = ['DYEING', 'PRINTING', 'EMBROIDERY', 'WASHING', 'OTHER'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['SENT', 'CANCELLED'],
        'SENT' => ['PARTIALLY_RETURNED', 'CLOSED', 'CANCELLED'],
        // Still open: the rest of the rolls are at the subcontractor.
        'PARTIALLY_RETURNED' => ['PARTIALLY_RETURNED', 'CLOSED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'subcontract_challans';

    protected $fillable = [
        'company_id', 'factory_id', 'challan_no', 'challan_date', 'vendor_id',
        'purpose', 'expected_return_date', 'status', 'notes', 'created_by',
        'sent_at', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'challan_date' => 'date',
            'expected_return_date' => 'date',
            'sent_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubcontractChallanLine::class)->orderBy('created_at')->orderBy('id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** Cloth is still out with the subcontractor. */
    public function isOut(): bool
    {
        return in_array($this->status, ['SENT', 'PARTIALLY_RETURNED'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOut()
            && $this->expected_return_date !== null
            && $this->expected_return_date->isPast();
    }
}
