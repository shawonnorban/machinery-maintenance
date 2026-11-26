<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One step on a master plan's audit trail. Only ever added. Capacity Planning → Master Plan (AnnotechERP 12/02).
 */
class PlanningPlanEvent extends BaseModel
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'planning_plan_events';

    protected $fillable = [
        'company_id', 'plan_no', 'version', 'action', 'summary', 'user_id',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
