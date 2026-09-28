<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * How money changes hands — cash, bank transfer, cheque, LC (Master Data
 * spec §11). Distinct from payment terms, which say when.
 *
 * Not tenant-scoped: platform rows carry a null company_id and stay visible to
 * every company.
 */
class PaymentType extends BaseModel
{
    protected $table = 'payment_types';

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
