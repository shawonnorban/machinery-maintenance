<?php

declare(strict_types=1);

namespace App\Modules\Metering\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class MeterType extends BaseModel
{
    protected $fillable = ['company_id', 'name', 'code', 'unit', 'is_cumulative', 'active'];

    protected function casts(): array
    {
        return ['is_cumulative' => 'boolean', 'active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
