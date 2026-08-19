<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A claim made against a warranty (SRS 26).
 *
 * Linked to the breakdown or work order it came from, because the first
 * question a vendor asks is what happened and when, and the second is what it
 * cost. Both are already recorded; a claim that repeats them by hand is a claim
 * that disagrees with the maintenance record it came from.
 */
class WarrantyClaim extends BaseModel
{
    /**
     * Submitted, then the vendor's answer, then money.
     *
     * SETTLED is separate from APPROVED on purpose: a vendor agreeing to a
     * claim and a vendor paying it are months apart, and a factory chasing the
     * difference needs to see which claims are stuck in between.
     */
    public const STATUSES = ['SUBMITTED', 'ACKNOWLEDGED', 'APPROVED', 'REJECTED', 'SETTLED'];

    public const OPEN_STATUSES = ['SUBMITTED', 'ACKNOWLEDGED', 'APPROVED'];

    use BelongsToTenant;

    protected $table = 'warranty_claims';

    protected $fillable = [
        'company_id', 'warranty_id', 'asset_id', 'breakdown_id', 'work_order_id',
        'claim_number', 'claim_date', 'description', 'status', 'claimed_amount',
        'settled_amount', 'currency', 'resolution', 'resolved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'immutable_date',
            'resolved_at' => 'immutable_date',
        ];
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(Breakdown::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
