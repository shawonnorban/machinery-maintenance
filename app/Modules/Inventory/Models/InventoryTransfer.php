<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock moving between factories (SRS 21).
 *
 * Requested, approved, dispatched, received. Quantities move at dispatch and at
 * receipt, never at request or approval: a transfer that decrements stock when
 * somebody asks for it leaves the source factory short of parts it still
 * physically has on the shelf.
 */
class InventoryTransfer extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['REQUESTED', 'APPROVED', 'IN_TRANSIT', 'RECEIVED', 'REJECTED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'REQUESTED' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['IN_TRANSIT', 'CANCELLED'],
        'IN_TRANSIT' => ['RECEIVED'],
        'RECEIVED' => [],
        'REJECTED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'inventory_transfers';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'transfer_number', 'from_factory_id', 'to_factory_id',
        'in_transit_bin_id', 'status', 'requested_by', 'approved_by', 'approved_at',
        'dispatched_by', 'dispatched_at', 'received_by', 'received_at',
        'rejected_by', 'rejected_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function fromFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'from_factory_id');
    }

    public function toFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'to_factory_id');
    }

    public function inTransitBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'in_transit_bin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['RECEIVED', 'REJECTED', 'CANCELLED'], true);
    }
}
