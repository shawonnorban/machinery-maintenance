<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Nothing to bind yet — migrations and routes are already auto-discovered
 * by `ModuleServiceProvider`. Kept for the same reason every other module
 * has one: the day this needs an observer or a binding, it has somewhere
 * to go without inventing the file then.
 */
class CustomFieldServiceProvider extends ServiceProvider {}
