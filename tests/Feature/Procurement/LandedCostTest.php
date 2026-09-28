<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\LandedCostVoucher;
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
 * Landed cost (Procurement spec §11).
 *
 * The purchase rate is only part of the price; freight, duty and clearing
 * arrive weeks later. These tests pin the parts that go wrong quietly: the
 * distribution has to add back to the whole, a quantity item takes its
 * share through the ledger without any stock moving, fabric takes it on the
 * roll, and once posted the receipt's rates are locked.
 */
class LandedCostTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $storeManager;

    private Vendor $mill;

    private Item $button;

    private Item $thread;

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
        $this->thread = ItemFixture::item($this->delta, 'TRM-THR-40', 'Thread 40/2');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @param  list<array{0: Item, 1: string, 2: string}>  $lines  item, quantity, rate
     */
    private function receipt(array $lines): GoodsReceipt
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => array_map(fn (array $line): array => [
                'item_id' => $line[0]->id,
                'received_qty' => $line[1],
                'rate' => $line[2],
                'bin_id' => $this->bin->id,
            ], $lines),
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        return GoodsReceipt::findOrFail($grn['id']);
    }

    /**
     * @param  list<string>  $receiptIds
     * @param  list<array<string, mixed>>  $charges
     * @return array<string, mixed>
     */
    private function voucher(array $receiptIds, array $charges, string $basis = 'VALUE'): array
    {
        return $this->withToken($this->tokenFor($this->storeManager))->postJson('/api/v1/landed-costs', [
            'factory_id' => $this->dhaka->id,
            'basis' => $basis,
            'goods_receipt_ids' => $receiptIds,
            'charges' => $charges,
        ])->assertCreated()->json('data');
    }

    private function balance(Item $item): ItemStockBalance
    {
        return ItemStockBalance::where('item_id', $item->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail();
    }

    public function test_charges_are_spread_by_value_and_add_back_to_the_whole(): void
    {
        $receipt = $this->receipt([
            [$this->button, '1000', '2'],   // value 2000
            [$this->thread, '100', '30'],   // value 3000
        ]);

        $voucher = $this->voucher([$receipt->id], [
            ['charge_type' => 'FREIGHT', 'amount' => '3000'],
            ['charge_type' => 'DUTY', 'amount' => '2000'],
        ]);

        $this->assertStringStartsWith('LCV-DHK-', $voucher['voucher_no']);
        $this->assertSame('5000.0000', $voucher['total_charges']);

        $preview = $this->withToken($this->tokenFor($this->storeManager))
            ->getJson("/api/v1/landed-costs/{$voucher['id']}/preview")
            ->assertOk()->json('data');

        $shares = collect($preview['lines'])->keyBy('item_code');

        // 5000 over a value basis of 5000: 2000/5000 and 3000/5000.
        $this->assertSame('2000.0000', $shares['TRM-BTN-18L']['share_amount']);
        $this->assertSame('3000.0000', $shares['TRM-THR-40']['share_amount']);

        // The parts add back to the whole, exactly.
        $total = collect($preview['lines'])->reduce(
            fn (string $sum, array $line): string => bcadd($sum, $line['share_amount'], 4),
            '0.0000',
        );
        $this->assertSame('5000.0000', $total);

        // 2000 over 1000 buttons is 2 a button, on top of the 2 they cost.
        $this->assertSame('2.0000', $shares['TRM-BTN-18L']['unit_uplift']);
        $this->assertSame('4.0000', $shares['TRM-BTN-18L']['new_unit_cost']);
    }

    public function test_an_awkward_split_still_adds_back_to_the_whole(): void
    {
        // 1000 over three equal lines is 333.33 each — and 0.01 left over.
        $receipt = $this->receipt([
            [$this->button, '10', '10'],
            [$this->thread, '10', '10'],
            [ItemFixture::item($this->delta, 'TRM-ZIP-5', 'Zipper #5'), '10', '10'],
        ]);

        $voucher = $this->voucher([$receipt->id], [['charge_type' => 'CNF', 'amount' => '1000']]);

        $preview = $this->withToken($this->tokenFor($this->storeManager))
            ->getJson("/api/v1/landed-costs/{$voucher['id']}/preview")
            ->assertOk()->json('data');

        $total = collect($preview['lines'])->reduce(
            fn (string $sum, array $line): string => bcadd($sum, $line['share_amount'], 4),
            '0.0000',
        );

        // The last line carries the remainder rather than the ledger losing it.
        $this->assertSame('1000.0000', $total);
    }

    public function test_posting_revalues_stock_without_moving_any_of_it(): void
    {
        $receipt = $this->receipt([[$this->button, '1000', '2']]);

        $this->assertSame('2.0000', (string) $this->balance($this->button)->weighted_average_cost);

        $voucher = $this->voucher([$receipt->id], [['charge_type' => 'FREIGHT', 'amount' => '1000']]);

        $posted = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/landed-costs/{$voucher['id']}/post")
            ->assertOk()->json('data');

        $this->assertSame('POSTED', $posted['status']);

        $balance = $this->balance($this->button);

        // 1000 over 1000 buttons is 1 each: 2 becomes 3.
        $this->assertSame('3.0000', (string) $balance->weighted_average_cost);
        // And not one button moved.
        $this->assertSame('1000.0000', (string) $balance->quantity_on_hand);

        $revaluation = ItemStockTransaction::where('transaction_type', 'LANDED_COST')->firstOrFail();
        $this->assertSame('0.0000', (string) $revaluation->quantity);
        $this->assertSame('1000.0000', (string) $revaluation->total_cost);
        $this->assertSame('landed_cost_voucher', $revaluation->reference_type);

        // The receipt line now reads the landed rate.
        $this->assertSame('3.0000', (string) $receipt->lines()->firstOrFail()->rate);
    }

    public function test_a_posted_voucher_locks_the_receipt_and_cannot_be_posted_twice(): void
    {
        $receipt = $this->receipt([[$this->button, '100', '5']]);
        $voucher = $this->voucher([$receipt->id], [['charge_type' => 'DUTY', 'amount' => '500']]);

        $token = $this->tokenFor($this->storeManager);

        $this->withToken($token)->postJson("/api/v1/landed-costs/{$voucher['id']}/post")->assertOk();

        $this->assertNotNull($receipt->fresh()->landed_cost_posted_at);
        $this->assertTrue($receipt->fresh()->isRateLocked());

        // Posting again, editing the voucher, or cancelling it are all
        // refused — the valuation has already been reported.
        $this->withToken($token)->postJson("/api/v1/landed-costs/{$voucher['id']}/post")->assertStatus(409);
        $this->withToken($token)->postJson("/api/v1/landed-costs/{$voucher['id']}/cancel")->assertStatus(409);
        $this->withToken($token)->patchJson("/api/v1/landed-costs/{$voucher['id']}", [
            'goods_receipt_ids' => [$receipt->id],
            'charges' => [['charge_type' => 'DUTY', 'amount' => '600']],
        ])->assertStatus(409);
    }

    public function test_one_voucher_covers_several_receipts(): void
    {
        $first = $this->receipt([[$this->button, '100', '10']]);   // value 1000
        $second = $this->receipt([[$this->thread, '100', '30']]);  // value 3000

        $voucher = $this->voucher([$first->id, $second->id], [
            ['charge_type' => 'FREIGHT', 'amount' => '400'],
        ]);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/landed-costs/{$voucher['id']}/post")->assertOk();

        // 400 split 1000:3000 is 100 and 300.
        $this->assertSame('11.0000', (string) $this->balance($this->button)->weighted_average_cost);
        $this->assertSame('33.0000', (string) $this->balance($this->thread)->weighted_average_cost);

        $this->assertNotNull($first->fresh()->landed_cost_posted_at);
        $this->assertNotNull($second->fresh()->landed_cost_posted_at);
    }

    public function test_fabric_takes_its_share_on_the_roll(): void
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

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => [
                ['roll_no' => 'LC-R-1', 'bin_id' => $this->bin->id, 'net_length' => '120'],
                ['roll_no' => 'LC-R-2', 'bin_id' => $this->bin->id, 'net_length' => '80'],
            ]],
        ])->assertOk();

        foreach (['LC-R-1', 'LC-R-2'] as $rollNo) {
            $roll = FabricRoll::where('roll_no', $rollNo)->firstOrFail();
            $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/inspect", [
                'grade' => 'A', 'shade_match' => true,
            ])->assertOk();
        }

        // One roll is cut in two before the freight bill arrives.
        $parent = FabricRoll::where('roll_no', 'LC-R-1')->firstOrFail();
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$parent->id}/split", [
            'children' => [
                ['roll_no' => 'LC-R-1-A', 'net_length' => '70'],
                ['roll_no' => 'LC-R-1-B', 'net_length' => '50'],
            ],
        ])->assertCreated();

        $voucher = $this->voucher([$grn['id']], [['charge_type' => 'FREIGHT', 'amount' => '4000']]);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/landed-costs/{$voucher['id']}/post")->assertOk();

        // 4000 over 200 metres is 20 a metre: 250 becomes 270 — and the
        // correction follows the roll that was already cut up.
        foreach (['LC-R-1', 'LC-R-2', 'LC-R-1-A', 'LC-R-1-B'] as $rollNo) {
            $this->assertSame(
                '270.0000',
                (string) FabricRoll::where('roll_no', $rollNo)->firstOrFail()->unit_cost,
                "roll $rollNo",
            );
        }

        // The parent is closed, so its correction is declared (§10.5).
        $this->assertTrue((bool) FabricRoll::where('roll_no', 'LC-R-1')->firstOrFail()->cost_adjusted_after_issue);
        // Fabric never touches the quantity ledger.
        $this->assertSame(0, ItemStockTransaction::where('item_id', $fabric->id)->count());
    }

    public function test_posting_needs_its_own_permission(): void
    {
        $receipt = $this->receipt([[$this->button, '100', '5']]);
        $voucher = $this->voucher([$receipt->id], [['charge_type' => 'DUTY', 'amount' => '100']]);

        // A storekeeper may see the store's papers but not move its valuation.
        $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson("/api/v1/landed-costs/{$voucher['id']}/post")
            ->assertForbidden();

        $this->assertSame('DRAFT', LandedCostVoucher::findOrFail($voucher['id'])->status);
    }

    public function test_a_draft_receipt_has_no_valuation_to_lift(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [['item_id' => $this->button->id, 'received_qty' => '10', 'rate' => '5', 'bin_id' => $this->bin->id]],
        ])->assertCreated()->json('data');

        $this->withToken($this->tokenFor($this->storeManager))->postJson('/api/v1/landed-costs', [
            'factory_id' => $this->dhaka->id,
            'goods_receipt_ids' => [$grn['id']],
            'charges' => [['charge_type' => 'FREIGHT', 'amount' => '100']],
        ])->assertStatus(409);
    }
}
