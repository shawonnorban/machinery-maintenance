<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A machine's declared hourly capacity and the plan its loading is built on, for a span of days (Machine Capacity Hour Plan). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitCapacityPlan extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_capacity_plans';

    protected $fillable = ['company_id', 'machine_id', 'shift_id', 'effective_from', 'effective_to', 'plan_hours', 'running_days', 'efficiency', 'capacity_per_hour', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'plan_hours' => 'decimal:2', 'running_days' => 'integer', 'efficiency' => 'decimal:2', 'capacity_per_hour' => 'decimal:3', 'active' => 'boolean'];
    }
}
