<?php

declare(strict_types=1);

use App\Modules\Analytics\Console\SnapshotKpis;
use App\Modules\Approval\Console\EscalateOverdueRequests;
use App\Modules\Billing\Console\AdvanceSubscriptions;
use App\Modules\ColourLab\Console\RemindExpiringCertificates;
use App\Modules\Erp\Console\NotifyPendingWorkCommand;
use App\Modules\Chat\Console\SyncDepartmentGroups;
use App\Modules\HrPayroll\Console\ApplyMovements;
use App\Modules\HrPayroll\Console\ProcessAttendance;
use App\Modules\ItemMaster\Console\AlertExpiringLots;
use App\Modules\ItemMaster\Console\ChaseOpenStockCounts;
use App\Modules\Maintenance\Console\GenerateMaintenanceSchedules;
use App\Modules\Notification\Console\EscalateNotifications;
use App\Modules\OrderSummary\Console\SendEditDigest;
use App\Modules\Platform\Console\CheckTicketSla;
use App\Modules\Procurement\Console\ScoreSuppliers;
use App\Modules\Procurement\Console\TrackImportShipments;
use App\Modules\Reporting\Console\PruneReportFiles;
use App\Modules\Requisition\Console\RemindOutstandingRequisitions;
use App\Modules\Settings\Console\PruneJobRuns;
use App\Modules\Settings\Console\PurgeRetention;
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
/*
 * Daily attendance (AnnotechERP 24/04): today and yesterday worked out again
 * from the terminals' punches every ten minutes, so Daily Attendance is never
 * far behind the gate. A terminal posting punches also updates the employees
 * it heard about at once.
 */
Schedule::command(ProcessAttendance::class)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Employee Movement (AnnotechERP 24/07): approved promotions and increments
 * dated ahead take effect on their day, just after midnight in Dhaka.
 */
Schedule::command(ApplyMovements::class)
    ->dailyAt('00:05')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();

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
 * Lab certificates nearing their Valid Date (AnnotechERP 16/01). Daily: the
 * notice period is counted in days, and each certificate is mailed once for
 * its Valid Date, so a later run never repeats one.
 */
Schedule::command(RemindExpiringCertificates::class)
    ->dailyAt('06:35')
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

/*
 * Settings → Data Retention & Purge (AnnotechERP 03/04): each company's data
 * past the period it chose. Daily, after the night's other sweeps.
 */
Schedule::command(PurgeRetention::class)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Settings → Scheduled Jobs: the run log itself is kept for ninety days.
 */
Schedule::command(PruneJobRuns::class)
    ->dailyAt('03:10')
    ->withoutOverlapping();

/*
 * Approval Center (AnnotechERP 04/03): a request left waiting past its due
 * date and the type's grace hours goes to the escalation contact. Every
 * fifteen minutes: thresholds are in hours.
 */
Schedule::command(EscalateOverdueRequests::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
 * Order Summary's edit mail (AnnotechERP 32): each company sends at the times
 * of day its admin set; checking every minute keeps a 10:00 mail at 10:00.
 */
Schedule::command(SendEditDigest::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Order Summary, Dev Sample Status and Pre-Budget's pending work (user,
 * 2026-11-21): each team hears at the start of the office day what is due —
 * orders near or past RFI/SD, bookings pending, sample deadlines, budget
 * costing, stock yarn use and accessories still to do.
 */
Schedule::command(NotifyPendingWorkCommand::class)
    ->dailyAt('09:00')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Chat's department groups (user, 2026-11-23): one group per HR department,
 * its people following HR & Payroll's moves.
 */
Schedule::command(SyncDepartmentGroups::class)
    ->hourly()
    ->withoutOverlapping();
