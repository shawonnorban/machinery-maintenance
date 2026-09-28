<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends BaseModel
{
    protected $fillable = ['name', 'code', 'status'];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
