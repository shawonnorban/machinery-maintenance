<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

    /**
     * Web *screens* fully replaced by the Next.js app (Phase D/F, docs/12-
     * Stack-Migration-Implementation-Plan.md). Left in place rather than
     * deleted — the whole point of gating this in one array instead of
     * removing files is that undoing it is a one-line revert, not a code
     * recovery, if a gap turns up later.
     *
     * Deliberately NOT here:
     * - Platform: its admin screens (`/platform/*`) are registered directly
     *   in the root `routes/web.php`, untouched by this list either way —
     *   but Platform also has its own `Routes/web.php` (the tenant-facing
     *   "my support tickets" screens), which this list doesn't touch out
     *   of caution even though Next.js's own `/support/tickets` already
     *   covers it — a smaller, separate call to make later.
     * - Tenancy: its actual screens (dashboard, factory admin) are gone,
     *   but that was done by editing `Tenancy/Routes/web.php` directly
     *   rather than by listing it here, because two of its routes have no
     *   screen of their own (`/locale`, `/factory-scope`) and the
     *   still-Blade Platform admin layout's language switcher posts to one
     *   of them directly — skipping this whole module here would have
     *   taken that down for no reason.
     * - Identity: same reasoning, same treatment — `/switch-company` has
     *   no screen of its own, and `Tenancy/Resources/views/{suspended,
     *   closed}.blade.php` (rendered by `ResolveTenantContext` middleware
     *   on every web request, independent of which module's own routes
     *   are registered) post to it directly. Edited directly in
     *   `Identity/Routes/web.php` rather than listed here.
     * - Api: same reasoning again — `/session-token` has no screen of its
     *   own, and is what the still-Blade technician mobile pages
     *   (`layouts.mobile`, the QR scan landing pages) use to mint the
     *   bearer token their own offline queue posts with (SRS 38). Edited
     *   directly in `Api/Routes/web.php` rather than listed here.
     *
     * Every module below was checked for exactly that kind of external
     * dependency before being added — `route('app.*')` calls reaching in
     * from outside the module's own views (Blade's shared header, the
     * Platform support-ticket notifier, `MaintenanceNotifier`, the QR
     * scan-to-action links in `ScanResolver`) were repointed at the
     * Next.js app (`config('tenancy.frontend_url')`) rather than left to
     * 404 once the route they named stopped existing.
     */
    private const WEB_DECOMMISSIONED = [
        'Analytics', 'Approval', 'Asset', 'Audit', 'Billing', 'Breakdown',
        'Calendar', 'Costing', 'Inventory', 'Maintenance', 'Metering',
        'Notification', 'Reporting', 'Settings', 'Vendor', 'Webhook', 'WorkOrder',
    ];

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
            $this->loadViewsFrom($views, $this->namespaceFor($module));
        }
    }

    private function loadModuleTranslations(string $module, string $path): void
    {
        $lang = $path.'/Resources/lang';

        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, $this->namespaceFor($module));
        }
    }

    /**
     * WorkOrder becomes work_order, not workorder.
     *
     * Snake case, so a two-word module reads the same in a view name as it does
     * in its translation file: work_order::work-orders.index alongside
     * lang/en/work_order.php. strtolower() would give "workorder" in one place
     * and "work_order" in the other, and the mismatch is only discovered when a
     * screen 500s.
     */
    private function namespaceFor(string $module): string
    {
        return Str::snake($module);
    }

    /**
     * Web routes are prefixed /app and use the session stack.
     * API routes are prefixed /api/v1 and use tokens.
     * Both hit controllers that delegate to the same Actions (ADR-066).
     */
    private function loadModuleRoutes(string $module, string $path): void
    {
        $web = $path.'/Routes/web.php';

        if (is_file($web) && ! in_array($module, self::WEB_DECOMMISSIONED, true)) {
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
