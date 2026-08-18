<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master data shared between the platform seed and a tenant's own additions.
 *
 * Deliberately NOT using BelongsToTenant: platform rows carry a null
 * company_id and must stay visible to every tenant (Seed Catalog 1).
 */
class AssetType extends BaseModel
{
    protected $fillable = ['company_id', 'name', 'code', 'default_criticality', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    /** Rows a company may use: the platform seed plus its own. */
    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
