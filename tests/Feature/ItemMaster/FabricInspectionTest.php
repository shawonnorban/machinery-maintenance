<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
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
 * Fabric roll creation and inspection (Inventory spec §3, §4; Phase 2).
 *
 * A ROLL-tracked item never posts to the item stock ledger — completing a
 * GRN for one creates physical roll rows instead, and the receipt itself
 * waits in UNDER_INSPECTION until every roll it produced is resolved.
 */
class FabricInspectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $binA;

    private Bin $binB;

    private User $storekeeper;

    private Vendor $vendor;

    private Item $fabric;

    private Item $bolt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->binA = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A1');
        $this->binB = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A2');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->vendor = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Dhaka Fabrics Ltd', 'code' => 'DF2', 'vendor_type' => 'SUPPLIER',
            'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $this->fabric->id,
            'fabric_type' => 'KNIT', 'gsm' => '180', 'width' => '72', 'composition' => '100% cotton',
        ]);

        $this->bolt = ItemFixture::item($this->delta);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @return array{grn: array<string, mixed>, lineId: string}
     */
    private function directFabricReceipt(string $token, array $rolls): array
    {
        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->binA->store_id,
            'vendor_id' => $this->vendor->id,
            'lines' => [['item_id' => $this->fabric->id, 'received_qty' => '100', 'rate' => '250']],
        ])->assertCreated()->json('data');

        $lineId = $grn['lines'][0]['id'];

        $response = $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$lineId => $rolls],
        ]);

        return ['grn' => $grn, 'lineId' => $lineId, 'response' => $response];
    }

    public function test_completing_a_fabric_line_requires_at_least_one_roll(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        ['response' => $response] = $this->directFabricReceipt($token, []);

        $response->assertStatus(422)->assertJsonValidationErrors(['lines.0.rolls']);
        $this->assertSame(0, FabricRoll::count());
    }

    public function test_completing_creates_rolls_under_inspection_and_the_grn_waits(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        ['grn' => $grn, 'response' => $response] = $this->directFabricReceipt($token, [
            ['roll_no' => 'R-0001', 'net_length' => '50', 'gross_length' => '51', 'shade' => 'Navy', 'bin_id' => $this->binA->id],
            ['roll_no' => 'R-0002', 'net_length' => '48', 'gross_length' => '49', 'shade' => 'Navy', 'bin_id' => $this->binB->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'UNDER_INSPECTION')
            ->assertJsonPath('data.lines.0.inspection_status', 'PENDING')
            ->assertJsonCount(2, 'data.lines.0.rolls');

        $rolls = FabricRoll::orderBy('roll_no')->get();
        $this->assertCount(2, $rolls);
        $this->assertSame('UNDER_INSPECTION', $rolls[0]->status);
        // Provisional Unit Cost — the GRN line's rate (Inventory spec §10).
        $this->assertSame('250.0000', (string) $rolls[0]->unit_cost);
        $this->assertSame($this->binA->id, $rolls[0]->bin_id);
        $this->assertSame($this->binB->id, $rolls[1]->bin_id);
        $this->assertSame($grn['id'], $rolls[0]->goods_receipt_id);

        // Cancelling while a roll is still under inspection is refused.
        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/cancel")->assertStatus(409);
    }

    public function test_a_mixed_receipt_posts_the_quantity_line_immediately(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->binA->store_id,
            'lines' => [
                ['item_id' => $this->fabric->id, 'received_qty' => '20', 'rate' => '250'],
                ['item_id' => $this->bolt->id, 'received_qty' => '30', 'rate' => '2'],
            ],
        ])->assertCreated()->json('data');

        $fabricLineId = $grn['lines'][0]['id'];

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'bins' => [$grn['lines'][1]['id'] => $this->binA->id],
            'rolls' => [$fabricLineId => [['roll_no' => 'R-MIX-1', 'net_length' => '20', 'bin_id' => $this->binA->id]]],
        ])->assertOk()->assertJsonPath('data.status', 'UNDER_INSPECTION');

        // The bolt (QUANTITY) posted right away; the fabric line is still pending.
        $this->assertSame('30.0000', $this->bolt->fresh()->totalOnHand());
    }

    public function test_accepting_a_roll_makes_it_available_and_completes_the_grn_once_all_resolved(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        ['response' => $response] = $this->directFabricReceipt($token, [
            ['roll_no' => 'R-A1', 'net_length' => '50', 'bin_id' => $this->binA->id],
        ]);

        $rollId = $response->json('data.lines.0.rolls.0.id');
        $lineId = $response->json('data.lines.0.id');
        $grnId = $response->json('data.id');

        $formOptions = $this->withToken($token)->getJson('/api/v1/fabric-rolls/form-options')->json('data');
        $this->assertArrayHasKey('KNIT', $formOptions['defects']);
        $knitDefectId = $formOptions['defects']['KNIT'][0]['id'];

        $accepted = $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$rollId}/inspect", [
            'actual_width' => '71.5',
            'shade_match' => true,
            'grade' => 'A',
            'defect_ids' => [$knitDefectId],
            'notes' => 'Minor snagging, within tolerance',
        ]);

        $accepted->assertOk()
            ->assertJsonPath('data.status', 'AVAILABLE')
            ->assertJsonPath('data.inspection_grade', 'A')
            ->assertJsonCount(1, 'data.defects');

        // A second inspection of the same roll is refused.
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$rollId}/inspect", ['grade' => 'A'])->assertStatus(409);

        $this->withToken($token)->getJson("/api/v1/goods-receipts/{$grnId}")
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.lines.0.inspection_status', 'PASSED');

        $this->withToken($token)->getJson("/api/v1/items/{$this->fabric->id}")
            ->assertJsonPath('data.stock', null)
            ->assertJsonPath('data.rolls.roll_count', 1)
            ->assertJsonPath('data.rolls.by_status.AVAILABLE', 1)
            ->assertJsonPath('data.rolls.net_length', '50.00');

        // A woven-only defect is refused against this knit item.
        $wovenDefectId = FabricDefect::where('fabric_family', 'WOVEN')->where('code', 'SLUB')->value('id');
        $line2 = $this->directFabricReceipt($token, [['roll_no' => 'R-A2', 'net_length' => '10', 'bin_id' => $this->binA->id]]);
        $roll2 = $line2['response']->json('data.lines.0.rolls.0.id');
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll2}/inspect", ['grade' => 'A', 'defect_ids' => [$wovenDefectId]])
            ->assertStatus(422)->assertJsonValidationErrors(['defect_ids']);
    }

    public function test_rejecting_a_roll_needs_a_disposition_and_the_line_goes_partial_when_mixed(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        ['response' => $response] = $this->directFabricReceipt($token, [
            ['roll_no' => 'R-B1', 'net_length' => '50', 'bin_id' => $this->binA->id],
            ['roll_no' => 'R-B2', 'net_length' => '40', 'bin_id' => $this->binA->id],
        ]);

        $rolls = $response->json('data.lines.0.rolls');
        $grnId = $response->json('data.id');

        // Grade REJECT with no disposition is refused.
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$rolls[0]['id']}/inspect", ['grade' => 'REJECT'])
            ->assertStatus(422)->assertJsonValidationErrors(['disposition']);

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$rolls[0]['id']}/inspect", [
            'grade' => 'REJECT', 'disposition' => 'DAMAGED', 'notes' => 'Torn edge throughout',
        ])->assertOk()->assertJsonPath('data.status', 'DAMAGED');

        // Only one of two resolved: the line and the GRN both still wait.
        $this->withToken($token)->getJson("/api/v1/goods-receipts/{$grnId}")
            ->assertJsonPath('data.status', 'UNDER_INSPECTION')
            ->assertJsonPath('data.lines.0.inspection_status', 'PENDING');

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$rolls[1]['id']}/inspect", ['grade' => 'B'])
            ->assertOk()->assertJsonPath('data.status', 'AVAILABLE');

        $this->withToken($token)->getJson("/api/v1/goods-receipts/{$grnId}")
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.lines.0.inspection_status', 'PARTIAL');
    }

    public function test_provisional_cost_from_a_purchase_order_line_carries_onto_the_roll(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        // Raising and approving an order is the store manager's job, not
        // the storekeeper's (RoleSeeder); receiving against it is.
        $managerToken = $this->tokenFor(TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test'));

        $po = $this->withToken($managerToken)->postJson('/api/v1/purchase-orders', [
            'factory_id' => $this->dhaka->id, 'vendor_id' => $this->vendor->id,
            'lines' => [['item_id' => $this->fabric->id, 'ordered_qty' => '30', 'rate' => '275.50']],
        ])->assertCreated()->json('data');
        $this->withToken($managerToken)->postJson("/api/v1/purchase-orders/{$po['id']}/submit")->assertOk();

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'po_id' => $po['id'], 'store_id' => $this->binA->store_id,
            'lines' => [['po_line_id' => $po['lines'][0]['id'], 'received_qty' => '30']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => [['roll_no' => 'R-PO-1', 'net_length' => '30', 'bin_id' => $this->binA->id]]],
        ])->assertOk();

        $roll = FabricRoll::where('roll_no', 'R-PO-1')->firstOrFail();
        $this->assertSame('275.5000', (string) $roll->unit_cost);

        // Once accepted, the order line's received_qty moves and the order completes.
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/inspect", ['grade' => 'A'])->assertOk();
        $this->withToken($token)->getJson("/api/v1/purchase-orders/{$po['id']}")
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.lines.0.received_qty', '30.0000');
    }

    public function test_permission_gates_on_the_inspection_screen(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->tokenFor($technician))->getJson('/api/v1/fabric-rolls')->assertForbidden();

        ['response' => $response] = $this->directFabricReceipt($this->tokenFor($this->storekeeper), [
            ['roll_no' => 'R-P1', 'net_length' => '10', 'bin_id' => $this->binA->id],
        ]);
        $rollId = $response->json('data.lines.0.rolls.0.id');

        $this->withToken($this->tokenFor($technician))->postJson("/api/v1/fabric-rolls/{$rollId}/inspect", ['grade' => 'A'])
            ->assertForbidden();
    }
}
