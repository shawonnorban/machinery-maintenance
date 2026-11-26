<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Company Management (AnnotechERP 01/02, 01/03, 01/05): the Brickland screens
 * over the organisation master data — codes issued when left blank, Brickland's
 * required fields, counted columns, and the in-use rule that sends a person to
 * "set it Inactive" instead of deleting.
 */
class CompanyStructureTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'fa@delta.test'); // master data is admin-only
    }

    public function test_a_building_left_without_a_code_is_issued_the_next_one(): void
    {
        $first = $this->postType('buildings', ['factory_id' => $this->factory->id, 'name' => 'Unit-1'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'BLD-001')
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->postType('buildings', ['factory_id' => $this->factory->id, 'name' => 'Unit-2', 'code' => 'BLD-007'])
            ->assertCreated();

        // Numbered past the highest one in use, not by counting rows.
        $this->postType('buildings', ['factory_id' => $this->factory->id, 'name' => 'Unit-3'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'BLD-008');

        $this->assertSame('Unit-1', Building::find($first->json('data.id'))->name);
    }

    public function test_bricklands_required_fields_are_required(): void
    {
        $this->postType('buildings', ['factory_id' => $this->factory->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postType('floors', ['name' => 'Sewing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('building_id');

        $this->postType('sections', ['name' => 'Sewing A'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('department_id');

        $department = $this->department();

        // Every line saved from Company Management carries a Line Type.
        $this->postType('production-lines', ['department_id' => $department->id, 'name' => 'Line 1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('line_type');

        $this->postType('production-lines', ['department_id' => $department->id, 'name' => 'Line 1', 'line_type' => 'KNITTING'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('line_type');

        $this->postType('production-lines', ['department_id' => $department->id, 'name' => 'Line 1', 'line_type' => 'SEWING'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LN-001');
    }

    public function test_the_list_counts_floors_sections_and_machines(): void
    {
        $building = Building::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Unit-1', 'code' => 'BLD-001',
            'number_of_floors' => 5,
        ]);
        foreach (['F1', 'F2'] as $code) {
            Floor::create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => $code, 'code' => $code]);
        }

        $this->getType('buildings')
            ->assertOk()
            ->assertJsonPath('data.0.number_of_floors', 5)
            ->assertJsonPath('data.0.floors_registered', 2)
            ->assertJsonPath('meta.schema.computed.0.name', 'floors_registered');

        $department = $this->department();
        $line = ProductionLine::create([
            'company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Line 1', 'code' => 'LN-001',
            'line_type' => 'SEWING',
        ]);
        $location = AssetLocation::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'production_line_id' => $line->id,
            'name' => 'Line 1', 'code' => 'L1',
        ]);
        foreach (['SEW-1', 'SEW-2', 'SEW-3'] as $code) {
            $machine = app(CreateAsset::class)->handle([
                'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
                'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
                'asset_code' => $code,
                'name' => 'Juki DDL-9000C',
                'criticality' => 'MEDIUM',
                'current_factory_id' => $this->factory->id,
                'asset_location_id' => $location->id,
            ]);
        }
        $machine->forceFill(['status' => 'UNDER_MAINTENANCE'])->save();

        $this->getType('production-lines')
            ->assertOk()
            ->assertJsonPath('data.0.machines', 3)
            ->assertJsonPath('data.0.machines_down', 1);
    }

    public function test_a_building_that_still_has_floors_cannot_be_deleted(): void
    {
        $empty = $this->postType('buildings', ['factory_id' => $this->factory->id, 'name' => 'Empty'])->json('data.id');
        $this->withToken($this->token())->deleteJson('/api/v1/master-data/buildings/'.$empty)->assertNoContent();

        $building = $this->postType('buildings', ['factory_id' => $this->factory->id, 'name' => 'Busy'])->json('data.id');
        $this->postType('floors', ['building_id' => $building, 'name' => 'Ground'])->assertCreated();

        $this->withToken($this->token())->deleteJson('/api/v1/master-data/buildings/'.$building)->assertStatus(409);

        // The way out is Inactive, not delete.
        $this->withToken($this->token())
            ->patchJson('/api/v1/master-data/buildings/'.$building, ['factory_id' => $this->factory->id, 'name' => 'Busy', 'status' => 'INACTIVE'])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');
    }

    public function test_designations_name_a_grade_and_a_grade_in_use_stays(): void
    {
        $grade = $this->postType('grades', ['name' => 'Grade 7', 'sort_order' => 7])
            ->assertCreated()
            ->assertJsonPath('data.code', 'GRD-001')
            ->json('data.id');

        $this->postType('designations', [
            'name' => 'Sewing Operator', 'employee_category' => 'WORKER', 'grade_id' => $grade,
            'department_id' => $this->department()->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'DSG-001')
            ->assertJsonPath('data.employee_category', 'WORKER');

        $this->postType('designations', ['name' => 'Odd', 'employee_category' => 'INTERN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_category');

        $this->withToken($this->token())->deleteJson('/api/v1/master-data/grades/'.$grade)->assertStatus(409);
    }

    public function test_the_schema_carries_readable_option_labels_and_the_auto_code_flag(): void
    {
        $schema = $this->getType('production-lines')->assertOk()->json('meta.schema.fields');
        $byName = collect($schema)->keyBy('name');

        $this->assertSame('Sewing', $byName['line_type']['option_labels']['SEWING']);
        $this->assertTrue($byName['code']['auto']);
        $this->assertFalse($byName['code']['required']);
        $this->assertSame('ACTIVE', $byName['status']['default']);
    }

    public function test_company_management_is_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->tokenFor($technician))
            ->postJson('/api/v1/master-data/designations', ['name' => 'X'])
            ->assertForbidden();
    }

    private function department(): Department
    {
        return Department::firstOrCreate(
            ['company_id' => $this->delta->id, 'code' => 'SEW'],
            ['factory_id' => $this->factory->id, 'name' => 'Sewing'],
        );
    }

    private function postType(string $type, array $data)
    {
        return $this->withToken($this->token())->postJson('/api/v1/master-data/'.$type, $data);
    }

    private function getType(string $type)
    {
        return $this->withToken($this->token())->getJson('/api/v1/master-data/'.$type);
    }

    private function token(): string
    {
        return $this->tokenFor($this->manager);
    }

    private function tokenFor(User $user): string
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $plain;
    }
}
