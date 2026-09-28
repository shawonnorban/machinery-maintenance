<?php

declare(strict_types=1);

namespace App\Modules\Asset\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends BaseModel
{
    protected $table = 'asset_categories';

    protected $fillable = ['company_id', 'asset_type_id', 'name', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    /**
     * Deliberately without the tenant scope: the question this answers is
     * "is anybody, anywhere, filing machines under this category", which is
     * asked before a shared row is removed.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_category_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
