<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Master Data (AnnotechERP 14/01): garment items and their
 * parts, the two access grids, learning curve points, responsible owners and
 * what each line can run — and who may change them.
 */
class WorkStudyMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => Factory::query()->first()->id, 'name' => 'Main', 'code' => 'B1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing 1', 'code' => 'SF-1']);
    }

    public function test_garment_items_are_mapped_to_parts_in_printed_order(): void
    {
        $items = collect($this->as()->getJson('/api/v1/erp/ws-garment-items')->assertOk()->json('data'));
        $this->assertCount(4, $items);
        $tee = $items->firstWhere('name', 'Basic T-Shirt')['id'];
        $body = GarmentPart::query()->create(['company_id' => $this->delta->id, 'code' => 'BODY', 'name' => 'Body', 'fabric_group' => 'BODY', 'is_process_group' => true, 'is_body' => true, 'active' => true]);

        $this->as()->postJson('/api/v1/erp/ws-item-parts', ['garment_part_id' => $body->id, 'component_sl' => 1])->assertUnprocessable()->assertJsonPath('errors.garment_item_id.0', 'Garments Item is required.');
        $this->as()->postJson('/api/v1/erp/ws-item-parts', ['garment_item_id' => $tee, 'garment_part_id' => $body->id, 'component_sl' => 1])->assertCreated()
            ->assertJsonPath('data.item_group', 'Knit Tops')->assertJsonPath('data.part', 'Body')->assertJsonPath('data.is_body', true)->assertJsonPath('data.included', true);
        $this->as()->postJson('/api/v1/erp/ws-item-parts', ['garment_item_id' => $tee, 'garment_part_id' => $body->id, 'component_sl' => 2])->assertUnprocessable()->assertJsonValidationErrors('garment_part_id');

        $this->as()->deleteJson("/api/v1/erp/ws-garment-items/{$tee}")->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/ws-garment-items', ['item_group' => 'Knit Tops', 'name' => 'Basic T-Shirt'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as()->postJson('/api/v1/erp/ws-garment-items', ['item_group' => 'Bottoms', 'name' => 'Basic T-Shirt'])->assertCreated();
    }

    public function test_access_grids_save_as_a_whole(): void
    {
        $row = ['user_id' => $this->owner->id, 'floor_ids' => [$this->floor->id], 'target_right' => 'W', 'product_right' => 'R', 'is_admin' => true];

        $this->as()->putJson('/api/v1/workstudy/target-access', ['rows' => [$row, $row]])->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'A user appears once.'));
        $this->as()->putJson('/api/v1/workstudy/target-access', ['rows' => [['user_id' => null] + $row]])->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Every line needs a user.'));
        $this->as()->putJson('/api/v1/workstudy/target-access', ['rows' => [$row]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product_right', 'R')->assertJsonPath('data.0.is_admin', true);
        $this->as()->putJson('/api/v1/workstudy/target-access', ['rows' => []])->assertOk()->assertJsonCount(0, 'data');

        $iron = ['user_id' => $this->owner->id, 'node_type' => 'IRON', 'floor_ids' => [$this->floor->id]];
        $this->as()->putJson('/api/v1/workstudy/finishing-access', ['rows' => [$iron, $iron]])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->putJson('/api/v1/workstudy/finishing-access', ['rows' => [['floor_ids' => []] + $iron]])->assertUnprocessable();
        $this->as()->putJson('/api/v1/workstudy/finishing-access', ['rows' => [$iron, ['node_type' => 'POLY'] + $iron]])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_learning_curve_points_are_one_per_day(): void
    {
        $point = ['fabric_class' => 'SINGLE_JERSEY', 'prod_type' => 'BASIC', 'day' => 1];

        $this->as()->postJson('/api/v1/erp/ws-learning-points', $point)->assertUnprocessable()->assertJsonPath('errors.efficiency.0', 'Efficiency % is required.');
        $this->as()->postJson('/api/v1/erp/ws-learning-points', $point + ['efficiency' => 45])->assertCreated();
        $this->as()->postJson('/api/v1/erp/ws-learning-points', $point + ['efficiency' => 50])->assertUnprocessable()->assertJsonValidationErrors('day');
        $this->as()->postJson('/api/v1/erp/ws-learning-points', ['prod_type' => 'CRITICAL'] + $point + ['efficiency' => 35])->assertCreated();
        $this->as()->postJson('/api/v1/erp/ws-learning-points', ['day' => 3] + $point + ['efficiency' => 120])->assertUnprocessable()->assertJsonPath('errors.efficiency.0', 'Efficiency is a percentage — above 100 is refused.');

        // Planning Learning Curve: the fabric class's whole grid replaces what was there.
        $grid = [['prod_type' => 'BASIC', 'day' => 1, 'efficiency' => 40], ['prod_type' => 'BASIC', 'day' => 2, 'efficiency' => 55], ['prod_type' => 'SEMI_CRITICAL', 'day' => 1, 'efficiency' => 32]];
        $this->as()->putJson('/api/v1/workstudy/learning-curve/SINGLE_JERSEY', ['points' => [...$grid, ['prod_type' => 'BASIC', 'day' => 1, 'efficiency' => 41]]])->assertUnprocessable();
        $this->as()->putJson('/api/v1/workstudy/learning-curve/SINGLE_JERSEY', ['points' => [['prod_type' => 'BASIC', 'day' => 1, 'efficiency' => 101]]])->assertUnprocessable();
        $this->as()->putJson('/api/v1/workstudy/learning-curve/SINGLE_JERSEY', ['points' => $grid])->assertOk()->assertJsonCount(3, 'data');
        $this->as()->getJson('/api/v1/erp/ws-learning-points')->assertJsonCount(3, 'data');
        $this->as()->putJson('/api/v1/workstudy/learning-curve/WOOL', ['points' => []])->assertNotFound();
    }

    public function test_responsible_owners_and_line_capability(): void
    {
        $depts = collect($this->as()->getJson('/api/v1/workstudy/departments')->assertOk()->json('data'));
        $this->assertSame(['Industrial Engineering', 'Production', 'Quality', 'Maintenance', 'Planning'], $depts->pluck('name')->all());
        $this->as()->postJson('/api/v1/workstudy/departments', ['code' => 'X'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Department is required.');
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'B-01', 'name' => 'Northwind', 'short_name' => 'NW', 'pi_address' => 'Dhaka', 'registered_from' => '2026-01-01', 'active' => true]);
        $ie = $depts->first()['id'];
        $person = ['user_id' => $this->owner->id, 'floor_ids' => [$this->floor->id], 'all_floors' => false, 'buyer_ids' => [$buyer->id], 'all_buyers' => true];
        $this->as()->putJson("/api/v1/workstudy/departments/{$ie}/persons", ['rows' => [$person, $person]])->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'already answers'));
        $this->as()->putJson("/api/v1/workstudy/departments/{$ie}/persons", ['rows' => [['user_id' => null] + $person]])->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Every line needs an employee.'));
        $this->as()->putJson("/api/v1/workstudy/departments/{$ie}/persons", ['rows' => [$person]])->assertOk()
            ->assertJsonPath('data.0.user', $this->owner->name)->assertJsonPath('data.0.floor_ids', [$this->floor->id])->assertJsonPath('data.0.all_buyers', true)->assertJsonPath('data.0.buyer_ids', []);
        $this->as()->getJson('/api/v1/workstudy/departments')->assertJsonPath('data.0.count', 1);
        $this->as()->getJson('/api/v1/workstudy/persons')->assertJsonCount(1, 'data')->assertJsonPath('data.0.dept', 'Industrial Engineering');

        $line = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $this->floor->id, 'code' => 'L-01', 'name' => 'Line 1', 'std_manpower' => 40, 'std_machines' => 36, 'std_hours' => 10, 'std_efficiency' => 60, 'display_order' => 1, 'active' => true]);
        $profile = ['operators' => 30, 'aso' => 8, 'machines' => 32];
        $this->as()->postJson('/api/v1/erp/ws-line-profiles', $profile)->assertUnprocessable()->assertJsonPath('errors.planning_line_id.0', 'Line is required.');
        $this->as()->postJson('/api/v1/erp/ws-line-profiles', $profile + ['planning_line_id' => $line->id])->assertCreated()->assertJsonPath('data.line', 'SF-1>>L-01')->assertJsonPath('data.machine_type', null);
        $this->as()->postJson('/api/v1/erp/ws-line-profiles', $profile + ['planning_line_id' => $line->id])->assertUnprocessable()->assertJsonValidationErrors('machine_type');
        $this->as()->postJson('/api/v1/erp/ws-line-profiles', $profile + ['planning_line_id' => $line->id, 'machine_type' => 'OVERLOCK'])->assertCreated();

        $this->as()->getJson('/api/v1/workstudy/criticality/BASIC')->assertJsonCount(1, 'data')->assertJsonPath('data.0.runs', false)->assertJsonPath('data.0.unit_floor', 'FAC01 || SF-1');
        $this->as()->putJson('/api/v1/workstudy/criticality/BASIC', ['rows' => [['planning_line_id' => $line->id, 'runs' => true, 'preferred_group' => 'Sleepwear']]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.preferred_group');
        $this->as()->putJson('/api/v1/workstudy/criticality/BASIC', ['rows' => [['planning_line_id' => $line->id, 'runs' => true, 'preferred_group' => 'Outerwear']]])->assertOk()
            ->assertJsonPath('data.0.line', 'L-01')->assertJsonPath('data.0.runs', true)->assertJsonPath('data.0.preferred_group', 'Outerwear');
        $this->as()->getJson('/api/v1/workstudy/criticality')->assertJsonPath('data.0.count', 1)->assertJsonPath('data.1.count', 0);
        $this->as()->putJson('/api/v1/workstudy/criticality/BASIC', ['rows' => [['planning_line_id' => $line->id, 'runs' => false, 'preferred_group' => null]]])->assertOk()->assertJsonPath('data.0.runs', false);
        $this->as()->getJson('/api/v1/workstudy/criticality')->assertJsonPath('data.0.count', 0);
        $this->as()->getJson('/api/v1/workstudy/criticality/NOPE')->assertNotFound();
    }

    public function test_who_may_change_the_work_study_masters(): void
    {
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/erp/ws-learning-points', ['fabric_class' => 'TWILL', 'prod_type' => 'BASIC', 'day' => 1, 'efficiency' => 40])->assertCreated();

        $chief = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($chief)->getJson('/api/v1/workstudy/lookups')->assertOk();
        $this->withToken($chief)->putJson('/api/v1/workstudy/target-access', ['rows' => []])->assertForbidden();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/erp/ws-learning-points')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
