<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform-seeded master data plus a tenant's own additions (Seed Catalog 3, 4).
 *
 * Deliberately not tenant-scoped: platform rows carry a null company_id and must
 * stay visible to every company.
 */
class FailureCategory extends BaseModel
{
    protected $table = 'failure_categories';

    protected $fillable = ['company_id', 'name', 'name_bn', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    /** The Bengali name when the interface is in Bengali, the English one otherwise. */
    public function label(): string
    {
        return app()->getLocale() === 'bn' && filled($this->name_bn)
            ? $this->name_bn
            : $this->name;
    }
}
