<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Models;

use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A production floor asking a store for items (Floor/Store Requisition
 * spec §3).
 *
 * The full life: drafted and submitted by the floor, then reviewed and
 * issued by the store. A request the store cannot fill does not fail — it
 * is issued as far as the stock goes and the rest becomes a shortage, which
 * raises a purchase requisition and a claim on the goods that are coming
 * (§5.3). CLOSED is the end: nothing outstanding, nothing left to collect.
 */
class FloorRequisition extends BaseModel
{
    use BelongsToTenant;

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'PARTIALLY_FULFILLED',
        'FULLY_FULFILLED', 'REJECTED', 'CLOSED', 'CANCELLED',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['UNDER_REVIEW', 'CANCELLED'],
        'UNDER_REVIEW' => ['PARTIALLY_FULFILLED', 'FULLY_FULFILLED', 'REJECTED', 'CANCELLED'],
        // A part-filled request stays open: the shortage is issued later,
        // when the purchase it raised arrives.
        'PARTIALLY_FULFILLED' => ['PARTIALLY_FULFILLED', 'FULLY_FULFILLED', 'CLOSED'],
        'FULLY_FULFILLED' => ['CLOSED'],
        'REJECTED' => [],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    /** Past the floor's hands — the store owns it from here. */
    public const STORE_STATUSES = ['SUBMITTED', 'UNDER_REVIEW', 'PARTIALLY_FULFILLED', 'FULLY_FULFILLED'];

    protected $table = 'floor_requisitions';

    protected $fillable = [
        'company_id', 'factory_id', 'fr_no', 'fr_date', 'requested_by', 'reviewed_by',
        'requester_department_id', 'target_item_department_id', 'priority', 'status', 'purpose',
        'rejection_reason', 'shortage_pr_id', 'submitted_at', 'reviewed_at', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'fr_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requesterDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requester_department_id');
    }

    public function targetItemDepartment(): BelongsTo
    {
        return $this->belongsTo(ItemDepartment::class, 'target_item_department_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FloorRequisitionLine::class)->orderBy('created_at')->orderBy('id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** The store may still issue against it. */
    public function isIssuable(): bool
    {
        return in_array($this->status, ['SUBMITTED', 'UNDER_REVIEW', 'PARTIALLY_FULFILLED'], true);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function shortagePurchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'shortage_pr_id');
    }
}
