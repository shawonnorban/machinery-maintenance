<?php

declare(strict_types=1);

namespace Tests\Feature\Assembly;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Assembly Floor → Reports (AnnotechERP 20/09): every Apparel Assembly
 * Report runs with only the parameters it reads, and the floor's own books
 * come out right — order closing (cut to shipped, by colour and size), the
 * end-line inspection with its defects by line and hour, hourly production,
 * sewing done / undone as per booking, the cutting store and sewing input,
 * end table and finishing manpower, finishing and export, the trims ledger
 * with its opening balance, booking against line capacity — and who may.
 */
class AssemblyReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $order;

    private PlanningLine $sew1;

    private ProductionLine $fin1;

    private ProductionLine $qc1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $sewFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing 1', 'code' => 'FLR-001']);
        $finFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FLR-FIN']);
        $qcFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Quality', 'code' => 'FLR-QC']);
        $this->sew1 = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $sewFloor->id, 'code' => 'LINE01', 'name' => 'Line LINE01', 'std_manpower' => 40, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Finishing Department', 'code' => 'FIN', 'created_at' => now(), 'updated_at' => now()]);
        $section = fn (string $name, string $code) => tap((string) Str::ulid(), fn ($id) => DB::table('sections')->insert(['id' => $id, 'company_id' => $this->delta->id, 'department_id' => $dept, 'name' => $name, 'code' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]));
        $finSection = $section('Finishing', 'FIN-01');
        $qcSection = $section('Final Inspection', 'QC-01');
        $this->fin1 = ProductionLine::query()->create(['company_id' => $this->delta->id, 'department_id' => $dept, 'section_id' => $finSection, 'floor_id' => $finFloor->id, 'code' => 'FIN01', 'name' => 'Finishing FIN01', 'line_type' => 'FINISHING', 'manpower' => 12, 'target_per_day' => 400, 'status' => 'ACTIVE']);
        ProductionLine::query()->create(['company_id' => $this->delta->id, 'department_id' => $dept, 'section_id' => $finSection, 'floor_id' => null, 'code' => 'PK09', 'name' => 'Packing PK09', 'line_type' => 'FINISHING', 'status' => 'ACTIVE']);
        $this->qc1 = ProductionLine::query()->create(['company_id' => $this->delta->id, 'department_id' => $dept, 'section_id' => $qcSection, 'floor_id' => $qcFloor->id, 'code' => 'QC01', 'name' => 'Quality QC01', 'line_type' => 'QUALITY', 'manpower' => 12, 'target_per_day' => 400, 'status' => 'ACTIVE']);

        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'ORD-11565705', 'job_no' => '868MI', 'style_no' => '868MI', 'buyer_id' => $lpp, 'order_type' => 'BULK_NEW', 'total_qty' => 600,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => '11565705', 'item' => 'T-Shirt'])->id;
        foreach (['S', 'M', 'L'] as $i => $size) {
            DB::table('merch_order_sizes')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'order_id' => $this->order, 'colour' => 'Black', 'size' => $size, 'qty' => 200, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_every_report_runs_and_reads_the_floor_books(): void
    {
        $a = $this->passed('S', 1, 10, 10);
        $b = $this->passed('M', 1, 8, 7, 1);
        $this->packedOut([$a, $b], ['S' => 10, 'M' => 7], 17);
        $key = $this->as()->getJson('/api/v1/assembly/shipments/entry')->json('data.ready.0.key');
        $s = $this->as()->postJson('/api/v1/assembly/shipments', ['key' => $key, 'qty' => 12, 'ship_date' => '2026-11-06', 'mode' => 'SEA', 'carton_qty' => 1])->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/assembly/shipments/{$s['id']}/confirm")->assertOk();
        $this->as()->postJson("/api/v1/assembly/shipments/{$s['id']}/ship")->assertOk();

        $o = $this->as()->getJson('/api/v1/assembly/reports/options')->assertOk()->json('data');
        $this->assertCount(29, $o['reports']);
        $this->assertSame(['buyer', 'order'], collect($o['reports'])->firstWhere('kind', 'order_closing')['params']);
        $this->assertSame(['868MI — ORD-11565705 [868MI]'], array_column($o['orders'], 'label'));

        $params = ['date' => '2026-11-06', 'from' => '2026-11-01', 'to' => '2026-12-31', 'store' => $this->trimsStore()];
        foreach ($o['reports'] as $r) {
            $q = array_intersect_key($params, array_flip($r['params']));
            $this->assertNotEmpty($this->report($r['kind'], $q)['columns'], $r['kind']);
        }

        // Order closing: the order from cut to shipped.
        $row = $this->report('order_closing', ['order' => $this->order])['rows'][0];
        $this->assertSame([600, 18, 18, 18, 18, 17, 17, 12, 588], [$row['order_qty'], $row['cut'], $row['sewing_in'], $row['sewing_out'], $row['finishing'], $row['qc_pass'], $row['packed'], $row['shipped'], $row['balance']]);
        $st = $this->report('order_closing_statement', ['order' => $this->order]);
        $this->assertSame([['S', 200, 10, 10, 10, 10], ['M', 200, 8, 7, 7, 2], ['L', 200, 0, 0, 0, 0]], array_map(fn ($r) => [$r['size'], $r['order_qty'], $r['cut'], $r['qc_pass'], $r['packed'], $r['shipped']], $st['rows']));

        // Quality: end-line inspection, defects by line and by hour, the final inspection detail.
        $qc = $this->report('endline_qc', ['date' => '2026-11-06'])['rows'][0];
        $this->assertSame([18, 17, 1, 0, 94.4, 5.6], [$qc['inspected'], $qc['pass'], $qc['reject'], $qc['rework'], (float) $qc['pass_pct'], (float) $qc['dhu_pct']]);
        $this->assertSame([['QC01 — Quality QC01', 'Stitching Defect', 1, 100.0]], array_map(fn ($r) => [$r['line'], $r['defect'], $r['qty'], (float) $r['share_pct']], $this->report('line_defect', ['date' => '2026-11-06'])['rows']));
        $hd = $this->report('hourly_defect', ['date' => '2026-11-06']);
        $this->assertSame(['defect', 'h10', 'total'], array_column($hd['columns'], 'key'));
        $this->assertSame('10-11', $hd['columns'][1]['label']);
        $this->assertCount(2, $this->report('final_inspection', ['date' => '2026-11-06'])['rows']);

        // Sewing: hourly production, done / undone as per booking, the day's input.
        $hp = $this->report('hourly_production', ['order' => $this->order, 'from' => '2026-11-06', 'to' => '2026-11-06']);
        $this->assertSame([['S', 10, 10], ['M', 8, 8]], array_map(fn ($r) => [$r['size'], $r['h10'], $r['total']], $hp['rows']));
        $this->assertSame(18.0, (float) $hp['totals']['total']);
        $this->assertSame([[600, 18, 582, 3.0]], array_map(fn ($r) => [$r['booked'], $r['sewn'], $r['undone'], (float) $r['done_pct']], $this->report('actual_sewing_done', ['from' => '2026-12-01', 'to' => '2026-12-31'])['rows']));
        $this->assertSame([], $this->report('actual_sewing_done', ['from' => '2026-11-01', 'to' => '2026-11-30'])['rows']);
        $this->assertSame([[1, 600, 18]], array_map(fn ($r) => [$r['orders'], $r['booked'], $r['sewn']], $this->report('sewing_done', ['from' => '2026-11-01', 'to' => '2026-12-31'])['rows']));
        $this->assertSame(0, $this->report('sewing_done', ['from' => '2026-12-01', 'to' => '2026-12-31'])['rows'][0]['sewn']);
        $this->assertSame([[18, 0]], array_map(fn ($r) => [$r['received'], $r['input']], $this->report('store_receive_input', ['date' => '2026-11-04'])['rows']));
        $this->assertSame([[0, 18]], array_map(fn ($r) => [$r['received'], $r['input']], $this->report('store_receive_input', ['date' => '2026-11-06'])['rows']));
        $this->assertSame(['S:10 M:8', 2, 18], [$this->report('colour_store_receive', ['date' => '2026-11-04'])['rows'][0]['sizes'], $this->report('colour_store_receive', ['date' => '2026-11-04'])['rows'][0]['bundles'], $this->report('colour_store_receive', ['date' => '2026-11-04'])['rows'][0]['qty']]);
        $this->assertSame([$a, $b], array_column($this->report('line_input', ['date' => '2026-11-06', 'line' => $this->sew1->id])['rows'], 'bundle_no'));
        $this->assertSame([[18, 18]], array_map(fn ($r) => [$r['input'], $r['output']], $this->report('sewing_by_floor', ['date' => '2026-11-06'])['rows']));

        // Finishing: end table, the detail, manpower; finishing and export over a range.
        $et = $this->report('end_table', ['date' => '2026-11-06'])['rows'][0];
        $this->assertSame([2, 18, 18, 100.0], [$et['bundles'], $et['received'], $et['good'], (float) $et['good_pct']]);
        $this->assertSame([[12, 18, 18, 1.5, 4.5]], array_map(fn ($r) => [$r['manpower'], $r['output'], (int) $r['output'], (float) $r['per_person'], (float) $r['achv_pct']], $this->report('fin_manpower', ['date' => '2026-11-06'])['rows']));
        $fe = $this->report('finishing_export', ['from' => '2026-11-01', 'to' => '2026-11-30'])['rows'][0];
        $this->assertSame([18, 17, 17, 12, 1], [$fe['finishing'], $fe['qc_pass'], $fe['packed'], $fe['shipped'], $fe['cartons']]);

        // Validation.
        $this->as()->getJson('/api/v1/assembly/reports/run?kind=endline_qc')->assertUnprocessable()->assertJsonPath('errors.date.0', 'Pick the production date.');
        $this->as()->getJson('/api/v1/assembly/reports/run?kind=trims_ledger&from=2026-11-01&to=2026-11-30')->assertUnprocessable()->assertJsonPath('errors.store.0', 'Pick the store.');
        $this->as()->getJson('/api/v1/assembly/reports/run?kind=sewing_done&from=2026-11-30&to=2026-11-01')->assertUnprocessable();
        $this->as()->getJson('/api/v1/assembly/reports/run?kind=sew_sc')->assertUnprocessable();
    }

    public function test_trims_ledger_capacity_and_who_may(): void
    {
        $store = $this->trimsStore();
        $item = DB::table('mp_items')->where('item_code', 'TR-BTN-12')->value('id');
        $move = fn (string $date, string $kind, string $doc, int $qty) => DB::table('asm_trims_moves')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'store_id' => $store, 'item_id' => $item,
            'move_date' => $date, 'kind' => $kind, 'doc_no' => $doc, 'qty' => $qty, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $move('2026-10-20', 'RECEIPT', 'MRR-2026100001', 500);
        $move('2026-11-02', 'ISSUE', 'ISS-2026110001', -120);
        $move('2026-11-05', 'RETURN', 'RTN-2026110001', -30);
        $l = $this->report('trims_ledger', ['store' => $store, 'from' => '2026-11-01', 'to' => '2026-11-30'])['rows'];
        $this->assertSame([['Opening balance', null, null, 500.0], ['ISS-2026110001', null, 120.0, 380.0], ['RTN-2026110001', null, 30.0, 350.0]],
            array_map(fn ($r) => [$r['doc_no'], $r['in'] === null ? null : (float) $r['in'], $r['out'] === null ? null : (float) $r['out'], (float) $r['balance']], $l));

        // Capacity: LINE01 — 40 people × 10 h × 60 × 65 % = 15,600 min a day; December 2026 has 27 days besides Fridays.
        DB::table('planning_style_smvs')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'style_no' => '868MI', 'smv' => 12.5, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $cap = $this->report('booking_capacity', ['from' => '2026-12-01', 'to' => '2026-12-31'])['rows'][0];
        $this->assertSame(['LPP', 600, 12.5, 7500, 15600 * 27, round(7500 / (15600 * 27) * 100, 1)], [$cap['buyer'], $cap['qty'], (float) $cap['smv'], $cap['minutes'], $cap['capacity'], (float) $cap['share_pct']]);
        $this->assertSame([], $this->report('actual_booking_capacity', ['from' => '2026-12-01', 'to' => '2026-12-31'])['rows']);

        $this->withToken($this->token('LINE_CHIEF', 'chief@delta.test'))->getJson('/api/v1/assembly/reports/options')->assertOk();
        $this->withToken($this->token('STORE_MANAGER', 'store@delta.test'))->getJson('/api/v1/assembly/reports/run?kind=endline_qc&date=2026-11-06')->assertForbidden();
    }

    private function report(string $kind, array $q = []): array
    {
        return $this->as()->getJson('/api/v1/assembly/reports/run?'.http_build_query(['kind' => $kind] + $q))->assertOk()->json('data');
    }

    /** A trims store with one item, for the ledger. */
    private function trimsStore(): string
    {
        if ($id = DB::table('stores')->where('code', 'ST-TRM')->value('id')) {
            return $id;
        }
        $wh = (string) Str::ulid();
        DB::table('warehouses')->insert(['id' => $wh, 'company_id' => $this->delta->id, 'factory_id' => DB::table('factories')->where('company_id', $this->delta->id)->value('id'), 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id = (string) Str::ulid();
        DB::table('stores')->insert(['id' => $id, 'company_id' => $this->delta->id, 'warehouse_id' => $wh, 'name' => 'Trims Store', 'code' => 'ST-TRM', 'active' => true, 'store_type' => 'GENERAL', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mp_items')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'TR-BTN-12', 'name' => 'Snap Button 12L', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** Bundles packed in and taken through all seven processes in one carton, then packed out. */
    private function packedOut(array $bundles, array $carton, int $final): void
    {
        foreach ($bundles as $no) {
            $this->as()->postJson('/api/v1/assembly/packing/ins', $this->loc($no))->assertCreated();
        }
        $id = DB::table('asm_packing_batches')->where('status', 'PACKING_IN')->value('id');
        $step = fn (string $s, string $a, array $p = []) => $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/steps/{$s}/{$a}", $p)->assertOk();
        foreach (['FOLDING', 'POLY'] as $s) {
            $step($s, 'start');
            $step($s, 'complete');
        }
        $labels = $step('LABEL', 'start')->json('data.labels');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/print")->assertOk();
        foreach ($labels as $l) {
            $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => $l['barcode']])->assertOk();
        }
        $step('LABEL', 'complete');
        $step('ASSORTMENT', 'start');
        $step('ASSORTMENT', 'complete');
        $step('CARTON', 'start');
        $no = $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/cartons", ['lines' => array_map(fn ($s, $q) => ['size' => $s, 'qty' => $q], array_keys($carton), $carton), 'gross_weight' => 6, 'net_weight' => 5])->assertCreated()->json('data.cartons.0');
        $step('CARTON', 'complete');
        $step('MARKING', 'start');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/marking", ['destination' => 'Gdansk', 'shipping_mark' => 'LPP'])->assertOk();
        $step('MARKING', 'complete');
        $step('SEALING', 'start');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/seal", ['barcode' => $no])->assertOk();
        $step('SEALING', 'complete');
        $packingNo = DB::table('asm_packing_batches')->where('id', $id)->value('packing_no');
        $this->as()->postJson('/api/v1/assembly/packing/outs', ['packing_no' => $packingNo, 'final_qty' => $final])->assertOk();
    }

    /** A printed card taken through sewing in full. */
    private function sewn(string $size, int $from, int $to): string
    {
        $no = $this->as()->postJson('/api/v1/cutting/bundles', ['order_id' => $this->order, 'cut_date' => '2026-11-04', 'colour' => 'Black', 'cutting_no' => 1, 'size' => $size, 'serial_from' => $from, 'serial_to' => $to, 'parts_name' => 'Front Panel', 'batch_no' => 'B-1'])
            ->assertCreated()->json('data.bundle_no');
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$no]])->assertOk();
        $this->as()->postJson('/api/v1/assembly/sewing/ins', ['code' => $no, 'receive_date' => '2026-11-06', 'section' => 'Sewing', 'floor_id' => $this->sew1->floor_id, 'line_id' => $this->sew1->id, 'short_qty' => 0])->assertCreated();
        $this->as()->postJson('/api/v1/assembly/sewing/outs', ['code' => $no, 'out_date' => '2026-11-06', 'mode' => 'FULL'])->assertCreated();

        return $no;
    }

    /** Sewn, finished good, then through Quality: inspected into pass and reject, and released. */
    private function passed(string $size, int $from, int $to, int $pass, int $reject = 0): string
    {
        $no = $this->sewn($size, $from, $to);
        $fin = $this->loc($no, $this->fin1);
        $this->as()->postJson('/api/v1/assembly/finishing/ins', $fin)->assertCreated();
        $this->as()->postJson('/api/v1/assembly/finishing/outs', $fin + ['good_qty' => $pass + $reject, 'reject_qty' => 0, 'rework_qty' => 0])->assertCreated();
        $qc = $this->loc($no, $this->qc1);
        $this->as()->postJson('/api/v1/assembly/quality/ins', $qc)->assertCreated();
        $this->as()->postJson('/api/v1/assembly/quality/inspections', $qc + ['pass_qty' => $pass, 'reject_qty' => $reject, 'rework_qty' => 0, 'defect_type' => $reject ? 'STITCHING' : null])->assertCreated();
        $this->as()->postJson('/api/v1/assembly/quality/outs', $qc)->assertCreated();

        return $no;
    }

    private function loc(string $no, ?ProductionLine $line = null): array
    {
        $line ??= $this->fin1;

        return ['code' => $no, 'section_id' => $line->section_id, 'floor_id' => $line->floor_id, 'line_id' => $line->id];
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
