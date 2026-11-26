<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line's target for a day, generated from its capacity, submitted and approved; a revision is a new row of the same plan number (Daily Production Plan, 30/06). Production Tracking (AnnotechERP 30).
 */
class PtDailyPlan extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pt_daily_plans';

    protected $fillable = ['company_id', 'plan_no', 'revision', 'plan_date', 'section', 'line_id', 'order_id', 'po_no', 'buyer_name', 'style', 'method', 'planned_hours', 'capacity', 'calculated_target', 'proposed_target', 'approved_target', 'override_reason', 'remarks', 'status', 'current', 'created_by', 'approved_by', 'approved_at', 'decision_note'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'revision' => 'integer', 'planned_hours' => 'decimal:2', 'capacity' => 'array', 'calculated_target' => 'integer', 'proposed_target' => 'integer', 'approved_target' => 'integer', 'current' => 'boolean', 'approved_at' => 'datetime'];
    }
}
