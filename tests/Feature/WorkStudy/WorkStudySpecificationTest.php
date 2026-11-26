<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Item Specification (AnnotechERP 14/04): a style's engineered
 * variation with its process groups and machines; the item groups and
 * categories as Brickland ships them, edited in place; each category's tech
 * variations; the GMT Item List reading the orders against their styles'
 * specifications — and who may change them.
 */
class WorkStudySpecificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $unit;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_specification_is_engineered_with_its_process_groups_and_machines(): void
    {
        $part = fn (string $code, string $name, bool $group) => tap((string) Str::ulid(), fn ($id) => DB::table('garment_parts')->insert(['id' => $id, 'company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'is_process_group' => $group, 'active' => true, 'created_at' => now(), 'updated_at' => now()]));
        $collar = $part('COL', 'Collar', true);
        $part('SLV', 'Sleeve', false);
        $options = $this->as()->getJson('/api/v1/workstudy/spec-options')->assertOk()->json('data');
        $this->assertSame(['Collar'], array_column($options['process_groups'], 'label'));
        $this->assertSame(['SNLS', 'OVERLOCK', 'FLATLOCK', 'BUTTON_ATTACH', 'BUTTON_HOLE', 'BARTACK'], $options['machine_types']);

        // Brickland's message for a zero sewing SMV; efficiency capped at 100; a process group and a machine type once each.
        $spec = ['style_no' => 'ss27-tee01', 'variation_no' => 1, 'description' => 'Crew neck tee', 'fabric_class' => 'SINGLE_JERSEY', 'op_manpower' => 30, 'aso_manpower' => 6, 'sewing_smv' => 8.5, 'avg_efficiency' => 62];
        $this->as()->postJson('/api/v1/workstudy/specs', ['sewing_smv' => 0] + $spec)->assertUnprocessable()->assertJsonPath('errors.sewing_smv.0', 'Sewing SMV must be greater than zero.');
        $this->as()->postJson('/api/v1/workstudy/specs', ['avg_efficiency' => 101] + $spec)->assertUnprocessable()->assertJsonValidationErrors('avg_efficiency');
        $this->as()->postJson('/api/v1/workstudy/specs', $spec + ['machines' => [['machine_type' => 'SNLS', 'requirement' => 10], ['machine_type' => 'SNLS', 'requirement' => 2]]])->assertUnprocessable()->assertJsonValidationErrors('machines.1.machine_type');
        $made = $this->as()->postJson('/api/v1/workstudy/specs', $spec + [
            'processes' => [['garment_part_id' => $collar, 'specification' => 'Rib collar 1x1, coverstitch']],
            'machines' => [['machine_type' => 'SNLS', 'requirement' => 16], ['machine_type' => 'OVERLOCK', 'requirement' => 12]],
        ])->assertCreated()->json('data');
        // The nature follows the sewing SMV when none is chosen (8.5 → Basic 0–12).
        $this->assertSame(['SS27-TEE01/1', 'BASIC', 2, 28, 1], [$made['label'], $made['criticality'], $made['machine_types'], $made['machines'], count($made['processes'])]);
        $this->as()->postJson('/api/v1/workstudy/specs', $spec)->assertUnprocessable()->assertJsonPath('errors.variation_no.0', 'SS27-TEE01 already has variation 1.');

        // The New tab: no grids; variation 2 with its own nature.
        $this->as()->postJson('/api/v1/workstudy/specs', ['variation_no' => 2, 'sewing_smv' => 14.2] + $spec)->assertCreated()->assertJsonPath('data.criticality', 'SEMI_CRITICAL')->assertJsonPath('data.machine_types', 0);
        $this->assertSame(['SS27-TEE01/1', 'SS27-TEE01/2'], array_column($this->as()->getJson('/api/v1/workstudy/specs')->json('data'), 'label'));

        // Edit by style and variation; grids left out are kept, grids sent are replaced.
        $one = '/api/v1/workstudy/specs/one?style=ss27-tee01&v=1';
        $this->as()->getJson($one)->assertOk()->assertJsonPath('data.processes.0.specification', 'Rib collar 1x1, coverstitch');
        $this->as()->putJson($one, ['op_manpower' => 32] + $spec)->assertOk()->assertJsonPath('data.op_manpower', 32)->assertJsonPath('data.machine_types', 2);
        $this->as()->putJson($one, $spec + ['machines' => [['machine_type' => 'FLATLOCK', 'requirement' => 8]]])->assertOk()->assertJsonPath('data.machines', 8);
        $this->as()->putJson($one, ['variation_no' => 2] + $spec)->assertUnprocessable()->assertJsonValidationErrors('variation_no');
        $this->as()->getJson('/api/v1/workstudy/specs/one?style=SS27-TEE01&v=9')->assertUnprocessable();

        // The operation bulletin is attached and its link kept.
        $path = $this->as()->post('/api/v1/workstudy/ob-files', ['file' => UploadedFile::fake()->create('ob.pdf', 40, 'application/pdf')])->assertCreated()->json('data.path');
        $this->as()->putJson($one, ['ob_file_path' => $path] + $spec)->assertOk()->assertJsonPath('data.ob_file_path', $path);

        // The GMT Item List: each order against its style's latest specification; CM/Dz waits for Spec-Wise CM.
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $order = fn (string $no, string $style, string $type, ?string $unit) => Order::query()->create(['company_id' => $this->delta->id, 'order_no' => $no, 'job_no' => $no, 'style_no' => $style, 'buyer_id' => $buyer->id, 'factory_id' => $unit, 'item' => 'Ladies Tee', 'gmt_type' => 'Knit', 'order_type' => $type, 'total_qty' => 1900, 'unit' => 'PCS', 'conf_date' => '2026-10-12', 'ship_date' => '2026-12-20', 'status' => 'CONFIRMED']);
        $order('LPP-2026-000001', 'SS27-TEE01', 'BULK_NEW', $this->unit->id);
        $order('LPP-2026-000002', 'SS27-POLO', 'REPEAT', null);
        $list = $this->as()->getJson('/api/v1/workstudy/item-list')->assertOk()->json('data');
        $this->assertSame(['2026-10'], $list['filters']['months']);
        $rows = collect($list['rows'])->keyBy('order_no');
        $this->assertSame(['SS27-TEE01/2', 14.2, 'N', 'SPECIFIED', 0], [$rows['LPP-2026-000001']['variation'], (float) $rows['LPP-2026-000001']['smv'], $rows['LPP-2026-000001']['nature'], $rows['LPP-2026-000001']['status'], $rows['LPP-2026-000001']['cm_dz']]);
        $this->assertSame([null, 'R', 'NO_SPEC'], [$rows['LPP-2026-000002']['variation'], $rows['LPP-2026-000002']['nature'], $rows['LPP-2026-000002']['status']]);
        $this->assertSame(['LPP-2026-000002'], array_column($this->as()->getJson('/api/v1/workstudy/item-list?nature=R')->json('data.rows'), 'order_no'));
        $this->assertSame(['LPP-2026-000002'], array_column($this->as()->getJson('/api/v1/workstudy/item-list?not_in_house=1')->json('data.rows'), 'order_no'));
        $this->assertSame(['LPP-2026-000001'], array_column($this->as()->getJson('/api/v1/workstudy/item-list?q=tee01')->json('data.rows'), 'order_no'));
        $this->assertSame([], $this->as()->getJson('/api/v1/workstudy/item-list?month=2026-11')->json('data.rows'));
        $this->assertSame([], $this->as()->getJson('/api/v1/workstudy/item-list?my_buyer=1')->json('data.rows'));
    }

    public function test_item_groups_categories_and_tech_variations(): void
    {
        // As Brickland ships them: three groups and four categories, each with its code.
        $groups = $this->as()->getJson('/api/v1/erp/ws-item-groups')->assertOk()->json('data');
        $this->assertSame([['Knit Tops', 'GRP-KNIT', 3], ['Bottoms', 'GRP-BOTM', 1], ['Outerwear', 'GRP-OUTR', 0]], array_map(fn ($g) => [$g['name'], $g['code'], $g['categories']], $groups));
        $knit = $this->as()->getJson('/api/v1/workstudy/item-groups/grp-knit/categories')->assertOk()->json('data');
        $this->assertSame([['Basic T-Shirt', 'ITM-TSHIRT'], ['Polo Shirt', 'ITM-POLO'], ['Pullover Hoodie', 'ITM-HOODIE']], array_map(fn ($c) => [$c['name'], $c['code']], $knit));

        // New Itm Group: its code from its name; a second by the same name is refused.
        $this->as()->postJson('/api/v1/erp/ws-item-groups', ['name' => 'Kids Wear'])->assertCreated()->assertJsonPath('data.code', 'GRP-KIDSWEAR');
        $this->as()->postJson('/api/v1/erp/ws-item-groups', ['name' => 'Kids Wear'])->assertUnprocessable()->assertJsonValidationErrors('name');

        // Categories in place: added, renamed, and a duplicate refused.
        $this->as()->putJson('/api/v1/workstudy/item-groups/GRP-KNIT/categories', ['rows' => [...$knit, ['name' => 'polo shirt']]])->assertUnprocessable()->assertJsonValidationErrors('rows.3.name');
        $saved = $this->as()->putJson('/api/v1/workstudy/item-groups/GRP-KNIT/categories', ['rows' => [...$knit, ['name' => 'Crew Neck Sweatshirt', 'active' => true]]])->assertOk()->json('data');
        $this->assertSame(['ITM-CREWNECKSW', 4], [$saved[3]['code'], count($saved)]);

        // New tech variation: Brickland's message without a category; a category of another group is refused.
        $tshirt = $knit[0]['id'];
        $this->as()->postJson('/api/v1/erp/ws-tech-variations', ['tech_spec' => 'X'])->assertUnprocessable()->assertJsonPath('errors.garment_item_id.0', 'Itm Category is required.');
        $this->as()->postJson('/api/v1/erp/ws-tech-variations', ['item_group' => 'Bottoms', 'garment_item_id' => $tshirt, 'tech_spec' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('garment_item_id');
        $this->as()->postJson('/api/v1/erp/ws-tech-variations', ['item_group' => 'Knit Tops', 'garment_item_id' => $tshirt, 'tech_spec' => 'Crew neck, s/s, 1 colour print', 'op' => 28, 'aso' => 5, 'smv' => 8.2, 'band1' => 9.0, 'band6' => 7.6])->assertCreated()->assertJsonPath('data.category', 'Basic T-Shirt');

        // A category's variations in place; every line needs its technical specification.
        $rows = $this->as()->getJson('/api/v1/workstudy/categories/ITM-TSHIRT/variations')->json('data');
        $this->assertCount(1, $rows);
        $this->as()->putJson('/api/v1/workstudy/categories/ITM-TSHIRT/variations', ['rows' => [...$rows, ['tech_spec' => '']]])->assertUnprocessable()->assertJsonValidationErrors(['rows.1.tech_spec' => 'Technical Spec. is required.']);
        $after = $this->as()->putJson('/api/v1/workstudy/categories/ITM-TSHIRT/variations', ['rows' => [...$rows, ['tech_spec' => 'V-neck, l/s', 'smv' => '9.4', 'band3' => '9.1']]])->assertOk()->json('data');
        $this->assertSame([['Crew neck, s/s, 1 colour print', 8.2, 7.6], ['V-neck, l/s', 9.4, null]], array_map(fn ($v) => [$v['tech_spec'], $v['smv'], $v['band6']], $after));

        // A category quoted on a variation stays; a group with categories stays.
        $this->as()->putJson('/api/v1/workstudy/item-groups/GRP-KNIT/categories', ['rows' => array_slice($saved, 1)])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->deleteJson("/api/v1/erp/ws-item-groups/{$groups[0]['id']}")->assertUnprocessable();

        // Renaming a group carries its categories along.
        $this->as()->patchJson("/api/v1/erp/ws-item-groups/{$groups[1]['id']}", ['name' => 'Bottoms & Trousers'])->assertOk();
        $this->assertSame(['Twill Trouser'], array_column($this->as()->getJson('/api/v1/workstudy/item-groups/GRP-BOTM/categories')->json('data'), 'name'));

        // Who may: a store manager neither sees nor changes them; a factory manager does.
        $store = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($store)->getJson('/api/v1/workstudy/specs')->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->putJson('/api/v1/workstudy/categories/ITM-TSHIRT/variations', ['rows' => $after])->assertOk();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
