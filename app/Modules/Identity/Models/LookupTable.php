<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named list of dropdown values (Access Control → Lookup Table Master,
 * AnnotechERP 02/01): Shift, Payment Term, Shipment Mode … A system table
 * (company_id null) is shared and read-only; a company adds its own tables,
 * and its own values to any table it can see.
 *
 * Not BelongsToTenant: the shared rows have no company, so visibility is the
 * explicit `visible()` scope rather than the tenant global scope.
 */
class LookupTable extends BaseModel
{
    protected $table = 'lookup_tables';

    protected $fillable = ['company_id', 'code', 'name', 'description', 'is_system', 'active'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'active' => 'boolean'];
    }

    /** The shared tables plus this company's own. */
    public function scopeVisible(Builder $query): Builder
    {
        $companyId = app(TenantContext::class)->companyId();

        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function values(): HasMany
    {
        return $this->hasMany(LookupValue::class);
    }

    public function isOwnedBy(string $companyId): bool
    {
        return $this->company_id === $companyId;
    }
}
