<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One activity of a master plan — booking, in-house, cutting, sewing, finishing, packing, shipment — with its dates. Capacity Planning → Master Plan (AnnotechERP 12/02).
 */
class PlanningMasterPlanActivity extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'planning_master_plan_activities';

    protected $fillable = [
        'company_id', 'master_plan_id', 'activity', 'duration', 'start_date', 'end_date', 'based_on', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['duration' => 'integer', 'start_date' => 'date', 'end_date' => 'date'];
    }
}
