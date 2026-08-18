<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class ProductionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'production_lines';

    protected $fillable = ['company_id', 'department_id', 'section_id', 'name', 'code'];
}
