<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnMove;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Collar/Cuff (AnnotechERP 15/06): a flat knit
 * requirement asks for pieces, the production desk records them per
 * source, a store challan moves no more than is open, the out-house
 * programme's Submit → Approve → Start and its received join on S/C
 * receive challans; and a collar & cuff yarn indent's Submit → Approve and
 * the store's releases off a stock line, posted to the yarn ledger.
 */
class KnittingCollarCuffTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $buyer;

    private string $order;

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
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
    }

    public function test_requirement_production_programme_and_store_receipt(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $party = MaterialSupplier::query()->create(['code' => 'SUP-60001', 'name' => 'Arafah Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        // A requirement has to ask for something; the order fills its buyer.
        $r = ['req_type' => 'BULK', 'order_id' => $this->order, 'work_style_no' => '529', 'ship_month' => '2026-12', 'item' => 'COLLAR', 'description' => '1x1 rib collar',
            'size_desc' => 'M', 'gauge' => '14'];
        $this->as()->postJson('/api/v1/knitting/prod/cc/requirements', $r)->assertUnprocessable()
            ->assertJsonPath('errors.req_in_house.0', 'A requirement has to ask for something. Fill in Req In-house, Req S/C Qty, or both.');
        $req = $this->as()->postJson('/api/v1/knitting/prod/cc/requirements', ['req_in_house' => 100, 'req_sc' => 50] + $r)->assertCreated()->json('data');
        $no = $req['req_no'];
        $this->assertEquals(["CCR-{$ym}0001", $this->buyer, 'PCS', 'OPEN', 0.0], [$no, $req['buyer_id'], $req['uom'], $req['status'], $req['open']]);

        // The production desk: added one at a time, corrected together; S/C output names a live programme.
        $p = "/api/v1/knitting/prod/cc/requirements/{$no}/productions";
        $this->as()->postJson($p, ['prod_date' => $today, 'source' => 'IN_HOUSE'])->assertUnprocessable()
            ->assertJsonPath('errors.qty.0', 'Every production line needs the pieces it made.');
        $this->as()->postJson($p, ['prod_date' => $today, 'source' => 'IN_HOUSE', 'qty' => 60, 'reject_qty' => 2])->assertCreated();
        $this->as()->postJson($p, ['prod_date' => $today, 'source' => 'SC_OUT_HOUSE', 'qty' => 10, 'program_no' => 'CCP-NONE'])->assertUnprocessable()
            ->assertJsonPath('errors.program_no.0', 'CCP-NONE is not an approved collar cuff programme.');
        $desk = $this->as()->postJson($p, ['prod_date' => $today, 'source' => 'SC_OUT_HOUSE', 'qty' => 20])->assertCreated()->json('data');
        $this->assertEquals([60.0, 20.0, 2.0, 80.0, 2], [$desk['prod_in'], $desk['prod_sc'], $desk['reject'], $desk['open'], count($desk['lines'])]);
        $lines = array_map(fn ($l) => ['qty' => $l['source'] === 'IN_HOUSE' ? 65 : $l['qty']] + $l, $desk['lines']);
        $this->assertEquals(85.0, $this->as()->putJson($p, ['lines' => $lines])->assertOk()->json('data.open'));

        // The store moves no more than is open, and only a requirement of its own kind.
        $c = ['challan_type' => 'INTERNAL_TRANSFER', 'challan_date' => $today, 'lines' => [['req_no' => $no, 'item' => 'COLLAR', 'qty' => 90]]];
        $this->as()->getJson('/api/v1/knitting/prod/cc/challans/sample/open')->assertOk()->assertJsonCount(0, 'data');
        $this->assertEquals(85.0, $this->as()->getJson('/api/v1/knitting/prod/cc/challans/bulk/open')->json('data.0.open'));
        $this->as()->postJson('/api/v1/knitting/prod/cc/challans/bulk', ['lines' => []] + $c)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A challan with no parts on it moves nothing. Pick an order on the left, or add a line.');
        $this->as()->postJson('/api/v1/knitting/prod/cc/challans/sample', $c)->assertUnprocessable()->assertJsonValidationErrors('lines.0.req_no');
        $this->as()->postJson('/api/v1/knitting/prod/cc/challans/bulk', $c)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Line 1 moves more of {$no} than is still open (85 pcs)."]);
        $ch = $this->as()->postJson('/api/v1/knitting/prod/cc/challans/bulk', ['lines' => [['qty' => 60] + $c['lines'][0]]] + $c)->assertCreated()->json('data');
        $this->assertEquals(["CCB-{$ym}0001", $this->order, 'DRAFT'], [$ch['challan_no'], $ch['lines'][0]['order_id'], $ch['status']]);
        $this->as()->putJson($p, ['lines' => array_map(fn ($l) => ['qty' => 5] + $l, $lines)])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', '60 pcs of this requirement are already on store challans — production cannot drop below that.');
        $cn = $ch['challan_no'];
        $this->as()->postJson("/api/v1/knitting/prod/cc/challans/bulk/{$cn}/receive")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$cn} is Draft, so it cannot be received at the store.");
        $this->as()->postJson("/api/v1/knitting/prod/cc/challans/bulk/{$cn}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $got = $this->as()->postJson("/api/v1/knitting/prod/cc/challans/bulk/{$cn}/receive")->assertOk()->json('data');
        $this->assertEquals(['RECEIVED', $today, 60.0], [$got['status'], $got['rcv_date'], $got['lines'][0]['received']]);
        $this->assertNull($this->as()->postJson("/api/v1/knitting/prod/cc/challans/bulk/{$cn}/reopen")->assertOk()->json('data.rcv_date'));
        $this->assertSame('ISSUED', $this->as()->getJson("/api/v1/knitting/prod/cc/challans/bulk/{$cn}")->json('data.status'));

        // The out-house programme: parts required; Submit → Approve → Start; received follows S/C receive challans.
        $g = ['program_date' => $today, 'delivery_date' => $today, 'party_id' => $party, 'cpi' => '12', 'order_id' => $this->order,
            'lines' => [['item' => 'COLLAR', 'description' => '1x1 rib collar', 'size_desc' => 'M', 'gauge' => '14', 'qty' => 50, 'rate' => 3.5]]];
        $this->as()->postJson('/api/v1/knitting/prod/cc/programs', ['lines' => []] + $g)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A programme with no parts on it places no work. Add at least one line.');
        $prog = $this->as()->postJson('/api/v1/knitting/prod/cc/programs', $g)->assertCreated()->json('data');
        $pn = $prog['program_no'];
        $this->assertEquals(["CCP-{$ym}0001", 175.0, 'PCS', $this->buyer], [$pn, $prog['lines'][0]['amount'], $prog['lines'][0]['uom'], $prog['buyer_id']]);
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/start")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$pn} is Draft, so it cannot be started.");
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/send-back")->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $part = collect($this->as()->getJson('/api/v1/knitting/prod/cc/options')->json('data.programs'))->firstWhere('value', $pn)['lines'][0]['value'];
        $sc = ['challan_type' => 'SC_RECEIVE', 'challan_date' => $today, 'lines' => [['req_no' => $no, 'item' => 'COLLAR', 'qty' => 20, 'program_line_id' => $part]]];
        $this->as()->postJson('/api/v1/knitting/prod/cc/challans/bulk', ['challan_type' => 'DELIVERY'] + $sc)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.program_line_id' => 'Line 1: only an S/C receive challan books pieces against an approved programme part.']);
        $sn = $this->as()->postJson('/api/v1/knitting/prod/cc/challans/bulk', $sc)->assertCreated()->json('data.challan_no');
        $this->as()->postJson("/api/v1/knitting/prod/cc/challans/bulk/{$sn}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/prod/cc/challans/bulk/{$sn}/receive")->assertOk();
        $row = collect($this->as()->getJson('/api/v1/knitting/prod/cc/programs')->json('data'))->firstWhere('program_no', $pn);
        $this->assertEquals([1, 50.0, 20.0, 'Arafah Knit'], [$row['parts'], $row['qty'], $row['received'], $row['party']]);
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/cc/programs/{$pn}/start")->assertOk()->assertJsonPath('data.status', 'IN_PRODUCTION');

        // The register's figures, and Close.
        $reg = collect($this->as()->getJson('/api/v1/knitting/prod/cc/requirements')->json('data'))->firstWhere('req_no', $no);
        $this->assertEquals([65.0, 20.0, 80.0, 5.0, 2, '529 · LPP-2026-000008'], [$reg['prod_in'], $reg['prod_sc'], $reg['delivered'], $reg['open'], $reg['prod_lines'], $reg['order']]);
        $this->as()->postJson("/api/v1/knitting/prod/cc/requirements/{$no}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->as()->postJson($p, ['prod_date' => $today, 'source' => 'IN_HOUSE', 'qty' => 1])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->getJson('/api/v1/knitting/prod/cc/challans/bulk/open')->assertJsonCount(0, 'data');
    }

    public function test_yarn_indent_approval_and_issue(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $lot = YarnStore::lotFor(['store_id' => $this->store, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'colour' => 'Navy', 'lot_no' => 'YD-7'], $today, 4.0);
        YarnStore::post('RECEIPT', 'YRC-T', $today, [['lot_id' => $lot->id, 'qty' => 30]]);
        $other = YarnStore::lotFor(['store_id' => $this->store, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'lot_no' => 'SOL-1'], $today, 3.0);
        YarnStore::post('RECEIPT', 'YRC-U', $today, [['lot_id' => $other->id, 'qty' => 50]]);
        $req = $this->as()->postJson('/api/v1/knitting/prod/cc/requirements', ['req_type' => 'BULK', 'order_id' => $this->order, 'item' => 'CUFF', 'description' => '2x1 rib cuff',
            'req_in_house' => 200])->assertCreated()->json('data.req_no');

        // Load Plan: the orders with open parts.
        $plan = $this->as()->getJson('/api/v1/knitting/prod/cc/yarn/plans')->json('data.0');
        $this->assertEquals(['529', 'LPP-2026-000008', 1, 200.0, $req], [$plan['style'], $plan['order_no'], $plan['parts'], $plan['req'], $plan['lines'][0]['req_no']]);

        // A yarn-dyed line names its lot; Yarn Details is required.
        $y = ['req_date' => $today, 'knit_plan_no' => '529', 'order_id' => $this->order, 'store_id' => $this->store, 'req_type' => 'COLLAR_CUFF_BULK',
            'lines' => [['req_no' => $req, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'lot_no' => 'yd-7', 'req_qty' => 25, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/prod/cc/yarn/yd', ['lines' => []] + $y)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Yarn Details is required.');
        $this->as()->postJson('/api/v1/knitting/prod/cc/yarn/yd', ['lines' => [['lot_no' => null] + $y['lines'][0]]] + $y)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.lot_no' => 'A yarn-dyed line has to name its lot — two lots of one shade are two shades on a collar.']);
        $doc = $this->as()->postJson('/api/v1/knitting/prod/cc/yarn/yd', $y)->assertCreated()->json('data');
        $no = $doc['req_no'];
        $this->assertEquals(["CYD-{$ym}0001", 'YD-7', 'KG', $req, $this->buyer], [$no, $doc['lines'][0]['lot_no'], $doc['lines'][0]['uom'], $doc['lines'][0]['req_no'], $doc['buyer_id']]);
        $this->as()->getJson("/api/v1/knitting/prod/cc/yarn/solid/{$no}")->assertNotFound();

        // Issued only once approved, off a matching stock line, never past the line or the lot.
        $base = "/api/v1/knitting/prod/cc/yarn/yd/{$no}";
        $line = $doc['lines'][0]['id'];
        $i = ['issue_date' => $today, 'lines' => [['req_line_id' => $line, 'lot_id' => $lot->id, 'qty' => 10]]];
        $this->as()->postJson("{$base}/issue", $i)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Draft — yarn is issued only against an approved requisition.");
        $this->as()->postJson("{$base}/submit")->assertOk();
        $this->as()->postJson("{$base}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->as()->postJson("{$base}/issue", ['lines' => [['lot_id' => $other->id] + $i['lines'][0]]] + $i)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.lot_id' => "Line 1: the stock line is not 100% Cotton 30/1 in this requisition's store."]);
        $this->as()->postJson("{$base}/issue", ['lines' => [['qty' => 26] + $i['lines'][0]]] + $i)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 issues more than it still asks for (25).']);
        $after = $this->as()->postJson("{$base}/issue", $i)->assertOk()->json('data');
        $this->assertEquals(['PARTLY_ISSUED', 10.0, "{$no}/1"], [$after['status'], $after['lines'][0]['issued'], $after['issues'][0]['issue_no']]);
        $this->assertEquals(20.0, YarnStore::balance($lot->id));
        $this->assertEquals(-40.0, (float) YarnMove::query()->where('doc_no', "{$no}/1")->value('value'));
        $this->as()->postJson("{$base}/cancel")->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} is Partly Issued, so it cannot be cancelled.");
        $this->as()->postJson("{$base}/issue", ['lines' => [['qty' => 15] + $i['lines'][0]]] + $i)->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals(5.0, YarnStore::balance($lot->id));
        $row = $this->as()->getJson('/api/v1/knitting/prod/cc/yarn/yd')->json('data.0');
        $this->assertEquals([25.0, 25.0, 0.0, 'ISSUED', '529 · LPP-2026-000008'], [$row['req_qty'], $row['issued'], $row['balance'], $row['status'], $row['order']]);
        $this->as()->postJson("{$base}/close")->assertUnprocessable();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
