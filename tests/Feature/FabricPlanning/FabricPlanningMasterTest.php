<?php

declare(strict_types=1);

namespace Tests\Feature\FabricPlanning;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric Requirement → Master Data (AnnotechERP 13/01): batch lead day rules
 * that need their days for the basis they count from, batch hour norms one
 * per combination, and machine routes onto Dye House machines — a criterion
 * is a value or Any — and who may change them.
 */
class FabricPlanningMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_batch_lead_rules_count_from_shipment_or_pcd(): void
    {
        $rule = ['colour_type' => 'SOLID', 'any_finish' => true, 'any_wash' => true, 'lead_from' => 60, 'lead_to' => 90, 'volume_from' => 0, 'volume_to' => 5000, 'production_lead_time' => 30, 'basis' => 'SHIPMENT'];

        $this->as()->postJson('/api/v1/erp/fp-batch-lead-rules', $rule)->assertUnprocessable()->assertJsonPath('errors.days_before_shipment.0', 'A shipment-based rule needs Std Days Before Shipment above zero.');
        $this->as()->postJson('/api/v1/erp/fp-batch-lead-rules', ['any_finish' => false] + $rule + ['days_before_shipment' => 21])->assertUnprocessable()->assertJsonValidationErrors('finish_type');
        $this->as()->postJson('/api/v1/erp/fp-batch-lead-rules', ['lead_to' => 30] + $rule + ['days_before_shipment' => 21])->assertUnprocessable()->assertJsonValidationErrors('lead_to');
        $id = $this->as()->postJson('/api/v1/erp/fp-batch-lead-rules', $rule + ['days_before_shipment' => 21, 'finish_type' => 'PEACH'])->assertCreated()
            ->assertJsonPath('data.finish_type', null)->assertJsonPath('data.any_finish', true)->assertJsonPath('data.all_colours', false)->json('data.id');

        $this->as()->patchJson("/api/v1/erp/fp-batch-lead-rules/{$id}", ['basis' => 'PCD'])->assertUnprocessable()->assertJsonValidationErrors('days_before_pcd');
        $this->as()->patchJson("/api/v1/erp/fp-batch-lead-rules/{$id}", ['basis' => 'PCD', 'days_before_pcd' => 10])->assertOk()->assertJsonPath('data.basis', 'PCD');
        $this->as()->patchJson("/api/v1/erp/fp-batch-lead-rules/{$id}", ['all_colours' => true])->assertOk()->assertJsonPath('data.colour_type', null);
    }

    public function test_batch_hour_norms_are_one_per_combination(): void
    {
        $group = $this->as()->getJson('/api/v1/erp/dye-colour-groups')->json('data.0.id');
        $norm = ['colour_group_id' => $group, 'fiber_template' => 'COTTON_100', 'any_category' => true, 'dyeing_method' => 'EXHAUST', 'batch_hours' => 8];

        $this->as()->postJson('/api/v1/erp/fp-batch-hours', ['batch_hours' => 0] + $norm)->assertUnprocessable()->assertJsonPath('errors.batch_hours.0', 'Standard Batch Hour must be greater than zero.');
        $this->as()->postJson('/api/v1/erp/fp-batch-hours', $norm)->assertCreated()->assertJsonPath('data.buyer_category', null);
        $this->as()->postJson('/api/v1/erp/fp-batch-hours', ['batch_hours' => 9] + $norm)->assertUnprocessable()->assertJsonValidationErrors('dyeing_method');
        $this->as()->postJson('/api/v1/erp/fp-batch-hours', ['any_category' => false, 'buyer_category' => 'A', 'batch_hours' => 7] + $norm)->assertCreated();
        $this->as()->getJson('/api/v1/erp/fp-batch-hours')->assertJsonCount(2, 'data');
    }

    public function test_machine_routes_point_at_dye_house_machines(): void
    {
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->json('data.id');
        $unit = Factory::query()->first();
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Dye', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dye Floor', 'code' => 'DF-1']);
        $machine = $this->as()->postJson('/api/v1/erp/dye-machines', [
            'machine_no' => 'MFL-01', 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'origin_country_id' => $country, 'capacity_unit' => 'KG', 'sl_no' => '01', 'floor_id' => $floor->id, 'mc_version' => '0', 'prod_status' => 'FREE',
        ])->assertCreated()->json('data.id');

        $route = ['colour_group_id' => $this->as()->getJson('/api/v1/erp/dye-colour-groups')->json('data.0.id'), 'fabric_group' => 'BODY', 'any_template' => true, 'any_buyer' => true, 'dye_machine_id' => $machine];
        $this->as()->postJson('/api/v1/erp/fp-machine-routes', $route + ['gsm_from' => 140])->assertUnprocessable()->assertJsonPath('errors.gsm_from.0', 'Give both ends of the GSM band, or tick Any.');
        $this->as()->postJson('/api/v1/erp/fp-machine-routes', $route + ['gsm_from' => 200, 'gsm_to' => 140])->assertUnprocessable()->assertJsonValidationErrors('gsm_to');
        $this->as()->postJson('/api/v1/erp/fp-machine-routes', array_diff_key($route, ['dye_machine_id' => 1]) + ['any_gsm' => true])->assertUnprocessable()->assertJsonPath('errors.dye_machine_id.0', 'Routed Dye M/C is required.');
        $this->as()->postJson('/api/v1/erp/fp-machine-routes', $route + ['gsm_from' => 140, 'gsm_to' => 200])->assertCreated()->assertJsonPath('data.machine', 'MFL-01')->assertJsonPath('data.any_gsm', false)->assertJsonPath('data.any_buyer', true);
        $this->as()->postJson('/api/v1/erp/fp-machine-routes', $route + ['any_gsm' => true, 'gsm_from' => 1])->assertCreated()->assertJsonPath('data.gsm_from', null)->assertJsonPath('data.any_gsm', true);
    }

    public function test_who_may_change_the_fabric_requirement_masters(): void
    {
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/erp/fp-batch-hours', ['colour_group_id' => $this->as()->getJson('/api/v1/erp/dye-colour-groups')->json('data.0.id'), 'any_template' => true, 'any_category' => true, 'dyeing_method' => 'PIGMENT', 'batch_hours' => 5])->assertCreated();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/erp/fp-batch-hours')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
