<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitProgram;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollSheet;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the grey store: rolls
 * scanned off the floor into a store and rack (once each, never a rejected
 * one, withdrawable until the day is confirmed); opening lines finalised
 * into the ledger; a store-to-store transfer out on submit and in on
 * receipt, a roll moving whole; a count correction posting only once
 * verified and approved, never taking off more than is there; and a
 * finished month closed only when nothing dated in it is half way, after
 * which it takes no more movement.
 */
class KnittingGreyStoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $fab;

    private string $cut;

    private string $r1;

    private string $r2;

    private string $c1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->fab = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Grey Fabric Store', 'code' => 'ST-GRY', 'active' => true])->id;
        $this->cut = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Cutting Sub-Store', 'code' => 'ST-CUT', 'active' => true])->id;
        $bin = function (string $store, string $code): string {
            $id = (string) Str::ulid();
            DB::table('bins')->insert(['id' => $id, 'company_id' => $this->delta->id, 'store_id' => $store, 'name' => "Rack {$code}", 'code' => $code,
                'is_in_transit' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        };
        $this->r1 = $bin($this->fab, 'G-A1');
        $this->r2 = $bin($this->fab, 'G-A2');
        $this->c1 = $bin($this->cut, 'C-01');
    }

    public function test_receipt_opening_transfer_correction_and_close(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $po = KnitProdOrder::query()->create(['po_no' => 'FPO-1', 'order_kind' => 'BULK', 'order_id' => $order, 'buyer_id' => $buyer, 'fabric_desc' => 'S/J 30/1 Combed 180 GSM',
            'colour_name' => 'Black', 'finish_dia' => '72', 'gsm' => 180, 'req_qty' => 500, 'status' => 'RUNNING']);
        $kp = KnitProgram::query()->create(['kp_no' => 'KP-1', 'prod_order_id' => $po->id, 'program_date' => $today, 'qty' => 400, 'status' => 'RUNNING']);
        $card = KnitCard::query()->create(['kc_no' => 'KC-1', 'program_id' => $kp->id, 'prod_order_id' => $po->id, 'card_date' => $today, 'assigned_qty' => 150, 'colour_name' => 'Jet Black', 'status' => 'RUNNING']);
        $sheet = KnitRollSheet::query()->create(['sheet_no' => 'RS-1', 'card_id' => $card->id]);
        $roll = fn (int $n, float $w, string $q = 'PASSED') => KnitRoll::query()->create(['sheet_id' => $sheet->id, 'card_id' => $card->id, 'roll_no' => $n, 'roll_code' => "KC-1/{$n}",
            'roll_at' => now(), 'weight' => $w, 'deducted' => 0.2, 'movement' => 'IN_KNITTING', 'quality' => $q]);
        $k1 = $roll(1, 22.4);
        $k2 = $roll(2, 21.0);
        $roll(3, 20.0, 'REJECTED');

        // Store Receipt: store and rack first; the rack is the store's; each roll once.
        $scan = ['receive_date' => $today, 'roll_code' => 'kc-1/1', 'store_id' => $this->fab, 'bin_id' => $this->r1];
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['bin_id' => null] + $scan)->assertUnprocessable()
            ->assertJsonPath('errors.bin_id.0', 'Pick the store and the rack first — a roll put somewhere nobody recorded is a roll the stock report can count but nobody can find.');
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['bin_id' => $this->c1] + $scan)->assertUnprocessable()
            ->assertJsonPath('errors.bin_id.0', 'Must be a rack in the store chosen above.');
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['roll_code' => 'KC-9/9'] + $scan)->assertUnprocessable()->assertJsonPath('errors.roll_code.0', 'No roll carries the tag KC-9/9.');
        $day = $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', $scan)->assertCreated()->json('data');
        $this->assertEquals(["GFR-{$ym}0001", 'DRAFT', 22.2, 'KC-1/1', 'Rack G-A1', 'Jet Black', 'BULK'],
            [$day['note_no'], $day['status'], $day['total'], $day['rolls'][0]['roll_no'], $day['rolls'][0]['rack'], $day['rolls'][0]['colour'], $day['rolls'][0]['prod_type']]);
        $this->assertSame('GREY_STORE', $k1->refresh()->movement);
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', $scan)->assertUnprocessable()
            ->assertJsonPath('errors.roll_code.0', "KC-1/1 is already in the store (GFR-{$ym}0001) — a roll is received once.");
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['roll_code' => 'KC-1/3'] + $scan)->assertUnprocessable()
            ->assertJsonPath('errors.roll_code.0', 'KC-1/3 was rejected at inspection and does not go into the grey store.');
        $two = $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['roll_code' => 'KC-1/2', 'bin_id' => $this->r2] + $scan)->assertCreated()->json('data');
        $this->assertEquals([2, 43.0], [count($two['rolls']), $two['total']]);

        // A scan is withdrawn while the day is open; the roll goes back to the floor.
        $this->as()->deleteJson('/api/v1/knitting/fstore/receipts/rolls/'.collect($two['rolls'])->firstWhere('roll_no', 'KC-1/2')['id'])->assertOk();
        $this->assertEquals(['IN_KNITTING', 1], [$k2->refresh()->movement, count($this->as()->getJson("/api/v1/knitting/fstore/receipts/day?store={$this->fab}&date={$today}")->json('data.rolls'))]);
        $this->as()->postJson("/api/v1/knitting/fstore/receipts/GFR-{$ym}0001/confirm")->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['roll_code' => 'KC-1/2'] + $scan)->assertUnprocessable()
            ->assertJsonPath('errors.receive_date.0', "GFR-{$ym}0001 is confirmed — its rolls can no longer be changed here.");
        $this->as()->deleteJson('/api/v1/knitting/fstore/receipts/rolls/'.$day['rolls'][0]['id'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $stock = $this->as()->getJson("/api/v1/knitting/fstore/stock?store={$this->fab}")->assertOk()->json('data');
        $this->assertEquals([['KC-1/1', 22.2, 1, '770MR', 'LPP']], array_map(fn ($l) => [$l['roll_no'], $l['balance'], $l['rolls'], $l['style'], $l['buyer']], $stock));
        $this->assertEquals([[1, 22.2, 'CONFIRMED']], array_map(fn ($n) => [$n['rolls'], $n['weight'], $n['status']], $this->as()->getJson('/api/v1/knitting/fstore/receipts')->json('data')));

        // Opening lines: Opening required; finalised in one go; a finalised line is fixed.
        $ob = ['kind' => 'IN_HOUSE', 'opening_date' => $today, 'store_id' => $this->fab, 'order_id' => $order, 'fabric' => 'Rib 1x1 30/1', 'colour_name' => 'Navy', 'finish_dia' => '36', 'gsm' => 220, 'qty' => 100, 'rate' => 4.5];
        $this->as()->postJson('/api/v1/knitting/fstore/openings', ['qty' => null] + $ob)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Opening is required.');
        $o1 = $this->as()->postJson('/api/v1/knitting/fstore/openings', $ob)->assertCreated()->json('data');
        $this->assertEquals(["GOB-{$ym}0001", $buyer], [$o1['opening_no'], $o1['buyer_id']]);
        $this->as()->postJson('/api/v1/knitting/fstore/openings', ['kind' => 'COLLAR_CUFF', 'fabric' => 'Flat knit collar', 'qty' => 12] + $ob)->assertCreated();
        $this->as()->postJson('/api/v1/knitting/fstore/openings/finalize', ['nos' => []])->assertUnprocessable()->assertJsonPath('errors.nos.0', 'Tick at least one open line.');
        $this->as()->postJson('/api/v1/knitting/fstore/openings/finalize', ['nos' => [$o1['opening_no']]])->assertOk();
        $this->as()->putJson("/api/v1/knitting/fstore/openings/{$o1['opening_no']}", $ob)->assertUnprocessable()->assertJsonValidationErrors('finalized');
        $this->assertEquals([1, 1], [count($this->as()->getJson('/api/v1/knitting/fstore/openings?kind=IN_HOUSE&finalized=YES')->json('data')),
            count($this->as()->getJson('/api/v1/knitting/fstore/openings?finalized=NO')->json('data'))]);
        $navy = KnitGreyLot::query()->where('fabric', 'Rib 1x1 30/1')->value('id');
        $this->assertSame(100.0, GreyStore::balance($navy));

        // Store to store: the roll moves whole, a balance moves what is typed, never more than is there.
        $rollLot = $stock[0]['lot_id'];
        $t = ['transfer_date' => $today, 'trans_type' => 'BULK_TO_BULK', 'trans_ref' => 'CUT-REQ-7', 'from_store_id' => $this->fab, 'to_store_id' => $this->cut, 'order_id' => $order,
            'lines' => [['lot_id' => $rollLot, 'qty' => 1], ['lot_id' => $navy, 'roll_no' => 'B-1', 'qty' => 30]]];
        $this->as()->postJson('/api/v1/knitting/fstore/transfers', ['lines' => []] + $t)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A transfer with no fabric on it moves nothing.');
        $this->as()->postJson('/api/v1/knitting/fstore/transfers', ['to_store_id' => $this->fab] + $t)->assertUnprocessable()->assertJsonPath('errors.to_store_id.0', 'From Store and To Store must differ.');
        $this->as()->postJson('/api/v1/knitting/fstore/transfers', ['lines' => [['lot_id' => $navy, 'qty' => 130]]] + $t)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 moves more than the store holds (100 kg).']);
        $tr = $this->as()->postJson('/api/v1/knitting/fstore/transfers', $t)->assertCreated()->json('data');
        $this->assertEquals(["GFT-{$ym}0001", 22.2, 1, 'KC-1/1', 'B-1'], [$tr['transfer_no'], $tr['lines'][0]['qty'], $tr['lines'][0]['rolls'], $tr['lines'][0]['roll_no'], $tr['lines'][1]['roll_no']]);
        $no = $tr['transfer_no'];
        $this->as()->postJson("/api/v1/knitting/fstore/transfers/{$no}/receive")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Create Draft, so it cannot be received.");
        $this->as()->postJson("/api/v1/knitting/fstore/transfers/{$no}/submit")->assertOk();
        $this->assertEquals([0.0, 70.0], [GreyStore::balance($rollLot), GreyStore::balance($navy)]);
        $this->as()->postJson("/api/v1/knitting/fstore/transfers/{$no}/receive")->assertOk()->assertJsonPath('data.status', 'RECEIVED');
        $cut = $this->as()->getJson("/api/v1/knitting/fstore/stock?store={$this->cut}")->json('data');
        $this->assertEqualsCanonicalizing([['KC-1/1', 22.2, 1], [null, 30.0, 0]], array_map(fn ($l) => [$l['roll_id'] ? $l['roll_no'] : null, $l['balance'], $l['rolls']], $cut));
        $this->as()->postJson("/api/v1/knitting/fstore/transfers/{$no}/cancel")->assertUnprocessable();

        // Correction: posts only once verified and approved; never takes off more than is there.
        $a = ['adj_date' => $today, 'store_id' => $this->fab, 'reason' => 'October count',
            'lines' => [['order_id' => $order, 'colour_name' => 'Navy', 'fabric' => 'Rib 1x1 30/1', 'qty' => -4.5, 'uom' => 'kg', 'rate' => 4.5, 'reason' => 'Short on count']]];
        $this->as()->postJson('/api/v1/knitting/fstore/adjustments', ['lines' => []] + $a)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'An adjustment with no lines on it corrects nothing. Add at least one.');
        $this->as()->postJson('/api/v1/knitting/fstore/adjustments', ['lines' => [['qty' => 0] + $a['lines'][0]]] + $a)->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');
        $adj = $this->as()->postJson('/api/v1/knitting/fstore/adjustments', $a)->assertCreated()->json('data.adj_no');
        $this->assertSame("GFA-{$ym}0001", $adj);
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$adj}/approve")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$adj} is Draft, so it cannot be approved.");
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$adj}/verify")->assertOk();
        $this->assertSame(70.0, GreyStore::balance($navy));
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$adj}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame(65.5, GreyStore::balance($navy));
        $row = $this->as()->getJson('/api/v1/knitting/fstore/adjustments')->json('data.0');
        $this->assertEquals([-4.5, -20.25, 'ACTIVE'], [$row['qty'], $row['value'], $row['activity']]);
        $big = $this->as()->postJson('/api/v1/knitting/fstore/adjustments', ['lines' => [['qty' => -500] + $a['lines'][0]]] + $a)->assertCreated()->json('data.adj_no');
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$big}/verify")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$big}/approve")->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Line 1 takes off more than the store holds (65.5 kg).');
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$big}/cancel")->assertOk()->assertJsonPath('data.activity', 'CANCELLED');
        $this->as()->postJson("/api/v1/knitting/fstore/adjustments/{$adj}/cancel")->assertOk();
        $this->assertSame(70.0, GreyStore::balance($navy));

        // Month close: only a finished month, only when nothing dated in it is half way.
        $last = CarbonImmutable::now()->subMonthNoOverflow();
        $this->as()->postJson('/api/v1/knitting/fstore/closes', ['period' => now()->format('Y-m')])->assertUnprocessable()->assertJsonValidationErrors('period');
        $late = $this->as()->postJson('/api/v1/knitting/fstore/openings', ['opening_date' => $last->startOfMonth()->toDateString(), 'fabric' => 'Interlock 40/1', 'qty' => 50] + $ob)
            ->assertCreated()->json('data.opening_no');
        $exc = $this->as()->getJson('/api/v1/knitting/fstore/closes/exceptions?period='.$last->format('Y-m'))->assertOk()->json('data.exceptions');
        $this->assertEquals([['OPENING', $late]], array_map(fn ($e) => [$e['kind'], $e['no']], $exc));
        $this->as()->postJson('/api/v1/knitting/fstore/closes', ['period' => $last->format('Y-m')])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->as()->postJson('/api/v1/knitting/fstore/openings/finalize', ['nos' => [$late]])->assertOk();
        $this->as()->postJson('/api/v1/knitting/fstore/closes', ['period' => $last->format('Y-m'), 'remarks' => 'Counted'])->assertCreated();
        $this->as()->postJson('/api/v1/knitting/fstore/openings', ['opening_date' => $last->startOfMonth()->addDays(2)->toDateString(), 'fabric' => 'Interlock 40/1', 'qty' => 5] + $ob)
            ->assertCreated()->json('data.opening_no');
        $again = $this->as()->getJson('/api/v1/knitting/fstore/openings?finalized=NO')->json('data');
        $this->as()->postJson('/api/v1/knitting/fstore/openings/finalize', ['nos' => array_column(array_filter($again, fn ($x) => $x['fabric'] === 'Interlock 40/1'), 'opening_no')])
            ->assertUnprocessable()->assertJsonPath('errors.nos.0', 'Grey fabric for '.$last->format('F Y').' is closed — nothing more is booked into it.');
        $this->assertSame([$last->format('Y-m')], array_column($this->as()->getJson('/api/v1/knitting/fstore/closes')->json('data'), 'period'));
    }

    public function test_grey_movements_and_racking_board(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = fn (string $no, string $style) => Order::query()->create(['company_id' => $this->delta->id, 'order_no' => $no, 'job_no' => $style, 'style_no' => $style, 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $a = $order('LPP-2026-000060', '770MR');
        $b = $order('LPP-2026-000061', '771MR');
        [$f1, $f2] = $this->knit($a, 'FLAT_BED_RIB', 'Flat knit rib 1x1', [5.4, 5.0], 'F');
        [$r1] = $this->knit($a, 'BULK', 'S/J 30/1 Combed 160 GSM', [22.4], 'K', '30/1 Combed Conventional Cotton, Square, Lot SQ-3011');
        [$x1] = $this->knit($b, 'FLAT_BED_RIB', 'Flat knit rib 2x1', [4.0], 'X');
        $base = ['transfer_date' => $today, 'trans_type' => 'BULK_TO_BULK'];

        // Collar / Cuff: flat-bed rolls of the From Order still on the floor, each on one open transfer.
        $cc = $base + ['cc_type' => 'COLLAR', 'order_id' => $a, 'to_store_id' => $this->fab, 'trans_ref' => 'GRY-77',
            'lines' => [['roll_id' => $f1->id, 'colour_name' => 'Navy', 'qty' => 5.2]]];
        $this->as()->postJson('/api/v1/knitting/fstore/moves/collar-cuff', ['cc_type' => null] + $cc)->assertUnprocessable()->assertJsonPath('errors.cc_type.0', 'Trans Type is required.');
        $this->as()->postJson('/api/v1/knitting/fstore/moves/collar-cuff', ['lines' => []] + $cc)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A transfer with no rolls on it moves nothing.');
        $this->as()->postJson('/api/v1/knitting/fstore/moves/collar-cuff', ['lines' => [['roll_id' => $x1->id, 'qty' => 4]]] + $cc)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.roll_id' => 'Line 1: X-1 is not a roll of the From Order still on the knitting floor.']);
        $this->assertEquals(['F-1', 'F-2', 'K-1'], array_column($this->as()->getJson("/api/v1/knitting/fstore/floor-rolls?order={$a}")->assertOk()->json('data'), 'roll_no'));
        $cct = $this->as()->postJson('/api/v1/knitting/fstore/moves/collar-cuff', $cc)->assertCreated()->json('data');
        $this->assertEquals(["CCT-{$ym}0001", 'Flat knit rib 1x1', 'Navy', 5.2], [$cct['transfer_no'], $cct['lines'][0]['fabric'], $cct['lines'][0]['colour'], $cct['lines'][0]['qty']]);
        $this->assertEquals(['F-2', 'K-1'], array_column($this->as()->getJson("/api/v1/knitting/fstore/floor-rolls?order={$a}")->json('data'), 'roll_no'));
        $this->as()->postJson('/api/v1/knitting/fstore/moves/collar-cuff', $cc)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.roll_id' => 'F-1 is already promised to another open transfer, or already received.']);
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['receive_date' => $today, 'roll_code' => 'F-1', 'store_id' => $this->fab, 'bin_id' => $this->r1])
            ->assertUnprocessable()->assertJsonPath('errors.roll_code.0', "F-1 is on open transfer {$cct['transfer_no']} — receive or cancel that first.");
        $this->as()->postJson("/api/v1/knitting/fstore/moves/collar-cuff/{$cct['transfer_no']}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/moves/collar-cuff/{$cct['transfer_no']}/receive")->assertOk()->assertJsonPath('data.status', 'RECEIVED');
        $collar = KnitGreyLot::query()->where('kind', 'COLLAR_CUFF')->first();
        $this->assertEquals([5.2, 'Collar', 'Navy', $this->fab, 'GREY_STORE'], [GreyStore::balance($collar->id), $collar->part, $collar->colour_name, $collar->store_id, $f1->refresh()->movement]);
        $this->as()->getJson("/api/v1/knitting/fstore/moves/to-batch/{$cct['transfer_no']}")->assertNotFound();

        // To batch: grey rolls in store, onto a dye batch, at the batch's counted weight.
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['receive_date' => $today, 'roll_code' => 'K-1', 'store_id' => $this->fab, 'bin_id' => $this->r2])->assertCreated();
        $rollLot = KnitGreyLot::query()->where('roll_id', $r1->id)->value('id');
        $ob = $this->as()->postJson('/api/v1/knitting/fstore/openings', ['kind' => 'IN_HOUSE', 'opening_date' => $today, 'store_id' => $this->fab, 'order_id' => $a,
            'fabric' => 'Rib 1x1 30/1', 'colour_name' => 'Navy', 'qty' => 100])->assertCreated()->json('data.opening_no');
        $this->as()->postJson('/api/v1/knitting/fstore/openings/finalize', ['nos' => [$ob]])->assertOk();
        $rib = KnitGreyLot::query()->where('fabric', 'Rib 1x1 30/1')->value('id');
        $tb = $base + ['order_id' => $a, 'from_store_id' => $this->fab, 'batch_no' => 'b-77', 'lines' => [['lot_id' => $rollLot, 'qty' => 22.0]]];
        $this->as()->postJson('/api/v1/knitting/fstore/moves/to-batch', ['batch_no' => null] + $tb)->assertUnprocessable()->assertJsonPath('errors.batch_no.0', 'To Batch is required.');
        $this->as()->postJson('/api/v1/knitting/fstore/moves/to-batch', ['lines' => [['lot_id' => $rib, 'qty' => 5]]] + $tb)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.lot_id' => 'Line 1 is not a grey roll in store (only weighed rolls go onto a batch, and once).']);
        $gbt = $this->as()->postJson('/api/v1/knitting/fstore/moves/to-batch', $tb)->assertCreated()->json('data.transfer_no');
        $this->as()->postJson("/api/v1/knitting/fstore/moves/to-batch/{$gbt}/submit")->assertOk();
        $this->assertSame(0.0, GreyStore::balance($rollLot));
        $this->as()->postJson("/api/v1/knitting/fstore/moves/to-batch/{$gbt}/receive")->assertOk();
        $batch = KnitGreyLot::query()->where('batch_no', 'B-77')->first();
        $this->assertEquals([22.0, $this->fab, 'BATCH_STORE'], [GreyStore::balance($batch->id), $batch->store_id, $r1->refresh()->movement]);

        // Order to order: the same fabric, credited to another order where it lies.
        $oo = $base + ['order_id' => $a, 'to_order_id' => $b, 'lines' => [['lot_id' => $rib, 'roll_no' => 'B-1', 'qty' => 40]]];
        $this->as()->postJson('/api/v1/knitting/fstore/moves/order', ['to_order_id' => $a] + $oo)->assertUnprocessable()
            ->assertJsonPath('errors.to_order_id.0', 'From Order# and To Order# have to be two different orders.');
        $oot = $this->as()->postJson('/api/v1/knitting/fstore/moves/order', $oo)->assertCreated()->json('data.transfer_no');
        $this->as()->postJson("/api/v1/knitting/fstore/moves/order/{$oot}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/moves/order/{$oot}/receive")->assertOk();
        $ribB = KnitGreyLot::query()->where('fabric', 'Rib 1x1 30/1')->where('order_id', $b)->first();
        $this->assertEquals([60.0, 40.0, $this->fab], [GreyStore::balance($rib), GreyStore::balance($ribB->id), $ribB->store_id]);

        // Into left-over, and back onto a live order.
        $lo = $base + ['order_id' => $a, 'from_store_id' => $this->fab, 'to_store_id' => $this->cut, 'lines' => [['lot_id' => $rib, 'qty' => 20]]];
        $this->as()->postJson('/api/v1/knitting/fstore/moves/to-leftover', ['order_id' => null] + $lo)->assertUnprocessable()->assertJsonPath('errors.order_id.0', 'Style/Order# is required.');
        $glo = $this->as()->postJson('/api/v1/knitting/fstore/moves/to-leftover', $lo)->assertCreated()->json('data.transfer_no');
        $this->as()->postJson("/api/v1/knitting/fstore/moves/to-leftover/{$glo}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/moves/to-leftover/{$glo}/receive")->assertOk();
        $left = KnitGreyLot::query()->where('store_id', $this->cut)->where('fabric', 'Rib 1x1 30/1')->first();
        $this->assertEquals([40.0, 20.0, $a], [GreyStore::balance($rib), GreyStore::balance($left->id), $left->order_id]);
        $ru = $base + ['order_id' => $b, 'from_store_id' => $this->cut, 'lines' => [['lot_id' => $left->id, 'qty' => 15]]];
        $gru = $this->as()->postJson('/api/v1/knitting/fstore/moves/reuse', $ru)->assertCreated()->json('data.transfer_no');
        $this->as()->postJson("/api/v1/knitting/fstore/moves/reuse/{$gru}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/moves/reuse/{$gru}/receive")->assertOk();
        $reused = KnitGreyLot::query()->where('store_id', $this->cut)->where('order_id', $b)->first();
        $this->assertEquals([5.0, 15.0], [GreyStore::balance($left->id), GreyStore::balance($reused->id)]);

        // Batch store to left-over: only what a batch holds; the roll goes to left-over.
        $bl = $base + ['order_id' => $a, 'from_store_id' => $this->fab, 'to_store_id' => $this->cut, 'lines' => [['lot_id' => $rib, 'qty' => 5]]];
        $this->as()->postJson('/api/v1/knitting/fstore/moves/batch-leftover', $bl)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.lot_id' => 'Line 1 is not held on a dye batch.']);
        $blo = $this->as()->postJson('/api/v1/knitting/fstore/moves/batch-leftover', ['lines' => [['lot_id' => $batch->id, 'qty' => 1]]] + $bl)->assertCreated()->json('data');
        $this->assertEquals([22.0, 1], [$blo['lines'][0]['qty'], $blo['lines'][0]['rolls']]);
        $this->as()->postJson("/api/v1/knitting/fstore/moves/batch-leftover/{$blo['transfer_no']}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/moves/batch-leftover/{$blo['transfer_no']}/receive")->assertOk();
        $out = KnitGreyLot::query()->where('store_id', $this->cut)->where('roll_id', $r1->id)->first();
        $this->assertEquals([0.0, 22.0, null, 'LEFT_OVER'], [GreyStore::balance($batch->id), GreyStore::balance($out->id), $out->batch_no, $r1->refresh()->movement]);
        $this->assertEquals([[$blo['transfer_no'], 1, 22.0, 'RECEIVED']],
            array_map(fn ($x) => [$x['transfer_no'], $x['rolls'], $x['qty'], $x['status']], $this->as()->getJson('/api/v1/knitting/fstore/moves/batch-leftover')->json('data')));

        // Racking Board: what each rack holds, narrowed by the card's yarn.
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['receive_date' => $today, 'roll_code' => 'F-2', 'store_id' => $this->fab, 'bin_id' => $this->r2])->assertCreated();
        $board = $this->as()->getJson("/api/v1/knitting/fstore/racks?store={$this->fab}")->assertOk()->json('data');
        $this->assertEquals(['Rack G-A2'], array_values(array_unique(array_filter(array_column($board, 'rack')))));
        $this->assertSame([], $this->as()->getJson("/api/v1/knitting/fstore/racks?store={$this->cut}&spin=Vortex")->json('data'));
        $this->assertEquals(['K-1'], array_column($this->as()->getJson("/api/v1/knitting/fstore/racks?store={$this->cut}&lot=SQ-3011")->json('data'), 'roll_no'));
    }

    /**
     * Rolls knitted on a production order of an order, still on the floor.
     *
     * @return list<KnitRoll>
     */
    private function knit(string $order, string $kind, string $fabric, array $weights, string $code, ?string $yarn = null): array
    {
        static $n = 0;
        $n++;
        $po = KnitProdOrder::query()->create(['po_no' => "FPO-{$n}", 'order_kind' => $kind, 'order_id' => $order, 'buyer_id' => Order::query()->whereKey($order)->value('buyer_id'),
            'fabric_desc' => $fabric, 'colour_name' => 'Navy', 'req_qty' => 500, 'status' => 'RUNNING']);
        $kp = KnitProgram::query()->create(['kp_no' => "KP-{$n}", 'prod_order_id' => $po->id, 'program_date' => now()->toDateString(), 'qty' => 400, 'status' => 'RUNNING']);
        $card = KnitCard::query()->create(['kc_no' => "KC-{$n}", 'program_id' => $kp->id, 'prod_order_id' => $po->id, 'card_date' => now()->toDateString(), 'assigned_qty' => 150,
            'yarn_details' => $yarn, 'status' => 'RUNNING']);
        $sheet = KnitRollSheet::query()->create(['sheet_no' => "RS-{$n}", 'card_id' => $card->id]);

        return array_map(fn ($w, $i) => KnitRoll::query()->create(['sheet_id' => $sheet->id, 'card_id' => $card->id, 'roll_no' => $i + 1, 'roll_code' => "{$code}-".($i + 1),
            'roll_at' => now(), 'weight' => $w + 0.2, 'deducted' => 0.2, 'movement' => 'IN_KNITTING', 'quality' => 'PASSED']), $weights, array_keys($weights));
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
