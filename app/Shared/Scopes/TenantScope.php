<?php

declare(strict_types=1);

namespace App\Shared\Scopes;

use App\Shared\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the resolved company.
 *
 * With no resolved context the scope throws rather than returning unscoped
 * rows. Failing loudly in a console command is recoverable; silently serving
 * another tenant's data is not (ADR-042 rule 1).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->hasContext()) {
            // Console and queue work resolves context explicitly, or opts out
            // through acrossAllTenants(). Tests always run scoped.
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            throw new TenantContextMissingException(
                sprintf('Query on %s ran without tenant context.', $model::class),
            );
        }

        $builder->where(
            $model->qualifyColumn('company_id'),
            $context->companyId(),
        );
    }
}
