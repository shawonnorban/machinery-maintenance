<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One version of an order's capacity plan (MP-2026110004 V1): drafted, confirmed and locked, Revised once a later version is locked. Capacity Planning → Master Plan (AnnotechERP 12/02).
 */
class PlanningMasterPlan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'LOCKED', 'REVISED'];

    protected $table = 'planning_master_plans';

    protected $fillable = [
        'company_id', 'plan_no', 'version', 'order_id', 'status', 'smv', 'smv_source', 'sam', 'sam_source', 'direction', 'forward_from', 'scenario',
        'order_qty', 'allocated_qty', 'daily_capacity', 'required_days', 'sewing_start', 'sewing_end', 'ship_date', 'remarks', 'created_by',
        'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'smv' => 'float', 'sam' => 'float', 'forward_from' => 'date', 'order_qty' => 'integer', 'allocated_qty' => 'integer', 'daily_capacity' => 'integer', 'required_days' => 'float', 'sewing_start' => 'date', 'sewing_end' => 'date', 'ship_date' => 'date', 'confirmed_at' => 'datetime'];
    }
}
