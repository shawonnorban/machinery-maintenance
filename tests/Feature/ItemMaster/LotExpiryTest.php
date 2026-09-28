<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\ItemMaster\Services\LotSuggestion;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * What a lot number means, and which drum to open first (Inventory spec
 * §12: yarn lot-wise, chemical expiry and FIFO).
 *
 * The order the suggestion puts lots in is the whole feature: nearest
 * expiry, then oldest received, with an expired lot last and flagged
 * rather than hidden — a lot that vanishes from the screen is a lot nobody
 * writes off.
 */
class LotExpiryTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private Item $dye;

    private ItemLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->ledger = app(ItemLedger::class);

        $this->dye = ItemFixture::item($this->delta, 'CHM-DYE-BLK', 'Reactive black dye', [
            'item_type' => 'CHEMICAL', 'tracking_mode' => 'LOT', 'uom_id' => ItemFixture::uomId($this->delta, 'KG'),
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    private function lot(string $lotNo, ?string $expiry, string $received): ItemLot
    {
        return ItemLot::create([
            'company_id' => $this->delta->id,
            'item_id' => $this->dye->id,
            'lot_no' => $lotNo,
            'expiry_date' => $expiry,
            'first_received_date' => $received,
        ]);
    }

    public function test_receiving_a_lot_records_what_its_number_means(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $vendor = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Chemico BD', 'code' => 'CH1',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $vendor->id,
            'grn_date' => '2026-09-01',
            'lines' => [[
                'item_id' => $this->dye->id,
                'received_qty' => '50',
                'rate' => '900',
                'bin_id' => $this->bin->id,
                'lot_no' => 'L-2026-07',
                'lot_expiry_date' => '2027-03-31',
                'lot_manufactured_date' => '2026-07-01',
                'supplier_lot_no' => 'MILL-77',
            ]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        $lot = ItemLot::where('lot_no', 'L-2026-07')->firstOrFail();

        $this->assertSame('2027-03-31', $lot->expiry_date->toDateString());
        $this->assertSame('2026-07-01', $lot->manufactured_date->toDateString());
        $this->assertSame('MILL-77', $lot->supplier_lot_no);
        $this->assertSame('2026-09-01', $lot->first_received_date->toDateString());

        // The item's own page now says which lot to open first.
        $lots = $this->withToken($token)->getJson("/api/v1/items/{$this->dye->id}")
            ->assertOk()->json('data.lots');

        $this->assertCount(1, $lots);
        $this->assertSame('L-2026-07', $lots[0]['lot_no']);
        $this->assertSame('50.0000', $lots[0]['available']);
        $this->assertFalse($lots[0]['expired']);
    }

    public function test_the_suggestion_is_nearest_expiry_then_fifo_with_expired_last(): void
    {
        // Three lots: one expiring soon, one later, one already gone.
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '10', '900', ['lot_no' => 'L-LATE']);
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '10', '900', ['lot_no' => 'L-SOON']);
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '10', '900', ['lot_no' => 'L-GONE']);

        $this->lot('L-LATE', now()->addYear()->toDateString(), '2026-01-01');
        $this->lot('L-SOON', now()->addMonth()->toDateString(), '2026-06-01');
        $this->lot('L-GONE', now()->subWeek()->toDateString(), '2025-01-01');

        $rows = app(LotSuggestion::class)->forItem($this->dye);

        $this->assertSame(['L-SOON', 'L-LATE', 'L-GONE'], array_column($rows, 'lot_no'));
        $this->assertTrue($rows[2]['expired']);
        // Listed, not hidden — somebody has to decide to scrap it.
        $this->assertSame('10.0000', $rows[2]['available']);

        // The pre-selection never lands on an expired lot.
        $this->assertSame('L-SOON', app(LotSuggestion::class)->bestFor($this->dye)['lot_no']);
    }

    public function test_a_lot_without_an_expiry_falls_back_to_plain_fifo(): void
    {
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '10', '900', ['lot_no' => 'L-NEW']);
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '10', '900', ['lot_no' => 'L-OLD']);

        $this->lot('L-NEW', null, '2026-06-01');
        $this->lot('L-OLD', null, '2026-01-01');

        $rows = app(LotSuggestion::class)->forItem($this->dye);

        $this->assertSame(['L-OLD', 'L-NEW'], array_column($rows, 'lot_no'));
    }

    public function test_the_sweep_warns_about_stock_that_will_expire_and_only_once(): void
    {
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '20', '900', ['lot_no' => 'L-SOON']);
        $this->lot('L-SOON', now()->addDays(10)->toDateString(), '2026-06-01');

        // A lot nobody has any of cannot go off; warning about it would
        // train people to ignore the warning.
        $this->lot('L-EMPTY', now()->addDays(5)->toDateString(), '2026-05-01');

        // And one comfortably inside its life.
        $this->ledger->post($this->dye, $this->bin, 'RECEIPT', '20', '900', ['lot_no' => 'L-FAR']);
        $this->lot('L-FAR', now()->addYear()->toDateString(), '2026-06-01');

        $this->artisan('itemmaster:expiry-alerts')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);

        $warnings = Notification::where('event_type', 'LOT_EXPIRING')
            ->where('user_id', $this->storekeeper->id)
            ->get();

        $this->assertCount(1, $warnings);
        $this->assertSame('item_lot', $warnings->first()->entity_type);

        // Swept again the same day, it stays at one.
        $this->artisan('itemmaster:expiry-alerts')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);

        $this->assertSame(1, Notification::where('event_type', 'LOT_EXPIRING')
            ->where('user_id', $this->storekeeper->id)->count());
    }
}
