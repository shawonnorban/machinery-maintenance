<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovers modules under app/Modules and wires up their migrations,
 * views, translations, and routes.
 *
 * A module is self-contained (Handbook 3). Adding one requires creating the
 * directory, not editing this provider.
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Module load order matters only for the foundation modules, which
     * everything else may depend on (Handbook 3.1 rule 4).
     */
    private const FOUNDATION = ['Tenancy', 'Identity', 'Settings', 'Calendar'];

    public function register(): void
    {
        foreach ($this->modules() as $module => $path) {
            $provider = "App\\Modules\\{$module}\\Providers\\{$module}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->modules() as $module => $path) {
            $this->loadModuleMigrations($path);
            $this->loadModuleViews($module, $path);
            $this->loadModuleTranslations($module, $path);
            $this->loadModuleRoutes($module, $path);
            $this->loadModuleCommands($module, $path);
        }
    }

    /**
     * @return array<string, string> module name => absolute path
     */
    private function modules(): array
    {
        $base = app_path('Modules');

        if (! is_dir($base)) {
            return [];
        }

        $found = [];

        foreach ((array) glob($base.'/*', GLOB_ONLYDIR) as $dir) {
            $found[basename($dir)] = $dir;
        }

        // Foundation modules first, remainder alphabetically.
        $ordered = [];

        foreach (self::FOUNDATION as $name) {
            if (isset($found[$name])) {
                $ordered[$name] = $found[$name];
                unset($found[$name]);
            }
        }

        ksort($found);

        return $ordered + $found;
    }

    /**
     * Console commands live in the module that owns them. Laravel only
     * auto-discovers app/Console/Commands, so without this a module command
     * exists but cannot be run, and its scheduled task silently never fires.
     */
    private function loadModuleCommands(string $module, string $path): void
    {
        $dir = $path.'/Console';

        if (! is_dir($dir)) {
            return;
        }

        $commands = [];

        foreach ((array) glob($dir.'/*.php') as $file) {
            $class = "App\\Modules\\{$module}\\Console\\".basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, Command::class)) {
                $commands[] = $class;
            }
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }

    private function loadModuleMigrations(string $path): void
    {
        $migrations = $path.'/Database/Migrations';

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    private function loadModuleViews(string $module, string $path): void
    {
        $views = $path.'/Resources/views';

        if (is_dir($views)) {
            $this->loadViewsFrom($views, strtolower($module));
        }
    }

    private function loadModuleTranslations(string $module, string $path): void
    {
        $lang = $path.'/Resources/lang';

        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, strtolower($module));
        }
    }

    /**
     * Web routes are prefixed /app and use the session stack.
     * API routes are prefixed /api/v1 and use tokens.
     * Both hit controllers that delegate to the same Actions (ADR-066).
     */
    private function loadModuleRoutes(string $module, string $path): void
    {
        $web = $path.'/Routes/web.php';

        if (is_file($web)) {
            Route::middleware('web')
                ->prefix('app')
                ->name('app.')
                ->group($web);
        }

        $api = $path.'/Routes/api.php';

        if (is_file($api)) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group($api);
        }
    }
}
