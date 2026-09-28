<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class Department extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'departments';

    protected $fillable = ['company_id', 'factory_id', 'name', 'code'];
}
