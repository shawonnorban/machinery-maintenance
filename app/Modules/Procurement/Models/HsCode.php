<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * A customs tariff code with its duty and VAT rates (Master Data spec §8).
 *
 * Not tenant-scoped: platform rows carry a null company_id and stay visible to
 * every company.
 */
class HsCode extends BaseModel
{
    protected $table = 'hs_codes';

    protected $fillable = ['company_id', 'code', 'description', 'duty_rate', 'vat_rate', 'active'];

    protected function casts(): array
    {
        return [
            'duty_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
