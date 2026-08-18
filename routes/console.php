<?php

declare(strict_types=1);

use App\Modules\Maintenance\Console\GenerateMaintenanceSchedules;
use Illuminate\Support\Facades\Schedule;

/*
 * Scheduled tasks (ADR-011).
 *
 * The scheduler being optional in development is a trap: without it nothing
 * looks broken, maintenance simply stops being generated. It runs in the
 * docker compose stack for that reason, and a missed heartbeat alerts in
 * production (ADR-061).
 */
Schedule::command(GenerateMaintenanceSchedules::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
