<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Notification\Models\Notification;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The item library's own stock ledger (Inventory spec §16, Phase 1).
 *
 * Same promise as the spare-parts ledger — replaying it reproduces the
 * balance exactly — plus one dimension it does not have: a lot-tracked item
 * keeps a balance per lot and cannot be received without naming one.
 */
class ItemLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private Item $item;

    private ItemLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->item = ItemFixture::item($this->delta);
        $this->ledger = app(ItemLedger::class);
    }

    private function balance(string $lot = ''): ItemStockBalance
    {
        return ItemStockBalance::where('item_id', $this->item->id)
            ->where('bin_id', $this->bin->id)
            ->where('lot_no', $lot)
            ->firstOrFail();
    }

    public function test_receipts_move_the_weighted_average_and_issues_do_not(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '250');
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '350');

        // (10 x 250 + 10 x 350) / 20 = 300
        $this->assertSame('20.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('300.0000', (string) $this->balance()->weighted_average_cost);

        $issue = $this->ledger->post($this->item, $this->bin, 'ISSUE', '5');

        $this->assertSame('15.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('300.0000', (string) $this->balance()->weighted_average_cost);
        // Drawn at the average in force, and the row carries what it left behind.
        $this->assertSame('300.0000', (string) $issue->unit_cost);
        $this->assertSame('1500.0000', (string) $issue->total_cost);
        $this->assertSame('15.0000', (string) $issue->balance_after);
        $this->assertSame('15.0000', $this->item->totalOnHand());
    }

    public function test_a_lot_tracked_item_needs_a_lot_and_keeps_one_balance_per_lot(): void
    {
        $dye = ItemFixture::item($this->delta, 'CHM-DYE-BLK', 'Reactive black dye', [
            'item_type' => 'CHEMICAL', 'tracking_mode' => 'LOT', 'uom_id' => ItemFixture::uomId($this->delta, 'KG'),
        ]);

        try {
            $this->ledger->post($dye, $this->bin, 'RECEIPT', '25', '900');
            $this->fail('A lot-tracked item was received without a lot number');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lot_no', $e->errors());
        }

        $this->ledger->post($dye, $this->bin, 'RECEIPT', '25', '900', ['lot_no' => 'L-2026-01']);
        $this->ledger->post($dye, $this->bin, 'RECEIPT', '10', '950', ['lot_no' => 'L-2026-02']);

        $lots = ItemStockBalance::where('item_id', $dye->id)->orderBy('lot_no')->get();

        $this->assertCount(2, $lots);
        $this->assertSame(['L-2026-01', 'L-2026-02'], $lots->pluck('lot_no')->all());
        $this->assertSame('900.0000', (string) $lots[0]->weighted_average_cost);
        $this->assertSame('950.0000', (string) $lots[1]->weighted_average_cost);
        $this->assertSame('35.0000', $dye->totalOnHand());

        // An issue names the lot it draws from; the other lot is untouched.
        $this->ledger->post($dye, $this->bin, 'ISSUE', '5', null, ['lot_no' => 'L-2026-02']);
        $this->assertSame('5.0000', $this->ledger->available($dye, $this->bin, 'L-2026-02'));
        $this->assertSame('25.0000', $this->ledger->available($dye, $this->bin, 'L-2026-01'));
    }

    public function test_a_pooled_item_ignores_a_stray_lot_number(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '4', '10', ['lot_no' => 'SHOULD-BE-IGNORED']);
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '6', '10');

        $this->assertSame(1, ItemStockBalance::where('item_id', $this->item->id)->count());
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('', ItemStockTransaction::where('item_id', $this->item->id)->first()->lot_no);
    }

    public function test_a_roll_tracked_item_is_refused_until_phase_2(): void
    {
        $fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL',
        ]);

        $this->expectException(ValidationException::class);

        $this->ledger->post($fabric, $this->bin, 'RECEIPT', '1', '1');
    }

    public function test_an_issue_beyond_stock_is_refused_unless_the_factory_allows_it(): void
    {
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '2', '100');

        try {
            $this->ledger->post($this->item, $this->bin, 'ISSUE', '5');
            $this->fail('Issued more than was on hand');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('2.0000', (string) $this->balance()->quantity_on_hand);
        }

        app(SetSetting::class)->handle('inventory.allow_negative_stock', true, factoryId: $this->dhaka->id);

        $this->ledger->post($this->item, $this->bin, 'ISSUE', '5');
        $this->assertSame('-3.0000', (string) $this->balance()->quantity_on_hand);

        // A receipt into a negative bin takes the incoming price as the new
        // average rather than an average dragged below zero.
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '120');
        $this->assertSame('7.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('120.0000', (string) $this->balance()->weighted_average_cost);
    }

    public function test_a_reversal_is_an_opposing_row_and_the_ledger_still_replays(): void
    {
        $user = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $receipt = $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '250', [
            'reference_type' => 'goods_receipt', 'reference_id' => $this->item->id, 'idempotency_key' => 'grn:test:1',
        ]);
        // A retry with the same key posts nothing new.
        $again = $this->ledger->post($this->item, $this->bin, 'RECEIPT', '10', '250', ['idempotency_key' => 'grn:test:1']);
        $this->assertSame($receipt->id, $again->id);
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);

        $reversal = $this->ledger->reverse($receipt, $user->id, 'Wrong bin');

        $this->assertSame('ADJUSTMENT_OUT', $reversal->transaction_type);
        $this->assertSame($receipt->id, $reversal->reverses_transaction_id);
        $this->assertSame('goods_receipt', $reversal->reference_type);
        $this->assertSame('0.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame(2, ItemStockTransaction::where('item_id', $this->item->id)->count());

        try {
            $this->ledger->reverse($receipt);
            $this->fail('Reversed the same movement twice');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $verified = $this->ledger->verify($this->item, $this->bin);
        $this->assertTrue($verified['matches']);
        $this->assertSame(2, $verified['transactions']);
    }

    public function test_crossing_the_reorder_level_warns_the_store_team_once(): void
    {
        $storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $user = User::findOrFail($storekeeper->id);

        // Reorder level is 5 (fixture). 8 -> 6 stays above; 6 -> 4 crosses.
        $this->ledger->post($this->item, $this->bin, 'RECEIPT', '8', '10');
        $this->ledger->post($this->item, $this->bin, 'ISSUE', '2');

        $this->assertSame(0, Notification::where('event_type', 'LOW_STOCK')->where('entity_id', $this->item->id)->count());

        $this->ledger->post($this->item, $this->bin, 'ISSUE', '2');
        $this->ledger->post($this->item, $this->bin, 'ISSUE', '1');

        $warnings = Notification::where('event_type', 'LOW_STOCK')
            ->where('entity_type', 'item')
            ->where('entity_id', $this->item->id)
            ->get();

        // One warning on the crossing, not another on every issue afterwards.
        $this->assertCount(1, $warnings);
        $this->assertSame($user->id, $warnings->first()->user_id);
        $this->assertTrue($this->item->fresh()->isBelowReorderLevel());
    }
}
