<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class Section extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sections';

    protected $fillable = ['company_id', 'department_id', 'name', 'code'];
}
