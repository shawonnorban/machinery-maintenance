<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\SupplierAdvance;
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
 * Claims against a supplier (Procurement spec §12) and money paid to one
 * before the goods arrive (§13).
 *
 * Both are money rather than stock, and the tests say so: not one movement
 * reaches the ledger. What they do carry is the arithmetic somebody argues
 * about — how much was recovered, and how much of an advance is still
 * unaccounted for.
 */
class ClaimAndAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storeManager;

    private User $storekeeper;

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
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

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

    private function receipt(): GoodsReceipt
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [[
                'item_id' => $this->button->id,
                'received_qty' => '1000',
                'rate' => '2',
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

    public function test_a_claim_is_money_and_settles_in_instalments(): void
    {
        $receipt = $this->receipt();
        $token = $this->tokenFor($this->storeManager);
        $movementsBefore = ItemStockTransaction::count();

        $claim = $this->withToken($token)->postJson('/api/v1/supplier-claims', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'goods_receipt_id' => $receipt->id,
            'reason' => 'QUALITY',
            'claim_amount' => '5000',
            'description' => 'Shade off on half the consignment; already cut.',
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('CLM-DHK-', $claim['claim_no']);
        $this->assertSame('RAISED', $claim['status']);
        $this->assertSame('5000.0000', $claim['outstanding']);

        // The mill accepts, then pays in two instalments.
        $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/accept", [
            'reference' => 'CN-2026-14',
        ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED');

        $partial = $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/settle", [
            'amount' => '2000', 'reference' => 'CN-2026-14',
        ])->assertOk()->json('data');

        $this->assertSame('PARTIALLY_SETTLED', $partial['status']);
        $this->assertSame('3000.0000', $partial['outstanding']);

        // More than is outstanding is a typo, not generosity.
        $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/settle", [
            'amount' => '4000',
        ])->assertStatus(409);

        $settled = $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/settle", [
            'amount' => '3000',
        ])->assertOk()->json('data');

        $this->assertSame('SETTLED', $settled['status']);
        $this->assertSame('0.0000', $settled['outstanding']);
        $this->assertNotNull($settled['settled_at']);

        // A settled claim takes no more.
        $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/settle", [
            'amount' => '1',
        ])->assertStatus(409);

        // Not one movement reached the ledger, and the shelf is untouched.
        $this->assertSame($movementsBefore, ItemStockTransaction::count());
        $this->assertSame('1000.0000', $this->onHand());
    }

    public function test_a_rejected_claim_is_closed_not_deleted(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $claim = $this->withToken($token)->postJson('/api/v1/supplier-claims', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'reason' => 'SHORT_SHIPMENT',
            'claim_amount' => '1200',
        ])->assertCreated()->json('data');

        $rejected = $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/reject", [
            'reason' => 'Mill produced the weighbridge slip',
        ])->assertOk()->json('data');

        $this->assertSame('REJECTED', $rejected['status']);
        $this->assertStringContainsString('weighbridge', $rejected['notes']);

        // Still there to learn from, and it takes no settlement.
        $this->withToken($token)->postJson("/api/v1/supplier-claims/{$claim['id']}/settle", [
            'amount' => '100',
        ])->assertStatus(409);

        $this->withToken($token)->getJson('/api/v1/supplier-claims?open=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_advance_is_adjusted_against_bills_until_nothing_is_left(): void
    {
        $receipt = $this->receipt();
        $token = $this->tokenFor($this->storeManager);

        $advance = $this->withToken($token)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '50000',
            'reference_no' => 'CHQ-9981',
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('ADV-DHK-', $advance['advance_no']);
        $this->assertSame('OPEN', $advance['status']);
        $this->assertSame('50000.0000', $advance['outstanding']);

        $partial = $this->withToken($token)->postJson("/api/v1/supplier-advances/{$advance['id']}/adjust", [
            'amount' => '20000',
            'goods_receipt_id' => $receipt->id,
        ])->assertOk()->json('data');

        $this->assertSame('PARTIALLY_ADJUSTED', $partial['status']);
        $this->assertSame('30000.0000', $partial['outstanding']);
        // The row says which bill consumed it, not just that it went.
        $this->assertSame($receipt->id, $partial['adjustments'][0]['goods_receipt']['id']);

        // More than is left is refused.
        $this->withToken($token)->postJson("/api/v1/supplier-advances/{$advance['id']}/adjust", [
            'amount' => '40000',
        ])->assertStatus(409);

        $closed = $this->withToken($token)->postJson("/api/v1/supplier-advances/{$advance['id']}/adjust", [
            'amount' => '30000',
        ])->assertOk()->json('data');

        $this->assertSame('ADJUSTED', $closed['status']);
        $this->assertSame('0.0000', $closed['outstanding']);
        $this->assertNotNull($closed['closed_at']);
    }

    public function test_reversing_an_adjustment_keeps_the_row_and_frees_the_money(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $advance = $this->withToken($token)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '10000',
        ])->assertCreated()->json('data');

        $adjusted = $this->withToken($token)->postJson("/api/v1/supplier-advances/{$advance['id']}/adjust", [
            'amount' => '4000',
        ])->assertOk()->json('data');

        $adjustmentId = $adjusted['adjustments'][0]['id'];

        $reversed = $this->withToken($token)->postJson("/api/v1/advance-adjustments/{$adjustmentId}/reverse", [
            'reason' => 'Set against the wrong receipt',
        ])->assertOk()->json('data');

        $this->assertSame('OPEN', $reversed['status']);
        $this->assertSame('10000.0000', $reversed['outstanding']);

        // The row stays, stamped — so the mistake is findable.
        $this->assertCount(1, $reversed['adjustments']);
        $this->assertFalse($reversed['adjustments'][0]['live']);
        $this->assertNotNull($reversed['adjustments'][0]['reversed_at']);

        $this->withToken($token)->postJson("/api/v1/advance-adjustments/{$adjustmentId}/reverse")
            ->assertStatus(409);
    }

    public function test_the_outstanding_report_sums_what_a_supplier_still_holds(): void
    {
        $token = $this->tokenFor($this->storeManager);

        foreach (['30000', '20000'] as $amount) {
            $advance = $this->withToken($token)->postJson('/api/v1/supplier-advances', [
                'factory_id' => $this->dhaka->id,
                'vendor_id' => $this->mill->id,
                'amount' => $amount,
            ])->assertCreated()->json('data');

            if ($amount === '30000') {
                $this->withToken($token)->postJson("/api/v1/supplier-advances/{$advance['id']}/adjust", [
                    'amount' => '5000',
                ])->assertOk();
            }
        }

        $report = $this->withToken($token)
            ->getJson("/api/v1/supplier-advances/outstanding?vendor_id={$this->mill->id}")
            ->assertOk()->json('data');

        $this->assertSame(2, $report['advances']);
        $this->assertSame('50000.0000', $report['paid']);
        $this->assertSame('5000.0000', $report['adjusted']);
        $this->assertSame('45000.0000', $report['outstanding']);

        // The list narrows to exactly what the report counts.
        $this->withToken($token)->getJson('/api/v1/supplier-advances?outstanding=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_advance_that_has_been_used_cannot_be_cancelled(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $advance = $this->withToken($token)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '8000',
        ])->assertCreated()->json('data');

        // Untouched, so it can go.
        $this->withToken($token)->postJson("/api/v1/supplier-advances/{$advance['id']}/cancel", [
            'reason' => 'Paid twice by mistake',
        ])->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        $second = $this->withToken($token)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '8000',
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/supplier-advances/{$second['id']}/adjust", [
            'amount' => '1000',
        ])->assertOk();

        // Now part of it has settled a bill; cancelling would erase that.
        $this->withToken($token)->postJson("/api/v1/supplier-advances/{$second['id']}/cancel")
            ->assertStatus(409);

        $this->assertSame('PARTIALLY_ADJUSTED', SupplierAdvance::findOrFail($second['id'])->status);
    }

    public function test_an_advance_cannot_be_hung_on_another_suppliers_order(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $other = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Savar Trims', 'code' => 'STR',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $order = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $other->id,
            'lines' => [['item_id' => $this->button->id, 'ordered_qty' => '100', 'rate' => '2']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '1000',
            'purchase_order_id' => $order['id'],
        ])->assertStatus(422)->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_the_store_floor_cannot_raise_claims_or_advances(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $this->withToken($token)->postJson('/api/v1/supplier-claims', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'reason' => 'QUALITY',
            'claim_amount' => '100',
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '100',
        ])->assertForbidden();
    }
}
