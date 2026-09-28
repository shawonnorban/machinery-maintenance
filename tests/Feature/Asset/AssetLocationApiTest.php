<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Modules\Tenancy\Models\Workstation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Where machines live, over the API (API 5, ADR-052).
 */
class AssetLocationApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $manager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_a_location_can_be_created_updated_and_closed(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 3',
                'code' => 'DHK-L3',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'DHK-L3')
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertStringContainsString('Line 3', $created->json('data.full_path'));

        $locationId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/locations/{$locationId}", [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 3 (Sewing)',
                'code' => 'DHK-L3',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Line 3 (Sewing)');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/locations/{$locationId}/active", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');
    }

    /** So an edit form can preselect these without a second round trip — and so re-saving without touching them doesn't silently clear the association. */
    public function test_the_detail_carries_the_underlying_structure_ids(): void
    {
        $building = Building::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id, 'name' => 'Building A', 'code' => 'A',
        ]);
        $department = Department::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id, 'name' => 'Sewing', 'code' => 'SEW',
        ]);

        $location = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 3',
                'code' => 'DHK-L3',
                'building_id' => $building->id,
                'department_id' => $department->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.building_id', $building->id)
            ->assertJsonPath('data.department_id', $department->id);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/locations/'.$location->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.building_id', $building->id)
            ->assertJsonPath('data.department_id', $department->id);
    }

    /**
     * `floor_id`/`section_id`/`workstation_id` were accepted end-to-end by
     * `SaveAssetLocation` (it already validated each against its own
     * model) but silently dropped by the controller's own validation
     * rules and left out of the response — so a location could never
     * actually be linked to a floor or section through this endpoint at
     * all, which is exactly why neither table had a single row in it.
     */
    public function test_a_location_can_be_linked_to_a_floor_section_and_workstation(): void
    {
        $building = Building::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id, 'name' => 'Building A', 'code' => 'A',
        ]);
        $floor = Floor::create([
            'company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Ground Floor', 'code' => 'A-GF',
        ]);
        $department = Department::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id, 'name' => 'Sewing', 'code' => 'SEW',
        ]);
        $section = Section::create([
            'company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Sewing Line 1', 'code' => 'SEW-L1',
        ]);
        $line = ProductionLine::create([
            'company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Line 1', 'code' => 'L1',
        ]);
        $workstation = Workstation::create([
            'company_id' => $this->delta->id, 'production_line_id' => $line->id, 'name' => 'Station 1', 'code' => 'L1-S1',
        ]);

        $location = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 1 workstation',
                'code' => 'DHK-L1-S1',
                'floor_id' => $floor->id,
                'section_id' => $section->id,
                'workstation_id' => $workstation->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.floor_id', $floor->id)
            ->assertJsonPath('data.section_id', $section->id)
            ->assertJsonPath('data.workstation_id', $workstation->id);

        $this->assertStringContainsString('Ground Floor', $location->json('data.full_path'));
        $this->assertStringContainsString('Sewing Line 1', $location->json('data.full_path'));

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/locations/'.$location->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.floor_id', $floor->id)
            ->assertJsonPath('data.section_id', $section->id)
            ->assertJsonPath('data.workstation_id', $workstation->id);
    }

    public function test_locations_can_be_listed_by_factory(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Line 3',
                'code' => 'DHK-L3',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/locations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'DHK-L3');
    }

    public function test_a_location_naming_a_machine_cannot_be_deleted(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/locations/{$asset->asset_location_id}")
            ->assertStatus(409);

        $this->assertNotNull(AssetLocation::find($asset->asset_location_id));
    }

    public function test_an_unused_location_can_be_deleted(): void
    {
        $location = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/locations', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Spare Room',
                'code' => 'DHK-SPARE',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/locations/{$location}")
            ->assertNoContent();

        $this->assertNull(AssetLocation::find($location));
    }

    public function test_a_location_cannot_claim_a_factory_outside_the_callers_reach(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $narayanganj = TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/locations', [
                'factory_id' => $narayanganj->id,
                'name' => 'Line 1',
                'code' => 'NGJ-L1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('factory_id');
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_configure(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/locations')
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/locations', [
                'factory_id' => $this->dhaka->id, 'name' => 'X', 'code' => 'X',
            ])
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
