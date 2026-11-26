<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DyeHouse\Models\DyeResponsibleEmployee;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Master Data (AnnotechERP 17/01): dyeing methods with one
 * default, dye machines and the lists they pick from, responsible people
 * with private pass codes, buyers' audit checks, the colour group grid, and
 * subcontract quotations with their lines — and who may change them.
 */
class DyeHouseMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Floor $floor;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Dye Building', 'code' => 'BLD-D']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dyeing Floor', 'code' => 'DF-1']);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_dyeing_methods_start_from_brickland_and_keep_one_default(): void
    {
        $methods = collect($this->as()->getJson('/api/v1/erp/dye-methods')->assertOk()->assertJsonCount(8, 'data')->json('data'));
        $reactive = $methods->firstWhere('name', 'Reactive');
        $pigment = $methods->firstWhere('name', 'Pigment');

        $this->as()->patchJson("/api/v1/erp/dye-methods/{$reactive['id']}", ['is_default' => true])->assertOk();
        $this->as()->patchJson("/api/v1/erp/dye-methods/{$pigment['id']}", ['is_default' => true])->assertOk();
        $this->assertSame(['Pigment'], collect($this->as()->getJson('/api/v1/erp/dye-methods')->json('data'))->where('is_default', true)->pluck('name')->values()->all());

        $this->as()->postJson('/api/v1/erp/dye-methods', ['short_name' => 'X', 'sd' => 'SINGLE'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Dyeing Method Name is required.');
        $this->as()->postJson('/api/v1/erp/dye-methods', ['name' => 'Reactive', 'short_name' => 'R', 'sd' => 'DOUBLE'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as()->postJson('/api/v1/erp/dye-methods', ['name' => 'Vat', 'short_name' => 'Vat', 'sd' => 'DOUBLE'])->assertCreated();
    }

    public function test_dye_machines_pick_from_their_lists(): void
    {
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->assertOk()->assertJsonCount(14, 'data')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->assertCreated()->json('data.id');
        $machine = [
            'machine_no' => 'mfl-01', 'model' => 'FC28', 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'location_id' => $lists->firstWhere('name', 'Dyeing Shed-1')['id'], 'origin_country_id' => $country, 'capacity' => 1250, 'capacity_unit' => 'KG', 'efficiency' => 85,
            'sl_no' => '01', 'year' => 2010, 'nozzles' => 4, 'floor_id' => $this->floor->id, 'mc_version' => '0', 'prod_status' => 'FREE',
        ];

        // A brand is not a machine type.
        $this->as()->postJson('/api/v1/erp/dye-machines', ['machine_type_id' => $machine['brand_id']] + $machine)->assertUnprocessable()->assertJsonValidationErrors('machine_type_id');
        $this->as()->postJson('/api/v1/erp/dye-machines', array_diff_key($machine, ['machine_type_id' => 1]))->assertUnprocessable()->assertJsonPath('errors.machine_type_id.0', 'Machine Type is required.');
        $this->as()->postJson('/api/v1/erp/dye-machines', $machine)->assertCreated()
            ->assertJsonPath('data.machine_no', 'MFL-01')->assertJsonPath('data.machine_type', 'Soft Flow')->assertJsonPath('data.brand', 'Fongs')->assertJsonPath('data.country', 'China')->assertJsonPath('data.floor', 'DF-1');
        $this->as()->postJson('/api/v1/erp/dye-machines', $machine)->assertUnprocessable()->assertJsonValidationErrors('machine_no');

        // A list entry a machine uses stays; an unused one may go.
        $this->as()->deleteJson('/api/v1/erp/dye-machine-lists/'.$machine['brand_id'])->assertUnprocessable();
        $this->as()->deleteJson('/api/v1/erp/dye-machine-lists/'.$lists->firstWhere('name', 'Thies')['id'])->assertNoContent();
        $this->as()->postJson('/api/v1/erp/dye-machine-lists', ['list' => 'BRAND', 'name' => 'Fongs'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as()->postJson('/api/v1/erp/dye-machine-lists', ['list' => 'LOCATION', 'name' => 'Fongs'])->assertCreated();
    }

    public function test_pass_codes_are_private_and_never_shared(): void
    {
        $operator = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'operator@delta.test');
        $inspector = TenantFixture::user($this->delta, 'STOREKEEPER', 'inspector@delta.test');

        $this->as()->postJson('/api/v1/erp/dye-responsible-employees', ['user_id' => $operator->id, 'pass_code' => '1234'])->assertUnprocessable()->assertJsonPath('errors.employee_type.0', 'Employee Type is required.');
        $this->as()->postJson('/api/v1/erp/dye-responsible-employees', ['employee_type' => 'DF_PROCESS_OPERATOR', 'user_id' => $operator->id, 'pass_code' => '12ab'])->assertUnprocessable()->assertJsonValidationErrors('pass_code');
        $id = $this->as()->postJson('/api/v1/erp/dye-responsible-employees', ['employee_type' => 'DF_PROCESS_OPERATOR', 'user_id' => $operator->id, 'pass_code' => '1234'])->assertCreated()
            ->assertJsonMissingPath('data.pass_code')->assertJsonMissingPath('data.pass_code_hash')->assertJsonPath('data.employee_name', $operator->name)->json('data.id');
        $this->assertNotSame('1234', DyeResponsibleEmployee::query()->whereKey($id)->value('pass_code_hash'));

        $this->as()->postJson('/api/v1/erp/dye-responsible-employees', ['employee_type' => 'DF_QUALITY_INSPECTION', 'user_id' => $inspector->id, 'pass_code' => '1234'])->assertUnprocessable()->assertJsonValidationErrors('pass_code');
        // Left blank on an edit, the code stays.
        $hash = DyeResponsibleEmployee::query()->whereKey($id)->value('pass_code_hash');
        $this->as()->patchJson("/api/v1/erp/dye-responsible-employees/{$id}", ['designation' => 'Operator'])->assertOk();
        $this->assertSame($hash, DyeResponsibleEmployee::query()->whereKey($id)->value('pass_code_hash'));

        // Only a member of this company can be named.
        $stranger = TenantFixture::user(TenantFixture::company('Other Ltd', 'OTH'), 'COMPANY_OWNER', 'stranger@other.test');
        TenantFixture::actingAsTenant($this->delta);
        $this->as()->postJson('/api/v1/erp/dye-responsible-employees', ['employee_type' => 'GFAB_PROCESS_OPERATOR', 'user_id' => $stranger->id, 'pass_code' => '5678'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_audit_checks_and_the_colour_group_grid_save_as_a_whole(): void
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP S.A.', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'country_id' => \App\Modules\Merchandise\Models\OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id, 'registered_from' => '2026-01-01', 'office_ids' => [$office]])->json('data.id');

        $this->as()->putJson("/api/v1/dyehouse/buyers/{$buyer}/audit-checks", ['checks' => [['item_name' => 'Azo dyes'], ['item_name' => 'Azo dyes']]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/dyehouse/buyers/{$buyer}/audit-checks", ['checks' => [['item_name' => 'Azo dyes', 'alter_item_name' => 'Arylamines'], ['item_name' => 'Formaldehyde', 'dropped' => true]]])->assertOk()->assertJsonCount(2, 'data');
        $this->as()->getJson('/api/v1/dyehouse/audit-buyers')->assertJsonPath('data.0.checks', 1);
        $this->as()->putJson("/api/v1/dyehouse/buyers/{$buyer}/audit-checks", ['checks' => [['item_name' => 'Formaldehyde']]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.dropped', false);

        $groups = collect($this->as()->getJson('/api/v1/erp/dye-colour-groups')->assertOk()->assertJsonCount(10, 'data')->json('data'));
        $white = $groups->firstWhere('name', 'White');
        $light = $groups->firstWhere('name', 'Light');
        // Codes may change hands within one save; a repeated code may not.
        $grid = $groups->map(fn ($g) => array_intersect_key($g, array_flip(['id', 'code', 'name', 'labdip_required', 'water_cost', 'wash_cost', 'display_order', 'active'])))->values()->all();
        $swap = array_map(fn ($g) => match ($g['id']) { $white['id'] => ['code' => '5', 'water_cost' => 12.5] + $g, $light['id'] => ['code' => '4'] + $g, default => $g }, $grid);
        $this->as()->putJson('/api/v1/dyehouse/colour-groups', ['groups' => $swap])->assertOk()->assertJsonPath('data.saved', 10);
        $after = collect($this->as()->getJson('/api/v1/erp/dye-colour-groups')->json('data'))->keyBy('id');
        $this->assertSame(['5', '4', 12.5], [$after[$white['id']]['code'], $after[$light['id']]['code'], (float) $after[$white['id']]['water_cost']]);
        $dup = array_map(fn ($g) => $g['id'] === $white['id'] ? ['code' => '4'] + $g : $g, $swap);
        $this->as()->putJson('/api/v1/dyehouse/colour-groups', ['groups' => $dup])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/dye-colour-groups', ['code' => '12', 'name' => 'Neon', 'labdip_required' => true])->assertCreated();
    }

    public function test_subcontract_quotations_carry_their_lines(): void
    {
        $party = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SC-01', 'name' => 'Star Dyeing', 'supplier_type' => 'LOCAL_NON_EPZ'])->json('data.id');
        $heads = $this->as()->getJson('/api/v1/dyehouse/sc-quotations/heads?group=DYEING')->assertOk()->json('data');
        $this->assertNotEmpty($heads);
        $this->as()->getJson('/api/v1/dyehouse/sc-quotations/heads?group=YARN_TWIST')->assertJsonCount(0, 'data');

        $quotation = ['rate_group' => 'DYEING', 'supplier_id' => $party, 'quotation_date' => '2026-10-01', 'currency' => 'USD', 'status' => 'DRAFT', 'auto' => true];
        $this->as()->postJson('/api/v1/dyehouse/sc-quotations', $quotation + ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->as()->postJson('/api/v1/dyehouse/sc-quotations', array_diff_key($quotation, ['rate_group' => 1]) + ['lines' => [['rate_line_id' => $heads[0]['value'], 'rate' => 1.2]]])->assertUnprocessable()->assertJsonValidationErrors('rate_group');
        $id = $this->as()->postJson('/api/v1/dyehouse/sc-quotations', $quotation + ['lines' => [['rate_line_id' => $heads[0]['value'], 'rate' => 1.2], ['rate_line_id' => $heads[1]['value'], 'rate' => 1.5]]])->assertCreated()
            ->assertJsonPath('data.quotation_no', 'DSQ-'.now()->format('Y').'-0001')->assertJsonPath('data.lines.0.head', $heads[0]['label'])->json('data.id');

        // A head from another group, or the same head twice, is refused; a typed head needs Yarn Twist's empty list.
        $knitting = $this->as()->getJson('/api/v1/dyehouse/sc-quotations/heads?group=KNITTING')->json('data.0.value');
        $this->as()->putJson("/api/v1/dyehouse/sc-quotations/{$id}", array_diff_key($quotation, ['rate_group' => 1]) + ['lines' => [['rate_line_id' => $knitting, 'rate' => 1]]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/dyehouse/sc-quotations/{$id}", array_diff_key($quotation, ['rate_group' => 1]) + ['status' => 'APPROVED', 'lines' => [['rate_line_id' => $heads[0]['value'], 'rate' => 1.1], ['rate_line_id' => $heads[0]['value'], 'rate' => 1]]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/dyehouse/sc-quotations/{$id}", ['status' => 'APPROVED', 'lines' => [['rate_line_id' => $heads[0]['value'], 'rate' => 1.1]]] + array_diff_key($quotation, ['rate_group' => 1]))->assertOk()
            ->assertJsonPath('data.status', 'APPROVED')->assertJsonCount(1, 'data.lines')->assertJsonPath('data.quotation_no', 'DSQ-'.now()->format('Y').'-0001');
        $this->as()->postJson('/api/v1/dyehouse/sc-quotations', ['rate_group' => 'YARN_TWIST', 'lines' => [['head' => 'Two-for-one twisting', 'uom' => 'Kg', 'rate' => 0.3]]] + $quotation)->assertCreated()
            ->assertJsonPath('data.quotation_no', 'DSQ-'.now()->format('Y').'-0002')->assertJsonPath('data.lines.0.head', 'Two-for-one twisting');
        $this->as()->getJson('/api/v1/dyehouse/sc-quotations')->assertJsonCount(2, 'data');
    }

    public function test_who_may_change_the_dye_house_masters(): void
    {
        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/erp/dye-methods')->assertOk();
        $this->withToken($store)->postJson('/api/v1/erp/dye-methods', ['name' => 'Vat', 'short_name' => 'Vat', 'sd' => 'SINGLE'])->assertForbidden();
        $this->withToken($store)->putJson('/api/v1/dyehouse/colour-groups', ['groups' => []])->assertForbidden();

        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/erp/dye-methods', ['name' => 'Vat', 'short_name' => 'Vat', 'sd' => 'SINGLE'])->assertCreated();

        $technician = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($technician)->getJson('/api/v1/dyehouse/lookups')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
