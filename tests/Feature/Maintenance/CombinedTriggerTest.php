<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Maintenance\Services\ScheduleGenerator;
use App\Modules\Metering\Actions\RecordMeterReading;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * "Every 30 days OR every 500 running hours, whichever occurs first"
 * (SRS 10, ADR-012).
 *
 * This is the example the specification leads with, and the reason meters and
 * the scheduler had to land together.
 */
class CombinedTriggerTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private MeterType $runningHours;

    private AssetMeter $meter;

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

        $this->runningHours = MeterType::create([
            'name' => 'Running hours', 'code' => 'RUNNING_HOURS', 'unit' => 'HOUR',
        ]);

        $this->meter = AssetMeter::create([
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->runningHours->id,
            'current_value' => '0',
        ]);
    }

    private function combinedPlan(string $logic = 'OR'): MaintenancePlan
    {
        $plan = MaintenancePlan::create([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Every 30 days or 500 running hours',
            'trigger_type' => 'COMBINED',
            'schedule_mode' => 'ROLLING',
            'rule_logic' => $logic,
            'grace_period_minutes' => 0,
            'lead_time_days' => 90,
            'non_working_day_policy' => 'NONE',
            // Tomorrow, so the calendar half has not arrived yet and the meter half
            // is the only thing that can bring the occurrence due.
            'start_date' => CarbonImmutable::now()->addDay()->toDateString(),
            'active' => true,
        ]);

        MaintenancePlanRule::create([
            'maintenance_plan_id' => $plan->id, 'rule_type' => 'TIME',
            'operator' => 'EVERY', 'value' => '30', 'unit' => 'DAY',
        ]);

        MaintenancePlanRule::create([
            'maintenance_plan_id' => $plan->id, 'rule_type' => 'METER',
            'operator' => 'EVERY', 'value' => '500', 'unit' => 'HOUR',
            'meter_type_id' => $this->runningHours->id,
        ]);

        return $plan->fresh(['rules']);
    }

    private function scheduleFor(MaintenancePlan $plan): ?MaintenanceSchedule
    {
        return MaintenanceSchedule::where('maintenance_plan_id', $plan->id)->orderBy('due_at')->first();
    }

    public function test_a_combined_occurrence_carries_both_a_date_and_a_meter_target(): void
    {
        $plan = $this->combinedPlan();

        app(ScheduleGenerator::class)->generateForPlan($plan);

        $schedule = $this->scheduleFor($plan);

        // Populating both is what makes "whichever occurs first" possible.
        $this->assertNotNull($schedule->due_at);
        $this->assertSame('500.0000', $schedule->due_meter);
        $this->assertSame($this->runningHours->id, $schedule->due_meter_type_id);
        $this->assertSame('PLANNED', $schedule->status);
    }

    public function test_reaching_the_meter_threshold_brings_it_due_before_the_date(): void
    {
        $plan = $this->combinedPlan('OR');
        app(ScheduleGenerator::class)->generateForPlan($plan);

        $schedule = $this->scheduleFor($plan);
        $this->assertTrue($schedule->due_at->isFuture());

        // The machine runs hard and hits 500 hours in eight days.
        $result = app(RecordMeterReading::class)->handle($this->meter, '512');

        $this->assertCount(1, $result['triggered']);

        $fresh = $schedule->fresh();
        $this->assertSame('DUE', $fresh->status);
        // Recorded so the history can explain why it appeared early.
        $this->assertSame('METER', $fresh->triggered_by);
    }

    public function test_staying_under_the_threshold_leaves_it_planned(): void
    {
        $plan = $this->combinedPlan('OR');
        app(ScheduleGenerator::class)->generateForPlan($plan);

        $result = app(RecordMeterReading::class)->handle($this->meter, '499.9');

        $this->assertCount(0, $result['triggered']);
        $this->assertSame('PLANNED', $this->scheduleFor($plan)->status);
    }

    public function test_with_and_the_meter_alone_is_not_enough(): void
    {
        $plan = $this->combinedPlan('AND');
        app(ScheduleGenerator::class)->generateForPlan($plan);

        $schedule = $this->scheduleFor($plan);
        $this->assertTrue($schedule->due_at->isFuture());

        // Both conditions are required, so passing 500 hours before the date
        // arrives changes nothing (ADR-012).
        $result = app(RecordMeterReading::class)->handle($this->meter, '900');

        $this->assertCount(0, $result['triggered']);
        $this->assertSame('PLANNED', $schedule->fresh()->status);
    }

    public function test_a_meter_only_plan_raises_its_occurrence_when_the_threshold_is_crossed(): void
    {
        $plan = MaintenancePlan::create([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Every 500 running hours',
            'trigger_type' => 'METER',
            'schedule_mode' => 'ROLLING',
            'grace_period_minutes' => 0,
            'lead_time_days' => 90,
            'non_working_day_policy' => 'NONE',
            // Tomorrow, so the calendar half has not arrived yet and the meter half
            // is the only thing that can bring the occurrence due.
            'start_date' => CarbonImmutable::now()->addDay()->toDateString(),
            'active' => true,
        ]);

        MaintenancePlanRule::create([
            'maintenance_plan_id' => $plan->id, 'rule_type' => 'METER',
            'operator' => 'EVERY', 'value' => '500', 'unit' => 'HOUR',
            'meter_type_id' => $this->runningHours->id,
        ]);

        // A meter-only plan has no calendar date, so the generator makes
        // nothing; the occurrence is born from a reading.
        app(ScheduleGenerator::class)->generateForPlan($plan->fresh(['rules']));
        $this->assertNull($this->scheduleFor($plan));

        app(RecordMeterReading::class)->handle($this->meter, '300', CarbonImmutable::now()->subHour());
        $this->assertNull($this->scheduleFor($plan));

        $result = app(RecordMeterReading::class)->handle($this->meter, '505');

        $this->assertCount(1, $result['triggered']);
        $this->assertSame('DUE', $this->scheduleFor($plan)->status);
        $this->assertSame('METER', $this->scheduleFor($plan)->triggered_by);
    }

    public function test_a_cumulative_meter_cannot_go_backwards(): void
    {
        app(RecordMeterReading::class)->handle($this->meter, '1000', CarbonImmutable::now()->subHour());

        // Accepting a lower value would drag every hours-based due date
        // backwards with it.
        $this->expectException(ValidationException::class);
        app(RecordMeterReading::class)->handle($this->meter->fresh(), '900');
    }

    public function test_a_meter_replacement_is_an_audited_reset(): void
    {
        app(RecordMeterReading::class)->handle($this->meter, '4187.5', CarbonImmutable::now()->subHour());

        $reading = app(RecordMeterReading::class)->reset(
            $this->meter->fresh(),
            '0',
            'Counter replaced under warranty',
        );

        $this->assertTrue($reading->is_reset_baseline);
        $this->assertSame('0.0000', $this->meter->fresh()->current_value);

        $this->assertDatabaseHas('meter_reset_events', [
            'meter_id' => $this->meter->id,
            'old_value' => '4187.5000',
            'new_value' => '0.0000',
        ]);

        // After a reset the meter may climb again from the new baseline.
        $after = app(RecordMeterReading::class)->handle($this->meter->fresh(), '12');
        $this->assertSame('12.0000', $after['reading']->value);
    }

    public function test_a_reading_records_its_delta(): void
    {
        app(RecordMeterReading::class)->handle($this->meter, '100', CarbonImmutable::now()->subHour());
        $second = app(RecordMeterReading::class)->handle($this->meter->fresh(), '175.5');

        $this->assertSame('100.0000', $second['reading']->previous_value);
        $this->assertSame('75.5000', $second['reading']->delta);
    }

    public function test_a_future_reading_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(RecordMeterReading::class)->handle(
            $this->meter,
            '100',
            CarbonImmutable::now()->addDay(),
        );
    }

    public function test_a_backdated_reading_does_not_rewind_the_current_value(): void
    {
        app(RecordMeterReading::class)->handle($this->meter, '500', CarbonImmutable::now());

        // A reading entered late is history, not the current state.
        app(RecordMeterReading::class)->handle(
            $this->meter->fresh(),
            '520',
            CarbonImmutable::now()->subDays(3),
        );

        $this->assertSame('500.0000', $this->meter->fresh()->current_value);
        $this->assertSame(2, $this->meter->readings()->count());
    }
}
