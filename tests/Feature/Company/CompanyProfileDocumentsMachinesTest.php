<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Department;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Company Management's own screens (AnnotechERP 01/01, 01/02, 01/04): the
 * company profile, its documents with scanned copies, the factory columns,
 * and the Brickland view of machinery maintenance's machine register.
 */
class CompanyProfileDocumentsMachinesTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_the_profile_is_edited_but_its_code_is_not(): void
    {
        $this->api()->getJson('/api/v1/company/profile')
            ->assertOk()
            ->assertJsonPath('data.code', 'DAL');

        $this->api()->putJson('/api/v1/company/profile', [
            'name' => 'Delta Apparels Ltd', 'code' => 'HACK', 'tin' => '123456789012', 'bin' => '000123456-0101',
            'trade_license_no' => 'TRAD/DNCC/1234', 'registration_date' => '2015-03-01', 'email' => 'info@delta.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.code', 'DAL')
            ->assertJsonPath('data.tin', '123456789012')
            ->assertJsonPath('data.registration_date', '2015-03-01');

        $this->api()->putJson('/api/v1/company/profile', ['name' => '', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_a_document_needs_a_type_and_title_and_an_expiry_after_issue(): void
    {
        $this->api()->postJson('/api/v1/company/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type', 'title']);

        $this->api()->postJson('/api/v1/company/documents', [
            'document_type' => 'TRADE_LICENSE', 'title' => 'Trade licence', 'issue_date' => '2026-07-01', 'expiry_date' => '2026-01-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expiry_date');

        $id = $this->api()->postJson('/api/v1/company/documents', [
            'document_type' => 'TRADE_LICENSE', 'title' => 'Trade licence', 'document_no' => 'TL-2026',
            'issue_date' => '2026-07-01', 'expiry_date' => '2027-06-30', 'issued_by' => 'DNCC',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->json('data.id');

        Storage::fake('local');
        $this->api()->post('/api/v1/company/documents/'.$id.'/files', [
            'file' => UploadedFile::fake()->create('licence.pdf', 120, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.original_name', 'licence.pdf');

        $this->api()->getJson('/api/v1/company/documents')
            ->assertOk()
            ->assertJsonPath('data.0.files_count', 1);
    }

    public function test_the_factory_carries_bricklands_columns(): void
    {
        $this->api()->patchJson('/api/v1/factories/'.$this->factory->id, [
            'name' => 'Dhaka Unit 1', 'timezone' => 'Asia/Dhaka', 'contact' => '+8801700000000',
            'established_date' => '2010-01-15', 'area_sqft' => '250000', 'remarks' => 'Main composite unit',
        ])
            ->assertOk()
            ->assertJsonPath('data.contact', '+8801700000000')
            ->assertJsonPath('data.established_date', '2010-01-15')
            ->assertJsonPath('data.remarks', 'Main composite unit');

        // An older client that only knows name/timezone does not wipe them.
        $this->api()->patchJson('/api/v1/factories/'.$this->factory->id, ['name' => 'Dhaka Unit 1', 'timezone' => 'Asia/Dhaka'])
            ->assertOk()
            ->assertJsonPath('data.contact', '+8801700000000');
    }

    public function test_the_machine_register_maps_statuses_and_keeps_capacity(): void
    {
        $department = Department::create(['company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Sewing', 'code' => 'SEW']);
        $line = ProductionLine::create([
            'company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Line 1', 'code' => 'LN-001', 'line_type' => 'SEWING',
        ]);
        $location = AssetLocation::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'department_id' => $department->id,
            'production_line_id' => $line->id, 'name' => 'Line 1', 'code' => 'L1',
        ]);
        $machine = app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => 'SEW-001', 'name' => 'Juki DDL-9000C', 'criticality' => 'MEDIUM',
            'current_factory_id' => $this->factory->id, 'asset_location_id' => $location->id,
        ]);
        $machine->forceFill(['status' => 'BREAKDOWN'])->save();

        $this->api()->getJson('/api/v1/company/machines')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'SEW-001')
            ->assertJsonPath('data.0.status', 'DAMAGED')
            ->assertJsonPath('data.0.asset_status', 'BREAKDOWN')
            ->assertJsonPath('data.0.line', 'Line 1')
            ->assertJsonPath('data.0.department', 'Sewing');

        $this->api()->patchJson('/api/v1/company/machines/'.$machine->id, ['capacity' => '480'])
            ->assertOk()
            ->assertJsonPath('data.capacity', '480.00');

        $this->api()->patchJson('/api/v1/company/machines/'.$machine->id, ['capacity' => '-1'])
            ->assertUnprocessable();
    }

    public function test_the_screens_are_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $token = $this->tokenFor($technician);

        $this->withToken($token)->getJson('/api/v1/company/profile')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/company/documents', ['document_type' => 'TIN', 'title' => 'x'])->assertForbidden();
    }

    private function api(): static
    {
        return $this->withToken($this->tokenFor($this->owner));
    }

    private function tokenFor(User $user): string
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $plain;
    }
}
