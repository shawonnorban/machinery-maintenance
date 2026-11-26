<?php

declare(strict_types=1);

namespace App\Modules\Settings\Providers;

use App\Modules\Settings\Listeners\RecordScheduledJobRuns;
use App\Modules\Settings\Services\SettingsResolver;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped: settings are read many times per request and the
        // catalog does not change mid-request.
        $this->app->singleton(SettingsResolver::class);
    }

    public function boot(): void
    {
        // Settings → Scheduled Jobs: the scheduler's run log (AnnotechERP 03/04).
        Event::listen(ScheduledTaskStarting::class, [RecordScheduledJobRuns::class, 'onStarting']);
        Event::listen(ScheduledTaskFinished::class, [RecordScheduledJobRuns::class, 'onFinished']);
        Event::listen(ScheduledBackgroundTaskFinished::class, [RecordScheduledJobRuns::class, 'onBackgroundFinished']);
        Event::listen(ScheduledTaskFailed::class, [RecordScheduledJobRuns::class, 'onFailed']);
    }
}
