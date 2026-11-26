<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\MasterData\MasterDataRegistry;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Reference data over the API (API 5.2, Gap Analysis 3.4 #34): asset types,
 * categories, manufacturers, and the rest of MasterDataRegistry's two dozen
 * lists, one generic controller for all of them exactly as the settings
 * screen is one controller for all of them.
 */
class MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'fa@delta.test'); // master data is admin-only
    }

    public function test_every_registered_list_answers(): void
    {
        // Each of the two dozen lists shares one controller, so a type whose
        // model or columns are wrong shows up here rather than in production.
        foreach (app(MasterDataRegistry::class)->all() as $key => $type) {
            $this->withToken($this->tokenFor($this->manager))
                ->getJson('/api/v1/master-data/'.$key)
                ->assertOk();
        }
    }

    public function test_a_company_can_add_its_own_entry(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/master-data/manufacturers', [
                'name' => 'Zzyzx Machinery',
                'code' => 'zzyzx-test-mfr',
                'country' => 'KR',
                'active' => '1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ZZYZX-TEST-MFR')
            ->assertJsonPath('data.is_platform', false);

        $this->assertSame($this->delta->id, $created->json('data.company_id'));
    }

    public function test_a_platform_entry_cannot_be_edited_or_deactivated(): void
    {
        $platform = AssetType::whereNull('company_id')->firstOrFail();

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson('/api/v1/master-data/asset-types/'.$platform->id, [
                'name' => 'Renamed by one tenant',
                'code' => $platform->code,
                'default_criticality' => 'LOW',
            ])
            ->assertStatus(403);

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson('/api/v1/master-data/asset-types/'.$platform->id.'/active', ['active' => false])
            ->assertStatus(403);

        $this->assertSame($platform->name, $platform->fresh()->name);
        $this->assertTrue($platform->fresh()->active);
    }

    public function test_a_code_cannot_shadow_one_the_company_already_sees(): void
    {
        $platform = AssetType::whereNull('company_id')->firstOrFail();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/master-data/asset-types', [
                'name' => 'Our own sewing',
                'code' => $platform->code,
                'default_criticality' => 'HIGH',
                'active' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_another_tenants_entry_is_not_reachable(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirs = AssetType::create([
            'company_id' => $other->id,
            'name' => 'Theirs',
            'code' => 'BTL_ONLY',
            'default_criticality' => 'MEDIUM',
            'active' => true,
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson('/api/v1/master-data/asset-types/'.$theirs->id, [
                'name' => 'Renamed',
                'code' => 'BTL_ONLY',
                'default_criticality' => 'LOW',
            ])
            ->assertNotFound();

        $this->assertSame('Theirs', $theirs->fresh()->name);
    }

    public function test_an_unused_company_entry_can_be_removed_but_a_used_one_cannot(): void
    {
        $unused = AssetType::create([
            'company_id' => $this->delta->id,
            'name' => 'Typed in by mistake',
            'code' => 'MISTAKE',
            'default_criticality' => 'LOW',
            'active' => true,
        ]);

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson('/api/v1/master-data/asset-types/'.$unused->id)
            ->assertNoContent();

        $this->assertNull(AssetType::find($unused->id));

        $used = AssetType::create([
            'company_id' => $this->delta->id,
            'name' => 'In use',
            'code' => 'INUSE',
            'default_criticality' => 'LOW',
            'active' => true,
        ]);

        \App\Modules\Asset\Models\AssetCategory::create([
            'company_id' => $this->delta->id,
            'asset_type_id' => $used->id,
            'name' => 'Category referencing it',
            'code' => 'CATREF',
            'active' => true,
        ]);

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson('/api/v1/master-data/asset-types/'.$used->id)
            ->assertStatus(409);
    }

    public function test_the_endpoints_are_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->tokenFor($technician))
            ->getJson('/api/v1/master-data/asset-types')
            ->assertForbidden();

        $this->withToken($this->tokenFor($technician))
            ->postJson('/api/v1/master-data/asset-types', [
                'name' => 'X', 'code' => 'X', 'default_criticality' => 'LOW',
            ])
            ->assertForbidden();
    }

    public function test_show_includes_the_schema_a_reference_field_needs(): void
    {
        // asset-categories has a REFERENCE field pointing at asset-types —
        // exactly what a create/edit form needs to know to render a
        // dropdown, which nothing before this endpoint's `meta` exposed.
        $response = $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/master-data/asset-categories')
            ->assertOk();

        $response->assertJsonPath('meta.schema.key', 'asset-categories');
        $response->assertJsonPath('meta.schema.display_column', 'name');

        $fields = collect($response->json('meta.schema.fields'));
        $typeField = $fields->firstWhere('name', 'asset_type_id');
        $this->assertNotNull($typeField);
        $this->assertSame('REFERENCE', $typeField['type']);
        $this->assertSame('asset-types', $typeField['reference']);

        $options = $response->json('meta.reference_options.asset_type_id');
        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('id', $options[0]);
        $this->assertArrayHasKey('label', $options[0]);
    }

    public function test_show_includes_reference_options_for_a_belongs_to_field_scoped_to_reachable_factories(): void
    {
        // buildings has a BELONGS_TO field pointing at Factory — scoped to
        // reachable factories only, the same rule
        // MasterDataController::referenceOptions() applies on the web, so
        // this doesn't leak the shape of the whole estate.
        $response = $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/master-data/buildings')
            ->assertOk();

        $factoryField = collect($response->json('meta.schema.fields'))->firstWhere('name', 'factory_id');
        $this->assertSame('BELONGS_TO', $factoryField['type']);

        $factory = Factory::where('company_id', $this->delta->id)->firstOrFail();
        $options = $response->json('meta.reference_options.factory_id');
        $this->assertContains($factory->id, array_column($options, 'id'));
    }

    public function test_an_unknown_type_key_is_not_found(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/master-data/not-a-real-type')
            ->assertNotFound();
    }

    /** The first NUMBER-typed fields in the registry: a decimal survives the round trip as a decimal. */
    public function test_an_hs_code_keeps_its_decimal_rates(): void
    {
        $token = $this->tokenFor($this->manager);

        $created = $this->withToken($token)
            ->postJson('/api/v1/master-data/hs-codes', [
                'code' => '5208.11.00',
                'description' => 'Unbleached plain weave cotton',
                'duty_rate' => '25.5',
                'vat_rate' => '15',
                'active' => '1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.duty_rate', '25.50')
            ->assertJsonPath('data.vat_rate', '15.00');

        $this->withToken($token)
            ->postJson('/api/v1/master-data/hs-codes', [
                'code' => 'BAD-RATE', 'description' => 'x', 'duty_rate' => '-1', 'vat_rate' => '0', 'active' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('duty_rate');

        $this->withToken($token)
            ->getJson('/api/v1/master-data/hs-codes')
            ->assertOk()
            ->assertJsonPath('meta.schema.fields.2.type', 'NUMBER')
            ->assertJsonFragment(['id' => $created->json('data.id')]);
    }

    /** The roll inspection screen's own catalogue — Knit and Woven stay two separate lists. */
    public function test_a_fabric_defect_is_scoped_to_its_family(): void
    {
        $token = $this->tokenFor($this->manager);

        $created = $this->withToken($token)
            ->postJson('/api/v1/master-data/fabric-defects', [
                'fabric_family' => 'KNIT', 'code' => 'PILLING', 'name' => 'Pilling', 'active' => '1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.fabric_family', 'KNIT');

        $this->withToken($token)
            ->postJson('/api/v1/master-data/fabric-defects', [
                'fabric_family' => 'STRETCH', 'code' => 'X', 'name' => 'x', 'active' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fabric_family');

        $this->withToken($token)
            ->getJson('/api/v1/master-data/fabric-defects')
            ->assertOk()
            ->assertJsonFragment(['id' => $created->json('data.id')])
            // Seeded platform defaults (Inventory spec §4.4) are present alongside it.
            ->assertJsonFragment(['code' => 'BARRE', 'fabric_family' => 'KNIT'])
            ->assertJsonFragment(['code' => 'SLUB', 'fabric_family' => 'WOVEN']);
    }

    /** A chart of accounts is a tree; the one thing it must never be is a loop. */
    public function test_a_unit_conversion_needs_two_different_units(): void
    {
        $token = $this->tokenFor($this->manager);

        // Units are General Store's one list now (`units_of_measure`); the conversion still lives here.
        $byCode = \App\Modules\Inventory\Models\UnitOfMeasure::query()->whereNull('company_id')->get(['id', 'code'])->keyBy('code');

        $this->withToken($token)
            ->postJson('/api/v1/master-data/uom-conversions', [
                'from_uom_id' => $byCode['YDS']['id'], 'to_uom_id' => $byCode['MTR']['id'], 'factor' => '0.9144',
            ])
            ->assertCreated()
            ->assertJsonPath('data.factor', '0.914400');

        $this->withToken($token)
            ->postJson('/api/v1/master-data/uom-conversions', [
                'from_uom_id' => $byCode['KG']['id'], 'to_uom_id' => $byCode['KG']['id'], 'factor' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_uom_id');
    }

    private function tokenFor(User $user): string
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->currentCompanyId($user), 'Test token');

        return $plain;
    }

    private function currentCompanyId(User $user): string
    {
        return $user->memberships()->latest()->value('company_id');
    }
}
