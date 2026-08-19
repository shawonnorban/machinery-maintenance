<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Breakdown\Services\DowntimeCalculator;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\Shift;
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
        FactoryCalendar::create([
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5],
            'effective_from' => '2026-01-01',
        ]);

        Shift::create([
            'factory_id' => $this->dhaka->id,
            'name' => 'Day', 'code' => 'DAY',
            'start_time' => '08:00:00', 'end_time' => '22:00:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

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
            $breakdown, 'user-c', $this->dhaka('2026-08-19 06:05:00'),
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
}
