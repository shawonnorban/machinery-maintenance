<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The people of one role a planning line needs (Manpower Master). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningLineManpower extends BaseModel
{
    use BelongsToTenant;

    public const ROLES = ['OPERATOR', 'HELPER', 'IRONMAN', 'QC', 'SUPERVISOR', 'OTHER'];

    protected $table = 'planning_line_manpower';

    protected $fillable = ['company_id', 'planning_line_id', 'role', 'required', 'remarks'];

    protected function casts(): array
    {
        return ['required' => 'integer'];
    }
}
