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
 * Assembly Floor → Shipment (AnnotechERP 20/07): a shipment entered against
 * a PO / colour from Ready for Shipment — never above what is available, the
 * quantity open shipments hold counted out — confirmed, then marked as
 * shipped out of finished goods stock oldest packed batch first, with the
 * Ready balance before and after; cancelled while open (a confirmed one by a
 * supervisor), corrected once shipped (reversed into stock, re-shipped at the
 * corrected quantity); the dashboard, history, the seventeen reports — and
 * who may.
 */
class AssemblyShipmentTest extends TestCase
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

    public function test_a_shipment_is_entered_confirmed_and_shipped_oldest_batch_first(): void
    {
        $a = $this->passed('S', 1, 10, 10);
        $b = $this->passed('M', 1, 8, 8);
        $this->packedOut([$a], ['S' => 10], 10);
        $this->travel(1)->minutes();
        $this->packedOut([$b], ['M' => 8], 8);

        $e = $this->as()->getJson('/api/v1/assembly/shipments/entry')->assertOk()->json('data');
        $this->assertCount(17, $e['reports']);
        $this->assertSame([[18, 18, 0, ['S', 'M'], 'Gdansk']], array_map(fn ($r) => [$r['ready_qty'], $r['available_qty'], $r['reserved_qty'], $r['sizes'], $r['destination']], $e['ready']));
        $key = $e['ready'][0]['key'];

        $this->create($key, 19)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Shipment quantity (19) is more than what is available (18).');
        $s1 = $this->create($key, 12, ['carton_qty' => 1, 'vehicle_ref' => 'MSKU1234567'])->assertCreated()->json('data');
        $this->assertSame(['SHP-20261106-0001', 'PLANNED', 12, true, 'Gdansk', 'SEA', null, null], [$s1['shipment_no'], $s1['status'], $s1['qty'], $s1['partial'], $s1['destination'], $s1['mode'], $s1['ready_before'], $s1['remaining_after']]);
        $e = $this->as()->getJson('/api/v1/assembly/shipments/entry')->json('data');
        $this->assertSame([18, 12, 6], [$e['ready'][0]['ready_qty'], $e['ready'][0]['reserved_qty'], $e['ready'][0]['available_qty']]);
        $this->assertSame(['SHP-20261106-0001'], array_column($e['open'], 'shipment_no'));
        $this->create($key, 7)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Shipment quantity (7) is more than what is available (6).');

        $this->act($s1['id'], 'ship')->assertUnprocessable()->assertJsonPath('errors.id.0', 'SHP-20261106-0001 must be confirmed before it is marked as shipped.');
        $this->act($s1['id'], 'confirm')->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $this->act($s1['id'], 'confirm')->assertUnprocessable()->assertJsonPath('errors.id.0', 'SHP-20261106-0001 is Confirmed.');
        $shipped = $this->act($s1['id'], 'ship')->assertOk()->json('data');
        $this->assertSame(['SHIPPED', 18, 6], [$shipped['status'], $shipped['ready_before'], $shipped['remaining_after']]);

        // Out of stock oldest packed batch first: all of the first batch's S, then M from the second.
        $outs = DB::table('asm_fg_ledger')->where('doc_type', 'SHIPMENT')->orderBy('id')->get();
        $this->assertSame([['S', 10, 'PKG-20261106-0001'], ['M', 2, 'PKG-20261106-0002']], $outs->map(fn ($l) => [$l->size, $l->qty_out, DB::table('asm_packing_batches')->where('id', $l->batch_id)->value('packing_no')])->all());
        $row = $this->as()->getJson('/api/v1/assembly/ready/dashboard')->json('data.rows.0');
        $this->assertSame([12, 6, 0, 6, 'PARTIALLY_SHIPPED'], [$row['shipped_qty'], $row['ready_qty'], $row['reserved_qty'], $row['available_qty'], $row['status']]);
        $this->assertSame([['M', 6]], array_map(fn ($r) => [$r['size'], $r['balance']], $this->as()->getJson('/api/v1/assembly/ready/stock')->json('data.stock')));

        // The rest: a full shipment completes the PO / colour.
        $s2 = $this->create($key, 6, ['mode' => 'AIR', 'destination' => 'Warsaw'])->assertCreated()->json('data');
        $this->assertFalse($s2['partial']);
        $this->act($s2['id'], 'confirm')->assertOk();
        $this->assertSame(0, $this->act($s2['id'], 'ship')->assertOk()->json('data.remaining_after'));
        $d = $this->as()->getJson('/api/v1/assembly/shipments/dashboard')->assertOk()->json('data');
        $this->assertSame([0, 18, 18, 0, 0, 1, 0, 1], array_values($d['kpis']));
        $this->assertSame(['SHP-20261106-0002', 'SHP-20261106-0001'], array_column($d['shipments'], 'shipment_no'));
        $this->assertSame('SHIPMENT_COMPLETED', $this->as()->getJson('/api/v1/assembly/ready/dashboard?all=1')->json('data.rows.0.status'));
        $det = $this->as()->getJson('/api/v1/assembly/ready/detail?'.http_build_query(['key' => $key]))->json('data');
        $this->assertSame([18, 0, 0], [$det['info']['shipped_qty'], $det['info']['ready_qty'], $det['info']['reserved_qty']]);
        $this->assertSame(['SHP-20261106-0001', 'SHP-20261106-0002'], array_column($det['shipments'], 'shipment_no'));

        $list = fn (array $q) => array_column($this->as()->getJson('/api/v1/assembly/shipments/history?'.http_build_query($q))->assertOk()->json('data.rows'), 'shipment_no');
        $this->assertSame([['SHP-20261106-0002', 'SHP-20261106-0001'], ['SHP-20261106-0002'], ['SHP-20261106-0001'], [], []], [$list(['status' => 'SHIPPED']), $list(['q' => 'warsaw']), $list(['q' => 'SHP-20261106-0001']),
            $list(['status' => 'CANCELLED']), $list(['from' => '2026-11-07'])]);
        $this->as()->getJson('/api/v1/assembly/shipments/history?from=2026-11-07&to=2026-11-01')->assertUnprocessable();

        $p = $this->as()->getJson('/api/v1/assembly/packing/dashboard')->json('data');
        $this->assertSame([18, 0, 18], [$p['kpis']['total_shipped'], $p['kpis']['ready_for_shipment'], end($p['pipeline'])['qty']]);
        $this->assertSame([], $p['ready']);
        $this->assertCount(2, $p['shipments']);
    }

    public function test_cancel_correct_reports_and_who_may(): void
    {
        $a = $this->passed('S', 1, 10, 10);
        $this->packedOut([$a], ['S' => 10], 10);
        $key = $this->as()->getJson('/api/v1/assembly/shipments/entry')->json('data.ready.0.key');
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->getJson('/api/v1/assembly/shipments/dashboard')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/assembly/shipments', ['key' => $key, 'qty' => 4, 'ship_date' => '2026-11-06', 'mode' => 'SEA'])->assertForbidden();

        // Cancel: a planned one with the reason; its quantity is available again.
        $s1 = $this->create($key, 4)->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/assembly/shipments/{$s1['id']}/cancel")->assertUnprocessable();
        $this->as()->postJson("/api/v1/assembly/shipments/{$s1['id']}/cancel", ['reason' => 'Buyer moved the date'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame(10, $this->as()->getJson('/api/v1/assembly/shipments/entry')->json('data.ready.0.available_qty'));
        $this->act($s1['id'], 'confirm')->assertUnprocessable()->assertJsonPath('errors.id.0', 'SHP-20261106-0001 is Cancelled.');

        // Correct: a shipped one reversed back into stock and re-shipped at the corrected quantity.
        $s2 = $this->create($key, 10)->assertCreated()->json('data');
        $this->withToken($chief)->postJson("/api/v1/assembly/shipments/{$s2['id']}/confirm")->assertForbidden();
        $this->act($s2['id'], 'confirm')->assertOk();
        $this->act($s2['id'], 'ship')->assertOk();
        $this->withToken($chief)->postJson("/api/v1/assembly/shipments/{$s2['id']}/correct", ['reason' => 'x'])->assertForbidden();
        $this->as()->postJson("/api/v1/assembly/shipments/{$s2['id']}/correct", ['reason' => 'Short shipped', 'corrected_qty' => 11])->assertUnprocessable()->assertJsonPath('errors.corrected_qty.0', 'Shipment quantity (11) is more than what is available (10).');
        $this->assertSame('SHIPPED', DB::table('asm_shipments')->where('id', $s2['id'])->value('status'));
        $c = $this->as()->postJson("/api/v1/assembly/shipments/{$s2['id']}/correct", ['reason' => 'Short shipped', 'corrected_qty' => 7])->assertOk()->json('data');
        $this->assertSame(['REVERSED', 'Short shipped', 'SHP-20261106-0003', 'SHIPPED', 'SHP-20261106-0002', 10, 3], [$c['reversed']['status'], $c['reversed']['reverse_reason'], $c['corrected']['shipment_no'],
            $c['corrected']['status'], $c['corrected']['corrects_no'], $c['corrected']['ready_before'], $c['corrected']['remaining_after']]);
        $this->assertSame([['SHIPMENT', 0, 10], ['SHIPMENT_REVERSAL', 10, 0], ['SHIPMENT', 0, 7]], DB::table('asm_fg_ledger')->where('doc_type', '!=', 'PACKING_OUT')->orderBy('id')->get()->map(fn ($l) => [$l->doc_type, $l->qty_in, $l->qty_out])->all());
        $row = $this->as()->getJson('/api/v1/assembly/ready/dashboard')->json('data.rows.0');
        $this->assertSame([7, 3, 'PARTIALLY_SHIPPED'], [$row['shipped_qty'], $row['ready_qty'], $row['status']]);
        $this->as()->postJson("/api/v1/assembly/shipments/{$s2['id']}/correct", ['reason' => 'again'])->assertUnprocessable()->assertJsonPath('errors.id.0', 'Only a shipped shipment can be corrected — SHP-20261106-0002 is not shipped.');
        $this->as()->postJson("/api/v1/assembly/shipments/{$c['corrected']['id']}/correct", ['reason' => 'Shipment returned'])->assertOk()->assertJsonPath('data.corrected', null);
        $row = $this->as()->getJson('/api/v1/assembly/ready/dashboard')->json('data.rows.0');
        $this->assertSame([0, 10, 'READY'], [$row['shipped_qty'], $row['ready_qty'], $row['status']]);

        // A confirmed shipment is cancelled by a supervisor.
        $s4 = $this->create($key, 5)->assertCreated()->json('data');
        $this->act($s4['id'], 'confirm')->assertOk();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->postJson("/api/v1/assembly/shipments/{$s4['id']}/cancel", ['reason' => 'Container not booked'])->assertOk();
        $s5 = $this->create($key, 6)->assertCreated()->json('data');
        $this->act($s5['id'], 'confirm')->assertOk();
        $this->act($s5['id'], 'ship')->assertOk();

        // The seventeen reports.
        $run = fn (string $kind, array $q = []) => $this->as()->getJson('/api/v1/assembly/shipments-reports?'.http_build_query($q + ['kind' => $kind, 'from' => '2026-11-01', 'to' => '2026-11-06']))->assertOk()->json('data');
        foreach (['daily', 'buyer', 'po', 'style', 'quantity', 'partial', 'completed', 'history', 'ready', 'buyer_ready', 'po_ready', 'style_ready', 'ready_qty', 'pending', 'partially_shipped', 'fg_stock', 'fg_ledger'] as $kind) {
            $this->assertNotEmpty($run($kind)['columns'], $kind);
        }
        $this->assertSame([['date' => '2026-11-06', 'shipments' => 1, 'qty' => 6, 'cartons' => 0, 'buyers' => 1]], $run('daily')['rows']);
        $this->assertSame([600, 6], [$run('po')['rows'][0]['order_qty'], $run('po')['rows'][0]['qty']]);
        $this->assertSame(['SHP-20261106-0005'], array_column($run('quantity')['rows'], 'shipment_no'));
        $this->assertSame([10, 6, 4], [$run('partial')['rows'][0]['ready_before'], $run('partial')['rows'][0]['qty'], $run('partial')['rows'][0]['remaining']]);
        $this->assertSame(['CANCELLED', 'REVERSED', 'REVERSED', 'CANCELLED', 'SHIPPED'], array_reverse(array_column($run('history')['rows'], 'status')));
        $this->assertArrayNotHasKey('order_id', $run('history')['rows'][0]);
        $this->assertSame([[4, 'PARTIALLY_SHIPPED']], array_map(fn ($r) => [$r['ready'], $r['status']], $run('ready')['rows']));
        $this->assertSame([['S', 4]], array_map(fn ($r) => [$r['size'], $r['qty']], $run('ready_qty')['rows']));
        $this->assertSame([], $run('pending')['rows']);
        $this->assertCount(1, $run('partially_shipped')['rows']);
        $this->assertSame([[27, 23, 4]], array_map(fn ($r) => [$r['in_qty'], $r['out_qty'], $r['qty']], $run('fg_stock')['rows']));
        $this->assertSame(6, count($run('fg_ledger')['rows']));
        $this->assertSame([], $run('daily', ['q' => 'H&M'])['rows']);
        $this->as()->getJson('/api/v1/assembly/shipments-reports?kind=nope&from=2026-11-01&to=2026-11-06')->assertUnprocessable();

        $this->withToken($this->token('STORE_MANAGER', 'store@delta.test'))->getJson('/api/v1/assembly/shipments/dashboard')->assertForbidden();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/assembly/shipments-reports?kind=daily&from=2026-11-01&to=2026-11-06')->assertForbidden();
    }

    private function create(string $key, int $qty, array $more = []): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/shipments', ['key' => $key, 'qty' => $qty, 'ship_date' => '2026-11-06', 'mode' => 'SEA'] + $more);
    }

    private function act(string $id, string $action): TestResponse
    {
        return $this->as()->postJson("/api/v1/assembly/shipments/{$id}/{$action}");
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
