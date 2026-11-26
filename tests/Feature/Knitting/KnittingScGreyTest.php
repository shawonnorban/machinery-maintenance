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
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract grey
 * challans: an outside knitter's receive challan booked into a store only
 * when received (and only with a store), collar / cuff challans passed,
 * held or rejected at QC; return challans taking rolls out on issue (a line
 * with no roll moves nothing), against a receive of that party; and an
 * in-house delivery carrying rolls, collar / cuff and the party's own yarn,
 * off both ledgers on issue and back on cancel.
 */
class KnittingScGreyTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $grey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->grey = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Grey Fabric Store', 'code' => 'ST-GRY', 'active' => true])->id;
    }

    public function test_out_house_receipt_qc_returns_and_in_house_delivery(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $knitter = MaterialSupplier::query()->create(['code' => 'SUP-30001', 'name' => 'Akij Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $other = MaterialSupplier::query()->create(['code' => 'SUP-30002', 'name' => 'Other Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $party = MaterialSupplier::query()->create(['code' => 'SUP-30003', 'name' => 'Tamishna Fashion', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;

        // Out-house receipt: lines and the party's challan required; a draft moves nothing.
        $line = ['type' => 'FABRIC', 'description' => 'S/J 30/1 Combed 160 GSM', 'colour_name' => 'Navy', 'shade' => 'A', 'roll_no' => 'AK-R1', 'finish_dia' => '72', 'gsm' => 160,
            'r_qty' => 3, 'qty' => 60, 'uom' => 'kg'];
        $collar = ['type' => 'COLLAR', 'description' => 'Flat knit collar 2x1', 'colour_name' => 'Navy', 'qty' => 400, 'uom' => 'pcs'];
        $r = ['rcv_date' => $today, 'party_id' => $knitter, 'challan_no' => 'AK/CH-501', 'challan_date' => $today, 'order_id' => $order, 'gate_pass_no' => 'GP-9', 'lines' => [$line, $collar]];
        $this->as()->postJson('/api/v1/knitting/fstore/sc/receipts', ['lines' => []] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A receive challan with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/knitting/fstore/sc/receipts', ['challan_no' => null] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.challan_no.0', "Challan# is required — it is the party's own number.");
        $rcv = $this->as()->postJson('/api/v1/knitting/fstore/sc/receipts', $r)->assertCreated()->json('data');
        $this->assertEquals(["OGR-{$ym}0001", 'PCS', 'DRAFT'], [$rcv['rcv_no'], $rcv['lines'][1]['uom'], $rcv['status']]);
        $no = $rcv['rcv_no'];
        $this->as()->postJson('/api/v1/knitting/fstore/sc/receipts', $r)->assertUnprocessable()->assertJsonPath('errors.challan_no.0', "The party's challan AK/CH-501 is already booked in.");
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$no}/receive")->assertUnprocessable()
            ->assertJsonPath('errors.store_id.0', 'Pick the store the fabric was put in before receiving the challan.');
        $this->as()->putJson("/api/v1/knitting/fstore/sc/receipts/{$no}", ['store_id' => $this->grey] + $r)->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$no}/receive")->assertOk()->assertJsonPath('data.status', 'RECEIVED');
        $fab = KnitGreyLot::query()->where('party_id', $knitter)->where('kind', 'IN_HOUSE')->first();
        $cc = KnitGreyLot::query()->where('party_id', $knitter)->where('kind', 'COLLAR_CUFF')->first();
        $this->assertEquals([60.0, 'AK-R1', $buyer, 400.0, 'Collar'], [GreyStore::balance($fab->id), $fab->roll_no, $fab->buyer_id, GreyStore::balance($cc->id), $cc->part]);
        $row = $this->as()->getJson('/api/v1/knitting/fstore/sc/receipts')->json('data.0');
        $this->assertEquals([60.0, 400.0, 3, 'Akij Knit'], [$row['fabric'], $row['pieces'], $row['rolls'], $row['party']]);

        // QC: only collar / cuff challans; pass from received or hold, never twice.
        $fabricOnly = $this->as()->postJson('/api/v1/knitting/fstore/sc/receipts', ['challan_no' => 'AK/CH-502', 'store_id' => $this->grey, 'lines' => [['roll_no' => 'AK-R2'] + $line]] + $r)->assertCreated()->json('data.rcv_no');
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$fabricOnly}/receive")->assertOk();
        $this->assertEquals([$no], array_column($this->as()->getJson('/api/v1/knitting/fstore/sc/receipts?qc=1')->json('data'), 'rcv_no'));
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$no}/qc-hold")->assertOk()->assertJsonPath('data.status', 'QC_HOLD');
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$no}/qc-pass")->assertOk()->assertJsonPath('data.status', 'QC_PASSED');
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$no}/qc-reject")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is QC Passed, so it cannot be rejected.");

        // Out-house return: against a receive of this party; a stock line leaves on issue, a bare quantity moves nothing.
        $ret = ['challan_date' => $today, 'party_id' => $knitter, 'store_id' => $this->grey, 'against' => $no, 'reason' => 'Holes in two rolls',
            'lines' => [['type' => 'FABRIC', 'lot_id' => $fab->id, 'qty' => 25, 'uom' => 'kg'], ['type' => 'FABRIC', 'description' => 'Cut pieces', 'qty' => 2, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/fstore/sc/returns/out-house', ['party_id' => $other] + $ret)->assertUnprocessable()
            ->assertJsonPath('errors.against.0', "That receive is not this party's.");
        $this->as()->postJson('/api/v1/knitting/fstore/sc/returns/out-house', ['lines' => [['lot_id' => $fab->id, 'qty' => 61] + $ret['lines'][0]]] + $ret)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 takes more than the store holds (60).']);
        $ogt = $this->as()->postJson('/api/v1/knitting/fstore/sc/returns/out-house', $ret)->assertCreated()->json('data');
        $this->assertEquals(["OGT-{$ym}0001", 'S/J 30/1 Combed 160 GSM', 'AK-R1', $no], [$ogt['challan_no'], $ogt['lines'][0]['description'], $ogt['lines'][0]['roll_no'], $ogt['against']]);
        $this->assertSame(60.0, GreyStore::balance($fab->id));
        $this->as()->postJson("/api/v1/knitting/fstore/sc/returns/out-house/{$ogt['challan_no']}/issue")->assertOk();
        $this->assertSame(35.0, GreyStore::balance($fab->id));
        $this->as()->postJson("/api/v1/knitting/fstore/sc/returns/out-house/{$ogt['challan_no']}/received-back")->assertOk()->assertJsonPath('data.status', 'RECEIVED_BACK');
        $this->as()->postJson("/api/v1/knitting/fstore/sc/receipts/{$no}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->getJson("/api/v1/knitting/fstore/sc/returns/in-house/{$ogt['challan_no']}")->assertNotFound();

        // In-house return: against the party's programme receipt.
        $scr = KnitScReceipt::query()->create(['program_no' => 'SCR-'.$ym.'0001', 'auto_number' => true, 'prog_date' => $today, 'party_id' => $party, 'yarn_store_id' => $this->grey, 'status' => 'CONFIRMED']);
        $igt = $this->as()->postJson('/api/v1/knitting/fstore/sc/returns/in-house', ['party_id' => $party, 'against' => $scr->program_no,
            'lines' => [['type' => 'FABRIC', 'description' => 'Rib 1x1', 'qty' => 5, 'uom' => 'kg']]] + $ret)->assertCreated()->json('data');
        $this->assertEquals(["IGT-{$ym}0001", $scr->program_no], [$igt['challan_no'], $igt['against']]);

        // In-house delivery: a knitted roll, collar, and the party's own yarn — off both ledgers on issue, back on cancel.
        $po = KnitProdOrder::query()->create(['po_no' => 'FPO-1', 'order_kind' => 'BULK', 'order_id' => $order, 'buyer_id' => $buyer, 'fabric_desc' => 'S/J 24/1 200 GSM', 'colour_name' => 'Red',
            'req_qty' => 500, 'status' => 'RUNNING']);
        $kp = KnitProgram::query()->create(['kp_no' => 'KP-1', 'prod_order_id' => $po->id, 'program_date' => $today, 'qty' => 400, 'status' => 'RUNNING']);
        $card = KnitCard::query()->create(['kc_no' => 'KC-1', 'program_id' => $kp->id, 'prod_order_id' => $po->id, 'card_date' => $today, 'assigned_qty' => 150, 'gauge' => '24G', 'status' => 'RUNNING']);
        $sheet = KnitRollSheet::query()->create(['sheet_no' => 'RS-1', 'card_id' => $card->id]);
        $roll = KnitRoll::query()->create(['sheet_id' => $sheet->id, 'card_id' => $card->id, 'roll_no' => 1, 'roll_code' => 'KC-1/1', 'roll_at' => now(), 'weight' => 21.0, 'deducted' => 0,
            'movement' => 'IN_KNITTING', 'quality' => 'PASSED']);
        $bin = (string) Str::ulid();
        DB::table('bins')->insert(['id' => $bin, 'company_id' => $this->delta->id, 'store_id' => $this->grey, 'name' => 'Rack G1', 'code' => 'G1', 'is_in_transit' => false, 'active' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->as()->postJson('/api/v1/knitting/fstore/receipts/scan', ['receive_date' => $today, 'roll_code' => 'KC-1/1', 'store_id' => $this->grey, 'bin_id' => $bin])->assertCreated();
        $rollLot = KnitGreyLot::query()->where('roll_id', $roll->id)->value('id');
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-24-1', 'count_system' => 'Ne', 'description' => '24/1', 'count_value' => 24, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $yarn = YarnStore::lotFor(['store_id' => $this->grey, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'colour' => 'Natural', 'lot_no' => 'TF-9', 'supplier_id' => $party,
            'source' => 'SC_PARTY', 'uom' => 'kg'], $today);
        YarnStore::post('SC_YARN_RECEIVE', 'SYC-T', $today, [['lot_id' => $yarn->id, 'qty' => 50]]);
        $this->assertEquals([$yarn->id], array_column($this->as()->getJson("/api/v1/knitting/fstore/sc/party-yarn?party={$party}&store={$this->grey}")->json('data'), 'lot_id'));
        $d = ['challan_date' => $today, 'party_id' => $party, 'store_id' => $this->grey, 'gate_pass_no' => 'GP-21', 'vehicle_no' => 'DM-TA-11', 'receiver' => 'Karim', 'receiver_mobile' => '01700000000',
            'lines' => [['section' => 'FABRIC', 'lot_id' => $rollLot, 'qty' => 20.8, 'uom' => 'kg'], ['section' => 'COLLAR_CUFF', 'part' => 'COLLAR', 'lot_id' => $cc->id, 'qty' => 150, 'uom' => 'pcs'],
                ['section' => 'YARN', 'yarn_lot_id' => $yarn->id, 'bags' => 2, 'qty' => 12, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/fstore/sc/deliveries', ['lines' => []] + $d)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A delivery challan with nothing on it — no fabric, collar/cuff or yarn — sends nothing.');
        $this->as()->postJson('/api/v1/knitting/fstore/sc/deliveries', ['party_id' => $other] + $d)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.2.yarn_lot_id' => "Line 3's yarn is not this party's."]);
        $igd = $this->as()->postJson('/api/v1/knitting/fstore/sc/deliveries', $d)->assertCreated()->json('data');
        $this->assertEquals(["IGD-{$ym}0001", 'KC-1/1', '24G', 'TF-9', 'Collar'], [$igd['challan_no'], $igd['lines'][0]['roll_no'], $igd['lines'][0]['mc_gauge'], $igd['lines'][2]['lot_no'],
            KnitGreyLot::query()->find($igd['lines'][1]['lot_id'])->part]);
        $this->as()->postJson("/api/v1/knitting/fstore/sc/deliveries/{$igd['challan_no']}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals([0.0, 250.0, 38.0, 'DELIVERED'], [GreyStore::balance($rollLot), GreyStore::balance($cc->id), YarnStore::balance($yarn->id), $roll->refresh()->movement]);
        $list = $this->as()->getJson('/api/v1/knitting/fstore/sc/deliveries')->json('data.0');
        $this->assertEquals([1, 20.8, 150.0, 12.0, 'Karim'], [$list['rolls'], $list['fabric'], $list['pieces'], $list['yarn'], $list['receiver']]);
        $this->as()->postJson("/api/v1/knitting/fstore/sc/deliveries/{$igd['challan_no']}/cancel")->assertOk();
        $this->assertEquals([21.0, 400.0, 50.0, 'GREY_STORE'], [GreyStore::balance($rollLot), GreyStore::balance($cc->id), YarnStore::balance($yarn->id), $roll->refresh()->movement]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
