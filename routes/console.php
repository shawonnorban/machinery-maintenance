<?php

declare(strict_types=1);

use App\Modules\Analytics\Console\SnapshotKpis;
use App\Modules\ItemMaster\Console\AlertExpiringLots;
use App\Modules\ItemMaster\Console\ChaseOpenStockCounts;
use App\Modules\Billing\Console\AdvanceSubscriptions;
use App\Modules\Maintenance\Console\GenerateMaintenanceSchedules;
use App\Modules\Notification\Console\EscalateNotifications;
use App\Modules\Platform\Console\CheckTicketSla;
use App\Modules\Procurement\Console\ScoreSuppliers;
use App\Modules\Procurement\Console\TrackImportShipments;
use App\Modules\Reporting\Console\PruneReportFiles;
use App\Modules\Requisition\Console\RemindOutstandingRequisitions;
use App\Modules\Vendor\Console\AlertExpiringCoverage;
use App\Modules\Webhook\Console\PruneWebhookPayloads;
use App\Modules\Webhook\Console\RetryWebhookDeliveries;
use Illuminate\Support\Facades\Schedule;

/*
 * Scheduled tasks (ADR-011).
 *
 * The scheduler being optional in development is a trap: without it nothing
 * looks broken, maintenance simply stops being generated. That is why a missed
 * heartbeat is the first thing on the alerting list in doc 11 §8, ahead of
 * anything that would actually show somebody an error page (ADR-061).
 *
 * It must run every minute rather than every five: escalation is evaluated on
 * its tick, and a rule promising "tell the manager after thirty minutes"
 * cannot be kept by a scheduler coarser than the promise.
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
 * Support ticket SLA breaches. Fifteen minutes: the shortest deadline
 * (URGENT's one-hour first response) still gets flagged within a small
 * fraction of itself, without polling every ticket every minute for a
 * clock that mostly hasn't moved.
 */
Schedule::command(CheckTicketSla::class)
    ->everyFifteenMinutes()
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

/*
 * Chemical and dye lots going off (Inventory spec §12). Daily, alongside the
 * other store sweeps: an expiry is measured in weeks, so an hourly check
 * would only repeat itself.
 */
Schedule::command(AlertExpiringLots::class)
    ->dailyAt('06:40')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Count sheets left open (Inventory spec §12). Daily, with the other store
 * sweeps: a sheet's frozen figures describe the shelf a little less with
 * every day that passes.
 */
Schedule::command(ChaseOpenStockCounts::class)
    ->dailyAt('06:35')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Borrowed stock that has not come back, shortage claims nobody chased, and
 * holds past their date (Floor/Store Requisition spec §7). Daily and early,
 * so the store team starts the shift knowing what is outstanding.
 */
Schedule::command(RemindOutstandingRequisitions::class)
    ->dailyAt('06:45')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Supplier scorecards (Procurement spec §15). Weekly rather than daily: the
 * window is three months, so a fresh run on Monday says almost exactly what
 * Sunday's did, and a rating that twitches every morning is one nobody
 * trusts enough to quote at a supplier.
 */
Schedule::command(ScoreSuppliers::class)
    ->weeklyOn(1, '02:30')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Imports in the air and letters of credit about to lapse (Procurement spec
 * §10). Daily, with the other store sweeps: an ETA moves in days and a bank
 * deadline in weeks, so anything finer would only repeat itself.
 */
Schedule::command(TrackImportShipments::class)
    ->dailyAt('06:50')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Subscription lifecycle and usage (ADR-029, SRS 40). Daily and early: a
 * customer moved to read-only should find out at the start of their day, not
 * halfway through a shift.
 */
Schedule::command(AdvanceSubscriptions::class)
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Webhook retries (ERD Section 22). Every five minutes, because the first
 * backoff step is one minute and anything slower makes it meaningless.
 */
Schedule::command(RetryWebhookDeliveries::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Webhook payloads are dropped after thirty days; the delivery record stays
 * (SRS 49.1).
 */
Schedule::command(PruneWebhookPayloads::class)
    ->dailyAt('02:45')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Horizon's own metrics graph (queue throughput/runtime) has no data until
 * something takes a snapshot on a schedule — this is that schedule.
 */
Schedule::command('horizon:snapshot')->everyFiveMinutes();
