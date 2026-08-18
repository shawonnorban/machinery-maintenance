<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies tenant isolation to every query on a tenant-owned model.
 *
 * SRS 4 and ADR-005: a user must never reach another company's data through
 * direct ids, filters, exports, reports, WebSockets, or APIs. This trait makes
 * that true by default, rather than by remembering to add a where clause.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // company_id comes from resolved context, never from a request payload.
        static::creating(function (Model $model): void {
            if ($model->getAttribute('company_id') !== null) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->hasContext()) {
                $model->setAttribute('company_id', $context->companyId());
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Escape hatch for platform-level and scheduled work that legitimately
     * spans tenants, such as the subscription lifecycle job.
     *
     * Every call site must be able to justify itself. This is not a
     * convenience for "the query is not returning anything".
     */
    public static function acrossAllTenants(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
