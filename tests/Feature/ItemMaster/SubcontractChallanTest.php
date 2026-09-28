<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
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
 * Fabric out for dyeing, printing or embroidery (Inventory spec §8).
 *
 * The cloth leaves the building and stays the factory's, so the two things
 * worth pinning down are that nothing reaches the stock ledger and that
 * sent = returned + wastage before a line is settled.
 */
class SubcontractChallanTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private Item $fabric;

    private Vendor $dyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->dyer = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Savar Dyeing Ltd', 'code' => 'SD1',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $this->fabric->id,
            'fabric_type' => 'KNIT', 'gsm' => '180', 'width' => '72', 'composition' => '100% cotton',
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    private function roll(string $rollNo, string $netLength): FabricRoll
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->dyer->id,
            'lines' => [['item_id' => $this->fabric->id, 'received_qty' => $netLength, 'rate' => '250']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => [[
                'roll_no' => $rollNo, 'bin_id' => $this->bin->id,
                'net_length' => $netLength, 'gross_length' => $netLength,
            ]]],
        ])->assertOk();

        $roll = FabricRoll::where('roll_no', $rollNo)->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/inspect", [
            'grade' => 'A', 'shade_match' => true,
        ])->assertOk();

        return $roll->fresh();
    }

    /**
     * @param  list<string>  $rollIds
     * @return array<string, mixed>
     */
    private function challan(string $token, array $rollIds): array
    {
        return $this->withToken($token)->postJson('/api/v1/subcontract-challans', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->dyer->id,
            'purpose' => 'DYEING',
            'expected_return_date' => '2026-10-20',
            'roll_ids' => $rollIds,
        ])->assertCreated()->json('data');
    }

    public function test_sending_takes_the_rolls_off_the_shelf_without_touching_the_ledger(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-100', '100');

        $challan = $this->challan($token, [$roll->id]);

        $this->assertStringStartsWith('SC-DHK-', $challan['challan_no']);
        $this->assertSame('DRAFT', $challan['status']);
        $this->assertSame('100.00', $challan['lines'][0]['sent_qty']);

        // A draft is a plan; the cloth has not moved.
        $this->assertSame('AVAILABLE', $roll->fresh()->status);

        $sent = $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/send")
            ->assertOk()->json('data');

        $this->assertSame('SENT', $sent['status']);
        $this->assertSame('SENT_FOR_SUBCONTRACT', $roll->fresh()->status);

        // Nothing was consumed and nothing was sold, so the ledger is silent.
        $this->assertSame(0, ItemStockTransaction::count());
    }

    public function test_a_roll_out_at_a_dyer_cannot_be_sent_twice(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-200', '100');

        $first = $this->challan($token, [$roll->id]);
        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$first['id']}/send")->assertOk();

        $this->withToken($token)->postJson('/api/v1/subcontract-challans', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->dyer->id,
            'purpose' => 'PRINTING',
            'roll_ids' => [$roll->id],
        ])->assertStatus(409);
    }

    public function test_a_partial_return_leaves_the_challan_open_and_the_rest_out(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $first = $this->roll('R-300', '100');
        $second = $this->roll('R-301', '80');

        $challan = $this->challan($token, [$first->id, $second->id]);
        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/send")->assertOk();

        $lines = collect($challan['lines'])->keyBy('roll_no');

        // The first roll comes back short: 96 of cloth and 4 lost to
        // shrinkage — which together account for the 100 that went out.
        $afterFirst = $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/returns", [
            'lines' => [[
                'line_id' => $lines['R-300']['id'],
                'returned_qty' => '96',
                'wastage_qty' => '4',
                'quality_status' => 'Shade matched',
            ]],
        ])->assertOk()->json('data');

        $this->assertSame('PARTIALLY_RETURNED', $afterFirst['status']);

        $returnedLine = collect($afterFirst['lines'])->firstWhere('roll_no', 'R-300');
        $this->assertSame('RETURNED', $returnedLine['status']);
        $this->assertSame('0.00', $returnedLine['outstanding_qty']);

        // The roll is back on the shelf, and genuinely shorter than it was.
        $this->assertSame('AVAILABLE', $first->fresh()->status);
        $this->assertSame('96.00', (string) $first->fresh()->net_length);

        // The second is still at the dyer.
        $this->assertSame('SENT_FOR_SUBCONTRACT', $second->fresh()->status);

        $done = $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/returns", [
            'lines' => [['line_id' => $lines['R-301']['id'], 'returned_qty' => '80']],
        ])->assertOk()->json('data');

        $this->assertSame('CLOSED', $done['status']);
        $this->assertSame('AVAILABLE', $second->fresh()->status);
        $this->assertSame(0, ItemStockTransaction::count());
    }

    public function test_more_cannot_come_back_than_went_out(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-400', '100');

        $challan = $this->challan($token, [$roll->id]);
        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/send")->assertOk();

        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/returns", [
            'lines' => [['line_id' => $challan['lines'][0]['id'], 'returned_qty' => '120']],
        ])->assertStatus(409);

        $this->assertSame('SENT_FOR_SUBCONTRACT', $roll->fresh()->status);
    }

    public function test_closing_writes_off_what_never_came_back(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-500', '100');

        $challan = $this->challan($token, [$roll->id]);
        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/send")->assertOk();

        $closed = $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/close", [
            'reason' => 'Dyer went out of business',
        ])->assertOk()->json('data');

        $this->assertSame('CLOSED', $closed['status']);
        $this->assertSame('WRITTEN_OFF', $closed['lines'][0]['status']);
        // Not left as stock the factory does not have.
        $this->assertSame('DAMAGED', $roll->fresh()->status);
    }

    public function test_cancelling_a_sent_challan_puts_the_rolls_back(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-600', '100');

        $challan = $this->challan($token, [$roll->id]);
        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/send")->assertOk();

        $this->withToken($token)->postJson("/api/v1/subcontract-challans/{$challan['id']}/cancel", [
            'reason' => 'Sent by mistake',
        ])->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame('AVAILABLE', $roll->fresh()->status);
    }

    public function test_the_floor_cannot_send_fabric_out(): void
    {
        $roll = $this->roll('R-700', '100');
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->tokenFor($technician))->postJson('/api/v1/subcontract-challans', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->dyer->id,
            'purpose' => 'DYEING',
            'roll_ids' => [$roll->id],
        ])->assertForbidden();
    }
}
