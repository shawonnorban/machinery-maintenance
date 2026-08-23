<?php

declare(strict_types=1);

namespace Tests\Feature\Metering;

use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterReading;
use App\Modules\Metering\Models\MeterResetEvent;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Meter readings (SRS 11, ADR-013).
 *
 * The half of usage-based maintenance that had no way in. A plan can say
 * "service every 500 running hours", but until somebody records the hours it
 * can never come due — and until this screen existed, nothing in the product
 * could record them.
 */
class MeterReadingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $engineer;

    private User $technician;

    private User $factoryAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        // Replacing a meter rewrites what every past reading meant, so it sits
        // with the factory administrator rather than the engineer.
        $this->factoryAdmin = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'fa@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function hours(): MeterType
    {
        return MeterType::whereNull('company_id')->where('code', 'RUNNING_HOURS')->firstOrFail();
    }

    private function meter(string $initial = '0'): AssetMeter
    {
        $this->actingAs($this->engineer)->post('/app/assets/'.$this->asset->id.'/meters', [
            'meter_type_id' => $this->hours()->id,
            'initial_value' => $initial,
        ]);

        return AssetMeter::where('asset_id', $this->asset->id)->firstOrFail();
    }

    public function test_a_meter_can_be_fitted_to_a_machine(): void
    {
        $meter = $this->meter('1200');

        $this->assertSame($this->hours()->id, $meter->meter_type_id);
        $this->assertSame('1200.0000', $meter->current_value);
        $this->assertSame('ACTIVE', $meter->status);
    }

    public function test_the_same_kind_of_meter_cannot_be_fitted_twice(): void
    {
        $this->meter();

        $this->actingAs($this->engineer)
            ->from('/app/assets/'.$this->asset->id)
            ->post('/app/assets/'.$this->asset->id.'/meters', ['meter_type_id' => $this->hours()->id])
            ->assertSessionHasErrors('meter_type_id');

        // Two of the same kind would give every usage-based due date two
        // answers.
        $this->assertSame(1, AssetMeter::where('asset_id', $this->asset->id)->count());
    }

    public function test_a_technician_can_record_a_reading(): void
    {
        $meter = $this->meter('1200');

        $this->actingAs($this->technician)
            ->post('/app/meters/'.$meter->id.'/readings', ['value' => '1450'])
            ->assertRedirect();

        $this->assertSame('1450.0000', $meter->fresh()->current_value);
        $this->assertNotNull($meter->fresh()->last_reading_at);

        $reading = MeterReading::where('meter_id', $meter->id)->firstOrFail();

        // The consumption since the last reading, which is what a usage-based
        // interval is measured in.
        $this->assertSame('250.0000', (string) $reading->delta);
    }

    /**
     * The rule that keeps every hours-based due date from jumping backwards.
     */
    public function test_a_cumulative_meter_cannot_go_backwards(): void
    {
        $meter = $this->meter('1200');

        $this->actingAs($this->technician)
            ->from('/app/meters/'.$meter->id)
            ->post('/app/meters/'.$meter->id.'/readings', ['value' => '900'])
            ->assertSessionHasErrors('value');

        $this->assertSame('1200.0000', $meter->fresh()->current_value);
    }

    public function test_a_replaced_meter_is_recorded_as_its_own_event(): void
    {
        $meter = $this->meter('1200');

        $this->actingAs($this->factoryAdmin)
            ->post('/app/meters/'.$meter->id.'/reset', [
                'new_value' => '0',
                'reason' => 'Hour counter replaced',
            ])
            ->assertRedirect();

        $this->assertSame('0.0000', $meter->fresh()->current_value);

        // The drop has an explanation, so consumption reporting can bridge it
        // instead of reading it as 1200 hours of negative use.
        $event = MeterResetEvent::where('meter_id', $meter->id)->firstOrFail();

        $this->assertSame('1200.0000', (string) $event->old_value);
        $this->assertSame('Hour counter replaced', $event->reason);

        $baseline = MeterReading::where('meter_id', $meter->id)
            ->where('is_reset_baseline', true)
            ->firstOrFail();

        $this->assertNotNull($baseline);
    }

    /**
     * The whole point of the feature: a reading is what brings usage-based
     * work due.
     */
    public function test_a_reading_brings_a_usage_based_plan_due(): void
    {
        $meter = $this->meter('0');

        $plan = MaintenancePlan::create([
            'company_id' => $this->delta->id,
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'name' => 'Service every 500 hours',
            'trigger_type' => 'METER',
            'schedule_mode' => 'ROLLING',
            'start_date' => now()->toDateString(),
            'active' => true,
        ]);

        MaintenancePlanRule::create([
            'company_id' => $this->delta->id,
            'maintenance_plan_id' => $plan->id,
            'rule_type' => 'METER',
            'meter_type_id' => $this->hours()->id,
            'operator' => 'GTE',
            'value' => '500',
            'unit' => 'HOUR',
        ]);

        // Below the threshold: nothing comes due.
        $this->actingAs($this->technician)
            ->post('/app/meters/'.$meter->id.'/readings', ['value' => '300']);

        $this->assertSame(0, MaintenanceSchedule::where('asset_id', $this->asset->id)->count());

        // Past it: the job appears, and the person holding the clipboard is
        // told rather than finding out overnight.
        $this->actingAs($this->technician)
            ->post('/app/meters/'.$meter->id.'/readings', ['value' => '520'])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual(
            1,
            MaintenanceSchedule::where('asset_id', $this->asset->id)->count(),
        );
    }

    public function test_a_reading_cannot_be_taken_in_the_future(): void
    {
        $meter = $this->meter('100');

        $this->actingAs($this->technician)
            ->from('/app/meters/'.$meter->id)
            ->post('/app/meters/'.$meter->id.'/readings', [
                'value' => '200',
                'reading_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('reading_at');

        $this->assertSame('100.0000', $meter->fresh()->current_value);
    }

    public function test_a_technician_may_read_but_not_fit_or_reset(): void
    {
        $meter = $this->meter('100');

        $this->actingAs($this->technician)
            ->post('/app/assets/'.$this->asset->id.'/meters', ['meter_type_id' => $this->hours()->id])
            ->assertForbidden();

        $this->actingAs($this->technician)
            ->post('/app/meters/'.$meter->id.'/reset', ['new_value' => '0', 'reason' => 'Trying it on'])
            ->assertForbidden();

        $this->assertSame('100.0000', $meter->fresh()->current_value);
    }

    public function test_another_companys_meter_is_not_reachable(): void
    {
        $meter = $this->meter('100');

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::factory($other, 'Their Unit', 'BTU');
        TenantFixture::actingAsTenant($other);
        $theirs = TenantFixture::user($other, 'MAINTENANCE_ENGINEER', 'eng@btl.test');

        $this->flushSession();

        $this->actingAs($theirs)->get('/app/meters/'.$meter->id)->assertNotFound();
    }

    public function test_the_list_shows_a_meter_nobody_has_read(): void
    {
        $this->meter('100');

        $this->actingAs($this->engineer)
            ->get('/app/meters')
            ->assertOk()
            ->assertSee($this->asset->asset_code)
            // A meter nobody has touched is the one quietly making a
            // usage-based plan wrong.
            ->assertSee(__('metering.never_read'));
    }
}
