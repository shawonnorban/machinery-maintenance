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
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Assembly Floor → Ready For Shipment (AnnotechERP 20/06): Packing Out books
 * the sealed cartons into finished goods stock by PO, colour and size (a
 * final packed quantity below the cartons coming off the last size); the
 * Ready for Shipment Dashboard reads one row per PO and colour — final
 * packed, shipped, ready, cartons, ship date and Ready / Partially Shipped /
 * Shipment Completed — with its key figures, filters and a row's detail;
 * Finished Goods Stock shows the balance and the ledger — and who may.
 */
class AssemblyReadyTest extends TestCase
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

    public function test_packing_out_books_stock_and_the_ready_dashboard_reads_it(): void
    {
        $a = $this->passed('S', 1, 10, 10);
        $b = $this->passed('M', 1, 10, 10);
        $this->packedOut([$a, $b], ['S' => 10, 'M' => 10], 18);

        $this->assertSame([['S', 10, 0, 'PACKING_OUT', 'PKG-20261106-0001'], ['M', 8, 0, 'PACKING_OUT', 'PKG-20261106-0001']],
            DB::table('asm_fg_ledger')->orderByRaw("case size when 'S' then 0 else 1 end")->get()->map(fn ($l) => [$l->size, $l->qty_in, $l->qty_out, $l->doc_type, $l->doc_no])->all());

        $d = $this->ready()->assertOk()->json('data');
        $this->assertSame([18, 1, 1, 1, 1, 1, 0, 0], array_values($d['kpis']));
        $this->assertSame(['LPP'], $d['buyers']);
        $row = $d['rows'][0];
        $this->assertSame(['11565705', 'LPP', '868MI', 'Black', 18, 0, 18, 1, '2026-12-15', 'READY', 'ORD-11565705'],
            [$row['po_no'], $row['buyer'], $row['style'], $row['colour'], $row['final_packed_qty'], $row['shipped_qty'], $row['ready_qty'], $row['cartons'], $row['ship_date'], $row['status'], $row['order_no']]);
        $this->assertArrayNotHasKey('batch_ids', $row);
        $this->assertArrayNotHasKey('search', $row);

        // Filters: buyer, status, the search (carton barcode, bundle, packing no), the ship date.
        $count = fn (array $q) => count($this->ready($q)->assertOk()->json('data.rows'));
        $this->assertSame([1, 0, 0, 1, 1, 1, 0, 1, 0, 0], [$count(['buyer' => 'LPP']), $count(['buyer' => 'H&M']), $count(['status' => 'SHIPMENT_COMPLETED']),
            $count(['q' => 'ctn202611060001']), $count(['q' => str_replace('-', '', $a)]), $count(['q' => 'PKG-20261106']), $count(['q' => 'zzz']),
            $count(['from' => '2026-12-01', 'to' => '2026-12-31']), $count(['from' => '2026-12-16']), $count(['to' => '2026-12-14'])]);
        $this->ready(['from' => '2026-12-31', 'to' => '2026-12-01'])->assertUnprocessable();

        // The detail: from the order to what is ready, and the batches.
        $det = $this->as()->getJson('/api/v1/assembly/ready/detail?'.http_build_query(['key' => $row['key']]))->assertOk()->json('data');
        $this->assertSame([600, 20, 20, 18, 1, 0, 0, 18, 2], array_values($det['info']));
        $this->assertSame([['PKG-20261106-0001', 18, 0, 1, 'PACKED_OUT']], array_map(fn ($x) => [$x['packing_no'], $x['final_packed_qty'], $x['shipped_qty'], $x['sealed_cartons'], $x['status']], $det['batches']));
        $this->assertSame([], $det['shipments']);
        $this->as()->getJson('/api/v1/assembly/ready/detail?key=nope')->assertUnprocessable()->assertJsonPath('errors.key.0', 'That PO / colour is not ready for shipment.');

        // A shipment out (20/07 writes these) moves the row on: partly, then fully.
        $this->ship('S', 5);
        $row = $this->ready()->json('data.rows.0');
        $this->assertSame([5, 13, 'PARTIALLY_SHIPPED'], [$row['shipped_qty'], $row['ready_qty'], $row['status']]);
        $this->ship('S', 5);
        $this->ship('M', 8);
        $this->assertSame([], $this->ready()->json('data.rows'));
        $d = $this->ready(['all' => 1])->json('data');
        $this->assertSame([0, 18, 'SHIPMENT_COMPLETED', 1, 0], [$d['rows'][0]['ready_qty'], $d['rows'][0]['shipped_qty'], $d['rows'][0]['status'], $d['kpis']['shipment_completed'], $d['kpis']['total_ready_qty']]);
        $this->assertCount(1, $this->ready(['status' => 'SHIPMENT_COMPLETED'])->json('data.rows'));
    }

    public function test_finished_goods_stock_and_its_ledger_and_who_may(): void
    {
        $a = $this->passed('S', 1, 10, 10);
        $b = $this->passed('M', 1, 6, 6);
        $this->packedOut([$a, $b], ['S' => 10, 'M' => 6], 16);

        $s = $this->stock()->assertOk()->json('data');
        $this->assertSame(['balance' => 16, 'in_qty' => 16, 'out_qty' => 0, 'orders' => 1], $s['totals']);
        $this->assertSame([['S', 10, 0, 10], ['M', 6, 0, 6]], array_map(fn ($r) => [$r['size'], $r['in_qty'], $r['out_qty'], $r['balance']], $s['stock']));
        $this->assertSame(['LPP', '11565705', '868MI', 'Black'], [$s['stock'][0]['buyer'], $s['stock'][0]['po_no'], $s['stock'][0]['style'], $s['stock'][0]['colour']]);
        $this->assertSame(['PACKING_OUT', 'PKG-20261106-0001', 'PKG-20261106-0001'], [$s['ledger'][0]['doc_type'], $s['ledger'][0]['doc_no'], $s['ledger'][0]['packing_no']]);

        $this->travel(1)->minutes();
        $this->ship('S', 10);
        $s = $this->stock()->json('data');
        $this->assertSame([16, 10, 6, 1], [$s['totals']['in_qty'], $s['totals']['out_qty'], $s['totals']['balance'], $s['totals']['orders']]);
        $this->assertSame(['M'], array_column($s['stock'], 'size'));
        $this->assertSame(['S', 'M'], array_column($this->stock(['all' => 1])->json('data.stock'), 'size'));
        $this->assertSame(['SHIPMENT', 'PACKING_OUT', 'PACKING_OUT'], array_column($s['ledger'], 'doc_type'));
        $this->assertSame([[], 0], [$this->stock(['q' => 'H&M'])->json('data.stock'), count($this->stock(['q' => 'H&M'])->json('data.ledger'))]);
        $this->assertCount(1, $this->stock(['q' => 'SHP-1'])->json('data.ledger'));

        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->getJson('/api/v1/assembly/ready/dashboard')->assertOk();
        $this->withToken($chief)->getJson('/api/v1/assembly/ready/stock')->assertOk();
        $this->withToken($this->token('STORE_MANAGER', 'store@delta.test'))->getJson('/api/v1/assembly/ready/dashboard')->assertForbidden();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/assembly/ready/stock')->assertForbidden();
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

    /** A shipment out of finished goods, as 20/07 Shipment will write it. */
    private function ship(string $size, int $qty): void
    {
        $in = DB::table('asm_fg_ledger')->where('size', $size)->where('doc_type', 'PACKING_OUT')->first();
        DB::table('asm_fg_ledger')->insert(['id' => (string) Str::ulid(), 'company_id' => $in->company_id, 'order_id' => $in->order_id, 'po_no' => $in->po_no, 'buyer_id' => $in->buyer_id,
            'style' => $in->style, 'colour' => $in->colour, 'size' => $size, 'batch_id' => $in->batch_id, 'doc_type' => 'SHIPMENT', 'doc_no' => 'SHP-1', 'qty_in' => 0, 'qty_out' => $qty,
            'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ready(array $q = []): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/ready/dashboard?'.http_build_query($q));
    }

    private function stock(array $q = []): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/ready/stock?'.http_build_query($q));
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
