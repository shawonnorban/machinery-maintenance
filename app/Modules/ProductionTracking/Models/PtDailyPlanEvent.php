<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One step in a daily plan's history: created, submitted, approved, rejected, revised or cancelled. Production Tracking (AnnotechERP 30).
 */
class PtDailyPlanEvent extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pt_daily_plan_events';

    public $timestamps = false;

    protected $fillable = ['company_id', 'plan_id', 'action', 'from_status', 'to_status', 'note', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
