<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The unit of maintenance work (SRS 13, Data Dictionary 3.1).
 */
class WorkOrder extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = [
        'DRAFT', 'PENDING_APPROVAL', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS',
        'ON_HOLD', 'COMPLETED', 'VERIFIED', 'CLOSED', 'CANCELLED', 'REJECTED',
    ];

    /** Statuses where work is still expected to happen. */
    public const OPEN_STATUSES = [
        'DRAFT', 'PENDING_APPROVAL', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS', 'ON_HOLD',
    ];

    public const TERMINAL_STATUSES = ['CLOSED', 'CANCELLED'];

    /**
     * The transition table from SRS 13.1. Anything not listed returns
     * 409 INVALID_STATUS_TRANSITION.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['PENDING_APPROVAL', 'SCHEDULED', 'CANCELLED'],
        'PENDING_APPROVAL' => ['SCHEDULED', 'REJECTED', 'CANCELLED'],
        'REJECTED' => ['DRAFT', 'CANCELLED'],
        'SCHEDULED' => ['ASSIGNED', 'CANCELLED'],
        'ASSIGNED' => ['IN_PROGRESS', 'SCHEDULED', 'CANCELLED'],
        'IN_PROGRESS' => ['ON_HOLD', 'COMPLETED', 'CANCELLED'],
        'ON_HOLD' => ['IN_PROGRESS', 'CANCELLED'],
        'COMPLETED' => ['VERIFIED', 'CLOSED', 'IN_PROGRESS'],
        'VERIFIED' => ['CLOSED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    public const HOLD_REASONS = [
        'AWAITING_PARTS', 'AWAITING_APPROVAL', 'AWAITING_VENDOR',
        'PRODUCTION_RUNNING', 'SHIFT_END', 'OTHER',
    ];

    protected $fillable = [
        'company_id', 'factory_id', 'asset_id', 'asset_location_id',
        'maintenance_schedule_id', 'breakdown_id', 'maintenance_type_id',
        'template_version_id', 'work_order_number', 'title', 'description',
        'status', 'priority', 'source', 'approval_status', 'approval_request_id',
        'requires_verification', 'requires_shutdown', 'downtime_class',
        'assigned_team_id', 'scheduled_start', 'scheduled_end',
        'estimated_parts_cost', 'currency',
        'created_by', 'is_imported',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
            'on_hold_since' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'requires_verification' => 'boolean',
            'requires_shutdown' => 'boolean',
            'is_imported' => 'boolean',
            'hold_minutes' => 'integer',
            'reopened_count' => 'integer',
            'version' => 'integer',
            'estimated_parts_cost' => MoneyCast::class,
            'actual_parts_cost' => MoneyCast::class,
            'actual_other_cost' => MoneyCast::class,
            'actual_cost' => MoneyCast::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTemplateVersion::class, 'template_version_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('unassigned_at');
    }

    public function laborEntries(): HasMany
    {
        return $this->hasMany(WorkOrderLaborEntry::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(WorkOrderHold::class);
    }

    public function statusHistories(): HasMany
    {
        // The id is a tiebreak, not decoration: ULIDs sort by creation time, so
        // the ordering stays deterministic even if two rows share a millisecond.
        return $this->hasMany(WorkOrderStatusHistory::class)
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }

    public function checklistResults(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistResult::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Repair time excludes hold time: waiting for a part is a supply problem,
     * not slow repair work, and folding it into MTTR hides the real
     * constraint (ADR-051).
     */
    public function repairMinutes(): ?int
    {
        if ($this->actual_start === null || $this->actual_end === null) {
            return null;
        }

        $elapsed = (int) round($this->actual_start->diffInMinutes($this->actual_end, absolute: true));

        return max($elapsed - $this->hold_minutes, 0);
    }

    /**
     * @return array{total: int, answered: int, required_remaining: int, failed: int}
     */
    public function checklistProgress(): array
    {
        $version = $this->template_version_id !== null
            ? MaintenanceTemplateVersion::find($this->template_version_id)
            : null;

        if ($version === null) {
            return ['total' => 0, 'answered' => 0, 'required_remaining' => 0, 'failed' => 0];
        }

        $items = $version->items()->get();
        $results = $this->checklistResults()->get()->keyBy('checklist_item_id');

        $requiredRemaining = $items
            ->filter(fn ($item) => $item->required && ! $results->has($item->id))
            ->count();

        return [
            'total' => $items->count(),
            'answered' => $results->count(),
            'required_remaining' => $requiredRemaining,
            'failed' => $results->where('result', 'FAIL')->count(),
        ];
    }
}
