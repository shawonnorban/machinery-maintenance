<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A derived, versioned downtime figure (SRS 17, ERD Section 12).
 *
 * Never edited in place. Changing a downtime rule must not silently rewrite the
 * KPIs of a closed period — a factory manager who reported 94% availability
 * last quarter should still be able to reproduce that number next year, even if
 * the rules changed since. A recalculation writes a new version alongside the
 * old one (SRS 17.3).
 */
class DowntimeRecord extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'downtime_records';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'factory_id', 'asset_id', 'breakdown_id', 'work_order_id',
        'production_line_id', 'failure_at', 'reported_at', 'acknowledged_at',
        'technician_arrival_at', 'repair_started_at', 'repair_completed_at',
        'production_resumed_at', 'response_minutes', 'repair_minutes',
        'total_downtime_minutes', 'hold_minutes', 'downtime_class',
        'downtime_reason_code_id', 'counts_against_availability', 'needs_review',
        'calendar_aware', 'calculation_basis', 'scheduled_operating_minutes_in_window',
        'calculation_version', 'calculated_at',
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
            'calculated_at' => 'datetime',
            'response_minutes' => 'integer',
            'repair_minutes' => 'integer',
            'total_downtime_minutes' => 'integer',
            'hold_minutes' => 'integer',
            'scheduled_operating_minutes_in_window' => 'integer',
            'calculation_version' => 'integer',
            'counts_against_availability' => 'boolean',
            'needs_review' => 'boolean',
            'calendar_aware' => 'boolean',
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

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(Breakdown::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(DowntimeReasonCode::class, 'downtime_reason_code_id');
    }
}
