<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Master Data, the reference lists (AnnotechERP 06/01 step
 * A): codes unique within a company, starting fabric types and fibers, fiber
 * compositions that total 100%, the GARM Part List, and who may change them.
 */
class MerchandiseMasterTest extends TestCase
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

    public function test_codes_are_unique_per_company_whatever_the_case(): void
    {
        $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => 'bd', 'name' => 'Bangladesh', 'region' => 'ASIAN'])->assertCreated()->assertJsonPath('data.code', 'BD');
        $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => 'BD', 'name' => 'Again'])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => 'XX', 'name' => 'Nowhere', 'region' => 'MARS'])->assertUnprocessable();

        $this->as()->postJson('/api/v1/erp/country-configs', ['code' => 'BD', 'name' => 'Bangladesh', 'currency' => 'bdt', 'dial_code' => '+880', 'is_ship_to' => true])
            ->assertCreated()->assertJsonPath('data.currency', 'BDT')->assertJsonPath('data.is_ship_to', true);

        // Another company may use the same code.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        $theirs = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        TenantFixture::actingAsTenant($other);
        $this->withToken($this->token($theirs, $other))->postJson('/api/v1/erp/origin-countries', ['code' => 'BD', 'name' => 'Bangladesh'])->assertCreated();
    }

    public function test_colours_and_sizes_are_unique_within_their_type_and_group(): void
    {
        $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy', 'pantone_no' => '19-4024'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'navy'])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'MELANGE', 'name' => 'Navy'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/colours', ['name' => 'No type'])->assertUnprocessable();

        $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'name' => 'M', 'display_order' => 3])->assertCreated();
        $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'name' => 'm'])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'NUMERIC', 'name' => 'M'])->assertCreated();
    }

    public function test_fabric_types_and_fibers_start_with_defaults_and_compositions_total_100(): void
    {
        $types = $this->as()->getJson('/api/v1/erp/fabric-types')->assertOk()->json('data');
        $this->assertCount(9, $types);
        $this->assertSame('SJ', $types[0]['code']);
        $fibers = collect($this->as()->getJson('/api/v1/erp/fibers')->json('data'))->keyBy('code');
        $this->assertCount(8, $fibers);

        // A derived type points at a base type; not every type is a base.
        $sj = collect($types)->firstWhere('code', 'SJ');
        $piq = collect($types)->firstWhere('code', 'PIQ');
        $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ-SLUB', 'name' => 'Slub Jersey', 'short_name' => 'SLUB', 'construction' => 'CIRCULAR', 'sort_order' => 95, 'parent_id' => $piq['id']])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ-SLUB', 'name' => 'Slub Jersey', 'short_name' => 'SLUB', 'construction' => 'CIRCULAR', 'sort_order' => 95, 'parent_id' => $sj['id']])
            ->assertCreated()->assertJsonPath('data.parent', 'Single Jersey');

        // 60 + 30 is not a composition; 60 + 40 is, described largest first.
        $this->as()->postJson('/api/v1/erp/fiber-compositions', ['code' => 'CVC6040', 'items' => [['fiber_id' => $fibers['POLY']['id'], 'percent' => 30], ['fiber_id' => $fibers['COT']['id'], 'percent' => 60]]])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/fiber-compositions', ['code' => 'CVC6040', 'items' => []])->assertUnprocessable();
        $id = $this->as()->postJson('/api/v1/erp/fiber-compositions', ['code' => 'cvc6040', 'items' => [['fiber_id' => $fibers['POLY']['id'], 'percent' => 40], ['fiber_id' => $fibers['COT']['id'], 'percent' => 60]]])
            ->assertCreated()->assertJsonPath('data.description', '60% Cotton 40% Polyester')->assertJsonPath('data.total', 100)->json('data.id');
        $this->as()->patchJson("/api/v1/erp/fiber-compositions/{$id}", ['items' => [['fiber_id' => $fibers['COT']['id'], 'percent' => 95], ['fiber_id' => $fibers['ELA']['id'], 'percent' => 5]]])
            ->assertOk()->assertJsonPath('data.description', '95% Cotton 5% Elastane');
        $this->as()->getJson('/api/v1/erp/fiber-compositions')->assertOk()->assertJsonPath('data.0.fiber_types', 'Cotton, Elastane');

        // A fiber a composition uses cannot be deleted.
        $this->as()->deleteJson("/api/v1/erp/fibers/{$fibers['COT']['id']}")->assertUnprocessable();
        $this->assertSame(1, collect($this->as()->getJson('/api/v1/erp/fibers')->json('data'))->firstWhere('code', 'COT')['used_by']);
    }

    public function test_the_garm_part_list_ticks_parts_per_fabric_type(): void
    {
        $sj = collect($this->as()->getJson('/api/v1/erp/fabric-types')->json('data'))->firstWhere('code', 'SJ')['id'];
        $body = $this->as()->postJson('/api/v1/erp/garment-parts', ['code' => 'BDY', 'name' => 'Body', 'fabric_group' => 'BODY', 'is_body' => true])->assertCreated()->json('data.id');
        $sleeve = $this->as()->postJson('/api/v1/erp/garment-parts', ['code' => 'slv', 'name' => 'Sleeve'])->assertCreated()->assertJsonPath('data.code', 'SLV')->json('data.id');

        $rows = $this->as()->putJson("/api/v1/merchandise/fabric-types/{$sj}/garment-parts", ['garment_part_ids' => [$body, $sleeve]])->assertOk()->json('data');
        $this->assertSame([true, true], array_column($rows, 'assigned'));
        $this->as()->putJson("/api/v1/merchandise/fabric-types/{$sj}/garment-parts", ['garment_part_ids' => [$body]])->assertOk();
        $this->assertSame(['Body'], collect($this->as()->getJson('/api/v1/erp/fabric-types')->json('data'))->firstWhere('code', 'SJ')['garment_parts']);

        // A yarn count row keeps its fabric type from being deleted.
        $this->as()->postJson('/api/v1/erp/yarn-count-configs', ['fabric_type_id' => $sj, 'gauge' => '24G', 'gsm_min' => 180, 'gsm_max' => 140])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/yarn-count-configs', ['fabric_type_id' => $sj, 'template' => 'COTTON_100', 'gauge' => '24G', 'gsm_min' => 140, 'gsm_max' => 180, 'count_top' => '30/1'])
            ->assertCreated()->assertJsonPath('data.construction', 'Single Jersey');
        $this->as()->deleteJson("/api/v1/erp/fabric-types/{$sj}")->assertUnprocessable();
    }

    public function test_only_master_data_keepers_change_the_lists(): void
    {
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->token($manager))->getJson('/api/v1/erp/colours')->assertOk();
        $this->withToken($this->token($manager))->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Red'])->assertForbidden();
        $this->withToken($this->token($technician))->getJson('/api/v1/erp/colours')->assertForbidden();
    }

    private function token(?User $user = null, ?Company $company = null): string
    {
        return app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken($this->token());
    }
}
