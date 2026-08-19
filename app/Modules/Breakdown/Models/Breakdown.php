<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An unplanned stoppage (SRS 15, ERD Section 10).
 *
 * Seven timestamps, not two. Response time and repair time answer different
 * questions — one is about how fast maintenance reacts, the other about how
 * long the job takes — and a record that stores only "down at" and "up at"
 * cannot separate a slow team from a slow reporting culture.
 */
class Breakdown extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = [
        'REPORTED', 'ACKNOWLEDGED', 'ASSIGNED', 'IN_REPAIR', 'ON_HOLD',
        'REPAIRED', 'PRODUCTION_RESUMED', 'CLOSED', 'CANCELLED',
    ];

    /** Still costing production time. */
    public const OPEN_STATUSES = [
        'REPORTED', 'ACKNOWLEDGED', 'ASSIGNED', 'IN_REPAIR', 'ON_HOLD',
    ];

    public const TERMINAL_STATUSES = ['CLOSED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'REPORTED' => ['ACKNOWLEDGED', 'CANCELLED'],
        'ACKNOWLEDGED' => ['ASSIGNED', 'IN_REPAIR', 'CANCELLED'],
        'ASSIGNED' => ['IN_REPAIR', 'ACKNOWLEDGED', 'CANCELLED'],
        'IN_REPAIR' => ['ON_HOLD', 'REPAIRED', 'CANCELLED'],
        'ON_HOLD' => ['IN_REPAIR', 'CANCELLED'],
        // Repaired is not the end: the machine is fixed but the line may not be
        // running yet, and the gap between those two is real downtime.
        'REPAIRED' => ['PRODUCTION_RESUMED', 'IN_REPAIR', 'CLOSED'],
        'PRODUCTION_RESUMED' => ['CLOSED', 'IN_REPAIR'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    public const PRIORITIES = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

    public const SEVERITIES = ['CATASTROPHIC', 'MAJOR', 'MINOR', 'NEGLIGIBLE'];

    public const HOLD_REASONS = [
        'AWAITING_PARTS', 'AWAITING_VENDOR', 'AWAITING_APPROVAL',
        'PRODUCTION_RUNNING', 'SHIFT_END', 'OTHER',
    ];

    /**
     * The chain, in order. Every one of these must be non-decreasing
     * (ERD Section 10 rule 2).
     *
     * @var list<string>
     */
    public const TIMESTAMP_CHAIN = [
        'failure_at', 'reported_at', 'acknowledged_at', 'technician_arrival_at',
        'repair_started_at', 'repair_completed_at', 'production_resumed_at',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'factory_id', 'asset_id', 'asset_location_id', 'production_line_id',
        'breakdown_number', 'reported_by', 'failure_at', 'reported_at',
        'status', 'priority', 'severity', 'problem_description',
        'failure_category_id', 'failure_code_id', 'root_cause_id',
        'corrective_action', 'preventive_action', 'production_order_reference',
        'assigned_technician_id', 'assigned_team_id',
        'downtime_class', 'downtime_reason_code_id', 'is_recurrence_of_breakdown_id',
    ];

    protected function casts(): array
    {
        return [
            'failure_at' => 'datetime',
            'reported_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'technician_arrival_at' => 'datetime',
            'repair_started_at' => 'datetime',
            'repair_completed_at' => 'datetime',
            'production_resumed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'on_hold_since' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'hold_minutes' => 'integer',
            'version' => 'integer',
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

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function failureCategory(): BelongsTo
    {
        return $this->belongsTo(FailureCategory::class);
    }

    public function failureCode(): BelongsTo
    {
        return $this->belongsTo(FailureCode::class);
    }

    public function rootCause(): BelongsTo
    {
        return $this->belongsTo(RootCause::class);
    }

    public function downtimeReasonCode(): BelongsTo
    {
        return $this->belongsTo(DowntimeReasonCode::class, 'downtime_reason_code_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'assigned_technician_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BreakdownStatusHistory::class)
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }

    public function productionImpacts(): HasMany
    {
        return $this->hasMany(ProductionImpact::class);
    }

    public function downtimeRecords(): HasMany
    {
        return $this->hasMany(DowntimeRecord::class)->orderByDesc('calculation_version');
    }

    /** The current downtime figures: the latest calculation version. */
    public function currentDowntime(): ?DowntimeRecord
    {
        return DowntimeRecord::where('breakdown_id', $this->id)
            ->orderByDesc('calculation_version')
            ->first();
    }

    public function recurrences(): HasMany
    {
        return $this->hasMany(self::class, 'is_recurrence_of_breakdown_id');
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
     * Closing needs a cause, not just a repair (ERD Section 10 rule 3).
     *
     * A breakdown closed without a failure code and a root cause is a machine
     * that broke for no recorded reason, which makes the whole failure-analysis
     * half of the product produce nothing.
     */
    public function canBeClosed(): bool
    {
        return $this->repair_completed_at !== null
            && $this->failure_code_id !== null
            && $this->root_cause_id !== null;
    }

    /**
     * @return list<string>
     */
    public function missingForClosure(): array
    {
        $missing = [];

        if ($this->repair_completed_at === null) {
            $missing[] = 'repair_completed_at';
        }

        if ($this->failure_code_id === null) {
            $missing[] = 'failure_code_id';
        }

        if ($this->root_cause_id === null) {
            $missing[] = 'root_cause_id';
        }

        return $missing;
    }
}
