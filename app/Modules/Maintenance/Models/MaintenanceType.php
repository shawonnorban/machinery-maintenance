<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform-seeded master data plus a tenant's own additions (Seed Catalog 6).
 *
 * Deliberately not tenant-scoped: platform rows carry a null company_id and
 * must stay visible to every company.
 */
class MaintenanceType extends BaseModel
{
    protected $fillable = ['company_id', 'name', 'code', 'default_priority', 'is_planned', 'active'];

    protected function casts(): array
    {
        return ['is_planned' => 'boolean', 'active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
