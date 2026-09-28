<?php

declare(strict_types=1);

namespace App\Modules\Asset\Providers;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Policies\AssetPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AssetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);
    }
}
