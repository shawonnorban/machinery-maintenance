<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Machines of one type installed on a planning line (Machine Master). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningLineMachine extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['SEWING', 'SPECIAL', 'FINISHING', 'OTHER'];

    protected $table = 'planning_line_machines';

    protected $fillable = ['company_id', 'planning_line_id', 'machine_type', 'name_code', 'category', 'installed', 'working', 'standby', 'capacity_per_hour', 'active'];

    protected function casts(): array
    {
        return ['installed' => 'integer', 'working' => 'integer', 'standby' => 'integer', 'capacity_per_hour' => 'decimal:2', 'active' => 'boolean'];
    }
}
