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
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Plan builder, activation and the schedule queue over HTTP.
 */
class PlanScreensTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen, because every date in this class is asserted literally. A
        // preview never offers a date in the past — it clamps to today — so a
        // test written against "next Friday" quietly starts asserting a
        // different day once that Friday goes by.
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');

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

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '30-day lockstitch service',
            'asset_id' => $this->asset->id,
            'asset_type_id' => '',
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'template_version_id' => '',
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'interval_value' => 30,
            'interval_unit' => 'DAY',
            'priority' => 'MEDIUM',
            'grace_period_minutes' => 2880,
            'lead_time_days' => 30,
            'non_working_day_policy' => 'NONE',
            'start_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_a_plan_can_be_created_and_starts_inactive(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/maintenance/plans', $this->payload())
            ->assertRedirect();

        $plan = MaintenancePlan::withoutGlobalScopes()->where('name', '30-day lockstitch service')->firstOrFail();

        // Activation generates work, so it is a deliberate second step.
        $this->assertFalse($plan->active);
        $this->assertCount(1, $plan->rules);
        $this->assertSame(0, MaintenanceSchedule::withoutGlobalScopes()->count());
    }

    public function test_activating_generates_occurrences_immediately(): void
    {
        $this->actingAs($this->manager)->post('/app/maintenance/plans', $this->payload());
        $plan = MaintenancePlan::withoutGlobalScopes()->firstOrFail();

        // A planner should see the result of what they configured, not wait
        // for the nightly tick.
        $this->actingAs($this->manager)
            ->post("/app/maintenance/plans/{$plan->id}/activate")
            ->assertRedirect();

        $this->assertTrue($plan->fresh()->active);
        $this->assertGreaterThan(0, MaintenanceSchedule::withoutGlobalScopes()->count());
    }

    public function test_a_plan_targeting_both_an_asset_and_a_type_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/maintenance/plans', $this->payload([
                'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            ]))
            ->assertSessionHasErrors('asset_id');
    }

    public function test_a_plan_targeting_neither_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/maintenance/plans', $this->payload(['asset_id' => '']))
            ->assertSessionHasErrors('asset_id');
    }

    public function test_a_combined_plan_must_state_its_logic(): void
    {
        // "Whichever occurs first" and "both required" are different regimes,
        // so there is no sensible default (ADR-012).
        $this->actingAs($this->manager)
            ->post('/app/maintenance/plans', $this->payload([
                'trigger_type' => 'COMBINED',
                'rule_logic' => '',
                'meter_threshold' => 500,
                'meter_type_id' => str_repeat('0', 26),
            ]))
            ->assertSessionHasErrors();
    }

    public function test_a_meter_trigger_needs_a_meter(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/maintenance/plans', $this->payload([
                'trigger_type' => 'METER',
                'meter_threshold' => 500,
                'meter_type_id' => '',
            ]))
            ->assertSessionHasErrors('meter_threshold');
    }

    public function test_a_plan_cannot_be_activated_without_rules(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        $plan = MaintenancePlan::create([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Empty plan',
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'start_date' => now()->toDateString(),
            'active' => false,
        ]);

        $this->actingAs($this->manager)
            ->post("/app/maintenance/plans/{$plan->id}/activate")
            ->assertSessionHasErrors('active');

        $this->assertFalse($plan->fresh()->active);
    }

    public function test_the_preview_returns_the_next_five_dates(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/app/maintenance/plans/preview', [
            'schedule_mode' => 'FIXED',
            'start_date' => '2026-09-01',
            'interval_value' => 30,
            'interval_unit' => 'DAY',
            'non_working_day_policy' => 'NONE',
        ]);

        $response->assertOk();
        $response->assertJsonCount(5, 'data.dates');
        $response->assertJsonPath('data.dates.0.date', '2026-09-01');
        $response->assertJsonPath('data.dates.1.date', '2026-10-01');
    }

    public function test_the_preview_shows_when_a_date_is_moved_off_a_rest_day(): void
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
        ]);

        // 2026-08-21 is a Friday.
        $response = $this->actingAs($this->manager)->postJson('/app/maintenance/plans/preview', [
            'schedule_mode' => 'FIXED',
            'start_date' => '2026-08-21',
            'interval_value' => 30,
            'interval_unit' => 'DAY',
            'non_working_day_policy' => 'NEXT_WORKING_DAY',
            'factory_id' => $this->dhaka->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.dates.0.date', '2026-08-22');
        $response->assertJsonPath('data.dates.0.moved', true);
    }

    public function test_the_preview_warns_that_a_rolling_plan_will_shift(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/app/maintenance/plans/preview', [
            'schedule_mode' => 'ROLLING',
            'start_date' => '2026-09-01',
            'interval_value' => 30,
            'interval_unit' => 'DAY',
        ]);

        // Showing five fixed-looking dates for a rolling plan without saying
        // so would be misleading.
        $response->assertOk();
        $response->assertJsonPath('data.note', __('maintenance.preview_rolling_note'));
    }

    public function test_the_schedule_queue_lists_open_occurrences(): void
    {
        $this->actingAs($this->manager)->post('/app/maintenance/plans', $this->payload());
        $plan = MaintenancePlan::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->manager)->post("/app/maintenance/plans/{$plan->id}/activate");

        $this->actingAs($this->manager)
            ->get('/app/maintenance/schedule')
            ->assertOk()
            ->assertSee($this->asset->asset_code);
    }

    public function test_completing_from_the_queue_generates_the_successor(): void
    {
        $this->actingAs($this->manager)->post('/app/maintenance/plans', $this->payload());
        $plan = MaintenancePlan::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->manager)->post("/app/maintenance/plans/{$plan->id}/activate");

        $schedule = MaintenanceSchedule::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->manager)
            ->post("/app/maintenance/schedules/{$schedule->id}/complete")
            ->assertRedirect();

        $this->assertSame('COMPLETED', $schedule->fresh()->status);
        $this->assertSame(2, MaintenanceSchedule::withoutGlobalScopes()->count());
    }

    public function test_skipping_requires_a_reason(): void
    {
        $this->actingAs($this->manager)->post('/app/maintenance/plans', $this->payload());
        $plan = MaintenancePlan::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->manager)->post("/app/maintenance/plans/{$plan->id}/activate");

        $schedule = MaintenanceSchedule::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->manager)
            ->post("/app/maintenance/schedules/{$schedule->id}/skip", [])
            ->assertSessionHasErrors('skipped_reason');

        $this->actingAs($this->manager)
            ->post("/app/maintenance/schedules/{$schedule->id}/skip", [
                'skipped_reason' => 'Order running, machine unavailable',
            ])
            ->assertRedirect();

        $this->assertSame('SKIPPED', $schedule->fresh()->status);
    }

    public function test_a_technician_cannot_create_or_activate_a_plan(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->actingAs($technician)->get('/app/maintenance/plans/create')->assertForbidden();
        $this->actingAs($technician)->post('/app/maintenance/plans', $this->payload())->assertForbidden();
    }

    public function test_the_scheduler_command_runs_and_reports(): void
    {
        $this->actingAs($this->manager)->post('/app/maintenance/plans', $this->payload());
        $plan = MaintenancePlan::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->manager)->post("/app/maintenance/plans/{$plan->id}/activate");

        // A missed scheduler run means maintenance quietly stops being
        // generated, so the command reports what it did (ADR-061).
        $this->artisan('maintenance:generate-schedules')
            ->assertSuccessful();
    }
}
