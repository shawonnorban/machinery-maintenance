<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Must be a singleton: middleware resolves the tenant once per request
        // and every model, policy, and service reads that same instance. A new
        // instance per resolution would silently lose the context.
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Fail loudly on a missing relationship rather than issuing an extra
        // query per row. N+1 in a 20,000-asset list is a production outage.
        Model::preventLazyLoading(! app()->isProduction());

        // Reject writes to attributes that are not fillable, instead of
        // discarding them silently (API Schemas 2.3 rule 3).
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Model::unguard(false);
    }
}
