<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseReturn;
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
 * Goods back to the supplier (Procurement spec §12, Inventory §11).
 *
 * The order is the thing being tested: approval first, then the gate pass,
 * the stock and the debit note together. Stock that left before anybody
 * approved it is stock nobody can account for.
 */
class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $storeManager;

    private Vendor $mill;

    private Item $button;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');

        $this->mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Matin Textile Mills', 'code' => 'MTM',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    private function receipt(string $quantity = '1000', string $rate = '2'): GoodsReceipt
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [[
                'item_id' => $this->button->id,
                'received_qty' => $quantity,
                'rate' => $rate,
                'bin_id' => $this->bin->id,
            ]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        return GoodsReceipt::findOrFail($grn['id']);
    }

    private function onHand(): string
    {
        return (string) ItemStockBalance::where('item_id', $this->button->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail()->quantity_on_hand;
    }

    /** A one-step chain so the engine actually holds the return. */
    private function returnWorkflow(): void
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->delta->id,
            'name' => 'Purchase return',
            'entity_type' => 'PURCHASE_RETURN',
            'active' => true,
        ]);

        ApprovalRule::create([
            'company_id' => $this->delta->id,
            'workflow_id' => $workflow->id,
            'name' => 'Factory manager',
            'sequence' => 1,
            'role_id' => Role::whereNull('company_id')->where('name', 'FACTORY_MANAGER')->firstOrFail()->id,
            'condition_json' => ['min_cost' => '0'],
        ]);
    }

    public function test_nothing_leaves_the_shelf_until_the_goods_are_dispatched(): void
    {
        $receipt = $this->receipt();
        $token = $this->tokenFor($this->storekeeper);
        $lineId = $receipt->lines()->firstOrFail()->id;

        $return = $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'QUALITY_FAIL',
            'lines' => [['goods_receipt_line_id' => $lineId, 'quantity' => '200']],
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('PRT-DHK-', $return['return_no']);
        $this->assertSame('DRAFT', $return['status']);
        // 200 buttons at the rate they came in: 400.
        $this->assertSame('400.0000', $return['debit_note_amount']);
        $this->assertNull($return['debit_note_no']);
        $this->assertFalse($return['stock_moved']);

        // Drafting moved nothing.
        $this->assertSame('1000.0000', $this->onHand());

        // Neither does approval — the goods are still in the building.
        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$return['id']}/submit")->assertOk();
        $this->assertSame('1000.0000', $this->onHand());
        $this->assertSame('APPROVED', PurchaseReturn::findOrFail($return['id'])->status);

        $dispatched = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/purchase-returns/{$return['id']}/dispatch", ['gate_pass_no' => 'GP-77'])
            ->assertOk()->json('data');

        // Gate pass, stock and debit note, together.
        $this->assertSame('DISPATCHED', $dispatched['status']);
        $this->assertSame('GP-77', $dispatched['gate_pass_no']);
        $this->assertStringStartsWith('DN-DHK-', $dispatched['debit_note_no']);
        $this->assertTrue($dispatched['stock_moved']);
        $this->assertSame('800.0000', $this->onHand());

        $movement = ItemStockTransaction::where('reference_type', 'purchase_return')->firstOrFail();
        $this->assertSame('ADJUSTMENT_OUT', $movement->transaction_type);
        $this->assertSame('200.0000', (string) $movement->quantity);
    }

    public function test_an_unapproved_return_cannot_be_sent_out(): void
    {
        $this->returnWorkflow();

        $receipt = $this->receipt();
        $token = $this->tokenFor($this->storekeeper);

        $return = $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'DAMAGE',
            'lines' => [['goods_receipt_line_id' => $receipt->lines()->firstOrFail()->id, 'quantity' => '50']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$return['id']}/submit")->assertOk();

        // A workflow matched, so it is waiting rather than approved.
        $this->assertSame('PENDING_APPROVAL', PurchaseReturn::findOrFail($return['id'])->status);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/purchase-returns/{$return['id']}/dispatch")
            ->assertStatus(409);

        $this->assertSame('1000.0000', $this->onHand());

        $request = ApprovalRequest::where('entity_type', 'PURCHASE_RETURN')->firstOrFail();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');

        $this->withToken($this->tokenFor($manager))
            ->postJson("/api/v1/approval-requests/{$request->id}/approve", ['comment' => 'Agreed'])
            ->assertOk();

        $this->assertSame('APPROVED', PurchaseReturn::findOrFail($return['id'])->status);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/purchase-returns/{$return['id']}/dispatch")->assertOk();

        $this->assertSame('950.0000', $this->onHand());
    }

    public function test_a_line_cannot_be_returned_twice_over(): void
    {
        $receipt = $this->receipt('100');
        $token = $this->tokenFor($this->storekeeper);
        $lineId = $receipt->lines()->firstOrFail()->id;

        $first = $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'EXCESS',
            'lines' => [['goods_receipt_line_id' => $lineId, 'quantity' => '80']],
        ])->assertCreated()->json('data');

        // 80 already spoken for, so only 20 is left.
        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'EXCESS',
            'lines' => [['goods_receipt_line_id' => $lineId, 'quantity' => '30']],
        ])->assertStatus(409);

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'EXCESS',
            'lines' => [['goods_receipt_line_id' => $lineId, 'quantity' => '20']],
        ])->assertCreated();

        // Cancelling the first frees its 80 again.
        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$first['id']}/cancel", [
            'reason' => 'Supplier agreed to a discount instead',
        ])->assertOk();

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'EXCESS',
            'lines' => [['goods_receipt_line_id' => $lineId, 'quantity' => '80']],
        ])->assertCreated();
    }

    public function test_a_fabric_roll_goes_back_whole_and_only_from_the_shelf(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $fabric->id,
            'fabric_type' => 'KNIT', 'gsm' => '180', 'width' => '72', 'composition' => '100% cotton',
        ]);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [['item_id' => $fabric->id, 'received_qty' => '200', 'rate' => '250']],
        ])->assertCreated()->json('data');

        $lineId = $grn['lines'][0]['id'];

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$lineId => [
                ['roll_no' => 'RT-R-1', 'bin_id' => $this->bin->id, 'net_length' => '120'],
                ['roll_no' => 'RT-R-2', 'bin_id' => $this->bin->id, 'net_length' => '80'],
            ]],
        ])->assertOk();

        $good = FabricRoll::where('roll_no', 'RT-R-1')->firstOrFail();
        $bad = FabricRoll::where('roll_no', 'RT-R-2')->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$good->id}/inspect", ['grade' => 'A'])->assertOk();
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$bad->id}/inspect", [
            'grade' => 'REJECT', 'disposition' => 'DAMAGED',
        ])->assertOk();

        // The rejected roll goes back — measured length, at its own cost.
        $return = $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/returns", [
            'reason' => 'QUALITY_FAIL',
            'lines' => [['goods_receipt_line_id' => $lineId, 'fabric_roll_id' => $bad->id]],
        ])->assertCreated()->json('data');

        $this->assertSame('RT-R-2', $return['lines'][0]['roll_no']);
        $this->assertSame('80.0000', $return['lines'][0]['quantity']);
        $this->assertSame('20000.0000', $return['debit_note_amount']);

        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$return['id']}/submit")->assertOk();
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/purchase-returns/{$return['id']}/dispatch")->assertOk();

        $this->assertSame('RETURNED', $bad->fresh()->status);
        // A roll never had a balance row, so the quantity ledger stays silent.
        $this->assertSame(0, ItemStockTransaction::where('item_id', $fabric->id)->count());

        // An issued roll is on the cutting table — that is a claim, not a
        // return (Inventory spec §11).
        $good->forceFill(['status' => 'ISSUED'])->save();

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/returns", [
            'reason' => 'QUALITY_FAIL',
            'lines' => [['goods_receipt_line_id' => $lineId, 'fabric_roll_id' => $good->id]],
        ])->assertStatus(409);
    }

    public function test_sending_goods_out_needs_its_own_permission(): void
    {
        $receipt = $this->receipt();
        $token = $this->tokenFor($this->storekeeper);

        $return = $this->withToken($token)->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
            'reason' => 'WRONG_ITEM',
            'lines' => [['goods_receipt_line_id' => $receipt->lines()->firstOrFail()->id, 'quantity' => '10']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$return['id']}/submit")->assertOk();

        // The storekeeper writes it up; somebody else lets the goods leave.
        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$return['id']}/dispatch")
            ->assertForbidden();

        $this->assertSame('1000.0000', $this->onHand());
    }

    public function test_a_draft_receipt_has_nothing_on_the_shelf_to_return(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [['item_id' => $this->button->id, 'received_qty' => '10', 'rate' => '2', 'bin_id' => $this->bin->id]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/returns", [
            'reason' => 'DAMAGE',
            'lines' => [['goods_receipt_line_id' => $grn['lines'][0]['id'], 'quantity' => '5']],
        ])->assertStatus(409);
    }
}
