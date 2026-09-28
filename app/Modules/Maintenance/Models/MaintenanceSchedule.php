<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Asset\Models\Asset;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One concrete occurrence of a plan (Data Dictionary 2.4).
 */
class MaintenanceSchedule extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = [
        'PLANNED', 'DUE', 'OVERDUE', 'IN_PROGRESS', 'COMPLETED', 'SKIPPED', 'CANCELLED',
    ];

    /** Statuses that still expect work to happen. */
    public const OPEN_STATUSES = ['PLANNED', 'DUE', 'OVERDUE', 'IN_PROGRESS'];

    protected $fillable = [
        'company_id', 'maintenance_plan_id', 'asset_id', 'due_at',
        'due_meter', 'due_meter_type_id', 'status', 'grace_until',
        'completed_at', 'work_order_id', 'generated_from_schedule_id',
        'generated_at', 'rescheduled_from_due_at', 'rescheduled_reason',
        'skipped_reason', 'skipped_by', 'triggered_by', 'timezone',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'grace_until' => 'datetime',
            'completed_at' => 'datetime',
            'generated_at' => 'datetime',
            'rescheduled_from_due_at' => 'datetime',
            'due_meter' => MoneyCast::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Overdue means past the grace period, not merely past the due date.
     * A plan with a two-day grace is not late on day one, and reporting it as
     * late would make compliance figures meaningless (SRS 31.1).
     */
    public function isOverdue(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return ($this->grace_until ?? $this->due_at)->isPast();
    }

    public function isDue(): bool
    {
        return $this->isOpen() && $this->due_at->isPast();
    }
}
