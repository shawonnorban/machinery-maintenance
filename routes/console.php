<?php

declare(strict_types=1);

use App\Modules\Analytics\Console\SnapshotKpis;
use App\Modules\Maintenance\Console\GenerateMaintenanceSchedules;
use App\Modules\Notification\Console\EscalateNotifications;
use App\Modules\Reporting\Console\PruneReportFiles;
use App\Modules\Vendor\Console\AlertExpiringCoverage;
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

/*
 * Escalation runs often, because its whole value is being timely: a rule that
 * says "tell the manager after thirty minutes" is worthless if it is evaluated
 * hourly.
 */
Schedule::command(EscalateNotifications::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * KPI snapshots (ADR-058). Hourly, because today's figure moves all day and a
 * dashboard showing this morning's availability at six in the evening loses
 * trust in the whole screen rather than one tile.
 */
Schedule::command(SnapshotKpis::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Expired report files (SRS 35). Daily is enough: retention is measured in
 * days, and the row survives to say what was asked for and by whom.
 */
Schedule::command(PruneReportFiles::class)
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Expiring warranties and AMC contracts (SRS 26, ADR-011). Daily: cover is
 * measured in months, and the alert thresholds are days apart, so an hourly
 * sweep would only repeat itself.
 */
Schedule::command(AlertExpiringCoverage::class)
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->runInBackground();
