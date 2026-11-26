<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\Identity\Models\User;
use App\Modules\Knitting\Models\KnitNeedleMove;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Needle Store (AnnotechERP 15/07): the opening sheet
 * posts on finalise; a receipt posts on Verify and a return no more than is
 * outstanding; a confirmed breakage record feeds a machine-wise indent,
 * whose issue never exceeds what is owing nor what the releasing store
 * holds, moves the needles store to store and can be withdrawn; a loan
 * indent writes Issue Qty once verified; the consumption board sums the
 * book; and a struck month takes no more movement.
 */
class KnittingNeedleStoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Factory $unit;

    private string $main;

    private string $sub;

    private string $needle;

    private string $sinker;

    private string $cat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->unit = Factory::query()->first();
        $c = $this->delta->id;
        $this->cat = GsCategory::query()->create(['company_id' => $c, 'code' => 'NDL', 'name' => 'Needle & Sinker', 'active' => true])->id;
        $pcs = GsUnit::query()->create(['company_id' => $c, 'code' => 'PCS', 'name' => 'Pieces', 'active' => true])->id;
        $this->needle = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $this->cat, 'gs_unit_id' => $pcs, 'item_code' => 'ND-VO-71', 'name' => 'Groz-Beckert Vo 71.52 G04', 'default_price' => 9, 'active' => true])->id;
        $this->sinker = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $this->cat, 'gs_unit_id' => $pcs, 'item_code' => 'SK-01', 'name' => 'Sinker 24G', 'active' => true])->id;
        $this->main = GsStore::query()->create(['company_id' => $c, 'code' => 'MAINT', 'name' => 'Maintenance Store', 'active' => true])->id;
        $this->sub = GsStore::query()->create(['company_id' => $c, 'code' => 'KNT', 'name' => 'Knitting Floor Store', 'active' => true])->id;
    }

    public function test_opening_receipt_return_and_closing(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $supplier = MaterialSupplier::query()->create(['code' => 'SUP-70001', 'name' => 'Groz-Beckert Asia', 'supplier_type' => 'FOREIGN', 'active' => true])->id;

        // Opening sheet: loaded per store and date; a zero line is not stored; Save & Finalize posts and locks.
        $sheet = $this->as()->getJson("/api/v1/knitting/needle/openings/sheet?store_id={$this->main}&ob_date={$today}&category_id={$this->cat}")->assertOk()->json('data');
        $this->assertEquals([2, 9.0], [count($sheet['lines']), collect($sheet['lines'])->firstWhere('item_id', $this->needle)['rate']]);
        $ob = ['store_id' => $this->main, 'ob_date' => $today, 'lines' => [['item_id' => $this->needle, 'qty' => 500, 'rate' => 9], ['item_id' => $this->sinker, 'qty' => 0]]];
        $this->as()->putJson('/api/v1/knitting/needle/openings/sheet', $ob)->assertOk();
        $this->assertEquals(0.0, NeedleStore::balance($this->main, $this->needle));
        $this->as()->putJson('/api/v1/knitting/needle/openings/sheet', ['finalize' => true] + $ob)->assertOk()->assertJsonPath('data.finalized', true);
        $this->assertEquals(500.0, NeedleStore::balance($this->main, $this->needle));
        $this->as()->putJson('/api/v1/knitting/needle/openings/sheet', $ob)->assertUnprocessable()->assertJsonValidationErrors('ob_date');
        $this->assertEquals([1, 500.0, true], array_values(collect($this->as()->getJson('/api/v1/knitting/needle/openings')->json('data.0'))->only(['items', 'qty', 'finalized'])->all()));

        // Receipt: lines required; posts only on Verify.
        $r = ['mrr_date' => $today, 'store_id' => $this->main, 'supplier_id' => $supplier, 'challan_no' => 'GB-CH-77', 'pay_method' => 'LC', 'lc_no' => 'LC-5501',
            'lines' => [['item_id' => $this->needle, 'uom' => 'pcs', 'challan_qty' => 1000, 'received_qty' => 980, 'rate' => 8.5]]];
        $this->as()->postJson('/api/v1/knitting/needle/receipts', ['lines' => []] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A receipt with no needles on it takes nothing in. Add a line before saving.');
        $this->as()->postJson('/api/v1/knitting/needle/receipts', ['pay_method' => 'CASH'] + $r)->assertUnprocessable()->assertJsonValidationErrors('lc_no');
        $rec = $this->as()->postJson('/api/v1/knitting/needle/receipts', $r)->assertCreated()->json('data');
        $no = $rec['mrr_no'];
        $this->assertEquals(["NMR-{$ym}0001", 8330.0, 'PCS'], [$no, $rec['lines'][0]['amount'], $rec['lines'][0]['uom']]);
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$no}/verify")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Draft, so it cannot be verified.");
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'WAITING');
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$no}/verify")->assertOk();
        $this->assertEquals(1480.0, NeedleStore::balance($this->main, $this->needle));
        $this->as()->putJson("/api/v1/knitting/needle/receipts/{$no}", $r)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$no}/accounts")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$no}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');

        // Return: against the receipt, never past what is outstanding; Issue at Gate posts it.
        $src = $this->as()->getJson("/api/v1/knitting/needle/receipts/{$no}/source")->assertOk()->json('data.lines.0');
        $t = ['mrr_no' => $no, 'challan_date' => $today, 'challan_type' => 'NON_RETURNABLE', 'gate_pass_no' => 'GP-9',
            'lines' => [['receipt_line_id' => $src['receipt_line_id'], 'qty' => 990]]];
        $this->as()->postJson('/api/v1/knitting/needle/returns', $t)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 sends back more than is still outstanding on the receipt (980).']);
        $ret = $this->as()->postJson('/api/v1/knitting/needle/returns', ['lines' => [['qty' => 30] + $t['lines'][0]]] + $t)->assertCreated()->json('data');
        $this->assertEquals(["{$no}-R01", 255.0, 950.0], [$ret['challan_no'], $ret['lines'][0]['amount'], $ret['lines'][0]['outstanding'] - 30]);
        $rn = $ret['challan_no'];
        $this->as()->postJson("/api/v1/knitting/needle/returns/{$rn}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals(1450.0, NeedleStore::balance($this->main, $this->needle));
        $this->assertEquals(30.0, $this->as()->getJson('/api/v1/knitting/needle/receipts')->json('data.0.returned'));
        $this->as()->postJson("/api/v1/knitting/needle/returns/{$rn}/finalize")->assertOk();

        // The consumption board sums the book.
        $board = collect($this->as()->getJson("/api/v1/knitting/needle/consumption?store_id={$this->main}&from={$today}&to={$today}")->assertOk()->json('data'))->firstWhere('item_id', $this->needle);
        $this->assertEquals([500.0, 980.0, 30.0, 1450.0], [$board['opening'], $board['received'], $board['returned'], $board['closing']]);

        // Closing: unfinished paper is an exception; a struck month takes no more movement.
        $draft = $this->as()->postJson('/api/v1/knitting/needle/receipts', $r)->assertCreated()->json('data.mrr_no');
        $ex = $this->as()->getJson("/api/v1/knitting/needle/closings/exceptions?store_id={$this->main}&date={$today}")->assertOk()->json('data');
        $this->assertEquals([$draft], array_column($ex, 'no'));
        $this->as()->postJson('/api/v1/knitting/needle/closings', ['store_id' => $this->main, 'date' => $today])->assertUnprocessable()->assertJsonValidationErrors('store_id');
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$draft}/cancel")->assertOk();
        $done = $this->as()->postJson('/api/v1/knitting/needle/closings', ['store_id' => $this->main, 'date' => $today])->assertOk()->json('data.closed.0');
        $this->assertEquals([now()->format('Y-m'), 3], [$done['period'], $done['lines']]);
        $row = collect($this->as()->getJson('/api/v1/knitting/needle/closings')->json('data'))->firstWhere('store_id', $this->main);
        $this->assertEquals(['CLOSED', now()->addMonthNoOverflow()->format('Y-m')], [$row['status'], $row['next_period']]);
        $late = $this->as()->postJson('/api/v1/knitting/needle/receipts', $r)->assertCreated()->json('data.mrr_no');
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$late}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/receipts/{$late}/verify")->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_breakage_indent_issue_and_loan(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        KnitNeedleMove::query()->create(['store_id' => $this->main, 'item_id' => $this->needle, 'move_date' => $today, 'kind' => 'OPENING', 'doc_no' => 'OB-T', 'qty' => 40]);
        $machine = $this->machine();
        $shift = FactoryCalendarSetup::shift($this->delta->id, 'A', 'A shift', '06:00', '14:00');
        $party = MaterialSupplier::query()->create(['code' => 'SUP-70002', 'name' => 'Square Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        // Assignment: the unit's machines; Save & Finalize locks the fit.
        $fit = "/api/v1/knitting/needle/assignments/sheet?office_id={$this->unit->id}&category_id={$this->cat}";
        $this->assertEquals('MC-01', $this->as()->getJson($fit)->assertOk()->json('data.rows.0.machine_no'));
        $a = ['office_id' => $this->unit->id, 'category_id' => $this->cat, 'rows' => [['machine_id' => $machine, 'item_id' => $this->needle, 'qty' => 2880]]];
        $this->as()->putJson('/api/v1/knitting/needle/assignments/sheet', ['finalize' => true] + $a)->assertOk()->assertJsonPath('data.rows.0.qty', 2880);
        $this->as()->putJson('/api/v1/knitting/needle/assignments/sheet', $a)->assertUnprocessable()->assertJsonValidationErrors('category_id');

        // Breakage: machines required; confirmed records feed an indent.
        $b = ['broken_date' => $today, 'shift_id' => $shift, 'office_id' => $this->unit->id, 'lines' => [['machine_id' => $machine, 'item_id' => $this->needle, 'qty' => 12, 'cost_price' => 9]]];
        $this->as()->postJson('/api/v1/knitting/needle/brokens', ['lines' => []] + $b)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A shift with no machines on it records no breakage. Add a line before saving.');
        $brk = $this->as()->postJson('/api/v1/knitting/needle/brokens', $b)->assertCreated()->json('data');
        $this->assertEquals(["NBR-{$ym}0001", 108.0], [$brk['ref_no'], $brk['lines'][0]['amount']]);
        $q = ['req_date' => $today, 'reason' => 'NEEDLE_BROKEN', 'broken_no' => $brk['ref_no'], 'office_id' => $this->unit->id, 'from_store_id' => $this->sub, 'to_store_id' => $this->main,
            'lines' => [['machine_id' => $machine, 'item_id' => $this->needle, 'req_qty' => 50, 'rate' => 9]]];
        $this->as()->postJson('/api/v1/knitting/needle/reqs', $q)->assertUnprocessable()->assertJsonValidationErrors('broken_no');
        $this->as()->postJson("/api/v1/knitting/needle/brokens/{$brk['ref_no']}/confirm")->assertOk();
        $req = $this->as()->postJson('/api/v1/knitting/needle/reqs', $q)->assertCreated()->json('data.req_no');
        $this->assertSame("NRQ-{$ym}0001", $req);
        $this->as()->getJson('/api/v1/knitting/needle/issues/owing')->assertJsonCount(0, 'data');
        $this->as()->postJson("/api/v1/knitting/needle/reqs/{$req}/submit-verify")->assertOk()->assertJsonPath('data.status', 'VERIFIED');
        $this->as()->postJson("/api/v1/knitting/needle/brokens/{$brk['ref_no']}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');

        // Issue: never past what is owing nor what the releasing store holds; posts store to store.
        $owing = $this->as()->getJson('/api/v1/knitting/needle/issues/owing')->json('data.0.owing_lines.0');
        $this->assertEquals([50.0, 'MC-01'], [$owing['owing'], $owing['machine']]);
        $s = ['issue_date' => $today, 'req_no' => $req, 'lines' => [['req_line_id' => $owing['req_line_id'], 'qty' => 45]]];
        $this->as()->postJson('/api/v1/knitting/needle/issues', $s)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 takes more ND-VO-71 · Groz-Beckert Vo 71.52 G04 than the store releasing it holds (40).']);
        $iss = $this->as()->postJson('/api/v1/knitting/needle/issues', ['lines' => [['qty' => 30] + $s['lines'][0]]] + $s)->assertCreated()->json('data');
        $this->assertEquals(["NIS-{$ym}0001", 'PARTLY_ISSUED'], [$iss['issue_no'], $iss['req_status']]);
        $this->assertEquals([10.0, 30.0], [NeedleStore::balance($this->main, $this->needle), NeedleStore::balance($this->sub, $this->needle)]);
        $this->as()->postJson('/api/v1/knitting/needle/issues', ['lines' => [['qty' => 21] + $s['lines'][0]]] + $s)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 releases more than the indent still asks for (20).']);
        $this->as()->postJson("/api/v1/knitting/needle/reqs/{$req}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/needle/issues/{$iss['issue_no']}/withdraw")->assertOk()->assertJsonPath('data.status', 'WITHDRAWN');
        $this->assertEquals([40.0, 0.0], [NeedleStore::balance($this->main, $this->needle), NeedleStore::balance($this->sub, $this->needle)]);
        $this->assertSame('VERIFIED', $this->as()->getJson("/api/v1/knitting/needle/reqs/{$req}")->json('data.status'));

        // Loan indent: Issue Qty is written once verified, out of the store, never past its stock.
        $l = ['req_date' => $today, 'kind' => 'LOAN_ISSUE', 'party_id' => $party, 'store_id' => $this->main, 'reason' => 'Their set broke',
            'lines' => [['item_id' => $this->needle, 'required_qty' => 25, 'rate' => 9]]];
        $loan = $this->as()->postJson('/api/v1/knitting/needle/loan-reqs', $l)->assertCreated()->json('data');
        $ln = $loan['req_no'];
        $this->assertSame("NLR-{$ym}0001", $ln);
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$ln}", ['kind' => 'LOAN_RETURN'] + $l)->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->as()->postJson("/api/v1/knitting/needle/loan-reqs/{$ln}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/loan-reqs/{$ln}/verify")->assertOk();
        $w = fn (float $q) => ['lines' => [['id' => $loan['lines'][0]['id'], 'issue_qty' => $q]]];
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$ln}", $w(26))->assertUnprocessable()->assertJsonValidationErrors('lines.0.issue_qty');
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$ln}", $w(15))->assertOk()->assertJsonPath('data.status', 'PARTLY_ISSUED');
        $this->assertEquals(25.0, NeedleStore::balance($this->main, $this->needle));
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$ln}", $w(25))->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals(15.0, NeedleStore::balance($this->main, $this->needle));
        $row = $this->as()->getJson('/api/v1/knitting/needle/loan-reqs')->json('data.0');
        $this->assertEquals([25.0, 25.0, 0.0, 'Square Knit'], [$row['required'], $row['issued'], $row['balance'], $row['party']]);

        // Loan opening: one position per party and item, at least one direction.
        $o = ['ob_date' => $today, 'party_id' => $party, 'item_id' => $this->needle, 'store_id' => $this->main, 'cost_price' => 9];
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', $o)->assertUnprocessable()->assertJsonValidationErrors('loan_received');
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', ['loan_received' => 100, 'loan_issued' => 40, 'is_final' => true] + $o)->assertCreated();
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', ['loan_received' => 5] + $o)->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->assertEquals(60.0, $this->as()->getJson('/api/v1/knitting/needle/loan-openings')->json('data.0.net'));
    }

    private function machine(): string
    {
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knit', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Knit Floor', 'code' => 'KF-1']);
        $ref = fn (string $list, string $code) => collect($this->as()->getJson("/api/v1/knitting/ref-lists/{$list}")->json('data'))->firstWhere('code', $code)['id'];
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id')
            ?? collect($this->as()->getJson('/api/v1/erp/origin-countries')->json('data'))->firstWhere('name', 'Germany')['id'];

        return $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => 'MC-01', 'machine_type_id' => $ref('MC_TYPE', 'SJ'), 'brand_id' => $ref('BRAND', 'MAYER'),
            'origin_country_id' => $country, 'dia_id' => $ref('DIA', 'D30'), 'gauge_id' => $ref('GG', 'GG24'), 'supported_gauge_ids' => $ref('GG', 'GG24'), 'capacity_kg' => 180,
            'feeders' => 96, 'rpm' => 24, 'efficiency' => 85, 'floor_id' => $floor->id, 'mfg_year' => 2019])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
