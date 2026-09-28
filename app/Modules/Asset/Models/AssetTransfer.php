<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transfer history is immutable once received. A correction is a new row with
 * reverses_transfer_id set, never an edit (ERD Section 4).
 */
class AssetTransfer extends BaseModel
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'asset_transfer_history';

    /** Data Dictionary 3.4 pattern, applied to asset movement. */
    public const STATUSES = [
        'REQUESTED', 'APPROVED', 'REJECTED', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED', 'REVERSED',
    ];

    protected $fillable = [
        'company_id', 'asset_id', 'transfer_number',
        'from_factory_id', 'from_location_id', 'to_factory_id', 'to_location_id',
        'status', 'reason', 'notes',
        'requested_by', 'requested_at', 'approved_by', 'approved_at',
        'received_by', 'received_at', 'rejected_by', 'rejected_at',
        'rejection_reason', 'transfer_at', 'reverses_transfer_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'rejected_at' => 'datetime',
            'transfer_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'from_factory_id');
    }

    public function toFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'to_factory_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'to_location_id');
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, ['RECEIVED', 'REVERSED'], true);
    }
}
