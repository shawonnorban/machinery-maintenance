<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * `is_platform_admin` (see App\Modules\Platform\Http\Middleware\
     * EnsurePlatformAdmin) is the same boundary the rest of the app already
     * uses for "not scoped to any tenant" staff — the queue is shared
     * infrastructure across every company, so it belongs behind that same
     * gate rather than a hand-maintained email list.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) optional($user)->is_platform_admin;
        });
    }
}
