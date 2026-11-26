<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Http\Controllers\Api\KnitReportApiController;
use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitNeedleLoanOpening;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitProgram;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollSheet;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Reports (AnnotechERP 15/11): every report of the five
 * sets answers its columns, rows and stat tiles; production leaves rejected
 * rolls out and splits day from night on the 08:30 boundary; yarn stock is
 * physical less what open indents hold; the loan statement owes both ways;
 * the grey position's Due is the target less what was knitted; a report
 * from a module not built says so; and only report viewers read them.
 */
class KnittingReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Factory $unit;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->unit = Factory::query()->first();
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
    }

    public function test_production_yarn_loans_and_every_report(): void
    {
        $today = now()->toDateString();
        $c = $this->delta->id;
        $shift = fn (string $name, string $code, string $start, string $end) => FactoryCalendarSetup::shift($c, $code, $name, $start, $end);
        $day = $shift('Day', 'DAY', '09:00', '21:00');
        $night = $shift('Night', 'NGT', '21:00', '08:59');
        $machine = $this->machine();
        $buyer = Buyer::query()->create(['company_id' => $c, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = Order::query()->create(['company_id' => $c, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $po = KnitProdOrder::query()->create(['po_no' => 'FPO-1', 'order_kind' => 'BULK', 'order_id' => $order, 'buyer_id' => $buyer, 'fabric_desc' => 'S/J 30/1 Combed 180 GSM',
            'colour_name' => 'Black', 'finish_dia' => '72', 'gsm' => 180, 'req_qty' => 500, 'status' => 'RUNNING']);
        $kp = KnitProgram::query()->create(['kp_no' => 'KP-1', 'prod_order_id' => $po->id, 'program_date' => $today, 'qty' => 400, 'status' => 'RUNNING']);
        $card = KnitCard::query()->create(['kc_no' => 'KC-1', 'program_id' => $kp->id, 'prod_order_id' => $po->id, 'card_date' => $today, 'assigned_qty' => 150, 'colour_name' => 'Black',
            'machine_id' => $machine, 'status' => 'RUNNING']);
        $sheet = KnitRollSheet::query()->create(['sheet_no' => 'RS-1', 'card_id' => $card->id]);
        $roll = fn (int $n, float $w, string $shiftId, string $q) => KnitRoll::query()->create(['sheet_id' => $sheet->id, 'card_id' => $card->id, 'roll_no' => $n, 'roll_code' => "KC-1/{$n}",
            'roll_at' => now(), 'shift_id' => $shiftId, 'machine_id' => $machine, 'weight' => $w, 'deducted' => 0.2, 'movement' => 'IN_KNITTING', 'quality' => $q]);
        $roll(1, 22.4, $day, 'PASSED');
        $roll(2, 21.2, $night, 'PENDING');
        $roll(3, 20.0, $day, 'REJECTED');
        $range = "from={$today}&to={$today}";

        // Production: a rejected roll is knitted but not production; the shifts stand apart.
        $run = fn (string $set, string $report, string $q = '') => $this->as()->getJson("/api/v1/knitting/reports/{$set}/{$report}?{$q}")->assertOk();
        $mc = $run('knitting', 'daily-mc-and-shift-wise-knitting-statement', $range)->json('data');
        $this->assertCount(2, $mc['rows']);
        $this->assertEquals(['rows' => 2, 'rolls' => 2, 'production' => 43.2, 'deducted' => 0.4], array_column($mc['stats'], 'value', 'key'));
        $this->assertSame(['key' => 'prod_date', 'type' => 'date'], $mc['columns'][0]);

        // The day's output sheet: the Day shift starts after 08:30, the Night shift after 20:30.
        $out = $this->as()->getJson("/api/v1/knitting/reports/daily-output?{$range}")->assertOk()->json('data');
        $line = $out['rows'][0];
        $this->assertEquals([22.2, 1, 21.0, 1, 43.2, 2], [$line['day_qty'], $line['day_roll'], $line['night_qty'], $line['night_roll'], $line['total_qty'], $line['total_roll']]);
        $this->assertEquals([22.2, 21.0, 43.2, 500.0, 400.0], [$line['qc_qty'], $line['pending_qty'], $line['store_pending_qty'], $line['booking_qty'], $line['prog_qty']]);
        $this->assertSame('day', collect($out['columns'])->firstWhere('key', 'day_qty')['group']);
        $this->as()->getJson("/api/v1/knitting/reports/daily-output?{$range}&shift={$night}")->assertOk()->assertJsonPath('data.rows.0.total_qty', 21);

        // The knit card and the grey position: what is left is the target less what was knitted.
        $kc = $run('knitting', 'knit-card-wise-quality-check-status')->json('data.rows.0');
        $this->assertEquals([150.0, 43.2, 2, 22.2, 21.0, 106.8], [$kc['assigned'], $kc['produced'], $kc['rolls'], $kc['passed'], $kc['qc_pending'], $kc['balance']]);
        $grey = $this->as()->getJson('/api/v1/knitting/reports/grey-stock?part=GREY')->assertOk()->json('data.rows.0');
        $this->assertEquals(['FABRIC', 500.0, 456.8], [$grey['type'], $grey['target'], $grey['due']]);

        // Yarn: physical on the ledger, Alloc what an open indent still wants of the lot, Stock what is left free.
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton Combed', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $lot = YarnStore::lotFor(['store_id' => $this->store, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'colour' => 'Natural', 'lot_no' => 'SQ-3011'], $today, 4.0);
        YarnStore::post('RECEIPT', 'YRC-T', $today, [['lot_id' => $lot->id, 'qty' => 100]]);
        $req = YarnIssueReq::query()->create(['req_no' => 'YIR-1', 'req_date' => $today, 'req_type' => 'KNIT_BULK', 'store_id' => $this->store, 'buyer_id' => $buyer, 'order_id' => $order,
            'program_no' => 'KP-1', 'status' => 'OPEN']);
        YarnIssueReqLine::query()->create(['req_id' => $req->id, 'sort_order' => 1, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'lot_no' => 'SQ-3011', 'uom' => 'kg', 'req_qty' => 30, 'issued_qty' => 0]);
        $stock = $run('yarn-store', 'yarn-stock-with-value')->json('data');
        $this->assertEquals([100.0, 30.0, 70.0, 400.0], [$stock['rows'][0]['physical'], $stock['rows'][0]['allocated'], $stock['rows'][0]['free'], $stock['rows'][0]['value']]);
        $this->assertEquals(['rows' => 1, 'physical' => 100, 'free' => 70, 'value' => 400], array_column($stock['stats'], 'value', 'key'));
        $bulk = $run('knitting', 'yarn-requsition-statment-for-knitting-bulk-by-date', $range)->json('data');
        $this->assertEquals([30.0, 0.0, 30.0], [$bulk['rows'][0]['requested'], $bulk['rows'][0]['issued'], $bulk['rows'][0]['pending']]);
        $position = $this->as()->getJson('/api/v1/knitting/reports/yarn-stock?spin=Combed&lot=sq-30')->assertOk()->json('data');
        $this->assertSame('SQ-3011', $position['rows'][0]['lot_no']);
        $this->as()->getJson('/api/v1/knitting/reports/yarn-stock?spin=Carded')->assertOk()->assertJsonCount(0, 'data.rows');

        // Loans: lent and not got back is receivable, borrowed and not given back is payable — each book on its own.
        $party = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Coats Bangladesh', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $cat = GsCategory::query()->create(['company_id' => $c, 'code' => 'NDL', 'name' => 'Needles', 'active' => true])->id;
        $pcs = GsUnit::query()->create(['company_id' => $c, 'code' => 'PCS', 'name' => 'Pieces', 'active' => true])->id;
        $needle = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $cat, 'gs_unit_id' => $pcs, 'item_code' => 'ND-1', 'name' => 'Cylinder needle', 'active' => true])->id;
        KnitNeedleLoanOpening::query()->create(['book' => 'NEEDLE', 'ob_date' => $today, 'party_id' => $party, 'item_id' => $needle, 'uom' => 'PCS', 'loan_received' => 4, 'loan_issued' => 10, 'cost_price' => 5]);
        $loan = $run('knitting', 'knit-needle-loan-statement')->json('data');
        $this->assertEquals([4.0, 10.0, 10.0, 4.0], [$loan['rows'][0]['borrowed'], $loan['rows'][0]['lent'], $loan['rows'][0]['receivable'], $loan['rows'][0]['payable']]);
        $run('knitting', 'knit-oil-loan-statement')->assertJsonCount(0, 'data.rows');

        // A report from a module not built yet answers an empty grid and says which.
        $run('gfs', 'sc-pre-treatment-ledger')->assertJsonPath('data.pending_module', 'dyeing')->assertJsonCount(0, 'data.rows');
        $run('knitting', 'oil-stock-taking')->assertJsonPath('data.pending_module', null);

        // Every report of every set reads.
        foreach (KnitReportApiController::SETS as $set => $reports) {
            foreach ($reports as $report) {
                $data = $run($set, $report, $range)->json('data');
                $this->assertSame([$set, $report], [$data['set'], $data['report']]);
                $this->assertSame('rows', $data['stats'][0]['key']);
                foreach ($data['rows'] as $row) {
                    $this->assertSame([], array_diff(array_column($data['columns'], 'key'), array_keys($row)), "{$set}/{$report}");
                }
            }
        }
    }

    public function test_only_its_own_set_and_only_report_viewers(): void
    {
        $this->as()->getJson('/api/v1/knitting/reports/qc/yarn-receive-statement')->assertNotFound();
        $this->as()->getJson('/api/v1/knitting/reports/nope/yarn-receive-statement')->assertNotFound();
        $this->as()->getJson('/api/v1/knitting/reports/yarn-store/yarn-receive-statement?from=2026-11-02&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->as()->getJson('/api/v1/knitting/reports/options')->assertOk()->assertJsonPath('data.cc_req_types', ['SOLID', 'YD']);
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/knitting/reports/yarn-store/yarn-receive-statement')->assertForbidden();
        $this->as($chief)->getJson('/api/v1/knitting/reports/daily-output')->assertForbidden();
    }

    private function machine(): string
    {
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knit', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Knit Floor', 'code' => 'KF-1']);
        $ref = fn (string $list, string $code) => collect($this->as()->getJson("/api/v1/knitting/ref-lists/{$list}")->json('data'))->firstWhere('code', $code)['id'];
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id')
            ?? collect($this->as()->getJson('/api/v1/erp/origin-countries')->json('data'))->firstWhere('name', 'Germany')['id'];

        return $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => 'KM-T1', 'machine_type_id' => $ref('MC_TYPE', 'SJ'), 'brand_id' => $ref('BRAND', 'MAYER'),
            'origin_country_id' => $country, 'dia_id' => $ref('DIA', 'D30'), 'gauge_id' => $ref('GG', 'GG24'), 'supported_gauge_ids' => $ref('GG', 'GG24'), 'capacity_kg' => 180,
            'feeders' => 96, 'rpm' => 24, 'efficiency' => 85, 'floor_id' => $floor->id, 'mfg_year' => 2019])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
