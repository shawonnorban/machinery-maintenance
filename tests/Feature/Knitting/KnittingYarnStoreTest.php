<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Models\YarnPurchaseOrderLine;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03): an opening balance
 * finalised onto the yarn ledger; receipts against a placed purchase order
 * (never more than it still owes) moving its received column and status, one
 * cancelled; a return challan trimmed and issued; the desk checks; a
 * physical-count adjustment and its cancellation; received yarn racked; the
 * store's month grid adjusted and signed; and the month closed once nothing
 * is outstanding, after which it takes no more movement.
 */
class KnittingYarnStoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $cotton;

    private string $count;

    private string $spinner;

    private string $usd;

    private string $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
        $this->cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $this->count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $this->spinner = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Square Spinning', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
    }

    public function test_opening_receipt_return_adjustment_racking_and_month_close(): void
    {
        // Last month, so it has finished and can be closed.
        $month = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $period = $month->format('Y-m');
        $ym = $month->format('Ym');
        $day = fn (int $d) => $month->setDay($d)->toDateString();
        $yarn = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour' => 'Natural', 'brand' => 'Square'];

        // Opening balance: Total Qty is Pack Qty × Qty/Pack; a currency is required.
        $ob = ['ob_date' => $day(1), 'store_id' => $this->store, 'currency_id' => $this->usd, 'is_buffer' => true,
            'lines' => [$yarn + ['source' => 'LOCAL', 'lot_no' => 'l-1', 'supplier_id' => $this->spinner, 'pack_qty' => 10, 'qty_per_pack' => 50, 'pack_uom' => 'BAG', 'cost_price' => 3]]];
        $this->as()->postJson('/api/v1/knitting/ystore/obs', ['currency_id' => null] + $ob)->assertUnprocessable()->assertJsonPath('errors.currency_id.0', 'Currency is required.');
        $this->as()->postJson('/api/v1/knitting/ystore/obs', ['lines' => [['source' => 'LOCAL', 'total_qty' => 0] + $yarn]] + $ob)->assertUnprocessable()->assertJsonValidationErrors('lines.0.total_qty');
        $made = $this->as()->postJson('/api/v1/knitting/ystore/obs', $ob)->assertCreated()->json('data');
        $this->assertEquals(["YOB-{$ym}0001", 500.0, false], [$made['ob_no'], $made['lines'][0]['total_qty'], $made['finalized']]);
        $this->assertSame(0, DB::table('knit_yarn_moves')->count());
        $this->as()->postJson("/api/v1/knitting/ystore/obs/{$made['ob_no']}/finalize")->assertOk()->assertJsonPath('data.finalized', true);
        $this->as()->putJson("/api/v1/knitting/ystore/obs/{$made['ob_no']}", $ob)->assertUnprocessable()->assertJsonValidationErrors('finalized');
        $obLot = YarnLot::query()->where('lot_no', 'L-1')->firstOrFail();
        $this->assertEquals(500.0, YarnStore::balance($obLot->id));
        $register = $this->as()->getJson('/api/v1/knitting/ystore/obs?store=ST-YRN&finalized=YES')->assertOk()->json('data');
        $this->assertEquals([[$made['ob_no'], 500.0, 1500.0]], array_map(fn ($r) => [$r['ob_no'], $r['total_qty'], $r['value']], $register));

        // A placed purchase order the store receives against.
        $po = YarnPurchaseOrder::query()->create(['po_no' => "YPO-{$ym}0001", 'po_date' => $day(2), 'supplier_id' => $this->spinner, 'store_id' => $this->store, 'buyer_id' => $this->buyer,
            'source' => 'LOCAL', 'method' => 'LC', 'currency_id' => $this->usd, 'status' => 'PLACED']);
        $poLine = YarnPurchaseOrderLine::query()->create(['po_id' => $po->id, 'sort_order' => 1, 'uom' => 'kg', 'qty' => 1000, 'rate' => 3.2] + $yarn)->id;
        $this->assertEquals([[$poLine, 1000.0]], array_map(fn ($l) => [$l['po_line_id'], $l['left']], $this->as()->getJson("/api/v1/knitting/ystore/receipts/po/{$po->po_no}")->assertOk()->json('data.lines')));

        $mrr = ['mrr_date' => $day(5), 'store_id' => $this->store, 'recv_opt' => 'PO', 'po_no' => $po->po_no, 'challan_no' => 'CH-77', 'buyer_id' => $this->buyer,
            'lines' => [$yarn + ['po_line_id' => $poLine, 'lot_no' => 'L-2', 'recv_qty' => 600, 'challan_qty' => 620, 'rate' => 3.2, 'cones' => 480]]];
        $this->as()->postJson('/api/v1/knitting/ystore/receipts', ['recv_opt' => 'PI', 'pi_no' => 'PI-1'] + $mrr)->assertUnprocessable()->assertJsonValidationErrors('pi_no');
        $this->as()->postJson('/api/v1/knitting/ystore/receipts', ['lines' => [['recv_qty' => 1100] + $mrr['lines'][0]]] + $mrr)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.recv_qty' => 'Line 1 receives more than the order still owes (1000).']);
        $first = $this->as()->postJson('/api/v1/knitting/ystore/receipts', $mrr)->assertCreated()->json('data');
        $this->assertEquals(["YMRR-{$ym}0001", 'STORE_RECEIVE', $this->spinner], [$first['mrr_no'], $first['status'], $first['supplier_id']]);
        $this->assertEquals([600.0, 'PART_RECEIVED'], [(float) DB::table('knit_yarn_po_lines')->value('received_qty'), $po->refresh()->status]);
        // The rest arrives on a second receipt, which closes the order; cancelling it reopens it.
        $second = $this->as()->postJson('/api/v1/knitting/ystore/receipts', ['lines' => [['recv_qty' => 400] + $mrr['lines'][0]]] + $mrr)->assertCreated()->json('data.mrr_no');
        $this->assertSame('RECEIVED', $po->refresh()->status);
        $lot = YarnLot::query()->where('lot_no', 'L-2')->firstOrFail();
        $this->assertEquals(1000.0, YarnStore::balance($lot->id));
        $this->as()->postJson("/api/v1/knitting/ystore/receipts/{$second}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertEquals([600.0, 'PART_RECEIVED'], [YarnStore::balance($lot->id), $po->refresh()->status]);
        $this->as()->postJson("/api/v1/knitting/ystore/receipts/{$second}/return", ['type' => 'RETURN'])->assertUnprocessable()->assertJsonValidationErrors('status');

        // Ten wet bags go back: the challan comes with every held line, trimmed to what leaves and issued.
        $ret = $this->as()->postJson("/api/v1/knitting/ystore/receipts/{$first['mrr_no']}/return", ['type' => 'RETURN'])->assertCreated()->json('data');
        $this->assertEquals(['NON_RETURNABLE', false, 600.0], [$ret['challan_type'], $ret['finalized'], $ret['lines'][0]['qty']]);
        $issue = ['return_date' => $day(6), 'challan_type' => 'NON_RETURNABLE', 'reason' => 'Wet bags', 'finalized' => true,
            'lines' => [['receipt_line_id' => $ret['lines'][0]['receipt_line_id'], 'qty' => 50]]];
        $this->as()->putJson("/api/v1/knitting/ystore/returns/{$ret['return_no']}", ['lines' => [['qty' => 700] + $issue['lines'][0]]] + $issue)->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');
        $this->as()->putJson("/api/v1/knitting/ystore/returns/{$ret['return_no']}", $issue)->assertOk()->assertJsonPath('data.finalized', true);
        $this->as()->putJson("/api/v1/knitting/ystore/returns/{$ret['return_no']}", $issue)->assertUnprocessable()->assertJsonValidationErrors('finalized');
        $this->assertEquals([550.0, 550.0], [YarnStore::balance($lot->id), (float) DB::table('knit_yarn_po_lines')->value('received_qty')]);
        $this->assertEquals([[$ret['return_no'], $first['mrr_no'], 50.0, true]], array_map(fn ($r) => [$r['return_no'], $r['mrr_no'], $r['qty'], $r['finalized']], $this->as()->getJson('/api/v1/knitting/ystore/returns?supplier='.$this->spinner)->json('data')));

        // The desk checks run in order; after the first the receipt can no longer be corrected.
        $no = $first['mrr_no'];
        $this->as()->postJson("/api/v1/knitting/ystore/receipts/{$no}/audit")->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} is Store Receive, so it cannot be audit verified.");
        foreach (['verify' => 'STORE_VERIFY', 'audit' => 'AUDIT_VERIFY', 'approve' => 'ACCOUNTS_APPROVE'] as $action => $status) {
            $this->as()->postJson("/api/v1/knitting/ystore/receipts/{$no}/{$action}")->assertOk()->assertJsonPath('data.status', $status);
        }
        $this->as()->putJson("/api/v1/knitting/ystore/receipts/{$no}", $mrr)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertEquals([[$no, 600.0, 620.0, 1920.0]], array_map(fn ($r) => [$r['mrr_no'], $r['qty'], $r['challan_qty'], $r['value']], $this->as()->getJson('/api/v1/knitting/ystore/receipts?status=ACCOUNTS_APPROVE')->json('data')));

        // A physical count: never below what a lot holds; a cancelled one comes back off the ledger.
        $adj = ['adj_date' => $day(20), 'store_id' => $this->store, 'reason' => 'Physical count', 'lines' => [['lot_id' => $obLot->id, 'qty' => -20]]];
        $this->as()->postJson('/api/v1/knitting/ystore/adjustments', ['lines' => [['lot_id' => $obLot->id, 'qty' => -900]]] + $adj)->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');
        $this->as()->postJson('/api/v1/knitting/ystore/adjustments', ['reason' => ''] + $adj)->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Reason is required.');
        $a1 = $this->as()->postJson('/api/v1/knitting/ystore/adjustments', $adj)->assertCreated()->json('data.adj_no');
        $this->assertEquals(480.0, YarnStore::balance($obLot->id));
        $this->as()->postJson("/api/v1/knitting/ystore/adjustments/{$a1}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertEquals(500.0, YarnStore::balance($obLot->id));
        $this->as()->postJson('/api/v1/knitting/ystore/adjustments', $adj)->assertCreated();
        $this->assertEquals([['CANCELLED', -20.0, -60.0], ['ACTIVE', -20.0, -60.0]], array_map(fn ($r) => [$r['status'], $r['qty'], $r['value']], array_reverse($this->as()->getJson('/api/v1/knitting/ystore/adjustments')->json('data'))));

        // Racking: what was received less what went back waits to be put away.
        $this->as()->getJson('/api/v1/knitting/ystore/racks/pending')->assertUnprocessable()->assertJsonValidationErrors('store');
        $pending = $this->as()->getJson('/api/v1/knitting/ystore/racks/pending?store=ST-YRN')->assertOk()->json('data');
        $this->assertEquals([[$no, 600.0, 50.0, 550.0]], array_map(fn ($r) => [$r['mrr_no'], $r['recv_qty'], $r['returned'], $r['pending']], $pending));
        $line = $pending[0]['receipt_line_id'];
        $this->as()->postJson('/api/v1/knitting/ystore/racks', ['rows' => [['receipt_line_id' => $line, 'rack_no' => 'r-01', 'qty' => 600]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.qty');
        $this->as()->postJson('/api/v1/knitting/ystore/racks', ['rows' => [['receipt_line_id' => $line, 'rack_no' => 'r-01', 'qty' => 300]]])->assertOk();
        $this->as()->postJson('/api/v1/knitting/ystore/racks', ['rows' => [['receipt_line_id' => $line, 'rack_no' => 'R-02']]])->assertOk();
        $this->assertSame([], $this->as()->getJson('/api/v1/knitting/ystore/racks/pending?store=ST-YRN')->json('data'));
        $board = collect($this->as()->getJson('/api/v1/knitting/ystore/racks/board?store=ST-YRN&lot=L-2')->assertOk()->json('data'));
        $this->assertEquals([600.0, 50.0, 550.0, 0.0, 550.0], [$board[0]['recv'], $board[0]['returned'], $board[0]['racked'], $board[0]['pending'], $board[0]['balance']]);
        $this->assertEquals([['rack_no' => 'R-01', 'qty' => 300.0], ['rack_no' => 'R-02', 'qty' => 250.0]], $board[0]['racks']);

        // The month grid: only Adjustment and its reason are typed.
        $grid = $this->as()->getJson("/api/v1/knitting/ystore/period?store=ST-YRN&period={$period}")->assertOk()->json('data.rows');
        $rows = collect($grid)->keyBy('lot_no');
        $this->assertEquals([0.0, 500.0, -20.0, 480.0], [$rows['L-1']['opening'], $rows['L-1']['receive'], $rows['L-1']['adjusted'], $rows['L-1']['closing']]);
        $this->assertEquals([600.0, 50.0, 550.0], [$rows['L-2']['receive'], $rows['L-2']['returned'], $rows['L-2']['closing']]);
        $save = ['store' => 'ST-YRN', 'period' => $period, 'rows' => [['lot_id' => $obLot->id, 'adjustment' => 5]]];
        $this->as()->putJson('/api/v1/knitting/ystore/period', $save)->assertUnprocessable()->assertJsonValidationErrors('rows.0.reason');
        $saved = $this->as()->putJson('/api/v1/knitting/ystore/period', ['rows' => [['lot_id' => $obLot->id, 'adjustment' => 5, 'reason' => 'Cone weight']]] + $save)->assertOk()->json('data.rows');
        $this->assertEquals([5.0, 485.0], [collect($saved)->firstWhere('lot_no', 'L-1')['adjustment'], collect($saved)->firstWhere('lot_no', 'L-1')['closing']]);
        $this->assertSame(1, DB::table('knit_yarn_moves')->where('doc_no', "YPA-ST-YRN-{$period}")->count());

        // Month close: the store's unsigned grid stops it; signed, the month closes and is locked.
        $ex = $this->as()->getJson("/api/v1/knitting/ystore/closes/exceptions?period={$period}")->assertOk()->json('data.exceptions');
        $this->assertSame([['GRID', 'Yarn Store (ST-YRN)']], array_map(fn ($e) => [$e['kind'], $e['ref']], $ex));
        $this->as()->postJson('/api/v1/knitting/ystore/closes', ['period' => $period])->assertUnprocessable()->assertJsonPath('errors.period.0', '1 exception(s) stop the close — press Show Exception to see them.');
        $this->as()->postJson('/api/v1/knitting/ystore/period/finalize', ['store' => 'ST-YRN', 'period' => $period])->assertOk();
        $this->as()->putJson('/api/v1/knitting/ystore/period', $save + ['rows' => []])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->assertSame([], $this->as()->getJson("/api/v1/knitting/ystore/closes/exceptions?period={$period}")->json('data.exceptions'));
        $this->as()->postJson('/api/v1/knitting/ystore/closes', ['period' => $period])->assertOk();
        $this->as()->postJson('/api/v1/knitting/ystore/closes', ['period' => $period])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->assertSame([$period], array_column($this->as()->getJson('/api/v1/knitting/ystore/closes')->json('data'), 'period'));
        $this->as()->postJson('/api/v1/knitting/ystore/adjustments', $adj)->assertUnprocessable()->assertJsonValidationErrors('adj_date');
        $this->assertSame('UNFINISHED', $this->as()->getJson('/api/v1/knitting/ystore/closes/exceptions?period='.CarbonImmutable::now()->format('Y-m'))->json('data.exceptions.0.kind'));

        // A comparison's present stock reads the ledger; the issue pickers list the store's lots.
        $this->assertEquals(1035.0, YarnStore::stockOf($this->cotton, $this->count));
        $this->assertEquals([485.0, 550.0], array_column($this->as()->getJson('/api/v1/knitting/ystore/lots?store=ST-YRN')->assertOk()->json('data'), 'balance'));

        // A line chief has no part in the yarn store.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/knitting/ystore/receipts')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
