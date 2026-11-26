<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a lookup table (AnnotechERP 02/01). A shared value
 * (company_id null) is read-only; a company's own values sit beside it.
 */
class LookupValue extends BaseModel
{
    protected $table = 'lookup_values';

    protected $fillable = ['company_id', 'lookup_table_id', 'code', 'name', 'name_bn', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }

    /** The shared values plus this company's own. */
    public function scopeVisible(Builder $query): Builder
    {
        $companyId = app(TenantContext::class)->companyId();

        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(LookupTable::class, 'lookup_table_id');
    }
}
