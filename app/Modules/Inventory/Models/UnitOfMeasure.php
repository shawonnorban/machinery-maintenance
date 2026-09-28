<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * A unit stock is counted or bought in (Master Data spec §7).
 *
 * Not tenant-scoped: platform rows carry a null company_id and stay visible to
 * every company, the same as spare part categories.
 */
class UnitOfMeasure extends BaseModel
{
    protected $table = 'units_of_measure';

    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
