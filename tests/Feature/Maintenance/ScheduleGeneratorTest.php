<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Maintenance\Actions\CompleteSchedule;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Maintenance\Services\ScheduleGenerator;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The scheduling engine: SRS 10, ERD 7, ADR-012.
 *
 * Rolling versus fixed is not a preference. A factory that services a machine
 * a week late expects the next service a week later; an inspection regime
 * expects the first of the month to stay the first of the month.
 */
class ScheduleGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private ScheduleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');

        TenantFixture::actingAsTenant($this->delta);

        $location = AssetLocation::create([
            'factory_id' => $this->dhaka->id, 'name' => 'Line 3', 'code' => 'DHK-L3',
        ]);

        $this->asset = app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => 'SEW-DHK-00412',
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->dhaka->id,
            'asset_location_id' => $location->id,
        ]);

        $status = app(ChangeAssetStatus::class);

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $s) {
            $this->asset = $status->handle($this->asset, $s);
        }

        $this->generator = app(ScheduleGenerator::class);
    }

    private function plan(array $overrides = [], array $rules = []): MaintenancePlan
    {
        $plan = MaintenancePlan::create(array_merge([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => '30-day service',
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'priority' => 'MEDIUM',
            'grace_period_minutes' => 2880,
            'lead_time_days' => 90,
            'non_working_day_policy' => 'NONE',
            'start_date' => '2026-01-01',
            'active' => true,
        ], $overrides));

        foreach ($rules === [] ? [['TIME', 30, 'DAY', null]] : $rules as $rule) {
            MaintenancePlanRule::create([
                'maintenance_plan_id' => $plan->id,
                'rule_type' => $rule[0],
                'operator' => 'EVERY',
                'value' => (string) $rule[1],
                'unit' => $rule[2],
                'meter_type_id' => $rule[3],
            ]);
        }

        return $plan->fresh(['rules']);
    }

    private function schedules(MaintenancePlan $plan)
    {
        return MaintenanceSchedule::where('maintenance_plan_id', $plan->id)
            ->orderBy('due_at')
            ->get();
    }

    public function test_a_rolling_plan_generates_one_occurrence_at_a_time(): void
    {
        $plan = $this->plan();

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));

        // The next due date is not knowable until this one is completed, so
        // generating a queue of them would be inventing dates.
        $this->assertCount(1, $this->schedules($plan));
    }

    public function test_a_rolling_plan_measures_the_next_interval_from_completion(): void
    {
        $plan = $this->plan();

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));
        $first = $this->schedules($plan)->first();

        // Serviced a week after it fell due.
        $completedAt = CarbonImmutable::parse('2026-03-08 10:00');
        app(CompleteSchedule::class)->handle($first, $completedAt);

        $next = $this->schedules($plan)->where('status', 'PLANNED')->first();

        $this->assertNotNull($next, 'Completing a rolling occurrence must generate its successor.');
        // 30 days from completion, not from the original due date.
        $this->assertSame('2026-04-07', $next->due_at->toDateString());
    }

    public function test_a_fixed_plan_keeps_the_calendar_grid(): void
    {
        $plan = $this->plan([
            'schedule_mode' => 'FIXED',
            'start_date' => '2026-01-01',
            'lead_time_days' => 120,
        ]);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-01-01 06:00'));

        $due = $this->schedules($plan)->pluck('due_at')->map->toDateString()->all();

        // Every 30 days from the anchor, regardless of completion.
        $this->assertContains('2026-01-01', $due);
        $this->assertContains('2026-01-31', $due);
        $this->assertContains('2026-03-02', $due);
        $this->assertGreaterThan(3, count($due));
    }

    public function test_a_fixed_plan_does_not_shift_when_a_service_runs_late(): void
    {
        $plan = $this->plan([
            'schedule_mode' => 'FIXED',
            'start_date' => '2026-01-01',
            'lead_time_days' => 120,
        ]);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-01-01 06:00'));
        $first = $this->schedules($plan)->first();

        app(CompleteSchedule::class)->handle($first, CarbonImmutable::parse('2026-01-20 10:00'));

        $remaining = $this->schedules($plan)
            ->where('status', 'PLANNED')
            ->pluck('due_at')->map->toDateString()->all();

        // The grid is untouched by a late completion. That is the whole point
        // of a fixed regime.
        $this->assertContains('2026-01-31', $remaining);
    }

    public function test_generation_is_idempotent(): void
    {
        $plan = $this->plan(['schedule_mode' => 'FIXED', 'lead_time_days' => 120]);
        $now = CarbonImmutable::parse('2026-01-01 06:00');

        $first = $this->generator->generateForPlan($plan, $now);
        $countAfterFirst = $this->schedules($plan)->count();

        // Re-running the job must not duplicate work. The unique index
        // enforces it rather than the job being careful.
        $second = $this->generator->generateForPlan($plan->fresh(['rules']), $now);

        $this->assertSame($countAfterFirst, $this->schedules($plan)->count());
        $this->assertGreaterThan(0, $first['created']);
        $this->assertSame(0, $second['created']);
    }

    public function test_an_open_occurrence_blocks_a_second_one(): void
    {
        $plan = $this->plan();

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));
        $this->generator->generateForPlan($plan->fresh(['rules']), CarbonImmutable::parse('2026-06-01 08:00'));

        // Two identical jobs for one machine would send a technician twice.
        $this->assertCount(1, $this->schedules($plan)->where('status', '!=', 'COMPLETED'));
    }

    public function test_an_inactive_plan_generates_nothing(): void
    {
        $plan = $this->plan(['active' => false]);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));

        $this->assertCount(0, $this->schedules($plan));
    }

    public function test_a_plan_past_its_end_date_generates_nothing(): void
    {
        $plan = $this->plan(['end_date' => '2026-02-01']);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-06-01 08:00'));

        $this->assertCount(0, $this->schedules($plan));
    }

    public function test_generation_does_not_backfill_missed_occurrences(): void
    {
        // Activated two years after the nominal start date.
        $plan = $this->plan(['start_date' => '2024-01-01']);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));

        $schedules = $this->schedules($plan);

        $this->assertCount(1, $schedules);
        // 24 months of history would be noise, not a maintenance backlog.
        $this->assertTrue($schedules->first()->due_at->greaterThanOrEqualTo(
            CarbonImmutable::parse('2026-03-01')->startOfDay(),
        ));
    }

    public function test_the_horizon_is_capped_by_the_company_setting(): void
    {
        // The plan asks for two years; the company allows 90 days.
        $plan = $this->plan([
            'schedule_mode' => 'FIXED',
            'lead_time_days' => 730,
            'start_date' => '2026-01-01',
        ]);

        $now = CarbonImmutable::parse('2026-01-01 06:00');
        $this->generator->generateForPlan($plan, $now);

        $latest = $this->schedules($plan)->last();

        $this->assertTrue(
            $latest->due_at->lessThanOrEqualTo($now->addDays(90)->endOfDay()),
            'Generation ran past the company horizon.',
        );
    }

    public function test_a_due_date_moves_off_a_non_working_day(): void
    {
        FactoryCalendar::create([
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5], // Friday
            'effective_from' => '2026-01-01',
        ]);

        Shift::create([
            'factory_id' => $this->dhaka->id,
            'name' => 'Day', 'code' => 'DAY',
            'start_time' => '08:00:00', 'end_time' => '22:00:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-01-01',
        ]);

        // 2026-08-21 is a Friday.
        $plan = $this->plan([
            'start_date' => '2026-08-21',
            'non_working_day_policy' => 'NEXT_WORKING_DAY',
        ]);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-08-21 06:00'));

        $due = $this->schedules($plan)->first()->due_at;

        // Without this, a PM landing on the weekly off day is reported overdue
        // on Saturday through nobody's fault.
        $this->assertSame('2026-08-22', $due->toDateString());
    }

    public function test_grace_is_applied_and_overdue_respects_it(): void
    {
        $plan = $this->plan(['grace_period_minutes' => 2880]); // two days

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));
        $schedule = $this->schedules($plan)->first();

        $this->assertSame(
            $schedule->due_at->addMinutes(2880)->toDateTimeString(),
            $schedule->grace_until->toDateTimeString(),
        );

        CarbonImmutable::setTestNow($schedule->due_at->addDay());
        $this->assertFalse($schedule->fresh()->isOverdue(), 'Within grace is not overdue.');

        CarbonImmutable::setTestNow($schedule->due_at->addDays(3));
        $this->assertTrue($schedule->fresh()->isOverdue());

        CarbonImmutable::setTestNow();
    }

    public function test_a_skipped_occurrence_still_advances_the_cycle(): void
    {
        $plan = $this->plan();

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));
        $first = $this->schedules($plan)->first();

        app(CompleteSchedule::class)->skip($first, 'Machine unavailable, order running');

        $this->assertSame('SKIPPED', $first->fresh()->status);
        // The machine is not serviced twice as often because one was missed.
        $this->assertCount(1, $this->schedules($plan)->where('status', '!=', 'SKIPPED'));
    }

    public function test_a_type_wide_plan_covers_every_asset_of_that_type(): void
    {
        $location = AssetLocation::where('code', 'DHK-L3')->firstOrFail();
        $status = app(ChangeAssetStatus::class);

        for ($i = 2; $i <= 4; $i++) {
            $extra = app(CreateAsset::class)->handle([
                'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
                'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
                'asset_code' => "SEW-DHK-0042{$i}",
                'name' => 'Juki DDL-9000C',
                'criticality' => 'MEDIUM',
                'current_factory_id' => $this->dhaka->id,
                'asset_location_id' => $location->id,
            ]);

            foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $s) {
                $extra = $status->handle($extra, $s);
            }
        }

        // One plan covering 400 sewing machines is how a factory actually
        // works, rather than 400 plans.
        $plan = $this->plan([
            'asset_id' => null,
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
        ]);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));

        $this->assertCount(4, $this->schedules($plan));
    }

    public function test_a_scrapped_asset_is_excluded_from_a_type_wide_plan(): void
    {
        $status = app(ChangeAssetStatus::class);
        $scrapped = $status->handle($this->asset, 'BREAKDOWN');
        $scrapped = $status->handle($scrapped, 'UNDER_REPAIR');
        $status->handle($scrapped, 'SCRAPPED', reason: 'Beyond economic repair');

        $plan = $this->plan([
            'asset_id' => null,
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
        ]);

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));

        // A scrapped machine does not need its next service.
        $this->assertCount(0, $this->schedules($plan));
    }

    public function test_the_plans_next_due_date_is_maintained(): void
    {
        $plan = $this->plan();

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-03-01 08:00'));

        $fresh = $plan->fresh();
        $this->assertNotNull($fresh->next_due_at);
        $this->assertNotNull($fresh->last_generated_at);
        $this->assertSame(
            $this->schedules($plan)->first()->due_at->toDateTimeString(),
            $fresh->next_due_at->toDateTimeString(),
        );
    }

    public function test_a_zero_interval_cannot_spin_forever(): void
    {
        // A misconfigured plan must fail safe rather than generating
        // occurrences at the same instant until the request times out.
        $plan = $this->plan(
            ['schedule_mode' => 'FIXED', 'lead_time_days' => 90],
            [['TIME', 0, 'DAY', null]],
        );

        $this->generator->generateForPlan($plan, CarbonImmutable::parse('2026-01-01 06:00'));

        $this->assertLessThanOrEqual(1, $this->schedules($plan)->count());
    }
}
