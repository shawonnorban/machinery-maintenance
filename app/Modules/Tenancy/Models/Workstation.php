<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class Workstation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'workstations';

    protected $fillable = ['company_id', 'production_line_id', 'name', 'code'];
}
