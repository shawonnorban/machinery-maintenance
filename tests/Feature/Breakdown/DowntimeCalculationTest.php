<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Breakdown\Services\DowntimeCalculator;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Downtime derivation (SRS 17, ADR-048).
 *
 * The case that decides whether these figures are usable: a breakdown at 21:50
 * in a factory whose shift ends at 22:00 and resumes at 06:00. Wall-clock says
 * the machine was down for over eight hours. The factory says ten minutes were
 * lost. Both are arithmetically correct; only one is an answer to "how much
 * production did this cost".
 */
class DowntimeCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private ReportBreakdown $report;

    private TransitionBreakdown $transition;

    private DowntimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->report = app(ReportBreakdown::class);
        $this->transition = app(TransitionBreakdown::class);
        $this->calculator = app(DowntimeCalculator::class);
    }

    /** One 08:00-22:00 shift, Saturday to Thursday, Friday off. */
    private function shiftCalendar(): void
    {
        FactoryCalendarSetup::factory($this->delta->id, $this->dhaka->id, 'SHIFT_BASED');
        FactoryCalendarSetup::shift($this->delta->id, 'DAY', 'Day', '08:00', '22:00');

        app(SetSetting::class)->handle(
            'metrics.downtime_uses_shift_calendar', true, factoryId: $this->dhaka->id,
        );
    }

    /**
     * The ADR-048 case. Down at 21:50, ten minutes before the shift ends; back
     * at 06:10, ten minutes after it resumes.
     *
     * Each step is stamped with the time it actually happened rather than "now",
     * which is also how the real screens work: a technician who repairs a
     * machine at 06:05 records 06:05.
     */
    private function dhaka(string $wallTime): CarbonImmutable
    {
        // The factory's clock, not the server's. Storage is UTC throughout; the
        // shift that decides these figures is 08:00-22:00 in Dhaka.
        return CarbonImmutable::parse($wallTime, 'Asia/Dhaka')->setTimezone('UTC');
    }

    private function overnightBreakdown(): Breakdown
    {
        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Motor tripped at end of shift',
            'failure_at' => $this->dhaka('2026-08-18 21:50:00'),
            'reported_at' => $this->dhaka('2026-08-18 21:50:00'),
        ], 'user-a');

        $breakdown = $this->transition->acknowledge(
            $breakdown, 'user-b', $this->dhaka('2026-08-18 21:52:00'),
        );

        $breakdown = $this->transition->startRepair(
            $breakdown, 'user-c', $this->dhaka('2026-08-18 21:55:00'),
        );

        $breakdown = $this->transition->completeRepair(
            $breakdown,
            [
                'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
                'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
            ],
            'user-c',
            $this->dhaka('2026-08-19 06:05:00'),
        );

        $breakdown = $this->transition->resumeProduction(
            $breakdown, 'user-c', $this->dhaka('2026-08-19 06:10:00'),
        );

        return $breakdown->fresh();
    }

    public function test_the_overnight_case_counts_working_minutes_not_wall_clock(): void
    {
        $this->shiftCalendar();

        $breakdown = $this->overnightBreakdown();
        $downtime = $this->calculator->forBreakdown($breakdown);

        // The ADR-048 figure exactly. 21:50 to 22:00 is ten working minutes;
        // the machine was back before the 08:00 shift began, so the second day
        // accrues nothing. Reporting the 500 elapsed minutes would make every
        // overnight fault look catastrophic and every daytime one trivial.
        $this->assertSame(10, $downtime->total_downtime_minutes);
        $this->assertTrue($downtime->calendar_aware);
        $this->assertSame('SHIFT_CALENDAR', $downtime->calculation_basis);
    }

    public function test_the_same_breakdown_on_wall_clock_gives_the_elapsed_figure(): void
    {
        // Calendar-aware is on by default, so switching it off is explicit.
        app(SetSetting::class)->handle(
            'metrics.downtime_uses_shift_calendar', false, factoryId: $this->dhaka->id,
        );

        // The same events, measured the other way.
        $breakdown = $this->overnightBreakdown();
        $downtime = $this->calculator->forBreakdown($breakdown);

        // 21:50 to 06:10 is 500 minutes. Not wrong — a different question.
        $this->assertSame(500, $downtime->total_downtime_minutes);
        $this->assertFalse($downtime->calendar_aware);
        $this->assertSame('WALL_CLOCK', $downtime->calculation_basis);
    }

    public function test_the_basis_is_recorded_on_every_row(): void
    {
        $breakdown = $this->overnightBreakdown();
        $downtime = $this->calculator->forBreakdown($breakdown);

        // A report that silently changes basis is worse than one that says which
        // it used (SRS 47.2 rule 4).
        $this->assertNotNull($downtime->calculation_basis);
    }

    public function test_a_factory_with_no_calendar_says_so_rather_than_guessing(): void
    {
        app(SetSetting::class)->handle(
            'metrics.downtime_uses_shift_calendar', true, factoryId: $this->dhaka->id,
        );

        $breakdown = $this->overnightBreakdown();
        $downtime = $this->calculator->forBreakdown($breakdown);

        // Calendar-aware was asked for, but there is no calendar. It falls back
        // to continuous operation and names the fallback, so nobody reads the
        // number as shift-based.
        $this->assertSame('CONTINUOUS_FALLBACK', $downtime->calculation_basis);
        $this->assertSame(500, $downtime->total_downtime_minutes);
    }

    public function test_recalculating_at_a_new_version_leaves_the_old_figures_intact(): void
    {
        $breakdown = $this->overnightBreakdown();

        $v1 = $this->calculator->forBreakdown($breakdown, version: 1);
        $originalTotal = $v1->total_downtime_minutes;

        // The rules change. A closed period's KPIs must not be rewritten
        // underneath the person who reported them (SRS 17.3).
        $this->shiftCalendar();
        $v2 = $this->calculator->forBreakdown($breakdown->fresh(), version: 2);

        $this->assertSame($originalTotal, $v1->fresh()->total_downtime_minutes);
        $this->assertNotSame($v1->total_downtime_minutes, $v2->total_downtime_minutes);
        $this->assertSame(2, DowntimeRecord::where('breakdown_id', $breakdown->id)->count());

        // The breakdown reads the latest version.
        $this->assertSame(2, $breakdown->fresh()->currentDowntime()->calculation_version);
    }

    public function test_recalculating_at_the_same_version_overwrites_rather_than_duplicating(): void
    {
        $breakdown = $this->overnightBreakdown();

        $this->calculator->forBreakdown($breakdown);
        $this->calculator->forBreakdown($breakdown->fresh());

        $this->assertSame(1, DowntimeRecord::where('breakdown_id', $breakdown->id)->count());
    }

    public function test_an_unclassified_stoppage_is_flagged_not_dropped(): void
    {
        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped, cause unclear',
        ], 'user-a');

        $breakdown->forceFill(['downtime_reason_code_id' => null])->save();

        $downtime = $this->calculator->forBreakdown($breakdown->fresh());

        // Defaults to unplanned and counts, but is marked for review. Silently
        // excluding it would quietly inflate availability (ERD Section 12 rule 1).
        $this->assertTrue($downtime->needs_review);
        $this->assertSame('UNPLANNED', $downtime->downtime_class);
        $this->assertTrue($downtime->counts_against_availability);
    }

    public function test_an_external_cause_does_not_count_against_availability(): void
    {
        $outage = DowntimeReasonCode::where('code', 'POWER_OUTAGE')->firstOrFail();

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Grid power lost, machine stopped',
            'downtime_reason_code_id' => $outage->id,
        ], 'user-a');

        $downtime = $breakdown->currentDowntime();

        // A grid failure is not the maintenance team's availability problem, and
        // charging it to them makes the figure useless for judging them.
        $this->assertSame('EXTERNAL', $downtime->downtime_class);
        $this->assertFalse($downtime->counts_against_availability);
        $this->assertFalse($downtime->needs_review);
    }

    public function test_waiting_for_a_spare_still_counts_against_availability(): void
    {
        $awaiting = DowntimeReasonCode::where('code', 'AWAITING_SPARE')->firstOrFail();

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Hook worn, replacement not in store',
            'downtime_reason_code_id' => $awaiting->id,
        ], 'user-a');

        // This is the point of the column: it makes the cost of an understocked
        // store visible as downtime instead of hiding it inside repair time
        // (Seed Catalog 5).
        $this->assertTrue($breakdown->currentDowntime()->counts_against_availability);
        $this->assertSame('UNPLANNED', $breakdown->currentDowntime()->downtime_class);
    }

    public function test_an_open_breakdown_is_measured_to_now(): void
    {
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Still down',
        ], 'user-a');

        CarbonImmutable::setTestNow('2026-08-18 10:30:00');
        $downtime = $this->calculator->forBreakdown($breakdown->fresh());

        // A stoppage that is still costing money should be visible while it
        // costs it, not read as zero until somebody closes the record.
        $this->assertSame(90, $downtime->total_downtime_minutes);

        CarbonImmutable::setTestNow();
    }

    public function test_response_time_is_null_until_someone_responds(): void
    {
        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Nobody has looked at this yet',
        ], 'user-a');

        // Null, not zero. Zero would read as an instant response and would drag
        // the average down (SRS 31.2 rule 2).
        $this->assertNull($breakdown->currentDowntime()->response_minutes);
        $this->assertNull($breakdown->currentDowntime()->repair_minutes);
    }

    /**
     * SRS 15's extended sign-off chain (Verify -> Submit for Approval ->
     * Final Approval) sits entirely *after* production resumes — none of
     * those three steps should move a single downtime figure, since the
     * outage itself already ended. A regression here would show up as a
     * breakdown's reported downtime silently growing while it sits in
     * administrative review, which is exactly the kind of drift a factory
     * manager would never think to double-check.
     */
    public function test_downtime_figures_are_unaffected_by_the_verify_submit_close_steps(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 09:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Bearing seized',
        ], 'user-a');

        CarbonImmutable::setTestNow('2026-08-20 09:05:00');
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');

        CarbonImmutable::setTestNow('2026-08-20 09:10:00');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-20 09:40:00');
        $breakdown = $this->transition->completeRepair($breakdown, [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ], 'user-c');

        CarbonImmutable::setTestNow('2026-08-20 09:45:00');
        $breakdown = $this->transition->resumeProduction($breakdown, 'user-c');

        $afterResume = $breakdown->fresh()->currentDowntime();

        $this->assertSame(45, $afterResume->total_downtime_minutes);
        // technician_arrival_at is implicitly stamped by startRepair() (09:10)
        // since arrive() was never called separately, and that takes priority
        // over acknowledged_at (09:05) as the response end-point.
        $this->assertSame(10, $afterResume->response_minutes);
        $this->assertSame(30, $afterResume->repair_minutes);
        $this->assertSame(0, $afterResume->hold_minutes);
        $this->assertTrue($afterResume->counts_against_availability);

        // An hour and a half of Line Chief review + Engineer approval later —
        // none of it should read as downtime.
        CarbonImmutable::setTestNow('2026-08-20 10:30:00');
        $breakdown = $this->transition->verify($breakdown->fresh(), 'user-d');

        CarbonImmutable::setTestNow('2026-08-20 11:00:00');
        $breakdown = $this->transition->submitForApproval($breakdown, 'user-d');

        CarbonImmutable::setTestNow('2026-08-20 11:15:00');
        $this->transition->close($breakdown, null, 'user-b');

        $afterClose = $breakdown->fresh()->currentDowntime();

        $this->assertSame($afterResume->total_downtime_minutes, $afterClose->total_downtime_minutes);
        $this->assertSame($afterResume->response_minutes, $afterClose->response_minutes);
        $this->assertSame($afterResume->repair_minutes, $afterClose->repair_minutes);
        $this->assertSame($afterResume->hold_minutes, $afterClose->hold_minutes);

        CarbonImmutable::setTestNow();
    }

    /**
     * The Line Chief's own reject-at-verify (SRS 15) must make total downtime
     * start counting again — the machine was never actually fixed, so a
     * frozen "already resolved" figure while it sits back in `IN_REPAIR`
     * would understate the real cost of the failed first attempt.
     */
    public function test_a_line_chiefs_reject_makes_downtime_resume_counting_until_the_redo_finishes(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 09:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Motor overheating',
        ], 'user-a');

        CarbonImmutable::setTestNow('2026-08-21 09:05:00');
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');

        CarbonImmutable::setTestNow('2026-08-21 09:10:00');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-21 09:30:00');
        $breakdown = $this->transition->completeRepair($breakdown, [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ], 'user-c');

        $afterFirstComplete = $breakdown->fresh()->currentDowntime();
        $this->assertSame(30, $afterFirstComplete->total_downtime_minutes);

        // The Line Chief checks it twenty minutes later and it still isn't
        // actually fixed.
        CarbonImmutable::setTestNow('2026-08-21 09:50:00');
        $breakdown = $this->transition->rejectVerification($breakdown->fresh(), 'Still overheating', 'user-d');

        $this->assertNull($breakdown->repair_completed_at);

        $whileBackInRepair = $breakdown->fresh()->currentDowntime();
        // repair_completed_at/production_resumed_at were cleared by the
        // reject, so the outage is measured to "now" again instead of
        // staying frozen at the rejected attempt's own numbers.
        $this->assertSame(50, $whileBackInRepair->total_downtime_minutes);

        // The technician redoes it properly.
        CarbonImmutable::setTestNow('2026-08-21 10:00:00');
        $breakdown = $this->transition->completeRepair($breakdown->fresh(), [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ], 'user-c');

        CarbonImmutable::setTestNow('2026-08-21 10:05:00');
        $breakdown = $this->transition->resumeProduction($breakdown, 'user-c');

        $final = $breakdown->fresh()->currentDowntime();

        // Total downtime spans the whole ordeal — the rejected attempt, the
        // review, and the redo (09:00 to 10:05) — not just the successful
        // second try, since the machine was down the entire time.
        $this->assertSame(65, $final->total_downtime_minutes);
        // Repair minutes span the original `repair_started_at` (09:10, never
        // reset by the reject) to the final `repair_completed_at` (10:00):
        // the review-and-redo cycle is real repair time, not free.
        $this->assertSame(50, $final->repair_minutes);

        CarbonImmutable::setTestNow();
    }
}
