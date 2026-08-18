<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class BusinessUnit extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'business_units';

    protected $fillable = ['company_id', 'name', 'code', 'status'];
}
