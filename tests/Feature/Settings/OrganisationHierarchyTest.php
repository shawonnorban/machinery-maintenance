<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The floor plan a location hangs off (ADR-052).
 *
 * Buildings, departments and lines were selectable on the location form and
 * creatable nowhere, so every dropdown was empty unless a spreadsheet had been
 * imported. They run through the same registry as the rest of the reference
 * data, with two differences that matter: they are the company's own — nobody
 * else's factory has this company's buildings in it — and they carry no active
 * flag, so the way out of one is to remove it.
 */
class OrganisationHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createRow(string $type, array $payload): TestResponse
    {
        return $this->actingAs($this->owner)
            ->from('/app/settings/master-data/'.$type)
            ->post('/app/settings/master-data/'.$type, $payload);
    }

    public function test_the_whole_floor_plan_can_be_built_from_the_screens(): void
    {
        $this->createRow('buildings', [
            'factory_id' => $this->dhaka->id, 'name' => 'Building A', 'code' => 'bld-a',
        ])->assertRedirect();

        $building = Building::where('code', 'BLD-A')->firstOrFail();

        $this->createRow('floors', [
            'building_id' => $building->id, 'name' => 'Second floor', 'code' => 'F2',
        ])->assertRedirect();

        $this->createRow('departments', [
            'factory_id' => $this->dhaka->id, 'name' => 'Knitting', 'code' => 'KNIT',
        ])->assertRedirect();

        $department = Department::where('code', 'KNIT')->firstOrFail();

        $this->createRow('sections', [
            'department_id' => $department->id, 'name' => 'Single jersey', 'code' => 'SJ',
        ])->assertRedirect();

        $this->createRow('production-lines', [
            'department_id' => $department->id, 'name' => 'Line 3', 'code' => 'L3',
        ])->assertRedirect();

        $line = ProductionLine::where('code', 'L3')->firstOrFail();

        $this->createRow('workstations', [
            'production_line_id' => $line->id, 'name' => 'Station 1', 'code' => 'L3-S1',
        ])->assertRedirect();

        // Every level belongs to the company that created it, and the codes are
        // normalised the same way as everywhere else.
        $this->assertSame($this->delta->id, $building->company_id);
        $this->assertSame($this->dhaka->id, $department->factory_id);
        $this->assertSame($department->id, $line->department_id);
    }

    public function test_a_line_can_be_created_without_a_section(): void
    {
        $department = Department::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Sewing',
            'code' => 'SEW',
        ]);

        // A mill that does not divide its departments into sections still has
        // lines, so the section is optional rather than a level everyone has to
        // invent.
        $this->createRow('production-lines', [
            'department_id' => $department->id, 'name' => 'Line 1', 'code' => 'L1',
        ])->assertRedirect();

        $this->assertNull(ProductionLine::where('code', 'L1')->firstOrFail()->section_id);
    }

    public function test_a_building_cannot_be_hung_off_another_companys_factory(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        TenantFixture::actingAsTenant($this->delta);

        $this->createRow('buildings', [
            'factory_id' => $theirFactory->id, 'name' => 'Borrowed', 'code' => 'BORROWED',
        ])->assertSessionHasErrors('factory_id');

        $this->assertSame(0, Building::withoutGlobalScopes()->where('code', 'BORROWED')->count());
    }

    public function test_a_factory_scoped_user_cannot_reach_another_factory(): void
    {
        $gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        $scoped = TenantFixture::user(
            $this->delta, 'FACTORY_MANAGER', 'dhaka@delta.test', factoryId: $this->dhaka->id,
        );

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($scoped)
            ->from('/app/settings/master-data/buildings')
            ->post('/app/settings/master-data/buildings', [
                'factory_id' => $gazipur->id, 'name' => 'Out of reach', 'code' => 'OOR',
            ])
            ->assertSessionHasErrors('factory_id');

        $this->assertSame(0, Building::where('code', 'OOR')->count());
    }

    public function test_a_level_a_location_stands_in_cannot_be_removed(): void
    {
        $department = Department::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Dyeing',
            'code' => 'DYE',
        ]);

        $line = ProductionLine::create([
            'company_id' => $this->delta->id,
            'department_id' => $department->id,
            'name' => 'Dye line 1',
            'code' => 'DL1',
        ]);

        AssetLocation::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'production_line_id' => $line->id,
            'name' => 'Soft flow 1',
            'code' => 'DHK-DL1-01',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->owner)
            ->from('/app/settings/master-data/production-lines')
            ->delete('/app/settings/master-data/production-lines/'.$line->id)
            ->assertSessionHasErrors('code');

        $this->assertNotNull(ProductionLine::find($line->id));

        // And the department above it is held by the line, so the refusal
        // cascades in the way somebody would expect.
        $this->actingAs($this->owner)
            ->from('/app/settings/master-data/departments')
            ->delete('/app/settings/master-data/departments/'.$department->id)
            ->assertSessionHasErrors('code');
    }

    public function test_an_unused_level_can_be_removed(): void
    {
        $building = Building::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Typed twice',
            'code' => 'TYPO',
        ]);

        $this->actingAs($this->owner)
            ->delete('/app/settings/master-data/buildings/'.$building->id)
            ->assertRedirect();

        $this->assertNull(Building::find($building->id));
    }

    /**
     * These tables have no active column, so the screen must not offer a
     * button that would write one.
     */
    public function test_the_organisation_screens_offer_no_activate_button(): void
    {
        Building::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Building A',
            'code' => 'BLD-A',
        ]);

        $this->actingAs($this->owner)
            ->get('/app/settings/master-data/buildings')
            ->assertOk()
            ->assertSee('Building A')
            ->assertDontSee(__('masterdata.deactivate'))
            // Nor an owner column: there are no platform rows here to tell
            // a company's own apart from.
            ->assertDontSee(__('masterdata.platform'));
    }

    public function test_the_list_shows_the_parent_by_name_rather_than_by_id(): void
    {
        Building::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Building A',
            'code' => 'BLD-A',
        ]);

        $this->actingAs($this->owner)
            ->get('/app/settings/master-data/buildings')
            ->assertOk()
            ->assertSee('Dhaka Unit 1');
    }
}
