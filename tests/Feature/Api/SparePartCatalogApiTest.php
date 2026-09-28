<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The remaining Spare Part catalogue gaps (docs/03-API-Specification.md
 * §13, plus web-only features not in the bare spec list): retiring or
 * deleting a catalogue entry, recording what a part fits or substitutes
 * for, receiving stock, reversing a posted movement, and the low-stock
 * list. Every write mirrors the web `SparePartController`,
 * `CompatibilityController` and `StockController` unchanged per ADR-003.
 */
class SparePartCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private Bin $bin;

    private User $storeManager;

    private string $storeManagerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->factory);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store-manager@delta.test');
        $this->storeManagerToken = app(IssueApiToken::class)
            ->forUser($this->storeManager, $this->delta->id, 'x')['plain'];
    }

    private function asStoreManager(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->storeManagerToken);

        return $this;
    }

    // -- Retire / delete ------------------------------------------------

    public function test_a_part_with_no_history_is_deleted(): void
    {
        $part = InventoryFixture::part($this->delta);

        $this->asStoreManager()->deleteJson("/api/v1/spare-parts/{$part->id}")->assertNoContent();

        $this->assertDatabaseMissing('spare_parts', ['id' => $part->id]);
    }

    public function test_a_part_with_ledger_history_cannot_be_deleted_only_deactivated(): void
    {
        $part = InventoryFixture::part($this->delta);
        app(ReceiveStock::class)->handle($part, $this->bin, '5', '100');

        $this->asStoreManager()->deleteJson("/api/v1/spare-parts/{$part->id}")->assertStatus(409);

        $toggle = $this->asStoreManager()->patchJson("/api/v1/spare-parts/{$part->id}/active", ['active' => false])
            ->assertOk();

        $this->assertFalse($toggle->json('data.active'));
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'active' => false]);
    }

    public function test_deleting_a_part_requires_the_permission(): void
    {
        $part = InventoryFixture::part($this->delta);
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $token = app(IssueApiToken::class)->forUser($engineer, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/spare-parts/{$part->id}")
            ->assertStatus(403);
    }

    // -- Compatibility ----------------------------------------------------

    public function test_a_part_is_marked_as_fitting_an_asset_model(): void
    {
        $part = InventoryFixture::part($this->delta);
        $manufacturer = Manufacturer::create([
            'company_id' => $this->delta->id, 'name' => 'Juki', 'code' => 'JUKI', 'active' => true,
        ]);

        $model = AssetModel::create([
            'company_id' => $this->delta->id,
            'manufacturer_id' => $manufacturer->id,
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'model' => 'Juki DDL-9000C',
            'code' => 'DDL-9000C',
            'active' => true,
        ]);

        $store = $this->asStoreManager()->postJson("/api/v1/spare-parts/{$part->id}/compatibility", [
            'compatibility_type' => 'FITS',
            'asset_model_id' => $model->id,
        ])->assertCreated();

        $this->assertSame('FITS', $store->json('data.compatibility_type'));
        $this->assertSame($model->id, $store->json('data.asset_model.id'));

        // Listing it twice is refused.
        $this->asStoreManager()->postJson("/api/v1/spare-parts/{$part->id}/compatibility", [
            'compatibility_type' => 'FITS',
            'asset_model_id' => $model->id,
        ])->assertStatus(422);

        $this->asStoreManager()->getJson("/api/v1/spare-parts/{$part->id}/compatibility")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->asStoreManager()
            ->deleteJson("/api/v1/spare-parts/{$part->id}/compatibility/".$store->json('data.id'))
            ->assertNoContent();
    }

    public function test_a_substitute_part_cannot_be_itself(): void
    {
        $part = InventoryFixture::part($this->delta);

        $this->asStoreManager()->postJson("/api/v1/spare-parts/{$part->id}/compatibility", [
            'compatibility_type' => 'SUBSTITUTE',
            'substitute_for_part_id' => $part->id,
        ])->assertStatus(422);
    }

    public function test_a_compatibility_row_from_another_part_is_not_found_through_this_one(): void
    {
        $part = InventoryFixture::part($this->delta);
        $other = InventoryFixture::part($this->delta, 'JK-OTHER', 'A different part');

        $store = $this->asStoreManager()->postJson("/api/v1/spare-parts/{$other->id}/compatibility", [
            'compatibility_type' => 'SUBSTITUTE',
            'substitute_for_part_id' => $part->id,
        ])->assertCreated();

        $this->asStoreManager()
            ->deleteJson("/api/v1/spare-parts/{$part->id}/compatibility/".$store->json('data.id'))
            ->assertNotFound();
    }

    // -- Receive / reverse ------------------------------------------------

    public function test_stock_is_received_and_sets_the_last_purchase_price(): void
    {
        $part = InventoryFixture::part($this->delta);

        $receipt = $this->asStoreManager()->postJson("/api/v1/spare-parts/{$part->id}/receive", [
            'bin_id' => $this->bin->id,
            'quantity' => 10,
            'unit_cost' => 250,
            'transaction_type' => 'RECEIPT',
        ])->assertCreated();

        $this->assertSame('RECEIPT', $receipt->json('data.transaction_type'));
        $this->assertSame('10.0000', $receipt->json('data.signed_quantity'));
        $this->assertSame('250.0000', $part->fresh()->unit_cost);
    }

    public function test_receiving_stock_requires_the_permission(): void
    {
        $part = InventoryFixture::part($this->delta);
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $token = app(IssueApiToken::class)->forUser($manager, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/spare-parts/{$part->id}/receive", [
                'bin_id' => $this->bin->id,
                'quantity' => 10,
                'unit_cost' => 250,
                'transaction_type' => 'RECEIPT',
            ])->assertStatus(403);
    }

    public function test_a_posted_movement_is_reversed_with_an_opposing_row(): void
    {
        $part = InventoryFixture::part($this->delta);

        $receipt = app(ReceiveStock::class)->handle($part, $this->bin, '10', '250', $this->storeManager->id);

        $reversal = $this->asStoreManager()
            ->postJson("/api/v1/inventory-transactions/{$receipt->id}/reverse", [
                'reason' => 'Wrong part number receipted by mistake',
            ])->assertCreated();

        $this->assertSame('ADJUSTMENT_OUT', $reversal->json('data.transaction_type'));
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $reversal->json('data.id'),
            'reverses_transaction_id' => $receipt->id,
        ]);

        // A transaction cannot be reversed twice.
        $this->asStoreManager()
            ->postJson("/api/v1/inventory-transactions/{$receipt->id}/reverse", ['reason' => 'Trying again'])
            ->assertStatus(409);
    }

    // -- Low stock ----------------------------------------------------------

    public function test_low_stock_lists_parts_at_or_below_their_reorder_level(): void
    {
        $low = InventoryFixture::part($this->delta, 'JK-LOW', 'Low stock part', ['reorder_level' => '5']);
        $healthy = InventoryFixture::part($this->delta, 'JK-HEALTHY', 'Healthy stock part', ['reorder_level' => '5']);

        app(ReceiveStock::class)->handle($low, $this->bin, '2', '100');
        app(ReceiveStock::class)->handle($healthy, $this->bin, '20', '100');

        $response = $this->asStoreManager()->getJson('/api/v1/spare-parts/low-stock')->assertOk();

        $partNumbers = collect($response->json('data'))->pluck('part_number')->all();

        $this->assertContains('JK-LOW', $partNumbers);
        $this->assertNotContains('JK-HEALTHY', $partNumbers);
    }

    // -- Ledger verification --------------------------------------------

    public function test_the_ledger_replays_to_the_balance_it_shows(): void
    {
        $part = InventoryFixture::part($this->delta);
        app(ReceiveStock::class)->handle($part, $this->bin, '10', '250');
        app(ReceiveStock::class)->handle($part->fresh(), $this->bin, '5', '300');

        $response = $this->asStoreManager()->getJson("/api/v1/spare-parts/{$part->id}/verify")->assertOk();

        $this->assertSame('15.0000', $response->json('data.0.balance'));
        $this->assertSame('15.0000', $response->json('data.0.replayed'));
        $this->assertTrue($response->json('data.0.matches'));
        $this->assertSame(2, $response->json('data.0.transactions'));
    }
}
