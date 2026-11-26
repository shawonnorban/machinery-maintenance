<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sewing line as planning sees it: standard manpower, machines, hours and efficiency, and the floor line its output is read from (Production Line). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'planning_lines';

    protected $fillable = ['company_id', 'floor_id', 'floor_line_id', 'code', 'name', 'std_manpower', 'std_machines', 'std_hours', 'std_efficiency', 'best_fit', 'display_order', 'under_maintenance', 'active'];

    protected function casts(): array
    {
        return ['std_manpower' => 'integer', 'std_machines' => 'integer', 'std_hours' => 'decimal:2', 'std_efficiency' => 'decimal:2', 'display_order' => 'integer', 'under_maintenance' => 'boolean', 'active' => 'boolean'];
    }
}
