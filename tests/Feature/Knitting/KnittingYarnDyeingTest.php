<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptYarn;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → yarn dyeing: a Y/D programme raised on the register
 * (shades, a buyer's order, revisions numbered in order); greige issued
 * against it on a Yarn Dye requisition counting as its Issue; a dyed receipt
 * moving nothing until confirmed, then booking Delivery and taking the cones
 * onto the store; an approved process-loss memo writing the shrinkage off so
 * With Party = Issue − Delivery − Loss; re-process documents (in-house ones
 * leaving the store on issue and coming back on rejection; S/C ones never
 * on it); and a sub-contract party's yarn received against its programme
 * receipt, onto the store only when accepted.
 */
class KnittingYarnDyeingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $dyer;

    private string $knitter;

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
        $this->dyer = MaterialSupplier::query()->create(['code' => 'SUP-20001', 'name' => 'Rainbow Dyeing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->knitter = MaterialSupplier::query()->create(['code' => 'SUP-20002', 'name' => 'Akij Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
    }

    public function test_programme_issue_receipt_loss_reprocess_and_sc_yarn(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $navy = Colour::query()->create(['code' => 'NV-01', 'name' => 'Navy', 'colour_type' => 'SOLID', 'active' => true])->id;
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000061', 'job_no' => '7702', 'style_no' => '771YD', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => now()->addMonth()->toDateString(), 'status' => 'CONFIRMED'])->id;
        $yarn = ['composition_id' => $cotton, 'yarn_count_id' => $count];

        // Greige to issue from: lot G-1, 1,000 kg.
        $ob = $this->as()->postJson('/api/v1/knitting/ystore/obs', ['ob_date' => $today, 'store_id' => $this->store, 'currency_id' => $usd, 'is_buffer' => true, 'lines' => [
            $yarn + ['colour' => 'Natural', 'source' => 'LOCAL', 'lot_no' => 'G-1', 'supplier_id' => $this->knitter, 'total_qty' => 1000, 'cost_price' => 3],
        ]])->assertCreated()->json('data.ob_no');
        $this->as()->postJson("/api/v1/knitting/ystore/obs/{$ob}/finalize")->assertOk();
        $greige = YarnLot::query()->where('lot_no', 'G-1')->value('id');

        // The programme: shades required; the order brings its buyer.
        $shade = $yarn + ['colour_id' => $navy, 'colour_name' => 'Navy', 'colour_group' => 'Dark Shade', 'lot_no' => 'G-1', 'qty' => 60, 'uom' => 'kg', 'rate' => 120];
        $p = ['program_date' => $today, 'yd_type' => 'IN_HOUSE', 'supplier_id' => $this->dyer, 'order_id' => $order, 'delivery_date' => now()->addDays(12)->toDateString(),
            'status' => 'RUNNING', 'lines' => [$shade, ['colour_id' => null, 'colour_name' => 'Off White', 'colour_group' => 'Light Shade', 'qty' => 40, 'rate' => 90] + $shade]];
        $this->as()->postJson('/api/v1/knitting/prod/yd/programs', ['lines' => []] + $p)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Add at least one line before saving.');
        $this->as()->postJson('/api/v1/knitting/prod/yd/programs', ['supplier_id' => null] + $p)->assertUnprocessable()->assertJsonPath('errors.supplier_id.0', 'Party is required.');
        $made = $this->as()->postJson('/api/v1/knitting/prod/yd/programs', $p)->assertCreated()->json('data');
        $no = "YDP-{$ym}0001";
        $this->assertEquals([$no, $buyer, 0, 100.0, '100% Cotton 30/1'], [$made['program_no'], $made['buyer_id'], $made['rev_no'], $made['qty'], $made['lines'][0]['yarn']]);
        [$sNavy, $sWhite] = array_column($made['lines'], 'id');

        // Revisions: numbered in order, each with why.
        $this->as()->postJson('/api/v1/knitting/prod/yd/revisions', ['program_no' => null, 'revised_on' => $today, 'reason' => 'x'])->assertUnprocessable()
            ->assertJsonPath('errors.program_no.0', 'Y/D Program # is required.');
        $this->as()->postJson('/api/v1/knitting/prod/yd/revisions', ['program_no' => $no, 'revised_on' => now()->subYear()->toDateString(), 'reason' => 'x'])->assertUnprocessable()
            ->assertJsonValidationErrors('revised_on');
        $this->as()->postJson('/api/v1/knitting/prod/yd/revisions', ['program_no' => $no, 'revised_on' => $today, 'reason' => 'Navy shade card revised by buyer'])->assertCreated();
        $rev = $this->as()->postJson('/api/v1/knitting/prod/yd/revisions', ['program_no' => $no, 'revised_on' => $today, 'reason' => 'Delivery moved'])->assertCreated()->json('data');
        $this->assertEquals([2, [2, 1], 'Delivery moved'], [$rev['rev_no'], array_column($rev['revisions'], 'rev_no'), $rev['revisions'][0]['reason']]);

        // Greige issued against it on a Yarn Dye requisition is its Issue.
        $req = $this->as()->postJson('/api/v1/knitting/ystore/issue-reqs', ['req_date' => $today, 'req_type' => 'YARN_DYE', 'store_id' => $this->store, 'program_no' => $no,
            'party_id' => $this->dyer, 'lines' => [$yarn + ['colour' => 'Natural', 'uom' => 'kg', 'req_qty' => 100]]])->assertCreated()->json('data');
        $reqLine = $this->as()->getJson("/api/v1/knitting/ystore/issue-reqs/{$req['req_no']}/source")->assertOk()->json('data.lines.0.id');
        $this->as()->postJson('/api/v1/knitting/ystore/issues', ['issue_date' => $today, 'req_no' => $req['req_no'], 'lines' => [['req_line_id' => $reqLine, 'lot_id' => $greige, 'qty' => 100]]])->assertCreated();
        $row = $this->as()->getJson('/api/v1/knitting/prod/yd/programs')->assertOk()->json('data.0');
        $this->assertEquals([100.0, 0.0, 100.0, 2, 'LPP', '771YD'], [$row['issue'], $row['delivery'], $row['balance'], $row['rev_no'], $row['buyer'], $row['style']]);

        // A dyed receipt: shades of this programme only; a draft moves nothing.
        $r = ['receive_date' => $today, 'program_no' => $no, 'working_ref' => 'FL-12', 'challan_no' => 'RD/CH-551', 'challan_date' => $today, 'store_id' => $this->store,
            'lines' => [['program_line_id' => $sNavy, 'lot_no' => 'd-nv-1', 'qty' => 58, 'uom' => 'kg', 'cones' => 48], ['program_line_id' => $sWhite, 'lot_no' => 'D-OW-1', 'qty' => 39, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/ystore/yd/receipts', ['lines' => []] + $r)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Add at least one line before saving.');
        $this->as()->postJson('/api/v1/knitting/ystore/yd/receipts', ['lines' => [['program_line_id' => $greige] + $r['lines'][0]]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.program_line_id' => "Line 1 is not a shade of {$no}."]);
        $rcv = $this->as()->postJson('/api/v1/knitting/ystore/yd/receipts', $r)->assertCreated()->json('data');
        $this->assertEquals(["YDR-{$ym}0001", 'Rainbow Dyeing', 'D-NV-1', 'DRAFT'], [$rcv['receive_no'], $rcv['party'], $rcv['lines'][0]['lot_no'], $rcv['status']]);
        $this->assertEquals(0.0, $this->as()->getJson('/api/v1/knitting/prod/yd/programs')->json('data.0.delivery'));

        // Confirming books the delivery and takes the cones onto the store.
        $this->as()->postJson("/api/v1/knitting/ystore/yd/receipts/{$rcv['receive_no']}/confirm")->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $dyed = YarnLot::query()->where('lot_no', 'D-NV-1')->first();
        $this->assertEquals([58.0, 'INHOUSE_DYED', 'Dark Shade', $buyer], [YarnStore::balance($dyed->id), $dyed->source, $dyed->colour, $dyed->buyer_id]);
        $this->as()->putJson("/api/v1/knitting/ystore/yd/receipts/{$rcv['receive_no']}", $r)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$rcv['receive_no']} is Confirmed, so it cannot be saved.");
        $opts = $this->as()->getJson('/api/v1/knitting/ystore/yd/options')->assertOk()->json('data.programs.0');
        $this->assertEquals([97.0, 3.0, [2.0, 1.0], [60.0, 40.0]], [$opts['delivery'], $opts['pending'], array_column($opts['lines'], 'pending'), array_column($opts['lines'], 'issued_share')]);
        // A received shade stays on the programme.
        $this->as()->putJson("/api/v1/knitting/prod/yd/programs/{$no}", ['lines' => [['id' => $sWhite] + $p['lines'][1]]] + $p)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Shade Navy has yarn received against it, so it cannot be removed.');

        // Process loss: received never above issued; only approval moves the figures.
        $m = ['memo_date' => $today, 'program_no' => $no, 'lines' => [['program_line_id' => $sNavy, 'colour_group' => 'Dark Shade', 'lot_no' => 'G-1', 'issued_qty' => 60, 'received_qty' => 58],
            ['program_line_id' => $sWhite, 'issued_qty' => 40, 'received_qty' => 39]]];
        $this->as()->postJson('/api/v1/knitting/ystore/yd/loss-memos', ['memo_date' => null] + $m)->assertUnprocessable()->assertJsonPath('errors.memo_date.0', 'Date is required.');
        $this->as()->postJson('/api/v1/knitting/ystore/yd/loss-memos', ['lines' => [['received_qty' => 61] + $m['lines'][0]]] + $m)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.received_qty' => 'Line 1: Received Qty cannot be more than Issued Qty.']);
        $memo = $this->as()->postJson('/api/v1/knitting/ystore/yd/loss-memos', $m)->assertCreated()->json('data');
        $this->assertEquals(["YDL-{$ym}0001", 2.0, 3.33], [$memo['memo_no'], $memo['lines'][0]['loss'], $memo['lines'][0]['loss_pct']]);
        $this->assertEquals(3.0, $this->as()->getJson('/api/v1/knitting/ystore/yd/statement')->json('data.0.balance'));
        $this->as()->postJson("/api/v1/knitting/ystore/yd/loss-memos/{$memo['memo_no']}/approve")->assertOk();
        $st = $this->as()->getJson("/api/v1/knitting/ystore/yd/statement?buyer={$buyer}&status=RUNNING&month=".now()->addMonth()->format('Y-m'))->assertOk()->json('data');
        $this->assertEquals([[$no, 100.0, 97.0, 3.0, 0.0]], array_map(fn ($x) => [$x['program_no'], $x['issue'], $x['delivery'], $x['loss'], $x['balance']], $st));
        $this->assertSame([], $this->as()->getJson('/api/v1/knitting/ystore/yd/statement?status=CLOSED')->json('data'));

        // In-house re-process: off the shelf on issue, back on rejection; counted in the programme's Issue.
        $rp = ['ref_date' => $today, 'supplier_id' => $this->dyer, 'bill_no' => 'B-1', 'challan_no' => 'RP-01', 'program_no' => $no, 'store_id' => $this->store,
            'lines' => [['lot_id' => $dyed->id, 'qty' => 10, 'uom' => 'kg', 'reason' => 'Off-shade']]];
        $this->as()->postJson('/api/v1/knitting/ystore/yd/reprocess/in-house', ['lines' => [['reason' => null] + $rp['lines'][0]]] + $rp)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.reason' => 'Every line needs its reason.']);
        $this->as()->postJson('/api/v1/knitting/ystore/yd/reprocess/sc-knitting', ['party_id' => $this->knitter, 'lines' => [$yarn + ['qty' => 5, 'uom' => 'kg', 'reason' => 'Tippy']]] + $rp)
            ->assertUnprocessable()->assertJsonPath('errors.program_no.0', "{$no} is marked In-House; this screen takes the other kind of programme.");
        $ih = $this->as()->postJson('/api/v1/knitting/ystore/yd/reprocess/in-house', $rp)->assertCreated()->json('data');
        $this->assertEquals(["YRP-{$ym}0001", 'D-NV-1', 'Dark Shade', '100% Cotton 30/1'], [$ih['ref_no'], $ih['lines'][0]['lot_no'], $ih['lines'][0]['colour_group'], $ih['lines'][0]['yarn']]);
        $this->as()->postJson("/api/v1/knitting/ystore/yd/reprocess/in-house/{$ih['ref_no']}/issue")->assertOk();
        $this->assertSame(48.0, YarnStore::balance($dyed->id));
        $this->assertEquals([110.0, 10.0], [$this->as()->getJson('/api/v1/knitting/prod/yd/programs')->json('data.0.issue'), $this->as()->getJson('/api/v1/knitting/prod/yd/programs')->json('data.0.balance')]);
        $this->as()->putJson("/api/v1/knitting/ystore/yd/reprocess/in-house/{$ih['ref_no']}", $rp)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/ystore/yd/reprocess/in-house/{$ih['ref_no']}/reject")->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertEquals([58.0, 100.0], [YarnStore::balance($dyed->id), $this->as()->getJson('/api/v1/knitting/prod/yd/programs')->json('data.0.issue')]);

        // A confirmed receipt whose cones have left cannot be cancelled.
        $all = $this->as()->postJson('/api/v1/knitting/ystore/yd/reprocess/in-house', ['lines' => [['qty' => 58] + $rp['lines'][0]]] + $rp)->assertCreated()->json('data.ref_no');
        $this->as()->postJson("/api/v1/knitting/ystore/yd/reprocess/in-house/{$all}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/ystore/yd/receipts/{$rcv['receive_no']}/cancel")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$rcv['receive_no']} cannot be cancelled: lot D-NV-1 has already gone out of the store.");
        $this->as()->postJson("/api/v1/knitting/ystore/yd/reprocess/in-house/{$all}/issue")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$all} is Issued, so it cannot be issued.");

        // S/C re-process: the knitter named, never on the store.
        $scp = ['ref_date' => $today, 'supplier_id' => $this->dyer, 'store_id' => $this->store, 'lines' => [$yarn + ['colour_group' => 'Dyed', 'lot_no' => 'AK-9', 'qty' => 5, 'uom' => 'kg', 'reason' => 'Tippy']]];
        $this->as()->postJson('/api/v1/knitting/ystore/yd/reprocess/sc-knitting', $scp)->assertUnprocessable()->assertJsonPath('errors.party_id.0', 'Party is required.');
        $sc = $this->as()->postJson('/api/v1/knitting/ystore/yd/reprocess/sc-knitting', ['party_id' => $this->knitter] + $scp)->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/knitting/ystore/yd/reprocess/sc-knitting/{$sc['ref_no']}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/ystore/yd/reprocess/sc-knitting/{$sc['ref_no']}/accept")->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        $this->assertEquals([['YRP-'.$ym.'0003', 'Akij Knit', 5.0]], array_map(fn ($x) => [$x['ref_no'], $x['party'], $x['qty']], $this->as()->getJson('/api/v1/knitting/ystore/yd/reprocess/sc-knitting')->json('data')));
        $this->assertSame(0, YarnLot::query()->where('lot_no', 'AK-9')->count());
        $this->as()->getJson("/api/v1/knitting/ystore/yd/reprocess/in-house/{$sc['ref_no']}")->assertNotFound();

        // A sub-contract party's yarn, against its confirmed programme receipt.
        $scr = KnitScReceipt::query()->create(['program_no' => 'SCR-'.$ym.'0001', 'auto_number' => true, 'prog_date' => $today, 'party_id' => $this->knitter, 'yarn_store_id' => $this->store, 'status' => 'CONFIRMED']);
        KnitScReceiptYarn::query()->create(['receipt_id' => $scr->id, 'sort_order' => 1, 'sl' => 1, 'required_qty' => 200] + $yarn);
        $c = ['program_no' => $scr->program_no, 'challan_no' => 'AK/CH-1', 'challan_date' => $today, 'store_id' => $this->store,
            'lines' => [$yarn + ['colour_group' => 'Natural', 'brand' => 'Square', 'lot_no' => 'ak-l1', 'qty' => 150, 'uom' => 'kg', 'cones' => 120]]];
        $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans', ['program_no' => null] + $c)->assertUnprocessable()
            ->assertJsonPath('errors.program_no.0', 'Pick the S/C programme on the left first — a receive challan is always against one.');
        $ch = $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans', $c)->assertCreated()->json('data');
        $this->assertEquals(['AK/CH-1', 'Akij Knit', 'AK-L1'], [$ch['challan_no'], $ch['party'], $ch['lines'][0]['lot_no']]);
        $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans', $c)->assertUnprocessable()->assertJsonPath('errors.challan_no.0', 'Challan# AK/CH-1 is already in use.');
        $auto = $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans', ['challan_no' => null] + $c)->assertCreated()->json('data.challan_no');
        $this->assertSame("SYC-{$ym}0001", $auto);
        $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans/'.rawurlencode('AK/CH-1').'/accept')->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'AK/CH-1 is Draft, so it cannot be accepted.');
        $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans/'.rawurlencode('AK/CH-1').'/issue')->assertOk();
        $this->assertSame(0, YarnLot::query()->where('lot_no', 'AK-L1')->count());
        $this->as()->postJson('/api/v1/knitting/ystore/sc-yarn-challans/'.rawurlencode('AK/CH-1').'/accept')->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        $lot = YarnLot::query()->where('lot_no', 'AK-L1')->first();
        $this->assertEquals([150.0, 'SC_PARTY', $this->knitter], [YarnStore::balance($lot->id), $lot->source, $lot->supplier_id]);
        $sp = collect($this->as()->getJson('/api/v1/knitting/prod/yd/options')->json('data.sc_programs'))->firstWhere('value', $scr->program_no);
        $this->assertEquals([200.0, 150.0, 50.0], [$sp['required'], $sp['received'], $sp['pending']]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
