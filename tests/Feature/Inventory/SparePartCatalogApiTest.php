<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The spare-parts catalogue, over the API (API 13, SRS 22) — what a part is,
 * not what is on the shelf. Stock only ever moves through the ledger.
 */
class SparePartCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $storeManager;

    private User $storekeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
    }

    public function test_a_part_can_be_catalogued_and_updated(): void
    {
        $created = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/spare-parts', [
                'part_number' => 'JK-DDL9000-HOOK',
                'name' => 'Rotary hook, Juki DDL-9000C',
                'unit' => 'PCS',
                'minimum_stock' => 2,
                'reorder_level' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.part_number', 'JK-DDL9000-HOOK')
            ->assertJsonPath('data.active', true);

        $partId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->patchJson("/api/v1/spare-parts/{$partId}", [
                'part_number' => 'JK-DDL9000-HOOK',
                'name' => 'Rotary hook, Juki DDL-9000C (genuine)',
                'unit' => 'PCS',
                'reorder_level' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rotary hook, Juki DDL-9000C (genuine)')
            ->assertJsonPath('data.reorder_level', '8.0000');
    }

    public function test_a_duplicate_part_number_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/spare-parts', [
                'part_number' => 'JK-DDL9000-HOOK', 'name' => 'Rotary hook', 'unit' => 'PCS',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/spare-parts', [
                'part_number' => 'JK-DDL9000-HOOK', 'name' => 'Different name', 'unit' => 'PCS',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('part_number');
    }

    public function test_a_storekeeper_can_read_but_not_catalogue_a_part(): void
    {
        $this->withToken($this->tokenFor($this->storekeeper))
            ->getJson('/api/v1/spare-parts')
            ->assertOk();

        $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson('/api/v1/spare-parts', [
                'part_number' => 'X', 'name' => 'X', 'unit' => 'PCS',
            ])
            ->assertForbidden();
    }

    public function test_a_storekeeper_can_list_bins_to_receive_stock_against(): void
    {
        // STOREKEEPER has inventory.stock.receive but not masterdata.manage
        // — the exact gap this endpoint exists to close (bins are a
        // master-data type everywhere else in the API).
        $bin = InventoryFixture::bin($this->delta, $this->factory);

        $response = $this->withToken($this->tokenFor($this->storekeeper))
            ->getJson('/api/v1/spare-parts/bins')
            ->assertOk();

        $this->assertContains($bin->id, array_column($response->json('data'), 'id'));
    }

    /**
     * STORE_MANAGER has `inventory.part.create`/`.update` but not
     * `masterdata.manage` (`RoleSeeder`'s `$storeManager` never merges from
     * `$factoryManager`, which is where that permission is granted) — the
     * category and asset-model dropdowns the create/edit and compatibility
     * forms need have to be their own lookup, same reasoning as `bins()`.
     */
    public function test_a_store_manager_can_reach_form_options_without_masterdata_permission(): void
    {
        $this->assertFalse($this->storeManager->can('masterdata.manage'));
        $this->assertTrue($this->storeManager->can('inventory.part.create'));

        $this->withToken($this->tokenFor($this->storeManager))
            ->getJson('/api/v1/spare-parts/form-options')
            ->assertOk()
            ->assertJsonStructure(['data' => ['categories', 'asset_models']]);
    }

    /**
     * `GET /inventory-balances` mirrors the web `StockController::index` —
     * what's on the shelf right now, bin by bin, across every part, as
     * opposed to `GET /spare-parts/{id}/stock`'s single-part view. Built for
     * Phase D's "bin-centric stock view" (docs/12-Stack-Migration-
     * Implementation-Plan.md), which had no API of its own before this.
     */
    public function test_balances_list_across_every_part_and_bin_with_totals(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->factory);
        $partId = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/spare-parts', [
                'part_number' => 'JK-DDL9000-HOOK', 'name' => 'Rotary hook', 'unit' => 'PCS',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/spare-parts/{$partId}/receive", [
                'bin_id' => $bin->id,
                'quantity' => 10,
                'unit_cost' => 250,
                'transaction_type' => 'RECEIPT',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->storeManager))
            ->getJson('/api/v1/inventory-balances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.spare_part.id', $partId)
            ->assertJsonPath('data.0.bin.id', $bin->id)
            ->assertJsonPath('data.0.on_hand', '10.0000')
            ->assertJsonPath('data.0.total_value', '2500.0000')
            ->assertJsonPath('meta.totals.value', '2500.0000')
            ->assertJsonPath('meta.totals.lines', '1');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
