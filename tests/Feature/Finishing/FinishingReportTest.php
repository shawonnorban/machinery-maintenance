<?php

declare(strict_types=1);

namespace Tests\Feature\Finishing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Finishing\Support\FinReports;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finishing Line → Fabric Finishing Reports (AnnotechERP 18/06): every
 * report Brickland lists answers columns, rows and tiles read from the
 * line's own records — production, sub-contract, quality and the store —
 * and who may read them.
 */
class FinishingReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $party;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finish Fabric Store', 'code' => 'ST-FIN', 'active' => true])->id;
        $this->party = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Ha-Meem Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
    }

    public function test_every_report_reads_the_lines_records(): void
    {
        $this->floorStory();
        foreach (FinReports::REPORTS as $report) {
            $res = $this->as()->getJson("/api/v1/finishing/reports/{$report}")->assertOk();
            $this->assertNotEmpty($res->json('data.columns'), $report);
            $this->assertSame('rows', $res->json('data.stats.0.key'), $report);
        }

        $daily = $this->as()->getJson('/api/v1/finishing/reports/daily-production')->json('data');
        $this->assertEquals([2, 198, 193], [count($daily['rows']), $daily['stats'][1]['value'], $daily['stats'][2]['value']]);
        $this->assertSame(3.06, collect($daily['rows'])->firstWhere('process', 'Open Compacting (OC)')['loss_pct']);
        $this->as()->getJson('/api/v1/finishing/reports/machine-shift-production')->assertJsonCount(2, 'data.rows');
        $this->as()->getJson('/api/v1/finishing/reports/monthly-production')->assertJsonPath('data.rows.0.batches', 1)->assertJsonPath('data.rows.0.loss_kg', 5);
        $target = collect($this->as()->getJson('/api/v1/finishing/reports/mc-production-vs-target')->json('data.rows'))->firstWhere('machine', 'ST-01 — Stenter 1');
        $this->assertEquals([1, 120, 98, 81.67], [$target['shifts'], $target['target_kg'], $target['output_kg'], $target['achievement_pct']]);

        $this->as()->getJson('/api/v1/finishing/reports/sc-delivery-receive-ledger')->assertJsonPath('data.rows.0.balance_kg', 0)->assertJsonPath('data.rows.0.received_kg', 59.5);
        $this->as()->getJson('/api/v1/finishing/reports/sc-bill-outstanding')->assertJsonPath('data.rows.0.billed', 476)->assertJsonPath('data.rows.0.outstanding', 476);
        $this->as()->getJson('/api/v1/finishing/reports/four-point-register')->assertJsonPath('data.rows.0.verdict', 'PASS')->assertJsonPath('data.rows.0.total_points', 8);
        $this->as()->getJson('/api/v1/finishing/reports/shade-approval-status')->assertJsonPath('data.rows.0.status', 'APPROVED');
        $this->as()->getJson('/api/v1/finishing/reports/delivery-to-store')->assertJsonPath('data.rows.0.yield_pct', 95.5)->assertJsonPath('data.rows.0.finish_qty', 95.5);
        $this->as()->getJson('/api/v1/finishing/reports/current-stock')->assertJsonPath('data.rows.0.balance_kg', 65.5);
        $ledger = $this->as()->getJson('/api/v1/finishing/reports/stock-details-ledger')->json('data.rows');
        $this->assertEquals([['RECEIPT', 95.5], ['ISSUE', 65.5]], array_map(fn ($r) => [$r['kind'], $r['balance_kg']], $ledger));
        $this->as()->getJson('/api/v1/finishing/reports/issue-register')->assertJsonPath('data.rows.0.issue_qty', 30);
        $this->as()->getJson('/api/v1/finishing/reports/order-monitoring')->assertJsonPath('data.rows.0.store_kg', 95.5)->assertJsonPath('data.rows.0.issued_kg', 30);
        $this->as()->getJson('/api/v1/finishing/reports/left-over-stock')->assertJsonPath('data.rows.0.balance_kg', 65.5);
        $this->as()->getJson('/api/v1/finishing/reports/process-type-register')->assertJsonCount(8, 'data.rows');
        $this->as()->getJson('/api/v1/finishing/reports/sc-aop-ledger')->assertJsonPath('data.pending_module', 'aop')->assertJsonCount(0, 'data.rows');
        // The boxes narrow what is read.
        $this->as()->getJson('/api/v1/finishing/reports/daily-production?from=2026-12-01')->assertJsonCount(0, 'data.rows');
        $this->as()->getJson('/api/v1/finishing/reports/daily-production?from=2026-11-10&to=2026-11-01')->assertUnprocessable()->assertJsonPath('errors.to.0', 'To Date cannot be before From Date.');
        $this->as()->getJson('/api/v1/finishing/reports/no-such-report')->assertNotFound();
    }

    public function test_the_floor_reports_read_where_each_batch_stands(): void
    {
        $card = $this->card('B-26110021');
        $no = DB::table('fin_batch_receipts')->where('id', $card)->value('receipt_no');
        $this->as()->postJson("/api/v1/finishing/delivery-board/{$no}/deliver", ['to_stage' => 'ON_HOLD'])->assertOk();
        DB::table('fin_batch_receipts')->where('id', $card)->update(['stage_at' => now()->subHours(30)]);
        $this->as()->getJson('/api/v1/finishing/reports/hold-list')->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.batch_no', 'B-26110021');
        $this->as()->getJson('/api/v1/finishing/reports/hold-list-summary')->assertJsonPath('data.rows.0.recv_qty_kg', 100);
        $this->as()->getJson('/api/v1/finishing/reports/wet-dry-hold-24hr')->assertJsonPath('data.rows.0.kind', 'DRY_HOLD')->assertJsonPath('data.rows.0.hours', fn ($h) => $h >= 30);
        $this->as()->getJson('/api/v1/finishing/reports/floor-status')->assertJsonPath('data.rows.0.stage', 'ON_HOLD');
    }

    public function test_who_may_read_the_reports(): void
    {
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->getJson('/api/v1/finishing/reports/options')->assertOk()->assertJsonPath('data.parties.0.label', 'Ha-Meem Processing');
        $this->withToken($chief)->getJson('/api/v1/finishing/reports/floor-status')->assertOk();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/finishing/reports/floor-status')->assertForbidden();
    }

    /** One batch through the whole line: produced, sent out and back, inspected, delivered to the store, issued to cutting. */
    private function floorStory(): void
    {
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => Factory::query()->first()->id, 'name' => 'Finishing', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FN-1']);
        $look = $this->as()->getJson('/api/v1/finishing/lookups')->assertOk()->json('data');
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id');
        $machine = fn (string $no, string $name, string $type) => $this->as()->postJson('/api/v1/erp/fin-machines', ['machine_no' => $no, 'name' => $name, 'machine_type' => $type, 'origin_country_id' => $country, 'brand_id' => $look['brands'][0]['value'], 'mou' => 'KG', 'status' => 'RUNNING', 'floor_id' => $floor->id])->assertCreated()->json('data.id');
        $stenter = $machine('ST-01', 'Stenter 1', 'STENTER');
        $compactor = $machine('CP-01', 'Compactor 1', 'COMPACTOR');
        $p = collect($this->as()->getJson('/api/v1/erp/fin-process-types')->json('data'))->pluck('id', 'code')->all();
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $stenter, 'process_id' => $p['DF-ST'], 'dia_type' => 'OPEN', 'sequence' => 1, 'batch_status' => 'AWAITING_LOAD', 'next_status' => 'STENTERING'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $compactor, 'process_id' => $p['DF-CMP'], 'dia_type' => 'OPEN', 'sequence' => 2, 'batch_status' => 'STENTERING', 'next_status' => 'COMPACTING'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/fin-machine-targets', ['machine_id' => $stenter, 'dia_type' => 'OPEN', 'effective_from' => '2026-11-01', 'target_qty' => 120, 'shift_hours' => 8])->assertCreated();

        $card = $this->card('B-26110011');
        $sheet = $this->as()->postJson('/api/v1/finishing/production-sheets', ['line' => 'OPEN', 'production_date' => '2026-11-06', 'shift' => 'DAY', 'lines' => [
            ['receipt_id' => $card, 'machine_id' => $stenter, 'process_id' => $p['DF-ST'], 'input_kg' => 100, 'output_kg' => 98, 'load_time' => '2026-11-06T08:00', 'unload_time' => '2026-11-06T09:30'],
            ['receipt_id' => $card, 'machine_id' => $compactor, 'process_id' => $p['DF-CMP'], 'input_kg' => 98, 'output_kg' => 95, 'load_time' => '2026-11-06T22:00', 'unload_time' => '2026-11-06T23:00'],
        ]])->assertCreated()->json('data.sheet_no');
        $this->as()->postJson("/api/v1/finishing/production-sheets/{$sheet}/post")->assertOk();

        $ch = $this->as()->postJson('/api/v1/finishing/sc-challans', ['challan_date' => '2026-11-06', 'supplier_id' => $this->party, 'store_id' => $this->store, 'lines' => [['receipt_id' => $card, 'process_id' => $p['DF-CAL'], 'qty_kg' => 60, 'rate' => 8]]])->assertCreated()->json('data.challan_no');
        $this->as()->postJson("/api/v1/finishing/sc-challans/{$ch}/post")->assertOk();
        $chOpt = collect($this->as()->getJson('/api/v1/finishing/sc/options')->json('data.challans'))->firstWhere('challan_no', $ch);
        $mrr = $this->as()->postJson('/api/v1/finishing/sc-receipts', ['receive_date' => '2026-11-07', 'challan_id' => $chOpt['value'], 'supplier_id' => $this->party, 'store_id' => $this->store, 'lines' => [['challan_line_id' => $chOpt['lines'][0]['value'], 'recv_kg' => 59.5, 'short_kg' => 0.5]]])->assertCreated()->json('data.mrr_no');
        $this->as()->postJson("/api/v1/finishing/sc-receipts/{$mrr}/post")->assertOk();
        $this->as()->postJson('/api/v1/finishing/sc-bills', ['bill_date' => '2026-11-08', 'bill_no' => 'INV-1', 'supplier_id' => $this->party, 'lines' => [['challan_id' => $chOpt['value'], 'process_id' => $p['DF-CAL'], 'qty_kg' => 59.5, 'rate' => 8]]])->assertCreated();

        $this->as()->postJson('/api/v1/erp/fin-shade-decisions', ['receipt_id' => $card, 'status' => 'APPROVED'])->assertCreated();
        $insp = $this->as()->postJson('/api/v1/finishing/inspections', ['receipt_id' => $card, 'insp_date' => '2026-11-08', 'shift' => 'DAY', 'verdict' => 'PASS', 'rolls' => [['roll_no' => 'R1', 'weight_kg' => 48, 'points' => 5, 'result' => 'PASS'], ['roll_no' => 'R2', 'weight_kg' => 47.5, 'points' => 3, 'result' => 'PASS']]])->assertCreated()->json('data.ref_no');
        $this->as()->postJson("/api/v1/finishing/inspections/{$insp}/post")->assertOk();

        $dn = $this->as()->postJson('/api/v1/finishing/store-deliveries', ['receive_date' => '2026-11-08', 'store_id' => $this->store, 'lines' => [['receipt_id' => $card, 'grey_qty' => 100, 'finish_qty' => 95.5, 'rolls' => 2]]])->assertCreated()->json('data.delivery_no');
        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$dn}/send")->assertOk();
        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$dn}/receive")->assertOk();
        $lot = $this->as()->getJson('/api/v1/finishing/store/options')->json('data.lots.0.value');
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', ['req_no' => 'CR-1', 'req_date' => now('Asia/Dhaka')->toDateString(), 'store_id' => $this->store, 'status' => 'COMPLETE', 'lines' => [['lot_id' => $lot, 'req_qty' => 30, 'issue_qty' => 30]]])->assertCreated();
    }

    /** A dyed batch received onto the floor (100 kg, 6 rolls); its card id. */
    private function card(string $no): string
    {
        $batch = (string) Str::ulid();
        DB::table('dye_batches')->insert(['id' => $batch, 'company_id' => $this->delta->id, 'batch_no' => $no, 'batch_date' => '2026-11-01', 'scope' => 'BULK', 'source' => 'MANUAL', 'plan_qty' => 100, 'card_qty' => 100, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dye_productions')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'batch_id' => $batch, 'production_date' => '2026-11-04', 'liquor_ratio' => '1:8', 'created_at' => now(), 'updated_at' => now()]);

        return $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-05', 'batch_id' => $batch, 'dia_type' => 'OPEN', 'recv_qty_kg' => 100, 'recv_rolls' => 6])->assertCreated()->json('data.id');
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
