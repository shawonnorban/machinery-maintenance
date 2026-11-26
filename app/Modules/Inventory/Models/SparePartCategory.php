<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform-seeded categories plus a tenant's own (Seed Catalog 7).
 *
 * Not tenant-scoped: platform rows carry a null company_id and stay visible to
 * every company.
 */
class SparePartCategory extends BaseModel
{
    /** The one category list: `item_categories`, kept on General Store → Categories (user, 2026-11-15). */
    protected $table = 'item_categories';

    protected $fillable = ['company_id', 'name', 'name_bn', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function label(): string
    {
        return app()->getLocale() === 'bn' && filled($this->name_bn) ? $this->name_bn : $this->name;
    }
}
