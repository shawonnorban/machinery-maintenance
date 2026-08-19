<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scope's KPI components for one closed period (ERD Section 28, ADR-058).
 *
 * A snapshot stores counts, not conclusions. The percentages alongside them are
 * a convenience for reading a single row; anything spanning more than one row
 * re-derives from the components, because averaging thirty daily availability
 * percentages gives a shut day the same weight as a full production day.
 */
class KpiSnapshot extends BaseModel
{
    use BelongsToTenant;

    public const SCOPE_TYPES = ['COMPANY', 'FACTORY', 'LINE', 'ASSET'];

    public const PERIOD_TYPES = ['DAY', 'WEEK', 'MONTH'];

    protected $table = 'kpi_snapshots';

    protected $fillable = [
        'company_id', 'factory_id', 'asset_id', 'scope_type',
        'period_type', 'period_start', 'period_end',
        'scheduled_operating_minutes', 'downtime_minutes',
        'unplanned_downtime_minutes', 'counted_downtime_minutes',
        'failure_count', 'repair_count', 'repair_minutes_total',
        'response_count', 'response_minutes_total',
        'arrival_count', 'arrival_minutes_total',
        'pm_due_count', 'pm_on_time_count',
        'work_order_scheduled_count', 'work_order_closed_count',
        'availability_percent', 'mtbf_minutes', 'mttr_minutes',
        'pm_compliance_percent', 'calculation_version', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'computed_at' => 'immutable_datetime',
            'availability_percent' => 'float',
            'mtbf_minutes' => 'float',
            'mttr_minutes' => 'float',
            'pm_compliance_percent' => 'float',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
