<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Master Data (AnnotechERP 06/01): the Item Type Master
 * and the Nature of Work Master — started from the lists the Garment Items
 * step offered, a code once, a type once per item group, a nature without a
 * comma; the Garment Items step then offers exactly what the masters hold,
 * several natures at a time, kept as the text the item carries.
 */
class MerchandiseItemTypeWorkNatureTest extends TestCase
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

    public function test_the_masters_start_from_the_old_lists_and_feed_the_garment_items_step(): void
    {
        // The starting lists: every type of the four groups, the four natures.
        $types = $this->as()->getJson('/api/v1/erp/item-types')->assertOk()->json('data');
        $this->assertContains(['KNIT_TOP', 'KT-TSHIRT', 'T-Shirt'], array_map(fn ($t) => [$t['item_group'], $t['code'], $t['name']], $types));
        $this->assertSame(['KNIT_BOTTOM', 'KNIT_TOP', 'WOVEN_BOTTOM', 'WOVEN_TOP'], collect($types)->pluck('item_group')->unique()->sort()->values()->all());
        $natures = $this->as()->getJson('/api/v1/erp/work-natures')->assertOk()->json('data');
        $this->assertSame(['Sewing', 'Sewing + Print', 'Sewing + Embroidery', 'Cut & Make'], array_column($natures, 'name'));

        $this->as()->postJson('/api/v1/erp/item-types', [])->assertUnprocessable()->assertJsonValidationErrors(['item_group' => 'Item Group is required.', 'code' => 'Code is required.', 'name' => 'Item Type is required.']);
        $this->as()->postJson('/api/v1/erp/item-types', ['item_group' => 'KNIT_TOP', 'code' => 'kt-new', 'name' => 'T-Shirt'])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'This item group already has that item type.']);
        $this->as()->postJson('/api/v1/erp/item-types', ['item_group' => 'WOVEN_TOP', 'code' => ' it-wtsh ', 'name' => 'T-Shirt', 'remarks' => 'Woven tee', 'sort_order' => 5])->assertCreated()->assertJsonPath('data.code', 'IT-WTSH');
        $this->as()->postJson('/api/v1/erp/item-types', ['item_group' => 'KNIT_TOP', 'code' => 'IT-WTSH', 'name' => 'Raglan Tee'])->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $raglan = $this->as()->postJson('/api/v1/erp/item-types', ['item_group' => 'KNIT_TOP', 'code' => 'KT-RAGLAN', 'name' => 'Raglan Tee'])->assertCreated()->json('data.id');

        $this->as()->postJson('/api/v1/erp/work-natures', ['code' => 'NW-X', 'name' => 'Cutting, Sewing'])->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'A nature of work cannot contain a comma — a style item lists several, separated by commas.']);
        $this->as()->postJson('/api/v1/erp/work-natures', ['code' => 'nw-cut', 'name' => 'Cutting', 'sort_order' => 1])->assertCreated()->assertJsonPath('data.code', 'NW-CUT');
        $this->as()->postJson('/api/v1/erp/work-natures', ['code' => 'NW-CUT2', 'name' => 'Cutting'])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'That nature of work is already on the list.']);
        $printId = collect($this->as()->getJson('/api/v1/erp/work-natures')->json('data'))->firstWhere('name', 'Sewing + Print')['id'];
        $this->as()->patchJson("/api/v1/erp/work-natures/{$printId}", ['active' => false])->assertOk();

        // The Garment Items step offers what the masters hold, active only.
        $style = $this->style();
        $options = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/items")->assertOk()->json('data.options');
        $this->assertContains('Raglan Tee', $options['types']['KNIT_TOP']);
        $this->assertContains('T-Shirt', $options['types']['WOVEN_TOP']);
        $this->assertSame([], $options['types']['OTHER']);
        $this->assertSame(['Cutting', 'Sewing', 'Sewing + Embroidery', 'Cut & Make'], $options['nature_of_work']);

        $item = ['for_style_ids' => [$style], 'item_group' => 'KNIT_TOP', 'item_type' => 'Raglan Tee', 'division' => 'KIDS', 'short_name' => 'RT', 'full_name' => 'RAGLAN TEE', 'status' => 'ACTIVE'];
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", [...$item, 'item_type' => 'Woven Pant', 'nature_of_work' => ['Cutting']])->assertUnprocessable()->assertJsonValidationErrors(['item_type']);
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", [...$item, 'nature_of_work' => ['Cutting', 'Sewing + Print']])->assertUnprocessable()->assertJsonValidationErrors(['nature_of_work.1']);
        $saved = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", [...$item, 'nature_of_work' => ['Cutting', 'Sewing']])->assertCreated()->json('data');
        $this->assertSame('Cutting, Sewing', $saved['nature_of_work']);
        // An old code is still read as its name.
        $this->assertSame('Cut & Make', $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", [...$item, 'short_name' => 'RT2', 'nature_of_work' => 'CUT_MAKE'])->assertCreated()->json('data.nature_of_work'));

        // A type or nature an item carries is switched off, not removed.
        $this->as()->deleteJson("/api/v1/erp/item-types/{$raglan}")->assertUnprocessable();
        $cutting = collect($this->as()->getJson('/api/v1/erp/work-natures')->json('data'))->firstWhere('name', 'Cutting')['id'];
        $this->as()->deleteJson("/api/v1/erp/work-natures/{$cutting}")->assertUnprocessable();
        $this->as()->patchJson("/api/v1/erp/work-natures/{$cutting}", ['active' => false])->assertOk();
        // …and stays valid on the item that carries it.
        $this->as()->putJson("/api/v1/merchandise/style-items/{$saved['id']}", [...$item, 'nature_of_work' => ['Cutting', 'Sewing']])->assertOk();

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->postJson('/api/v1/erp/work-natures', ['code' => 'NW-FIN', 'name' => 'Finishing'])->assertForbidden();
    }

    private function style(): string
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Spolka Akcyjna Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');

        return $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
