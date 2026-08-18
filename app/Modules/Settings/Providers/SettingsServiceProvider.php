<?php

declare(strict_types=1);

namespace App\Modules\Settings\Providers;

use App\Modules\Settings\Services\SettingsResolver;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped: settings are read many times per request and the
        // catalog does not change mid-request.
        $this->app->singleton(SettingsResolver::class);
    }
}
