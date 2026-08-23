<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Where spare parts are kept (SRS 23).
 *
 * Every stock movement names a bin, and until now nothing but a seeder could
 * create one — so a company that signed up could not receive a single part.
 * Three levels, because a factory store is not one room: warehouse, store, bin.
 */
class StorageLocationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function create(string $type, array $payload): TestResponse
    {
        return $this->actingAs($this->manager)
            ->from('/app/settings/master-data/'.$type)
            ->post('/app/settings/master-data/'.$type, $payload);
    }

    /**
     * The path a new company actually has to walk before it can hold stock.
     */
    public function test_a_company_can_build_its_store_from_the_screens(): void
    {
        $this->create('warehouses', [
            'factory_id' => $this->dhaka->id, 'name' => 'Main warehouse', 'code' => 'dhk-wh', 'active' => '1',
        ])->assertRedirect();

        $warehouse = Warehouse::where('code', 'DHK-WH')->firstOrFail();

        $this->create('stores', [
            'warehouse_id' => $warehouse->id, 'name' => 'Spare parts store', 'code' => 'DHK-ST', 'active' => '1',
        ])->assertRedirect();

        $store = Store::where('code', 'DHK-ST')->firstOrFail();

        $this->create('bins', [
            'store_id' => $store->id, 'name' => 'Rack A1', 'code' => 'DHK-A1', 'active' => '1',
        ])->assertRedirect();

        $bin = Bin::where('code', 'DHK-A1')->firstOrFail();

        $this->assertSame($this->delta->id, $bin->company_id);
        $this->assertSame($store->id, $bin->store_id);
        $this->assertFalse((bool) $bin->is_in_transit);

        // And the point of all three: stock can now be received.
        $part = InventoryFixture::part($this->delta);
        app(InventoryLedger::class)->post($part, $bin, 'RECEIPT', '10', '250');

        $this->assertSame('10.0000', $part->fresh()->totalOnHand());
    }

    /**
     * An in-transit bin holds stock that has left one factory and not reached
     * the other. Nobody puts anything in one by hand.
     */
    public function test_in_transit_bins_are_not_offered_for_management(): void
    {
        $store = Store::create([
            'company_id' => $this->delta->id,
            'warehouse_id' => Warehouse::create([
                'company_id' => $this->delta->id,
                'factory_id' => $this->dhaka->id,
                'name' => 'Main warehouse',
                'code' => 'DHK-WH',
                'active' => true,
            ])->id,
            'name' => 'Spare parts store',
            'code' => 'DHK-ST',
            'active' => true,
        ]);

        $transit = Bin::create([
            'company_id' => $this->delta->id,
            'store_id' => $store->id,
            'name' => 'In transit',
            'code' => 'DHK-TRANSIT',
            'is_in_transit' => true,
            'active' => true,
        ]);

        $shelf = Bin::create([
            'company_id' => $this->delta->id,
            'store_id' => $store->id,
            'name' => 'Rack A1',
            'code' => 'DHK-A1',
            'active' => true,
        ]);

        $response = $this->actingAs($this->manager)->get('/app/settings/master-data/bins');

        $response->assertOk()->assertSee($shelf->code)->assertDontSee($transit->code);

        // Nor reachable by id: editing one would be editing the accounting for
        // stock on a van.
        $this->actingAs($this->manager)
            ->get('/app/settings/master-data/bins?edit='.$transit->id)
            ->assertOk()
            ->assertDontSee($transit->name);
    }

    public function test_a_bin_holding_stock_cannot_be_removed(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta);

        app(InventoryLedger::class)->post($part, $bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->manager)
            ->from('/app/settings/master-data/bins')
            ->delete('/app/settings/master-data/bins/'.$bin->id)
            ->assertSessionHasErrors('code');

        // The ledger names this bin. Removing it would leave movements
        // pointing at a shelf that no longer exists.
        $this->assertNotNull(Bin::find($bin->id));
    }

    public function test_a_warehouse_with_a_store_in_it_cannot_be_removed(): void
    {
        InventoryFixture::bin($this->delta, $this->dhaka);

        $warehouse = Warehouse::firstOrFail();

        $this->actingAs($this->manager)
            ->from('/app/settings/master-data/warehouses')
            ->delete('/app/settings/master-data/warehouses/'.$warehouse->id)
            ->assertSessionHasErrors('code');

        $this->assertNotNull(Warehouse::find($warehouse->id));
    }

    public function test_an_empty_bin_can_be_removed(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-EMPTY');

        $this->actingAs($this->manager)
            ->delete('/app/settings/master-data/bins/'.$bin->id)
            ->assertRedirect();

        $this->assertNull(Bin::find($bin->id));
    }

    public function test_a_bin_cannot_be_hung_off_another_companys_store(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');
        TenantFixture::actingAsTenant($other);
        $theirBin = InventoryFixture::bin($other, $theirFactory, 'BTU-A1');

        TenantFixture::actingAsTenant($this->delta);

        $this->create('bins', [
            'store_id' => $theirBin->store_id, 'name' => 'Borrowed', 'code' => 'BORROWED', 'active' => '1',
        ])->assertSessionHasErrors('store_id');

        $this->assertSame(0, Bin::withoutGlobalScopes()->where('code', 'BORROWED')->count());
    }

    public function test_a_deactivated_bin_is_not_offered_when_receiving_stock(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);

        $this->actingAs($this->manager)
            ->post('/app/settings/master-data/bins/'.$bin->id.'/toggle')
            ->assertRedirect();

        $this->assertFalse((bool) $bin->fresh()->active);

        // A shelf taken out of use should stop appearing where stock is put
        // away, while the movements already against it stay readable.
        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        InventoryFixture::part($this->delta);

        $this->actingAs($storeManager)
            ->get('/app/inventory/stock')
            ->assertOk()
            ->assertDontSee('>'.$bin->code.'<', escape: false);
    }

    public function test_the_screens_are_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/settings/master-data/bins')->assertForbidden();
    }
}
