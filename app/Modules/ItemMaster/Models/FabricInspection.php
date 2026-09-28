<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One four-point inspection pass over a delivered lot (Inventory spec §4.3).
 *
 * The lot is the unit of judgement, not the roll. A roll's percentage says
 * whether that roll is usable; whether the delivery is accepted is a
 * pooled figure over the yardage actually looked at, against a bar the
 * buyer sets.
 *
 * **Sampling escalates rather than repeating.** The first pass covers a
 * sample; if it fails, a wider one is opened against the same receipt and
 * points back at the pass that caused it. A failed pass is never edited
 * into a better one — that is the record of what was found, and the mill
 * is owed an honest account of why 100% of their delivery was opened.
 */
class FabricInspection extends BaseModel
{
    use BelongsToTenant;

    /**
     * How much of the lot a pass covers. The percentages themselves are
     * settings, because a buyer's AQL is a commercial term, not physics.
     */
    public const STAGES = ['FIRST', 'SECOND', 'FULL'];

    public const RESULTS = ['PASS', 'FAIL'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        // The sheet is filled roll by roll while it is DRAFT; submitting
        // is what freezes the arithmetic.
        'DRAFT' => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        // Terminal: the rolls have been graded off the back of this.
        'APPROVED' => [],
        'REJECTED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'fabric_inspections';

    protected $fillable = [
        'company_id', 'factory_id', 'goods_receipt_id', 'item_id', 'vendor_id',
        'inspection_no', 'buyer_reference', 'style_reference', 'order_reference',
        'colour', 'composition', 'lot_no',
        'stage', 'sampling_percent', 'rolls_in_lot', 'yards_in_lot',
        'rolls_inspected', 'yards_inspected', 'total_points', 'penalty_percent',
        'acceptance_threshold', 'result', 'status', 'supersedes_id',
        'inspected_by', 'inspected_at', 'verified_by', 'verified_at',
        'approved_by', 'approved_at', 'inspection_date', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'sampling_percent' => 'decimal:2',
            'rolls_in_lot' => 'integer',
            'yards_in_lot' => 'decimal:2',
            'rolls_inspected' => 'integer',
            'yards_inspected' => 'decimal:2',
            'total_points' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
            'acceptance_threshold' => 'decimal:2',
            'inspection_date' => 'immutable_date',
            'inspected_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function rolls(): HasMany
    {
        return $this->hasMany(FabricRoll::class, 'fabric_inspection_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isOpen(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function hasPassed(): bool
    {
        return $this->result === 'PASS';
    }

    /**
     * What a failure means next: a wider look, or the end of the road.
     *
     * The last stage has no wider sample left to take, so the decision
     * there is commercial — accept the lot with a concession or send it
     * back — and the system says so rather than inventing a fourth pass.
     */
    public function nextStage(): ?string
    {
        return match ($this->stage) {
            'FIRST' => 'SECOND',
            'SECOND' => 'FULL',
            default => null,
        };
    }
}
