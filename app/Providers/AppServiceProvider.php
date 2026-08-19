<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use App\Shared\View\Composers\AppShellComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Must be a singleton: middleware resolves the tenant once per request
        // and every model, policy, and service reads that same instance. A new
        // instance per resolution would silently lose the context.
        $this->app->singleton(TenantContext::class);

        // Also a singleton, and for the same reason: it caches the resolved
        // zone for the request, and a fresh instance per view would re-query
        // the company on every timestamp rendered in a list.
        $this->app->singleton(TenantTimezone::class);
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

        // Bootstrap 5 pagination markup, matching the compact CoreUI listing.
        Paginator::useBootstrapFive();

        View::composer(
            ['layouts.app', 'layouts.mobile', 'components.layout.*'],
            AppShellComposer::class,
        );

        /*
         * Timestamps are stored in UTC and read on the factory's clock.
         *
         * @dt($value) renders one; @dtinput($value) fills a datetime-local
         * field. Both go through TenantTimezone, so no view can accidentally
         * print a UTC instant as though it were local — which, in Dhaka, is a
         * six-hour lie that looks entirely plausible (SRS 47.2).
         */
        Blade::directive('dt', fn (string $expression) => sprintf(
            "<?php echo e(app(%s::class)->format(%s) ?? '—'); ?>",
            TenantTimezone::class,
            $expression,
        ));

        Blade::directive('dtinput', fn (string $expression) => sprintf(
            '<?php echo e(app(%s::class)->forInput(%s)); ?>',
            TenantTimezone::class,
            $expression,
        ));
    }
}
