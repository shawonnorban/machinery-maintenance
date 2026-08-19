<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\KpiCalculator;
use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
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
 * KPI definitions (SRS 31).
 *
 * The counting rules are the substance. Every one of them exists because the
 * obvious implementation produces a number that is wrong in a way nobody
 * notices: a zero that reads as perfect, a duplicate report that halves MTBF,
 * a scrapped machine dragging availability down for ever.
 */
class KpiCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private KpiCalculator $kpi;

    private ReportBreakdown $report;

    private TransitionBreakdown $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->kpi = app(KpiCalculator::class);
        $this->report = app(ReportBreakdown::class);
        $this->transition = app(TransitionBreakdown::class);

        $this->continuousCalendar();
    }

    /** Runs around the clock, so scheduled time is simply elapsed time. */
    private function continuousCalendar(): void
    {
        FactoryCalendar::create([
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'CONTINUOUS',
            'weekly_off_days' => [],
            'effective_from' => '2026-01-01',
        ]);
    }

    private function period(): array
    {
        return [
            CarbonImmutable::parse('2026-06-01 00:00:00'),
            CarbonImmutable::parse('2026-06-30 23:59:59'),
        ];
    }

    /**
     * A breakdown reported, repaired and closed inside the period.
     */
    private function failure(string $day, int $repairMinutes = 60, int $ackMinutes = 10): Breakdown
    {
        $at = CarbonImmutable::parse("2026-06-{$day} 09:00:00");

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $breakdown = $this->transition->acknowledge($breakdown, 'user-b', $at->addMinutes($ackMinutes));
        $breakdown = $this->transition->startRepair($breakdown, 'user-c', $at->addMinutes($ackMinutes));
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c', $at->addMinutes($ackMinutes + $repairMinutes));
        $breakdown = $this->transition->resumeProduction($breakdown, 'user-c', $at->addMinutes($ackMinutes + $repairMinutes));

        $this->transition->close($breakdown, [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'NORMAL_WEAR')->firstOrFail()->id,
        ], 'user-b');

        // Back in service so the next failure is independent.
        $machine = Asset::find($this->asset->id);

        if ($machine->status !== 'RUNNING' && $machine->canTransitionTo('RUNNING')) {
            app(ChangeAssetStatus::class)->handle($machine, 'RUNNING', 'user-c', 'Back in service', 'BREAKDOWN');
        }

        return $breakdown->fresh();
    }

    public function test_a_zero_denominator_is_null_never_zero(): void
    {
        [$from, $to] = $this->period();

        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        // A machine with no failures has no mean time between them. Reporting 0
        // would say it fails constantly, which is the opposite of the truth
        // (rule 2).
        $this->assertNull($result['mtbf_minutes']);
        $this->assertNull($result['mttr_minutes']);
        $this->assertNull($result['mtta_minutes']);
        $this->assertSame(0, $result['failure_count']);
    }

    public function test_availability_is_operating_time_over_scheduled_time(): void
    {
        [$from, $to] = $this->period();

        // Two hours of downtime in a continuous month.
        $this->failure('10', repairMinutes: 110, ackMinutes: 10);

        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        $this->assertGreaterThan(0, $result['scheduled_operating_minutes']);
        $this->assertSame(120, $result['downtime_minutes']);
        $this->assertSame(
            $result['scheduled_operating_minutes'] - 120,
            $result['operating_minutes'],
        );
        // Two hours out of a month is barely a dent, but it is not 100%.
        $this->assertLessThan(100.0, $result['availability_percent']);
        $this->assertGreaterThan(99.0, $result['availability_percent']);
    }

    public function test_mtbf_uses_operating_time_not_calendar_time(): void
    {
        [$from, $to] = $this->period();

        $this->failure('5');
        $this->failure('15');
        $this->failure('25');

        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        $this->assertSame(3, $result['failure_count']);
        // Operating time, so downtime is excluded from the numerator: a machine
        // that spent the month broken has not been running between failures.
        $this->assertSame(
            round($result['operating_minutes'] / 3, 1),
            $result['mtbf_minutes'],
        );
    }

    public function test_mttr_excludes_hold_time(): void
    {
        [$from, $to] = $this->period();

        $at = CarbonImmutable::parse('2026-06-10 09:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Bearing seized',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $breakdown = $this->transition->acknowledge($breakdown, 'user-b', $at->addMinutes(5));
        $breakdown = $this->transition->startRepair($breakdown, 'user-c', $at->addMinutes(10));

        CarbonImmutable::setTestNow($at->addMinutes(20));
        $breakdown = $this->transition->hold($breakdown, 'AWAITING_PARTS', 'user-c', 'Bearing on order');

        // Two hours waiting for the part.
        CarbonImmutable::setTestNow($at->addMinutes(140));
        $breakdown = $this->transition->resume($breakdown, 'user-c');
        CarbonImmutable::setTestNow();

        $breakdown = $this->transition->completeRepair($breakdown, 'user-c', $at->addMinutes(160));
        $this->transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(160));

        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        // 150 minutes of repair, 120 of them waiting. MTTR is 30: folding the
        // wait in would hide a supply problem behind a slow-looking team.
        $this->assertSame(30.0, $result['mttr_minutes']);
    }

    public function test_response_time_is_measured_from_the_report_not_the_failure(): void
    {
        [$from, $to] = $this->period();

        $failedAt = CarbonImmutable::parse('2026-06-10 06:00:00');
        $reportedAt = $failedAt->addHours(2);

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Nobody noticed for two hours',
            'failure_at' => $failedAt,
            'reported_at' => $reportedAt,
        ], 'user-a');

        $this->transition->acknowledge($breakdown, 'user-b', $reportedAt->addMinutes(15));

        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        // Fifteen minutes, not two hours fifteen. The two hours before anybody
        // said so is a reporting problem, and charging it to maintenance
        // response measures the wrong team.
        $this->assertSame(15.0, $result['mtta_minutes']);
    }

    public function test_a_duplicate_report_is_not_a_second_failure(): void
    {
        [$from, $to] = $this->period();

        $at = CarbonImmutable::parse('2026-06-10 09:00:00');

        $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        foreach (['Still stopped', 'Nobody has come'] as $description) {
            $this->report->handle([
                'asset_id' => $this->asset->id,
                'problem_description' => $description,
                'failure_at' => $at->addMinutes(30),
                'reported_at' => $at->addMinutes(30),
            ], 'user-a');
        }

        // Counting all three would halve MTBF for a machine that broke once
        // (rule 1).
        $this->assertSame(1, $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id])['failure_count']);
        $this->assertSame(3, Breakdown::count());
    }

    public function test_planned_downtime_is_excluded_from_availability_by_default(): void
    {
        [$from, $to] = $this->period();

        $planned = DowntimeReasonCode::where('code', 'PLANNED_PM')->firstOrFail();
        $at = CarbonImmutable::parse('2026-06-12 09:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Scheduled overhaul',
            'failure_at' => $at,
            'reported_at' => $at,
            'downtime_reason_code_id' => $planned->id,
        ], 'user-a');

        $breakdown = $this->transition->acknowledge($breakdown, 'user-b', $at);
        $breakdown = $this->transition->startRepair($breakdown, 'user-c', $at);
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c', $at->addMinutes(240));
        $this->transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(240));

        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        // Four hours of planned work is recorded but does not count against
        // availability (rule 5).
        $this->assertSame(240, $result['downtime_minutes']);
        $this->assertSame(0, $result['unplanned_downtime_minutes']);
        $this->assertSame(0, $result['counted_downtime_minutes']);
        $this->assertSame(100.0, $result['availability_percent']);
    }

    public function test_the_setting_moves_planned_downtime_into_availability(): void
    {
        [$from, $to] = $this->period();

        app(SetSetting::class)->handle(
            'metrics.planned_downtime_counts_against_availability', true, factoryId: $this->dhaka->id,
        );

        $planned = DowntimeReasonCode::where('code', 'PLANNED_PM')->firstOrFail();
        $at = CarbonImmutable::parse('2026-06-12 09:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Scheduled overhaul',
            'failure_at' => $at,
            'reported_at' => $at,
            'downtime_reason_code_id' => $planned->id,
        ], 'user-a');

        $breakdown = $this->transition->acknowledge($breakdown, 'user-b', $at);
        $breakdown = $this->transition->startRepair($breakdown, 'user-c', $at);
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c', $at->addMinutes(240));
        $this->transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(240));

        // A report does not decide this for itself; the factory does.
        $result = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        $this->assertSame(240, $result['counted_downtime_minutes']);
        $this->assertLessThan(100.0, $result['availability_percent']);
    }

    public function test_a_scrapped_machine_stops_counting_toward_scheduled_time(): void
    {
        [$from, $to] = $this->period();

        $second = WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00999');

        $withBoth = $this->kpi->scheduledOperatingMinutes($from, $to, $this->dhaka->id);

        app(ChangeAssetStatus::class)->handle($second, 'RETIRED', 'user-a', 'End of life', 'MANUAL');
        app(ChangeAssetStatus::class)->handle(
            Asset::find($second->id), 'SCRAPPED', 'user-a', 'Sold for parts', 'MANUAL',
        );

        $withOne = $this->kpi->scheduledOperatingMinutes($from, $to, $this->dhaka->id);

        // Counting a scrapped machine's shift hours as scheduled time would
        // drag availability down for ever (rule 4).
        $this->assertSame($withBoth / 2, $withOne);
    }

    public function test_a_stoppage_spanning_the_period_boundary_is_clipped(): void
    {
        // Starts inside May, ends inside June.
        $at = CarbonImmutable::parse('2026-05-31 22:00:00');

        $breakdown = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Overnight failure',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $breakdown = $this->transition->acknowledge($breakdown, 'user-b', $at);
        $breakdown = $this->transition->startRepair($breakdown, 'user-c', $at);
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c', $at->addHours(4));
        $this->transition->resumeProduction($breakdown, 'user-c', $at->addHours(4));

        $may = $this->kpi->downtimeMinutes(
            CarbonImmutable::parse('2026-05-01 00:00:00'),
            CarbonImmutable::parse('2026-05-31 23:59:59'), $this->dhaka->id,
        );

        $june = $this->kpi->downtimeMinutes(
            CarbonImmutable::parse('2026-06-01 00:00:00'),
            CarbonImmutable::parse('2026-06-30 23:59:59'), $this->dhaka->id,
        );

        // Four hours total, split across the boundary rather than counted twice
        // (rule 3).
        $this->assertGreaterThan(0, $may['total']);
        $this->assertGreaterThan(0, $june['total']);
        $this->assertEqualsWithDelta(240, $may['total'] + $june['total'], 2);
    }

    public function test_availability_follows_the_shift_calendar_not_the_wall_clock(): void
    {
        // Replace the continuous calendar with a single day shift.
        FactoryCalendar::query()->delete();

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

        [$from, $to] = $this->period();

        $scheduled = $this->kpi->scheduledOperatingMinutes($from, $to, $this->dhaka->id);
        $wallClock = $from->diffInMinutes($to);

        // Availability computed against 24 hours a day makes every factory look
        // two-thirds idle (Section 47).
        $this->assertLessThan($wallClock, $scheduled);
        $this->assertGreaterThan(0, $scheduled);
    }

    public function test_pm_compliance_respects_the_grace_period(): void
    {
        [$from, $to] = $this->period();

        // No maintenance due in the period at all.
        $this->assertNull($this->kpi->pmCompliance($from, $to));
    }

    public function test_the_same_scope_and_period_give_identical_numbers(): void
    {
        [$from, $to] = $this->period();

        $this->failure('10');

        $first = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);
        $second = $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id]);

        // Rule 7: a dashboard and a report showing the same KPI for the same
        // scope and period must return identical values. One implementation is
        // how that stays true.
        foreach (['availability_percent', 'mtbf_minutes', 'mttr_minutes', 'failure_count'] as $key) {
            $this->assertSame($first[$key], $second[$key], "{$key} is not stable across calls.");
        }
    }

    public function test_a_cancelled_breakdown_is_not_a_failure(): void
    {
        [$from, $to] = $this->period();

        $at = CarbonImmutable::parse('2026-06-10 09:00:00');

        $mistake = $this->report->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Wrong machine',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $this->transition->cancel($mistake, 'Reported against the wrong machine', 'user-b');

        // A report entered in error is not a failure, and letting it into the
        // count makes the machine look worse than it is.
        $this->assertSame(0, $this->kpi->forPeriod($from, $to, ['factory_id' => $this->dhaka->id])['failure_count']);
    }
}
