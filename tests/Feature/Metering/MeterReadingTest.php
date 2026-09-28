<?php

declare(strict_types=1);

namespace Tests\Feature\Metering;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Meter readings (SRS 11, ADR-013) — the two cases here with no coverage
 * anywhere else once `/app/meters` was decommissioned (Phase D/F, docs/12-
 * Stack-Migration-Implementation-Plan.md). Everything else this file used
 * to test (fitting a meter, the same-kind-twice guard, recording a reading,
 * the cumulative-can't-go-backwards rule, a replacement's own baseline
 * reading, the technician-reads-but-doesn't-fit-or-reset boundary, both
 * `GET` redirect stubs) is already proven at `Api/MeterApiTest.php` and
 * `Api/ApiResourceTest.php`.
 */
class MeterReadingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function hours(): MeterType
    {
        return MeterType::whereNull('company_id')->where('code', 'RUNNING_HOURS')->firstOrFail();
    }

    private function meter(string $initial = '0'): AssetMeter
    {
        return AssetMeter::create([
            'company_id' => $this->delta->id,
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => $initial,
        ]);
    }

    private function api(): self
    {
        $token = app(IssueApiToken::class)->forUser($this->technician, $this->delta->id, 'Test')['plain'];
        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
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
        $this->api()->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '300']);

        $this->assertSame(0, MaintenanceSchedule::where('asset_id', $this->asset->id)->count());

        // Past it: the job appears, and the person holding the clipboard is
        // told rather than finding out overnight.
        $this->api()
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '520'])
            ->assertCreated();

        $this->assertGreaterThanOrEqual(
            1,
            MaintenanceSchedule::where('asset_id', $this->asset->id)->count(),
        );
    }

    public function test_a_reading_cannot_be_taken_in_the_future(): void
    {
        $meter = $this->meter('100');

        $this->api()
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', [
                'value' => '200',
                'reading_at' => now()->addDays(2)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reading_at');

        $this->assertSame('100.0000', $meter->fresh()->current_value);
    }
}
