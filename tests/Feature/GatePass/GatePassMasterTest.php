<?php

declare(strict_types=1);

namespace Tests\Feature\GatePass;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Gate Pass → Master Data (AnnotechERP 11/01): the lists a company starts
 * with, locations under their type, items with a unit, codes that repeat in
 * no case, nothing deleted, and who may change them.
 */
class GatePassMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_company_starts_with_brickland_lists(): void
    {
        $this->as()->getJson('/api/v1/erp/gatepass-categories')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', 'ITEM')->assertJsonPath('data.1.kind', 'MAIN');
        $this->as()->getJson('/api/v1/erp/gatepass-location-types')->assertJsonCount(10, 'data')->assertJsonPath('data.0.name', 'Embroidery Factory');
        $this->as()->getJson('/api/v1/erp/gatepass-purposes')->assertJsonCount(12, 'data')->assertJsonPath('data.10.name', 'Machine Repair');

        // Opening a list again does not add the defaults twice, even after one is renamed.
        $first = $this->as()->getJson('/api/v1/erp/gatepass-purposes')->json('data.0.id');
        $this->as()->patchJson("/api/v1/erp/gatepass-purposes/{$first}", ['name' => 'Embroidery Work'])->assertOk();
        $this->as()->getJson('/api/v1/erp/gatepass-purposes')->assertJsonCount(12, 'data');

        $this->as()->postJson('/api/v1/erp/gatepass-categories', ['code' => 'visit', 'name' => 'Visitor Pass', 'kind' => 'MAIN'])->assertCreated()->assertJsonPath('data.code', 'VISIT');
        $this->as()->postJson('/api/v1/erp/gatepass-categories', ['code' => 'item', 'name' => 'Again', 'kind' => 'ITEM'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->postJson('/api/v1/erp/gatepass-categories', ['code' => 'X', 'name' => 'X', 'kind' => 'VEHICLE'])->assertUnprocessable()->assertJsonValidationErrors('kind');
    }

    public function test_locations_sit_under_a_type_and_items_take_a_unit(): void
    {
        $type = collect($this->as()->getJson('/api/v1/erp/gatepass-location-types')->json('data'))->firstWhere('code', 'LT-01');

        $this->as()->postJson('/api/v1/erp/gatepass-locations', ['code' => 'LOC-001', 'name' => 'Star Embroidery'])->assertUnprocessable()->assertJsonValidationErrors('location_type_id');
        $id = $this->as()->postJson('/api/v1/erp/gatepass-locations', ['code' => 'loc-001', 'name' => 'Star Embroidery', 'location_type_id' => $type['id'], 'mobile' => '01711000000', 'email' => 'ops@star.test'])
            ->assertCreated()->assertJsonPath('data.code', 'LOC-001')->assertJsonPath('data.location_type', 'Embroidery Factory')->json('data.id');
        $this->assertSame(1, collect($this->as()->getJson('/api/v1/erp/gatepass-location-types')->json('data'))->firstWhere('code', 'LT-01')['locations']);
        $this->as()->deleteJson("/api/v1/erp/gatepass-locations/{$id}")->assertStatus(405);

        $this->as()->postJson('/api/v1/erp/gatepass-items', ['code' => 'GPI-001', 'name' => 'Polo Shirt', 'item_category' => 'FINISHED_GARMENTS', 'unit' => 'PCS'])->assertCreated()->assertJsonPath('data.unit', 'PCS');
        $this->as()->postJson('/api/v1/erp/gatepass-items', ['code' => 'GPI-002', 'name' => 'X', 'item_category' => 'VEHICLE', 'unit' => 'PCS'])->assertUnprocessable()->assertJsonValidationErrors('item_category');
        $this->as()->postJson('/api/v1/erp/gatepass-items', ['code' => 'GPI-003', 'name' => 'X', 'item_category' => 'FABRIC', 'unit' => 'TON'])->assertUnprocessable()->assertJsonValidationErrors('unit');
    }

    public function test_managers_read_the_lists_and_cannot_change_them(): void
    {
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->getJson('/api/v1/erp/gatepass-purposes')->assertOk();
        $this->withToken($manager)->postJson('/api/v1/erp/gatepass-purposes', ['code' => 'PUR-20', 'name' => 'Exhibition'])->assertForbidden();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->postJson('/api/v1/erp/gatepass-purposes', ['code' => 'PUR-20', 'name' => 'Exhibition'])->assertCreated();

        $technician = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($technician)->getJson('/api/v1/erp/gatepass-categories')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
