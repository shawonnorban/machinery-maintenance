<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Database\Seeders\PermissionSeeder;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped cache of resolved permissions, so a page rendering
        // fifty @can directives issues one query rather than fifty.
        $this->app->singleton(PermissionResolver::class);
    }

    public function boot(): void
    {
        $this->defineGates();
    }

    /**
     * Registers every catalog permission as a Gate ability, so `@can` in Blade
     * and `authorize()` in controllers use the same source of truth.
     *
     * Abilities are defined from the static catalog rather than from the
     * database: a typo'd permission string must fail closed, and a Gate that
     * is not defined denies by default.
     */
    private function defineGates(): void
    {
        foreach (PermissionSeeder::allCodes() as $code) {
            Gate::define($code, function (User $user, ?string $factoryId = null) use ($code): bool {
                $context = app(TenantContext::class);

                if (! $context->hasContext()) {
                    return false;
                }

                if (! $user->isActive()) {
                    return false;
                }

                return app(PermissionResolver::class)->has(
                    $user,
                    $context->companyId(),
                    $code,
                    $factoryId,
                );
            });
        }
    }
}
