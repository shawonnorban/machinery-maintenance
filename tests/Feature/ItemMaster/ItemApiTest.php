<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\Tenancy\Models\Company;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The item library over the API (Master Data spec §2): its own table, its
 * own screen, its own permissions — separate from the spare parts catalogue.
 */
class ItemApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $storeManager;

    private User $storekeeper;

    private User $technician;

    private string $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->kg = UnitOfMeasure::availableTo($this->delta->id)->where('code', 'KG')->value('id');
    }

    public function test_an_item_takes_its_type_from_the_department_and_carries_its_spec(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $fabricStore = ItemDepartment::create([
            'company_id' => $this->delta->id, 'name' => 'Fabric Store', 'code' => 'FABSTORE', 'default_item_type' => 'FABRIC',
        ]);

        // Derived type, but no spec: refused, naming the missing fields.
        $this->withToken($token)
            ->postJson('/api/v1/items', [
                'item_code' => 'FAB-SJ-180', 'name' => 'Single jersey 180', 'uom_id' => $this->kg,
                'item_department_id' => $fabricStore->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fabric.gsm', 'fabric.width', 'fabric.composition']);

        $created = $this->withToken($token)
            ->postJson('/api/v1/items', [
                'item_code' => 'FAB-SJ-180', 'name' => 'Single jersey 180', 'uom_id' => $this->kg,
                'item_department_id' => $fabricStore->id, 'tracking_mode' => 'ROLL',
                'fabric' => ['fabric_type' => 'KNIT', 'gsm' => '180', 'width' => '72', 'composition' => '100% cotton'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item_type', 'FABRIC')
            ->assertJsonPath('data.tracking_mode', 'ROLL')
            ->assertJsonPath('data.item_department.id', $fabricStore->id)
            ->assertJsonPath('data.uom.code', 'KG')
            ->assertJsonPath('data.fabric.gsm', '180.00')
            ->assertJsonPath('data.fabric.composition', '100% cotton');

        $itemId = $created->json('data.id');

        // An edit that leaves the spec block out keeps the stored spec.
        $this->withToken($token)
            ->patchJson("/api/v1/items/{$itemId}", ['item_code' => 'FAB-SJ-180', 'name' => 'Single jersey 180 gsm'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Single jersey 180 gsm')
            ->assertJsonPath('data.fabric.width', '72.00');

        $this->withToken($token)
            ->getJson('/api/v1/items?item_type=FABRIC')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemId);

        $this->withToken($token)
            ->getJson('/api/v1/items?search=JERSEY%20180')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/items?item_type=YARN')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_item_with_nothing_said_about_it_is_general_and_quantity_tracked(): void
    {
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/items', ['item_code' => 'GEN-1', 'name' => 'Gloves', 'uom_id' => $this->kg])
            ->assertCreated()
            ->assertJsonPath('data.item_type', 'GENERAL')
            ->assertJsonPath('data.tracking_mode', 'QUANTITY')
            ->assertJsonPath('data.is_returnable', false)
            ->assertJsonPath('data.fabric', null);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/items', ['item_code' => 'GEN-1', 'name' => 'Duplicate code', 'uom_id' => $this->kg])
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_code');
    }

    public function test_the_category_pickers_must_cascade(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $fabric = ItemCategory::create(['company_id' => $this->delta->id, 'name' => 'Fabric', 'code' => 'FAB', 'active' => true]);
        $knit = ItemCategory::create(['company_id' => $this->delta->id, 'parent_id' => $fabric->id, 'name' => 'Knit', 'code' => 'FAB-KNIT', 'active' => true]);
        $stray = ItemCategory::create(['company_id' => $this->delta->id, 'name' => 'Electrical', 'code' => 'ELEC', 'active' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/items', [
                'item_code' => 'C-1', 'name' => 'x', 'uom_id' => $this->kg,
                'category_id' => $stray->id, 'sub_category_id' => $knit->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sub_category_id');

        $this->withToken($token)
            ->postJson('/api/v1/items', [
                'item_code' => 'C-1', 'name' => 'x', 'uom_id' => $this->kg,
                'category_id' => $fabric->id, 'sub_category_id' => $knit->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sub_category.name', 'Knit');

        // The form-options payload carries parent_id so the pickers can cascade.
        $this->withToken($token)
            ->getJson('/api/v1/items/form-options')
            ->assertOk()
            ->assertJsonFragment(['id' => $knit->id, 'name' => 'Knit', 'parent_id' => $fabric->id])
            ->assertJsonPath('data.item_types', Item::ITEM_TYPES)
            ->assertJsonStructure(['data' => ['item_departments', 'categories', 'uoms', 'stores', 'hs_codes', 'vendors', 'tracking_modes']]);
    }

    /**
     * Anyone who raises a requisition reads the library — a technician
     * picks items on a floor requisition (Phase 1) — but only the store
     * manager and up maintain it.
     */
    public function test_reading_and_maintaining_are_separate_permissions(): void
    {
        $item = Item::create(['company_id' => $this->delta->id, 'item_code' => 'R-1', 'name' => 'Rivet', 'uom_id' => $this->kg]);

        $this->withToken($this->tokenFor($this->storekeeper))->getJson('/api/v1/items')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($this->tokenFor($this->storekeeper))->getJson("/api/v1/items/{$item->id}")->assertOk();
        $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson('/api/v1/items', ['item_code' => 'R-2', 'name' => 'x', 'uom_id' => $this->kg])
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))->getJson('/api/v1/items')->assertOk();
        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/items', ['item_code' => 'R-3', 'name' => 'x', 'uom_id' => $this->kg])
            ->assertForbidden();
    }

    public function test_an_item_can_be_retired_restored_and_removed(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $item = Item::create(['company_id' => $this->delta->id, 'item_code' => 'R-1', 'name' => 'Rivet', 'uom_id' => $this->kg]);

        $this->withToken($token)->patchJson("/api/v1/items/{$item->id}/active", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);

        $this->withToken($token)->getJson('/api/v1/items?active=0')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/v1/items?active=1')->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($token)->patchJson("/api/v1/items/{$item->id}/active", ['active' => true])
            ->assertOk()->assertJsonPath('data.active', true);

        $this->withToken($token)->deleteJson("/api/v1/items/{$item->id}")->assertNoContent();
        $this->assertNull(Item::find($item->id));
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
