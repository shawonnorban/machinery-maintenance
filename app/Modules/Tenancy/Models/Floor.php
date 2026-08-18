<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class Floor extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'floors';

    protected $fillable = ['company_id', 'building_id', 'name', 'code'];
}
