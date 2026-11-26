<?php

declare(strict_types=1);

namespace Tests\Feature\GeneralStore;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\GeneralStore\Models\GsStockMovement;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * General Store → Master Data (AnnotechERP 10/01): the store's own lists,
 * started from Brickland's; items whose opening stock is their first stock
 * movement; one default store; nothing deleted; Machinery's tables left
 * alone; and who may change them.
 */
class GeneralStoreMasterTest extends TestCase
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
        $this->as()->getJson('/api/v1/erp/store-categories')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.code', 'CLEAN');
        // Units are the one shared list: the platform's, plus Brickland's the platform lacks (CTN, FT, GM, PKT).
        $units = collect($this->as()->getJson('/api/v1/erp/store-units')->assertOk()->json('data'));
        $this->assertSame(UnitOfMeasure::query()->whereNull('company_id')->count() + 4, $units->count());
        $this->assertTrue($units->firstWhere('code', 'KG')['platform']);
        $this->assertFalse($units->firstWhere('code', 'CTN')['platform']);
        $this->as()->getJson('/api/v1/erp/store-departments')->assertJsonCount(12, 'data');
        $stores = collect($this->as()->getJson('/api/v1/erp/store-locations')->assertJsonCount(5, 'data')->json('data'));
        $this->assertSame(['MAIN'], $stores->where('is_default', true)->pluck('code')->values()->all());

        // Opening a list again does not add the defaults twice; codes repeat in no case.
        $this->as()->getJson('/api/v1/erp/store-categories')->assertJsonCount(10, 'data');
        $this->as()->postJson('/api/v1/erp/store-categories', ['code' => 'elec', 'name' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('code');
        // A platform unit's code is taken; a company unit joins the one list every module reads.
        $this->as()->postJson('/api/v1/erp/store-units', ['code' => 'kg', 'name' => 'Kilo again'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->postJson('/api/v1/erp/store-units', ['code' => 'tub', 'name' => 'Tub'])->assertCreated()->assertJsonPath('data.code', 'TUB');
        $this->assertSame($this->delta->id, UnitOfMeasure::query()->where('code', 'TUB')->value('company_id'));
        $kg = $units->firstWhere('code', 'KG');
        $this->as()->patchJson("/api/v1/erp/store-units/{$kg['id']}", ['name' => 'Kilo'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_a_new_item_posts_its_opening_stock(): void
    {
        $stat = collect($this->as()->getJson('/api/v1/erp/store-categories')->json('data'))->firstWhere('code', 'STAT');
        $ream = collect($this->as()->getJson('/api/v1/erp/store-units')->json('data'))->firstWhere('code', 'REAM');
        $main = collect($this->as()->getJson('/api/v1/erp/store-locations')->json('data'))->firstWhere('code', 'MAIN');
        $item = ['item_code' => 'itm-001', 'name' => 'A4 Paper', 'gs_category_id' => $stat['id'], 'gs_unit_id' => $ream['id'], 'brand' => 'Double A', 'minimum_stock' => 20, 'default_price' => 450, 'rack_location' => 'R-01'];

        $this->as()->postJson('/api/v1/erp/store-items', $item + ['opening_stock' => 10])->assertUnprocessable()->assertJsonValidationErrors('opening_store_id');
        $id = $this->as()->postJson('/api/v1/erp/store-items', $item + ['opening_stock' => 10, 'opening_store_id' => $main['id']])->assertCreated()
            ->assertJsonPath('data.item_code', 'ITM-001')->assertJsonPath('data.unit', 'REAM')->assertJsonPath('data.category', 'Stationery')
            ->assertJsonPath('data.stock', 10)->assertJsonPath('data.stock_status', 'LOW')->json('data.id');

        $opening = GsStockMovement::query()->where('gs_item_id', $id)->sole();
        $this->assertSame(['OPENING', $main['id'], 10.0, 450.0], [$opening->movement_type, $opening->gs_store_id, (float) $opening->quantity, (float) $opening->unit_price]);

        // The list brings the same figures; opening stock belongs to a new item only.
        $this->as()->getJson('/api/v1/erp/store-items')->assertJsonPath('data.0.stock', 10)->assertJsonPath('data.0.category', 'Stationery');
        $this->as()->getJson("/api/v1/erp/store-items?filter[gs_category_id]={$stat['id']}")->assertJsonCount(1, 'data');
        $this->as()->patchJson("/api/v1/erp/store-items/{$id}", ['opening_stock' => 5])->assertUnprocessable();
        $this->as()->patchJson("/api/v1/erp/store-items/{$id}", ['minimum_stock' => 5])->assertOk()->assertJsonPath('data.stock_status', 'OK');
        $this->as()->postJson('/api/v1/erp/store-items', ['item_code' => 'ITM-001'] + $item)->assertUnprocessable()->assertJsonValidationErrors('item_code');
        $this->as()->postJson('/api/v1/erp/store-items', ['item_code' => ''] + $item)->assertUnprocessable()->assertJsonPath('errors.item_code.0', 'Item code is required.');
        $this->as()->postJson('/api/v1/erp/store-items', ['item_code' => 'ITM-002', 'name' => 'Pen', 'gs_category_id' => $stat['id'], 'gs_unit_id' => $ream['id']])->assertCreated()->assertJsonPath('data.stock_status', 'OUT');
        $this->as()->deleteJson("/api/v1/erp/store-items/{$id}")->assertStatus(405);
        $this->assertSame(2, collect($this->as()->getJson('/api/v1/erp/store-categories')->json('data'))->firstWhere('code', 'STAT')['items']);
    }

    public function test_one_default_store_and_suppliers_of_the_stores_own(): void
    {
        $elec = $this->as()->postJson('/api/v1/erp/store-locations', ['code' => 'gen', 'name' => 'General Store', 'location' => 'Ground floor', 'in_charge' => 'Karim', 'is_default' => true])->assertCreated()->assertJsonPath('data.code', 'GEN')->json('data.id');
        $rows = collect($this->as()->getJson('/api/v1/erp/store-locations')->json('data'));
        $this->assertSame([$elec], $rows->where('is_default', true)->pluck('id')->values()->all());

        $this->as()->postJson('/api/v1/erp/store-suppliers', ['code' => 'sup-001', 'name' => 'Rahman Stationers', 'contact_person' => 'Rahman', 'mobile' => '01711000000', 'email' => 'sales@rahman.test'])
            ->assertCreated()->assertJsonPath('data.code', 'SUP-001')->assertJsonPath('data.active', true);
        $this->as()->postJson('/api/v1/erp/store-suppliers', ['code' => 'SUP-002', 'name' => 'X', 'email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(0, Vendor::query()->where('code', 'SUP-001')->count());
    }

    public function test_who_may_change_the_lists(): void
    {
        $manager = $this->token(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'));
        $this->withToken($manager)->getJson('/api/v1/erp/store-departments')->assertOk();
        $this->withToken($manager)->postJson('/api/v1/erp/store-departments', ['code' => 'LAB', 'name' => 'Lab'])->assertForbidden();
        $this->withToken($this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test')))->postJson('/api/v1/erp/store-departments', ['code' => 'LAB', 'name' => 'Lab'])->assertForbidden();
        $keeper = $this->token(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'admin@delta.test'));
        $this->withToken($keeper)->postJson('/api/v1/erp/store-departments', ['code' => 'LAB', 'name' => 'Lab'])->assertCreated();
        $technician = $this->token(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'));
        $this->withToken($technician)->getJson('/api/v1/erp/store-items')->assertForbidden();
    }

    private function token(User $user): string
    {
        return app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token')['plain'];
    }

    public function test_a_store_joins_the_one_store_list_in_a_warehouse(): void
    {
        $this->as()->getJson('/api/v1/erp/store-locations')->assertOk();
        $saved = $this->as()->postJson('/api/v1/erp/store-locations', ['code' => 'spr', 'name' => 'Spares Store', 'store_type' => 'GENERAL'])->assertCreated();

        // The shared list Machinery, Knitting and the store mappings read, placed in the company's warehouse.
        $row = DB::table('stores')->where('id', $saved->json('data.id'))->first();
        $this->assertSame(['SPR', 'GENERAL'], [$row->code, $row->store_type]);
        $this->assertNotNull($row->warehouse_id);
        $this->assertSame($this->delta->id, DB::table('warehouses')->where('id', $row->warehouse_id)->value('company_id'));
        $this->assertNotNull($saved->json('data.warehouse'));
    }

    public function test_categories_nest_three_levels_and_no_deeper(): void
    {
        $parent = null;
        foreach (['FAB', 'FAB-KNIT', 'FAB-KNIT-JERSEY'] as $code) {
            $parent = $this->as()->postJson('/api/v1/erp/store-categories', ['code' => $code, 'name' => $code, 'parent_id' => $parent])->assertCreated()->json('data.id');
        }

        $this->as()->postJson('/api/v1/erp/store-categories', ['code' => 'FAB-X', 'name' => 'Too deep', 'parent_id' => $parent])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        // The one category tree Access Control's category permission reads.
        $this->assertSame(3, DB::table('item_categories')->where('company_id', $this->delta->id)->where('code', 'like', 'FAB%')->count());
    }

    private function as(): static
    {
        return $this->withToken($this->token($this->owner));
    }
}
