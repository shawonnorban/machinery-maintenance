<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Lab-Dip (AnnotechERP 17/02): dosing templates — a header and
 * its agents in step order, coded on save — and lab dip recipes, one option
 * per attempt under a recipe number, with the style held to its buyer; and
 * who may read and write them.
 */
class DyeHouseLabDipTest extends TestCase
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

    public function test_dosing_template_curve(): void
    {
        $kg = UnitOfMeasure::availableTo($this->delta->id)->where('code', 'KG')->value('id');
        $dyes = ItemDepartment::query()->create(['company_id' => $this->delta->id, 'code' => 'DYES_CHEMICAL_STORE', 'name' => 'Dyes Chemical Store', 'active' => true])->id;
        $general = ItemDepartment::query()->create(['company_id' => $this->delta->id, 'code' => 'GENERAL_STORE', 'name' => 'General Store', 'active' => true])->id;
        $acid = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dyes, 'item_code' => 'DC-0003', 'name' => 'Acetic Acid', 'uom_id' => $kg, 'active' => true])->id;
        $soda = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dyes, 'item_code' => 'DC-0006', 'name' => 'Soda Ash', 'uom_id' => $kg, 'active' => true])->id;
        Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $general, 'item_code' => 'GS-0001', 'name' => 'Broom', 'uom_id' => $kg, 'active' => true]);

        // The dyes and chemicals, the methods and colour groups as Brickland ships them, and the phases' processes.
        $options = $this->as()->getJson('/api/v1/dyehouse/lab-dip/options')->assertOk()
            ->assertJsonCount(2, 'data.chemicals')->assertJsonPath('data.chemicals.0.label', 'Acetic Acid (DC-0003)')
            ->assertJsonPath('data.processes.DYEING.0', 'LEVELLING')->assertJsonPath('data.types.0', 'PRE_TREATMENT')->json('data');
        $reactive = collect($options['methods'])->firstWhere('label', 'Reactive')['value'];
        $medium = collect($options['colour_groups'])->firstWhere('label', 'Medium')['value'];

        $head = ['name' => 'CONVENTIONAL FOR REACTIVE', 'short_name' => 'CONV/REACT', 'dye_method_id' => $reactive, 'colour_group_id' => $medium, 'template_type' => 'DYEING', 'is_finalized' => false];
        $agents = [
            ['step_no' => 6, 'dye_method_id' => $reactive, 'phase' => 'DYEING', 'process' => 'ALKALI_DOSING', 'item_id' => $soda, 'is_labdip' => true, 'dose' => 15, 'uom' => 'kg'],
            ['step_no' => 3, 'dye_method_id' => $reactive, 'phase' => 'PRE_TREATMENT', 'process' => 'HOT_WASH', 'item_id' => $acid, 'is_labdip' => true, 'is_line_break' => true, 'dose' => 0.8, 'uom' => 'LTR'],
        ];

        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', ['dye_method_id' => null] + $head + ['agents' => []])->assertUnprocessable()->assertJsonPath('errors.dye_method_id.0', 'Dyeing Method is required.');
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', $head + ['agents' => [$agents[0], ['step_no' => 6] + $agents[1]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Step numbers must not repeat'));
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', $head + ['agents' => [['process' => 'SOAPING'] + $agents[0]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), "Step 6: that dye process is not one of its phase's."));
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', $head + ['agents' => [['uom' => 'FURLONG'] + $agents[0]]])->assertUnprocessable()->assertJsonValidationErrors('agents.0.uom');
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', $head + ['agents' => [['item_id' => null] + $agents[0]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Every line needs its dye or chemical agent.'));

        // Saved: TPL-0001, the agents in step order, the unit as the master's code.
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', $head + ['agents' => $agents])->assertCreated()
            ->assertJsonPath('data.code', 'TPL-0001')->assertJsonPath('data.is_finalized', false)
            ->assertJsonPath('data.agents.0.step_no', 3)->assertJsonPath('data.agents.0.item', 'Acetic Acid (DC-0003)')->assertJsonPath('data.agents.0.is_line_break', true)
            ->assertJsonPath('data.agents.1.step_no', 6)->assertJsonPath('data.agents.1.uom', 'KG')->assertJsonPath('data.agents.1.dose', 15);
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', $head + ['agents' => []])->assertUnprocessable()->assertJsonPath('errors.name.0', 'A template of that name is already on file.');

        // Finalised, with one agent left: the lines given are the lines kept.
        $this->as()->putJson('/api/v1/dyehouse/dosing-templates/TPL-0001', ['is_finalized' => true] + $head + ['agents' => [$agents[1]]])->assertOk()
            ->assertJsonPath('data.is_finalized', true)->assertJsonCount(1, 'data.agents');
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', ['name' => 'MACHINE WASH', 'template_type' => 'WASH'] + $head + ['agents' => []])->assertCreated()->assertJsonPath('data.code', 'TPL-0002');

        $this->as()->getJson('/api/v1/dyehouse/dosing-templates')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'TPL-0001')->assertJsonPath('data.0.agents', 1)->assertJsonPath('data.0.dye_method', 'Reactive')->assertJsonPath('data.0.colour_group', 'Medium')
            ->assertJsonPath('data.0.is_finalized', true)->assertJsonPath('data.1.template_type', 'WASH');
        $this->as()->getJson('/api/v1/dyehouse/dosing-templates/TPL-0001')->assertOk()->assertJsonPath('data.name', 'CONVENTIONAL FOR REACTIVE');

        $this->as()->deleteJson('/api/v1/dyehouse/dosing-templates/TPL-0002')->assertOk();
        $this->as()->getJson('/api/v1/dyehouse/dosing-templates/TPL-0002')->assertNotFound();
        // Codes follow the highest one on file.
        $this->as()->postJson('/api/v1/dyehouse/dosing-templates', ['name' => 'FINISHING TEMPLATE', 'template_type' => 'FINISHING'] + $head + ['agents' => []])->assertCreated()->assertJsonPath('data.code', 'TPL-0002');
    }

    public function test_lab_dip_recipes(): void
    {
        $c = $this->delta->id;
        $lpp = Buyer::query()->create(['company_id' => $c, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $kiabi = Buyer::query()->create(['company_id' => $c, 'code' => 'KIA', 'name' => 'Kiabi', 'short_name' => 'Kiabi', 'pi_address' => 'Lille', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $style = Style::query()->create(['company_id' => $c, 'buyer_id' => $lpp, 'style_ref' => '770MR', 'description' => 'Rib tee'])->id;
        $theirs = Style::query()->create(['company_id' => $c, 'buyer_id' => $kiabi, 'style_ref' => 'KB-01', 'description' => 'Polo'])->id;
        $this->as()->getJson('/api/v1/erp/dye-colour-groups')->assertOk();
        $dark = DyeColourGroup::query()->where('name', 'Dark')->value('id');
        $citron = Colour::query()->create(['company_id' => $c, 'code' => '120-0141', 'colour_type' => 'SOLID', 'name' => '120 CITRON', 'colour_group_id' => $dark, 'active' => true])->id;
        $rib = FabricType::query()->create(['company_id' => $c, 'code' => 'RIB', 'name' => '1x1 Rib', 'short_name' => 'Rib', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'active' => true])->id;

        $options = $this->as()->getJson('/api/v1/dyehouse/lab-dip/options')->assertOk()->assertJsonPath('data.colours.0.label', '120 CITRON (120-0141)')->json('data');
        $this->assertSame(['770MR · LPP', 'KB-01 · Kiabi'], array_column($options['styles'], 'label'));
        $reactive = collect($options['methods'])->firstWhere('label', 'Reactive')['value'];

        $recipe = ['recipe_no' => ' LD-2026-0901 ', 'option_no' => 1, 'buyer_id' => $lpp, 'style_id' => $style, 'colour_id' => $citron, 'fabric_type_id' => $rib, 'dye_method_id' => $reactive, 'fiber_composition' => '100% Cotton'];
        $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', ['buyer_id' => $lpp])->assertUnprocessable()
            ->assertJsonPath('errors.recipe_no.0', 'Recipe # is required.')->assertJsonPath('errors.colour_id.0', 'Color is required.');
        $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', array_diff_key($recipe, ['buyer_id' => 1]))->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', 'Buyer A/C is required.');
        $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', ['style_id' => $theirs] + $recipe)->assertUnprocessable()
            ->assertJsonPath('errors.style_id.0', 'Style KB-01 belongs to a different buyer. Pick a style of the buyer named above.');

        // The colour type and group are the shade's; the number is kept trimmed; who raised it is recorded.
        $id = $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', $recipe)->assertCreated()
            ->assertJsonPath('data.recipe_no', 'LD-2026-0901')->assertJsonPath('data.ref', 'LD-2026-0901-1')->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.style', '770MR')
            ->assertJsonPath('data.colour', '120 CITRON')->assertJsonPath('data.colour_type', 'SOLID')->assertJsonPath('data.colour_group', 'Dark')->assertJsonPath('data.fabric_type', '1x1 Rib')
            ->assertJsonPath('data.dye_method', 'Reactive')->assertJsonPath('data.created_by', $this->owner->name)->json('data.id');

        // A second attempt at the shade is the next option, never the same one again.
        $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', $recipe)->assertUnprocessable()->assertJsonPath('errors.option_no.0', 'That option of the recipe is already on file — the next attempt is the next option.');
        $second = $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', ['option_no' => 2, 'style_id' => null, 'fabric_type_id' => null] + $recipe)->assertCreated()->assertJsonPath('data.style', null)->json('data.id');
        $this->as()->patchJson("/api/v1/erp/dye-lab-dip-recipes/{$second}", ['option_no' => 1])->assertUnprocessable()->assertJsonValidationErrors('option_no');
        $this->as()->patchJson("/api/v1/erp/dye-lab-dip-recipes/{$id}", ['buyer_id' => $kiabi])->assertUnprocessable()->assertJsonValidationErrors('style_id');
        $this->as()->patchJson("/api/v1/erp/dye-lab-dip-recipes/{$id}", ['fiber_composition' => '95% Cotton 5% Elastane'])->assertOk()->assertJsonPath('data.fiber_composition', '95% Cotton 5% Elastane');

        $this->as()->getJson('/api/v1/erp/dye-lab-dip-recipes')->assertOk()->assertJsonCount(2, 'data');
        $this->as()->deleteJson("/api/v1/erp/dye-lab-dip-recipes/{$second}")->assertNoContent();
        $this->as()->getJson('/api/v1/erp/dye-lab-dip-recipes')->assertJsonCount(1, 'data');
    }

    public function test_who_may_write_templates_and_recipes(): void
    {
        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/dyehouse/lab-dip/options')->assertOk();
        $this->withToken($store)->getJson('/api/v1/dyehouse/dosing-templates')->assertOk();
        $this->withToken($store)->getJson('/api/v1/erp/dye-lab-dip-recipes')->assertOk();
        $this->withToken($store)->postJson('/api/v1/dyehouse/dosing-templates', ['name' => 'X'])->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/erp/dye-lab-dip-recipes', ['recipe_no' => 'X'])->assertForbidden();

        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/dyehouse/dosing-templates', ['name' => 'X'])->assertUnprocessable();

        $technician = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($technician)->getJson('/api/v1/dyehouse/dosing-templates')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/erp/dye-lab-dip-recipes')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
