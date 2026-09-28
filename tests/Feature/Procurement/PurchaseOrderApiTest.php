<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderRevision;
use App\Modules\Procurement\Models\PurchaseRequisition;
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
 * Purchase orders and goods receipts over the API (Procurement spec §7, §9).
 *
 * The whole local procure-to-store path: requisition → order → receipt →
 * stock on the shelf, plus the revision history an approved order keeps.
 */
class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storeManager;

    private User $storekeeper;

    private Vendor $vendor;

    private Item $bolt;

    private Item $dye;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->vendor = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Dhaka Fasteners', 'code' => 'DF', 'vendor_type' => 'SUPPLIER',
            'status' => 'ACTIVE', 'default_currency' => 'BDT', 'default_payment_terms' => 'Net 30',
        ]);

        $this->bolt = ItemFixture::item($this->delta);
        $this->dye = ItemFixture::item($this->delta, 'CHM-DYE-BLK', 'Reactive black dye', [
            'item_type' => 'CHEMICAL', 'tracking_mode' => 'LOT', 'uom_id' => ItemFixture::uomId($this->delta, 'KG'),
        ]);
    }

    public function test_requisition_to_order_to_receipt_puts_stock_on_the_shelf(): void
    {
        $sm = $this->tokenFor($this->storeManager);
        $sk = $this->tokenFor($this->storekeeper);

        $pr = $this->withToken($sk)->postJson('/api/v1/purchase-requisitions', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'lines' => [
                ['item_id' => $this->bolt->id, 'requested_qty' => '100', 'estimated_rate' => '2'],
                ['item_id' => $this->dye->id, 'requested_qty' => '20', 'estimated_rate' => '900'],
            ],
        ])->json('data');
        $this->withToken($sk)->postJson("/api/v1/purchase-requisitions/{$pr['id']}/submit")->assertJsonPath('data.status', 'APPROVED');

        // The storekeeper cannot raise an order; the store manager can, for
        // part of the first line only.
        $this->withToken($sk)->postJson("/api/v1/purchase-requisitions/{$pr['id']}/orders", [
            'vendor_id' => $this->vendor->id, 'lines' => [['pr_line_id' => $pr['lines'][0]['id']]],
        ])->assertForbidden();

        $order = $this->withToken($sm)->postJson("/api/v1/purchase-requisitions/{$pr['id']}/orders", [
            'vendor_id' => $this->vendor->id,
            'delivery_date' => '2026-10-05',
            'lines' => [
                ['pr_line_id' => $pr['lines'][0]['id'], 'ordered_qty' => '60', 'rate' => '2.25'],
                ['pr_line_id' => $pr['lines'][1]['id'], 'rate' => '880'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.purchase_type', 'LOCAL')
            ->assertJsonPath('data.payment_terms', 'Net 30')
            ->assertJsonPath('data.delivery_store.id', $this->bin->store_id)
            ->assertJsonPath('data.lines.0.ordered_qty', '60.0000')
            ->assertJsonPath('data.lines.1.ordered_qty', '20.0000')
            // 60 x 2.25 + 20 x 880
            ->assertJsonPath('data.subtotal', '17735.0000')
            ->json('data');

        $this->assertMatchesRegularExpression('/^PO-DHK-\d{4}-\d{4}$/', $order['po_no']);

        // The requisition knows 60 of 100 bolts and all the dye are on order.
        $this->withToken($sk)->getJson("/api/v1/purchase-requisitions/{$pr['id']}")
            ->assertJsonPath('data.status', 'PARTIALLY_CONVERTED')
            ->assertJsonPath('data.lines.0.ordered_qty', '60.0000')
            ->assertJsonPath('data.lines.0.pending_qty', '40.0000')
            ->assertJsonPath('data.lines.1.pending_qty', '0.0000');

        // Asking for more than is outstanding is refused.
        $this->withToken($sm)->postJson("/api/v1/purchase-requisitions/{$pr['id']}/orders", [
            'vendor_id' => $this->vendor->id, 'lines' => [['pr_line_id' => $pr['lines'][0]['id'], 'ordered_qty' => '41', 'rate' => '2']],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.ordered_qty']);

        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/submit")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.approved_subtotal', '17735.0000')
            ->assertJsonPath('data.can_receive', true)->assertJsonPath('data.can_revise', true);

        // Receive: everything outstanding, dye needs a lot, bins per line.
        $this->withToken($sk)->getJson("/api/v1/purchase-orders/{$order['id']}/receivable")
            ->assertOk()->assertJsonPath('data.lines.1.tracking_mode', 'LOT')->assertJsonCount(2, 'data.lines');

        $grn = $this->withToken($sk)->postJson('/api/v1/goods-receipts', [
            'po_id' => $order['id'],
            'store_id' => $this->bin->store_id,
            'gate_pass_no' => 'GP-77',
            'lines' => [
                ['po_line_id' => $order['lines'][0]['id'], 'received_qty' => '60', 'bin_id' => $this->bin->id],
                ['po_line_id' => $order['lines'][1]['id'], 'received_qty' => '20'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.vendor.id', $this->vendor->id)
            ->assertJsonPath('data.lines.0.rate', '2.2500')
            ->assertJsonPath('data.lines.1.rate', '880.0000')
            ->json('data');

        $this->assertMatchesRegularExpression('/^GRN-/', $grn['grn_no']);

        // Completing without a bin or a lot for the dye is refused, and
        // nothing was posted for the bolts either.
        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")
            ->assertStatus(422)->assertJsonValidationErrors(['lines.1.bin_id']);
        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", ['bins' => [$grn['lines'][1]['id'] => $this->bin->id]])
            ->assertStatus(422)->assertJsonValidationErrors(['lines.1.lot_no']);
        $this->assertSame(0, ItemStockTransaction::count());

        $this->withToken($sk)->patchJson("/api/v1/goods-receipts/{$grn['id']}", [
            'lines' => [
                ['po_line_id' => $order['lines'][0]['id'], 'received_qty' => '60', 'bin_id' => $this->bin->id],
                ['po_line_id' => $order['lines'][1]['id'], 'received_qty' => '20', 'bin_id' => $this->bin->id, 'lot_no' => 'L-2026-09'],
            ],
        ])->assertOk();

        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")
            ->assertOk()->assertJsonPath('data.status', 'COMPLETED')->assertJsonPath('data.order.status', 'RECEIVED');

        // Stock is on the shelf at the order's rate, per lot for the dye.
        $this->assertSame('60.0000', $this->bolt->fresh()->totalOnHand());
        $this->assertSame('20.0000', $this->dye->fresh()->totalOnHand());
        $dyeBalance = ItemStockBalance::where('item_id', $this->dye->id)->firstOrFail();
        $this->assertSame('L-2026-09', $dyeBalance->lot_no);
        $this->assertSame('880.0000', (string) $dyeBalance->weighted_average_cost);
        $this->assertSame('goods_receipt', ItemStockTransaction::first()->reference_type);

        // A second completion posts nothing twice.
        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertStatus(409);
        $this->assertSame(2, ItemStockTransaction::count());

        $this->withToken($sm)->getJson("/api/v1/purchase-orders/{$order['id']}")
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.lines.0.received_qty', '60.0000')
            ->assertJsonPath('data.receipts.0.grn_no', $grn['grn_no'])
            ->assertJsonPath('data.can_close', true);

        // The item's detail shows what arrived.
        $this->withToken($sk)->getJson("/api/v1/items/{$this->bolt->id}")
            ->assertJsonPath('data.stock.on_hand', '60.0000')
            ->assertJsonPath('data.stock.by_bin.0.quantity_on_hand', '60.0000');
    }

    public function test_a_revision_snapshots_the_order_and_a_costlier_one_goes_back_for_approval(): void
    {
        $sm = $this->tokenFor($this->storeManager);

        $order = $this->withToken($sm)->postJson('/api/v1/purchase-orders', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->vendor->id,
            'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '100', 'rate' => '2']],
        ])->assertCreated()->json('data');

        // IMPORT is a label on the order; its papers live in their own
        // tables (spec §10), so switching the type changes nothing else.
        $this->withToken($sm)->patchJson("/api/v1/purchase-orders/{$order['id']}", [
            'purchase_type' => 'IMPORT', 'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '100', 'rate' => '2']],
        ])->assertOk()->assertJsonPath('data.purchase_type', 'IMPORT');

        $this->withToken($sm)->patchJson("/api/v1/purchase-orders/{$order['id']}", [
            'purchase_type' => 'LOCAL', 'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '100', 'rate' => '2']],
        ])->assertOk()->assertJsonPath('data.purchase_type', 'LOCAL');

        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/revise", [
            'reason' => 'x', 'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '100', 'rate' => '2']],
        ])->assertStatus(409);

        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/submit")->assertJsonPath('data.status', 'APPROVED');

        // Cheaper revision: recorded, still approved.
        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/revise", [
            'reason' => 'Supplier discount',
            'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '100', 'rate' => '1.90']],
        ])->assertOk()
            ->assertJsonPath('data.revision_no', 1)
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.subtotal', '190.0000')
            ->assertJsonPath('data.revisions.0.reason', 'Supplier discount')
            ->assertJsonPath('data.revisions.0.snapshot.lines.0.rate', '2.0000');

        // Costlier revision with no workflow: back through the engine, which
        // approves it at once; the approved figure moves with it.
        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/revise", [
            'reason' => 'More bolts',
            'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '150', 'rate' => '1.90']],
        ])->assertOk()
            ->assertJsonPath('data.revision_no', 2)
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.approved_subtotal', '285.0000');

        $this->assertSame(2, PurchaseOrderRevision::where('purchase_order_id', $order['id'])->count());

        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/close")
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');
    }

    /**
     * The fabric line itself is covered end-to-end by
     * `tests/Feature/ItemMaster/FabricInspectionTest` (Phase 2); this test
     * only needs the mixed-line and over-receipt behaviour it was written
     * for, so a fabric line here is asserted just enough to show it no
     * longer refuses outright — it now asks for roll entries instead.
     */
    public function test_a_direct_receipt_needs_no_order_and_refuses_fabric_without_rolls_and_over_receipt(): void
    {
        $sk = $this->tokenFor($this->storekeeper);

        $fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey', ['item_type' => 'FABRIC', 'tracking_mode' => 'ROLL']);

        $grn = $this->withToken($sk)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->vendor->id,
            'lines' => [
                ['item_id' => $this->bolt->id, 'received_qty' => '10', 'rate' => '3', 'bin_id' => $this->bin->id],
                ['item_id' => $fabric->id, 'received_qty' => '2', 'rate' => '500', 'bin_id' => $this->bin->id],
            ],
        ])->assertCreated()->json('data');

        // A fabric line no longer refuses outright (Phase 2) — it asks for
        // at least one roll entry before the receipt can complete.
        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")
            ->assertStatus(422)->assertJsonValidationErrors(['lines.1.rolls']);
        $this->assertSame('0.0000', $this->bolt->fresh()->totalOnHand());

        $this->withToken($sk)->patchJson("/api/v1/goods-receipts/{$grn['id']}", [
            'lines' => [['item_id' => $this->bolt->id, 'received_qty' => '10', 'rate' => '3', 'bin_id' => $this->bin->id]],
        ])->assertOk();
        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();
        $this->assertSame('10.0000', $this->bolt->fresh()->totalOnHand());

        // Over-receipt against an order is refused by default.
        $sm = $this->tokenFor($this->storeManager);
        $order = $this->withToken($sm)->postJson('/api/v1/purchase-orders', [
            'factory_id' => $this->dhaka->id, 'vendor_id' => $this->vendor->id,
            'lines' => [['item_id' => $this->bolt->id, 'ordered_qty' => '5', 'rate' => '3']],
        ])->json('data');
        $this->withToken($sm)->postJson("/api/v1/purchase-orders/{$order['id']}/submit")->assertOk();

        $over = $this->withToken($sk)->postJson('/api/v1/goods-receipts', [
            'po_id' => $order['id'], 'store_id' => $this->bin->store_id,
            'lines' => [['po_line_id' => $order['lines'][0]['id'], 'received_qty' => '6', 'bin_id' => $this->bin->id]],
        ])->json('data');
        $this->withToken($sk)->postJson("/api/v1/goods-receipts/{$over['id']}/complete")
            ->assertStatus(422)->assertJsonValidationErrors(['lines.0.received_qty']);

        $this->assertSame('APPROVED', PurchaseOrder::findOrFail($order['id'])->status);
        $this->assertSame(PurchaseRequisition::count(), 0);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
