<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * A bank a supplier or the company holds an account with (Master Data spec §10).
 *
 * Not tenant-scoped: platform rows carry a null company_id and stay visible to
 * every company.
 */
class Bank extends BaseModel
{
    protected $table = 'banks';

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
