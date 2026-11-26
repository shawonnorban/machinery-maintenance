<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The default lead time of an activity a new master plan starts from (Other Setup). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningLeadTime extends BaseModel
{
    use BelongsToTenant;

    public const COUNTED_FROM = ['BEFORE_CUTTING', 'BEFORE_FIRST_SEWING', 'AFTER_LAST_SEWING'];

    protected $table = 'planning_lead_times';

    protected $fillable = ['company_id', 'activity', 'lead_days', 'counted_from', 'remarks', 'sort_order', 'changed'];

    protected function casts(): array
    {
        return ['lead_days' => 'integer', 'sort_order' => 'integer', 'changed' => 'boolean'];
    }
}
