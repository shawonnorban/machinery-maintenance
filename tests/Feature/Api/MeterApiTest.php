<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
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
 * The Meter API (docs/03-API-Specification.md §10).
 *
 * `attach`/`reset` mirror the web `MeterController` (ADR-003); `index`/
 * `readings`/`store` were already built and tested — this suite adds
 * coverage for the two endpoints completed this round.
 */
class MeterApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private Asset $asset;

    private User $engineer;

    private User $admin;

    private string $engineerToken;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->admin = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'admin@delta.test');

        $this->engineerToken = app(IssueApiToken::class)->forUser($this->engineer, $this->delta->id, 'x')['plain'];
        $this->adminToken = app(IssueApiToken::class)->forUser($this->admin, $this->delta->id, 'x')['plain'];
    }

    private function asEngineer(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->engineerToken);

        return $this;
    }

    private function asAdmin(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken);

        return $this;
    }

    private function hours(): MeterType
    {
        return MeterType::whereNull('company_id')->where('code', 'RUNNING_HOURS')->firstOrFail();
    }

    public function test_a_meter_is_fitted_to_a_machine(): void
    {
        $response = $this->asEngineer()->postJson("/api/v1/assets/{$this->asset->id}/meters", [
            'meter_type_id' => $this->hours()->id,
            'initial_value' => '1200',
        ])->assertCreated();

        $this->assertSame('1200.0000', $response->json('data.current_value'));
        $this->assertDatabaseHas('asset_meters', ['asset_id' => $this->asset->id, 'current_value' => '1200.0000']);
    }

    public function test_the_same_meter_type_cannot_be_fitted_twice(): void
    {
        $this->asEngineer()->postJson("/api/v1/assets/{$this->asset->id}/meters", [
            'meter_type_id' => $this->hours()->id,
        ])->assertCreated();

        $this->asEngineer()->postJson("/api/v1/assets/{$this->asset->id}/meters", [
            'meter_type_id' => $this->hours()->id,
        ])->assertStatus(422);
    }

    public function test_fitting_a_meter_requires_the_manage_permission(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $token = app(IssueApiToken::class)->forUser($technician, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/assets/{$this->asset->id}/meters", ['meter_type_id' => $this->hours()->id])
            ->assertStatus(403);
    }

    public function test_a_meter_is_reset_with_a_reason(): void
    {
        $meter = AssetMeter::create([
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '9800',
            'status' => 'ACTIVE',
        ]);

        // Below FACTORY_ADMIN, this is refused: replacing a meter rewrites
        // what every past reading meant.
        $this->asEngineer()->postJson("/api/v1/meters/{$meter->id}/reset", [
            'new_value' => '0',
            'reason' => 'Meter unit physically replaced',
        ])->assertStatus(403);

        $response = $this->asAdmin()->postJson("/api/v1/meters/{$meter->id}/reset", [
            'new_value' => '0',
            'reason' => 'Meter unit physically replaced',
        ])->assertOk();

        $this->assertSame('0.0000', $response->json('data.value'));
        $this->assertSame('0.0000', $response->json('data.meter.current_value'));
        $this->assertDatabaseHas('meter_reset_events', ['meter_id' => $meter->id, 'old_value' => '9800.0000']);
    }

    public function test_reset_requires_a_reason(): void
    {
        $meter = AssetMeter::create([
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '9800',
            'status' => 'ACTIVE',
        ]);

        $this->asAdmin()->postJson("/api/v1/meters/{$meter->id}/reset", ['new_value' => '0'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_all_meters_lists_across_the_callers_reachable_factories(): void
    {
        $meter = AssetMeter::create([
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '4200',
            'status' => 'ACTIVE',
        ]);

        $response = $this->asEngineer()->getJson('/api/v1/meters')->assertOk();

        $response->assertJsonFragment([
            'id' => $meter->id,
            'current_value' => '4200.0000',
        ]);
        $this->assertSame($this->asset->id, $response->json('data.0.asset.id'));
        $this->assertSame($this->asset->asset_code, $response->json('data.0.asset.asset_code'));
    }

    public function test_all_meters_is_scoped_to_reachable_factories(): void
    {
        AssetMeter::create([
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '4200',
            'status' => 'ACTIVE',
        ]);

        // A different company's meter must never surface in this caller's
        // list, no matter how the query is built (API 2: a cross-tenant
        // record reads as absent, never as a permission error).
        $omega = TenantFixture::company('Omega Textiles', 'OMT');
        $omegaFactory = TenantFixture::factory($omega, 'Chattogram Unit 1', 'CTG');
        TenantFixture::actingAsTenant($omega);
        $omegaAsset = WorkOrderFixture::runningAsset($omega, $omegaFactory);
        AssetMeter::create([
            'asset_id' => $omegaAsset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '9999',
            'status' => 'ACTIVE',
        ]);
        TenantFixture::actingAsTenant($this->delta);

        $response = $this->asEngineer()->getJson('/api/v1/meters')->assertOk();

        $response->assertJsonMissing(['current_value' => '9999.0000']);
    }

    public function test_show_returns_one_meters_summary(): void
    {
        $meter = AssetMeter::create([
            'asset_id' => $this->asset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '4200',
            'status' => 'ACTIVE',
        ]);

        $response = $this->asEngineer()->getJson("/api/v1/meters/{$meter->id}")->assertOk();

        $response->assertJson([
            'data' => [
                'id' => $meter->id,
                'current_value' => '4200.0000',
                'asset' => ['id' => $this->asset->id, 'asset_code' => $this->asset->asset_code],
            ],
        ]);
    }

    public function test_show_404s_for_a_meter_outside_reachable_factories(): void
    {
        $omega = TenantFixture::company('Omega Textiles', 'OMT');
        $omegaFactory = TenantFixture::factory($omega, 'Chattogram Unit 1', 'CTG');
        TenantFixture::actingAsTenant($omega);
        $omegaAsset = WorkOrderFixture::runningAsset($omega, $omegaFactory);
        $omegaMeter = AssetMeter::create([
            'asset_id' => $omegaAsset->id,
            'meter_type_id' => $this->hours()->id,
            'current_value' => '9999',
            'status' => 'ACTIVE',
        ]);
        TenantFixture::actingAsTenant($this->delta);

        $this->asEngineer()->getJson("/api/v1/meters/{$omegaMeter->id}")->assertStatus(404);
    }
}
