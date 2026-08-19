<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Models\KpiSnapshot;
use App\Modules\Analytics\Services\KpiCalculator;
use App\Modules\Analytics\Services\KpiSnapshotter;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Precomputed KPI snapshots (ADR-058).
 *
 * The property that matters is that precomputing changes nothing. A snapshot is
 * a latency decision; the day it starts producing a different number from a
 * live scan, the dashboard and the report disagree and nobody trusts either
 * (SRS 31.2 rule 7).
 */
class KpiSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private KpiSnapshotter $snapshotter;

    private KpiCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        FactoryCalendar::create([
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'CONTINUOUS',
            'weekly_off_days' => [],
            'effective_from' => '2026-01-01',
        ]);

        $this->snapshotter = app(KpiSnapshotter::class);
        $this->calculator = app(KpiCalculator::class);

        CarbonImmutable::setTestNow('2026-06-20 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** A stoppage on the given local day. */
    private function failure(string $day, int $repairMinutes = 60): void
    {
        $at = CarbonImmutable::parse("2026-06-{$day} 09:00:00", 'Asia/Dhaka')->setTimezone('UTC');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $transition = app(TransitionBreakdown::class);
        $breakdown = $transition->acknowledge($breakdown, 'user-b', $at->addMinutes(10));
        $breakdown = $transition->startRepair($breakdown, 'user-c', $at->addMinutes(10));
        $breakdown = $transition->completeRepair($breakdown, 'user-c', $at->addMinutes(10 + $repairMinutes));
        $transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(10 + $repairMinutes));
    }

    private function scope(): array
    {
        return ['factory_id' => $this->dhaka->id];
    }

    public function test_a_snapshot_stores_the_components_not_the_conclusions(): void
    {
        $this->failure('10', repairMinutes: 110);

        $snapshot = $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-10'), $this->scope());

        $this->assertSame('FACTORY', $snapshot->scope_type);
        $this->assertSame('DAY', $snapshot->period_type);
        $this->assertSame(120, (int) $snapshot->downtime_minutes);
        $this->assertSame(1, (int) $snapshot->failure_count);
        // The counts behind each mean are stored too, so a month can be summed
        // from its days rather than averaged from their averages.
        $this->assertSame(1, (int) $snapshot->repair_count);
        $this->assertSame(110, (int) $snapshot->repair_minutes_total);
        $this->assertSame(KpiCalculator::CALCULATION_VERSION, (int) $snapshot->calculation_version);
    }

    public function test_a_period_read_from_snapshots_equals_a_live_scan(): void
    {
        foreach (['10', '12', '15'] as $day) {
            $this->failure($day, repairMinutes: 45);
        }

        $from = CarbonImmutable::parse('2026-06-09 00:00:00', 'Asia/Dhaka')->setTimezone('UTC');
        $to = CarbonImmutable::parse('2026-06-17 00:00:00', 'Asia/Dhaka')->setTimezone('UTC');

        $live = $this->calculator->forPeriod($from, $to, $this->scope());

        for ($day = 9; $day <= 17; $day++) {
            $this->snapshotter->writeDay(CarbonImmutable::parse("2026-06-{$day}"), $this->scope());
        }

        $stored = $this->snapshotter->forPeriod($from, $to, $this->scope());

        foreach ([
            'scheduled_operating_minutes', 'downtime_minutes', 'unplanned_downtime_minutes',
            'failure_count', 'availability_percent', 'mtbf_minutes', 'mttr_minutes',
        ] as $key) {
            // Precomputing is a latency decision. It is not allowed to be a
            // different answer.
            $this->assertEquals($live[$key], $stored[$key], "{$key} drifted between snapshot and live.");
        }
    }

    public function test_a_partial_window_falls_back_to_a_live_scan(): void
    {
        $this->failure('10');

        $from = CarbonImmutable::parse('2026-06-09 00:00:00', 'Asia/Dhaka')->setTimezone('UTC');
        $to = CarbonImmutable::parse('2026-06-14 00:00:00', 'Asia/Dhaka')->setTimezone('UTC');

        // Only two of the five days stored.
        $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-09'), $this->scope());
        $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-10'), $this->scope());

        $stored = $this->snapshotter->forPeriod($from, $to, $this->scope());
        $live = $this->calculator->forPeriod($from, $to, $this->scope());

        // A missing snapshot must never become a missing number: a dashboard
        // that silently drops the first week of a month is worse than a slow
        // one.
        $this->assertEquals($live['downtime_minutes'], $stored['downtime_minutes']);
        $this->assertSame(1, $stored['failure_count']);
    }

    public function test_the_uncovered_part_of_today_is_still_counted(): void
    {
        // Two hours down this morning, before the last stored day ends.
        $at = CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Dhaka')->setTimezone('UTC');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped this morning',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $transition = app(TransitionBreakdown::class);
        $breakdown = $transition->acknowledge($breakdown, 'user-b', $at);
        $breakdown = $transition->startRepair($breakdown, 'user-c', $at);
        $breakdown = $transition->completeRepair($breakdown, 'user-c', $at->addHours(2));
        $transition->resumeProduction($breakdown, 'user-c', $at->addHours(2));

        for ($day = 18; $day <= 19; $day++) {
            $this->snapshotter->writeDay(CarbonImmutable::parse("2026-06-{$day}"), $this->scope());
        }

        $from = CarbonImmutable::parse('2026-06-18 00:00:00', 'Asia/Dhaka')->setTimezone('UTC');
        $stored = $this->snapshotter->forPeriod($from, CarbonImmutable::now(), $this->scope());

        // Today has no snapshot and never will while it is still running; the
        // reader scans the part that has happened rather than showing a
        // stoppage that is not there.
        $this->assertSame(120, $stored['downtime_minutes']);
        $this->assertSame(1, $stored['failure_count']);
    }

    public function test_recomputing_a_day_rewrites_it_rather_than_adding_a_second_row(): void
    {
        $this->failure('10');

        $day = CarbonImmutable::parse('2026-06-10');

        $this->snapshotter->writeDay($day, $this->scope());
        $this->snapshotter->writeDay($day, $this->scope());

        // Idempotent, so an hourly job does not accumulate a row per run and
        // double every figure it feeds.
        $this->assertSame(1, KpiSnapshot::where('period_start', '2026-06-10')->count());
    }

    public function test_a_closed_breakdown_after_midnight_changes_yesterdays_row(): void
    {
        $at = CarbonImmutable::parse('2026-06-19 22:00:00', 'Asia/Dhaka')->setTimezone('UTC');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Late shift failure',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $yesterday = CarbonImmutable::parse('2026-06-19');
        $before = $this->snapshotter->writeDay($yesterday, $this->scope());

        $transition = app(TransitionBreakdown::class);
        $breakdown = $transition->acknowledge($breakdown, 'user-b', $at);
        $breakdown = $transition->startRepair($breakdown, 'user-c', $at);
        $breakdown = $transition->completeRepair($breakdown, 'user-c', $at->addMinutes(30));
        $transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(30));

        $after = $this->snapshotter->writeDay($yesterday, $this->scope());

        // This is why the job recomputes more than one day: repair minutes only
        // exist once the work is finished, which is often after midnight.
        $this->assertSame(0, (int) $before->repair_count);
        $this->assertSame(1, (int) $after->repair_count);
        $this->assertSame(30, (int) $after->repair_minutes_total);
    }

    public function test_a_company_snapshot_is_separate_from_its_factory_snapshot(): void
    {
        $this->failure('10');

        $factoryRow = $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-10'), $this->scope());
        $companyRow = $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-10'));

        $this->assertSame('FACTORY', $factoryRow->scope_type);
        $this->assertSame('COMPANY', $companyRow->scope_type);
        $this->assertNotSame($factoryRow->id, $companyRow->id);
        $this->assertSame(2, KpiSnapshot::where('period_start', '2026-06-10')->count());
    }

    public function test_the_command_writes_a_row_per_scope_and_day(): void
    {
        $this->failure('19');

        $this->artisan('kpi:snapshot', ['--days' => 2])->assertSuccessful();

        // One company scope plus one factory scope, two days each.
        $this->assertSame(4, KpiSnapshot::withoutGlobalScopes()->count());
    }

    public function test_a_day_is_a_day_on_the_factory_clock(): void
    {
        // 01:00 on the 11th in Dhaka is 19:00 on the 10th in UTC. Slicing on
        // UTC midnight files this failure under the wrong day, and the night
        // shift's stoppage lands on the day before it happened.
        $late = CarbonImmutable::parse('2026-06-11 01:00:00', 'Asia/Dhaka')->setTimezone('UTC');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Night shift failure',
            'failure_at' => $late,
            'reported_at' => $late,
        ], 'user-a');

        $transition = app(TransitionBreakdown::class);
        $breakdown = $transition->acknowledge($breakdown, 'user-b', $late);
        $breakdown = $transition->startRepair($breakdown, 'user-c', $late);
        $breakdown = $transition->completeRepair($breakdown, 'user-c', $late->addMinutes(20));
        $transition->resumeProduction($breakdown, 'user-c', $late->addMinutes(20));

        $tenth = $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-10'), $this->scope());
        $eleventh = $this->snapshotter->writeDay(CarbonImmutable::parse('2026-06-11'), $this->scope());

        $this->assertSame(0, (int) $tenth->failure_count);
        $this->assertSame(1, (int) $eleventh->failure_count);
    }
}
