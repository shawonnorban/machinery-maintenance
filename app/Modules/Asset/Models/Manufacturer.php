<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Manufacturer extends BaseModel
{
    protected $fillable = ['company_id', 'name', 'code', 'country', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
