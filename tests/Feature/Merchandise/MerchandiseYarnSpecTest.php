<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric Booking → Style-Wise Yarn Spec Confirmation (AnnotechERP 06/11): a
 * style's yarn / fabric mappings — one per production category ticked —
 * confirmed and released, a confirmed one kept from removal, and the Spec
 * Confirmation list of styles, pending or confirmed.
 */
class MerchandiseYarnSpecTest extends TestCase
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

    public function test_a_mapping_is_saved_per_category_confirmed_released_and_kept_from_removal_while_confirmed(): void
    {
        [$style, $other] = $this->styles();
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true, 'is_body' => true])->id;
        $black = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Black', 'code' => 'COL-BLK'])->assertCreated()->json('data.id');

        $options = $this->as()->getJson('/api/v1/merchandise/yarn-specs/options')->assertOk()->json('data');
        $this->assertSame([['BULK', 'DEVELOP', 'SHORT_BULK', 'PROJECTION'], ['637PK', 'LPP', 'Hoodie'], 'Black (COL-BLK)', 'Delta Apparels Ltd'], [
            $options['categories'], [$options['styles'][0]['label'], $options['styles'][0]['buyer'], $options['styles'][0]['description']], $options['colours'][0]['label'], $options['company'],
        ]);

        $form = ['style_id' => $style, 'categories' => ['BULK', 'DEVELOP'], 'fabric_type_id' => $sj, 'gmt_item' => ' KIDS HOODIE ', 'part_ids' => [$body], 'gmt_colour_ids' => [$black], 'fab_colour_ids' => [$black]];
        $this->as()->postJson('/api/v1/merchandise/yarn-specs', ['fabric_type_id' => null] + $form)->assertUnprocessable()
            ->assertJsonValidationErrors(['fabric_type_id' => 'Fabric is needed before this can be saved.']);
        $this->as()->postJson('/api/v1/merchandise/yarn-specs', ['gmt_item' => ''] + $form)->assertUnprocessable()
            ->assertJsonValidationErrors(['gmt_item' => 'Gmt Item is needed before this can be saved.']);
        $this->as()->postJson('/api/v1/merchandise/yarn-specs', ['gmt_colour_ids' => []] + $form)->assertUnprocessable()
            ->assertJsonValidationErrors(['gmt_colour_ids' => 'Gmt Color is needed before this can be saved.']);
        $this->as()->postJson('/api/v1/merchandise/yarn-specs', ['fab_colour_ids' => []] + $form)->assertUnprocessable()
            ->assertJsonValidationErrors(['fab_colour_ids' => 'Fab Color is needed before this can be saved.']);
        // All Col? stands in for the garment colours.
        $this->as()->postJson('/api/v1/merchandise/yarn-specs', ['categories' => ['PROJECTION'], 'all_colours' => true, 'gmt_colour_ids' => []] + $form)->assertCreated();

        $saved = $this->as()->postJson('/api/v1/merchandise/yarn-specs', $form)->assertCreated()->json('data');
        $this->assertSame([['BULK', 'PENDING'], ['DEVELOP', 'PENDING']], array_map(fn ($r) => [$r['prod_category'], $r['status']], $saved));
        $this->assertSame(['Single Jersey', 'KIDS HOODIE', ['Body'], ['Black (COL-BLK)'], ['Black (COL-BLK)']], [$saved[0]['fabric'], $saved[0]['gmt_item'], $saved[0]['parts'], $saved[0]['gmt_colours'], $saved[0]['fab_colours']]);

        $bulk = $this->as()->getJson("/api/v1/merchandise/yarn-specs?style_id={$style}&category=BULK")->assertOk()->json('data');
        $this->assertCount(1, $bulk);
        $projection = $this->as()->getJson("/api/v1/merchandise/yarn-specs?style_id={$style}&category=PROJECTION")->json('data');
        $this->assertSame([true, []], [$projection[0]['all_colours'], $projection[0]['gmt_colours']]);

        $confirmed = $this->as()->postJson("/api/v1/merchandise/yarn-specs/{$bulk[0]['id']}/confirm")->assertOk()->json('data');
        $this->assertSame(['CONFIRMED', $this->owner->name], [$confirmed['status'], $confirmed['confirmed_by']]);
        $this->as()->deleteJson("/api/v1/merchandise/yarn-specs/{$bulk[0]['id']}")->assertUnprocessable()
            ->assertJsonValidationErrors(['spec' => 'A confirmed specification cannot be removed. Release it first.']);

        // The style is Confirmed only once every mapping is: still Pending with Develop and Projection open.
        $list = fn (array $q) => $this->as()->getJson('/api/v1/merchandise/yarn-specs/confirmation?'.http_build_query($q))->assertOk()->json('data');
        $this->assertSame(['637PK', 'PENDING', 'LPP Spolka Akcyjna', 3], [$list(['style_id' => $style])[0]['style'], $list(['style_id' => $style])[0]['status'], $list(['style_id' => $style])[0]['buyer'], $list(['style_id' => $style])[0]['specs']]);
        foreach ($this->as()->getJson("/api/v1/merchandise/yarn-specs?style_id={$style}&category=DEVELOP")->json('data') as $r) {
            $this->as()->postJson("/api/v1/merchandise/yarn-specs/{$r['id']}/confirm")->assertOk();
        }
        $this->as()->postJson("/api/v1/merchandise/yarn-specs/{$projection[0]['id']}/confirm")->assertOk();
        $done = $list(['state' => 'CONFIRMED']);
        $this->assertSame([['637PK', 'CONFIRMED', 100, now()->toDateString()]], array_map(fn ($r) => [$r['style'], $r['status'], $r['order_qty'], $r['confirm_date']], $done));
        // A style with nothing mapped is pending.
        $this->assertContains('900XL', array_column($list(['state' => 'PENDING']), 'style'));
        $this->assertSame([], $list(['state' => 'CONFIRMED', 'from' => now()->addDay()->toDateString()]));

        $released = $this->as()->postJson("/api/v1/merchandise/yarn-specs/{$bulk[0]['id']}/release")->assertOk()->json('data');
        $this->assertSame(['PENDING', null], [$released['status'], $released['confirmed_by']]);
        $this->assertSame('PENDING', $list(['style_id' => $style])[0]['status']);
        $this->as()->deleteJson("/api/v1/merchandise/yarn-specs/{$bulk[0]['id']}")->assertOk();
        $this->assertSame([], $this->as()->getJson("/api/v1/merchandise/yarn-specs?style_id={$style}&category=BULK")->json('data'));
        $this->assertNotEmpty($other);
    }

    public function test_specs_are_closed_to_a_technician_and_read_only_to_a_manager(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/merchandise/yarn-specs/options')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/merchandise/yarn-specs/confirmation')->assertOk();
        $this->as($manager)->postJson('/api/v1/merchandise/yarn-specs', [])->assertForbidden();
    }

    /** Two styles of one buyer, the first with an order of 100: [637PK, 900XL]. */
    private function styles(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $styles = array_map(fn (string $ref) => $this->as()->postJson('/api/v1/merchandise/styles', [
            'buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => $ref, 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]],
        ])->assertCreated()->json('data.id'), ['637PK', '900XL']);
        $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $styles[0], 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated();

        return $styles;
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
