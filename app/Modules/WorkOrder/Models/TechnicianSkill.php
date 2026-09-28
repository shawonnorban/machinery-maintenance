<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class TechnicianSkill extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'technician_id', 'skill_name', 'proficiency'];
}
