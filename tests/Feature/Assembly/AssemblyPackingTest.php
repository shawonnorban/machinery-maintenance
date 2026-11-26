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
 * Assembly Floor → Packing (AnnotechERP 20/04) and its Packing Process
 * (20/05): a Quality-passed bundle packed in — whole or in part, never more
 * than its QC Pass — joining the open batch of its PO and colour until
 * Folding starts, a supervisor removing a mistaken scan before then; the
 * seven processes strictly in order with the verified quantity, poly labels
 * printed and verified once, the assortment ratio checked against the sizes
 * packed in, cartons in whole ratio sets and never more of a size than is
 * left, marking, sealing with weights and a supervisor's unseal, a reopen
 * only before the next process starts; Packing Out refused until every check
 * passes and never more than the sealed cartons hold; the dashboard, the
 * trace, the eleven reports — and who may.
 */
class AssemblyPackingTest extends TestCase
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

    public function test_packing_in_opens_a_batch_per_po_and_colour_and_a_supervisor_may_remove_a_scan(): void
    {
        $opts = $this->as()->getJson('/api/v1/assembly/packing/options')->assertOk()->json('data');
        $this->assertSame(['Finishing'], array_column($opts['sections'], 'label'));
        $this->assertSame(['FIN01 — Finishing FIN01'], array_column($opts['lines'], 'label'));
        $this->assertSame(['PK09 — Packing PK09'], $opts['unfiled']);
        $this->assertSame(['FOLDING', 'POLY', 'LABEL', 'ASSORTMENT', 'CARTON', 'MARKING', 'SEALING'], $opts['steps']);
        $this->assertCount(11, $opts['reports']);

        $raw = $this->sewn('L', 1, 12);
        $p = $this->previewIn($raw)->assertOk()->json('data');
        $this->assertSame([false, "Bundle {$raw} has not been through Quality."], [$p['ok'], $p['message']]);
        $a = $this->passed('S', 1, 30, 30);
        $b = $this->passed('M', 1, 20, 18, 2);

        $p = $this->previewIn(str_replace('-', '', strtolower($a)))->assertOk()->json('data');
        $this->assertSame([true, 'LPP', 'ORD-11565705', '11565705', 'Black', 'S', 200, 30, 0, 30, '2026-12-15', null], [$p['ok'], $p['card']['buyer'], $p['card']['order_no'], $p['card']['po_no'], $p['card']['colour'], $p['card']['size'],
            $p['card']['order_qty'], $p['card']['qc_pass_qty'], $p['card']['already_packed_qty'], $p['card']['balance_qty'], $p['card']['ship_date'], $p['joins']]);

        $this->packIn($a, 20, $this->qc1)->assertUnprocessable()->assertJsonPath('errors.line_id.0', 'Pick an active packing location filed under that section and floor.');
        $in = $this->packIn($a, 20)->assertCreated()->json('data');
        $this->assertSame(['PKG-20261106-0001', true, 20], [$in['packing_no'], $in['opened'], $in['qty']]);
        $p = $this->previewIn($a)->json('data');
        $this->assertSame([20, 10, 'PKG-20261106-0001', 20, 1], [$p['card']['already_packed_qty'], $p['card']['balance_qty'], $p['joins']['packing_no'], $p['joins']['packing_in_qty'], $p['joins']['bundles']]);
        $this->packIn($a, 15)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Packing In Qty (15) is more than the balance (10).');
        $rest = $this->packIn($a)->assertCreated()->json('data');
        $this->assertSame([false, 10], [$rest['opened'], $rest['qty']]);
        $this->packIn($a)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} is fully packed in (30 pcs).");
        $this->packIn($b)->assertCreated()->assertJsonPath('data.packing_no', 'PKG-20261106-0001');

        $desk = $this->as()->getJson('/api/v1/assembly/packing/in')->assertOk()->json('data');
        $this->assertSame(['PKG-20261106-0001'], array_column($desk['open'], 'packing_no'));
        $this->assertSame([48, 2, [['size' => 'S', 'qty' => 30], ['size' => 'M', 'qty' => 18]], 'PACKING_IN', 'FIN01 — Finishing FIN01'], [$desk['open'][0]['packing_in_qty'], $desk['open'][0]['bundles'], $desk['open'][0]['sizes'], $desk['open'][0]['stage'], $desk['open'][0]['location']]);
        $this->assertSame([$b, $a, $a], array_column($desk['recent'], 'bundle_no'));
        $this->assertTrue($desk['recent'][0]['removable']);

        // A supervisor removes a mistaken scan while the batch is still at Packing In.
        $this->withToken($this->token('LINE_CHIEF', 'chief@delta.test'))->deleteJson('/api/v1/assembly/packing/ins/'.$desk['recent'][0]['id'])->assertForbidden();
        $this->as()->deleteJson('/api/v1/assembly/packing/ins/'.$desk['recent'][0]['id'])->assertOk()->assertJsonPath('data.bundle_no', $b);
        $batch = $desk['open'][0]['id'];
        $this->assertSame(30, $this->as()->getJson('/api/v1/assembly/packing/batches/'.$batch)->json('data.packing_in_qty'));

        // Folding closes the batch: no removal, and the next scan of that PO and colour opens a new batch.
        $this->step($batch, 'FOLDING', 'start')->assertOk()->assertJsonPath('data.stage', 'IN_PROGRESS');
        $this->as()->deleteJson('/api/v1/assembly/packing/ins/'.$desk['recent'][1]['id'])->assertUnprocessable()->assertJsonPath('errors.id.0', "Bundle {$a} can't be removed — PKG-20261106-0001 has started Folding.");
        $second = $this->packIn($b)->assertCreated()->json('data');
        $this->assertSame(['PKG-20261106-0002', true], [$second['packing_no'], $second['opened']]);
        $this->as()->deleteJson('/api/v1/assembly/packing/ins/'.$second['id'])->assertOk();
        $this->assertSame('CANCELLED', DB::table('asm_packing_batches')->where('packing_no', 'PKG-20261106-0002')->value('status'));
        $this->as()->getJson('/api/v1/assembly/packing/in')->assertJsonCount(0, 'data.open');
    }

    public function test_the_seven_processes_run_in_order_then_packing_out(): void
    {
        $a = $this->passed('S', 1, 24, 24);
        $b = $this->passed('M', 1, 24, 24);
        $this->packIn($a)->assertCreated();
        $id = $this->packIn($b)->assertCreated()->json('data.packing_no');
        $id = DB::table('asm_packing_batches')->where('packing_no', $id)->value('id');

        $this->step($id, 'POLY', 'start')->assertUnprocessable()->assertJsonPath('errors.step.0', 'Folding must be completed first.');
        $this->step($id, 'FOLDING', 'complete')->assertUnprocessable()->assertJsonPath('errors.step.0', 'Folding is not in progress.');
        $this->step($id, 'FOLDING', 'start')->assertOk();
        $this->step($id, 'FOLDING', 'start')->assertUnprocessable()->assertJsonPath('errors.step.0', 'Folding has already started.');
        $this->step($id, 'FOLDING', 'complete', ['qty' => 50])->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Verified Qty (50) is more than 48.');
        $f = $this->step($id, 'FOLDING', 'complete', ['remarks' => 'Folded by size'])->assertOk()->json('data.steps.0');
        $this->assertSame(['COMPLETED', 48, 'Folded by size'], [$f['status'], $f['qty'], $f['remarks']]);
        $this->assertSame([$id], array_column($this->as()->getJson('/api/v1/assembly/packing/process?step=FOLDING')->json('data.done_today'), 'id'));
        $this->assertSame([$id], array_column($this->as()->getJson('/api/v1/assembly/packing/process?step=POLY')->json('data.queue'), 'id'));
        $this->as()->getJson('/api/v1/assembly/packing/process?step=LABEL')->assertJsonCount(0, 'data.queue');

        $this->step($id, 'POLY', 'start')->assertOk();
        $this->step($id, 'POLY', 'complete', ['qty' => 48])->assertOk();

        // Barcode & Label: one label per size, printed, then each verified once.
        $labels = $this->step($id, 'LABEL', 'start')->assertOk()->json('data.labels');
        $this->assertSame([['S', 24, 'LBL20261106000101'], ['M', 24, 'LBL20261106000102']], array_map(fn ($l) => [$l['size'], $l['qty'], $l['barcode']], $labels));
        $this->step($id, 'LABEL', 'complete')->assertUnprocessable()->assertJsonPath('errors.step.0', '2 poly label(s) are not verified yet.');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => 'LBL20261106000101'])->assertUnprocessable()->assertJsonPath('errors.barcode.0', 'Label LBL20261106000101 has not been printed yet — print the labels first.');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/print")->assertOk()->assertJsonPath('data.labels.0.print_count', 1);
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => 'LBL20261106000101'])->assertOk()->assertJsonPath('data.size', 'S');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => 'LBL20261106000101'])->assertUnprocessable()->assertJsonPath('errors.barcode.0', 'Duplicate scan — label LBL20261106000101 was already verified at 2026-11-06 10:00.');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => 'LBL99'])->assertUnprocessable()->assertJsonPath('errors.barcode.0', 'Label LBL99 is not a label of PKG-20261106-0001.');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => 'lbl20261106000102'])->assertOk();
        $this->step($id, 'LABEL', 'complete')->assertOk()->assertJsonPath('data.steps.2.qty', 48);

        // Assortment: the ratio's sizes must be packed in; a supervisor may reopen it before Carton Packing starts.
        $this->step($id, 'ASSORTMENT', 'start')->assertOk();
        $this->step($id, 'ASSORTMENT', 'complete', ['ratio' => 'S:1, XL:1'])->assertUnprocessable()->assertJsonPath('errors.ratio.0', 'Assortment Ratio Not Matched — XL is not packed in.');
        $this->step($id, 'ASSORTMENT', 'complete', ['ratio' => 'S-1'])->assertUnprocessable()->assertJsonPath('errors.ratio.0', 'Write the ratio as size:number, comma-separated — e.g. S:1, M:2, L:2.');
        $this->step($id, 'ASSORTMENT', 'complete', ['ratio' => 's:1; M:1'])->assertOk()->assertJsonPath('data.assort_ratio', 'S:1, M:1');
        $this->withToken($this->token('LINE_CHIEF', 'chief@delta.test'))->postJson("/api/v1/assembly/packing/batches/{$id}/steps/ASSORTMENT/reopen", ['reason' => 'Wrong ratio'])->assertForbidden();
        $this->step($id, 'ASSORTMENT', 'reopen')->assertUnprocessable();
        $this->step($id, 'ASSORTMENT', 'reopen', ['reason' => 'Buyer changed the ratio'])->assertOk()->assertJsonPath('data.steps.3.status', 'IN_PROGRESS');
        $this->step($id, 'ASSORTMENT', 'complete', ['ratio' => 'S:1, M:1'])->assertOk();
        $this->step($id, 'FOLDING', 'reopen', ['reason' => 'x'])->assertUnprocessable()->assertJsonPath('errors.step.0', 'Poly Packing has already started — it cannot be reopened now.');

        // Carton Packing: whole ratio sets, never more of a size than is left.
        $this->step($id, 'CARTON', 'start')->assertOk();
        $this->cartons($id, ['S' => 6, 'M' => 5])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Each carton must hold whole sets of the ratio S:1, M:1.');
        $made = $this->cartons($id, ['S' => 6, 'M' => 6], ['copies' => 4, 'carton_type' => '5-ply', 'carton_size' => '60×40×40 cm', 'gross_weight' => 10.5, 'net_weight' => 9.8])->assertCreated()->json('data');
        $this->assertSame(['CTN-20261106-0001', 'CTN-20261106-0002', 'CTN-20261106-0003', 'CTN-20261106-0004'], $made['cartons']);
        $this->assertSame([[1, 12, 'OPEN', 10.5, [['size' => 'S', 'qty' => 6], ['size' => 'M', 'qty' => 6]]]], array_map(fn ($c) => [$c['serial'], $c['qty'], $c['status'], $c['gross_weight'], $c['lines']], array_slice($made['batch']['carton_list'], 0, 1)));
        $this->cartons($id, ['S' => 1, 'M' => 1])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Size S: 1 pcs is more than is left (0).');
        $this->as()->deleteJson('/api/v1/assembly/packing/cartons/'.$made['batch']['carton_list'][1]['id'])->assertOk();
        $after = $this->cartons($id, ['S' => 6, 'M' => 6])->assertCreated()->json('data.batch.carton_list');
        $this->assertSame([[1, 'CTN-20261106-0001'], [2, 'CTN-20261106-0003'], [3, 'CTN-20261106-0004'], [4, 'CTN-20261106-0005']], array_map(fn ($c) => [$c['serial'], $c['carton_no']], $after));
        $this->step($id, 'CARTON', 'complete')->assertOk()->assertJsonPath('data.steps.4.qty', 48);

        // Carton Marking: destination and shipping mark mark every carton.
        $this->step($id, 'MARKING', 'start')->assertOk();
        $this->step($id, 'MARKING', 'complete')->assertUnprocessable()->assertJsonPath('errors.step.0', 'Generate the marking first — every carton must be marked.');
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/marking", ['shipping_mark' => 'LPP'])->assertUnprocessable()->assertJsonPath('errors.destination.0', 'Enter the destination.');
        $m = $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/marking", ['destination' => 'Hamburg', 'country' => 'Germany', 'shipping_mark' => 'LPP / PO 11565705 / 868MI'])->assertOk()->json('data');
        $this->assertSame(['MARKED'], array_values(array_unique(array_column($m['carton_list'], 'status'))));
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/marking/print")->assertOk()->assertJsonPath('data.marking_print_count', 1);
        $this->step($id, 'MARKING', 'complete')->assertOk();

        // Carton Sealing: scanned, weighed, checked, sealed; a supervisor may unseal.
        $this->step($id, 'SEALING', 'start')->assertOk();
        $seal = fn (string $code, array $w = []) => $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/seal", ['barcode' => $code] + $w);
        $seal('CTN202611060001')->assertOk()->assertJsonPath('data.carton_no', 'CTN-20261106-0001');
        $seal('CTN-20261106-0001')->assertUnprocessable()->assertJsonPath('errors.barcode.0', 'Carton CTN-20261106-0001 is already sealed.');
        $seal('CTN202611060002')->assertUnprocessable()->assertJsonPath('errors.barcode.0', 'Carton CTN202611060002 is not a carton of PKG-20261106-0001.');
        $seal('CTN202611060005')->assertUnprocessable()->assertJsonPath('errors.gross_weight.0', 'Enter the gross weight of carton CTN-20261106-0005.');
        $seal('CTN202611060005', ['gross_weight' => 10, 'net_weight' => 11])->assertUnprocessable()->assertJsonPath('errors.net_weight.0', 'Net weight cannot be more than gross weight.');
        $seal('CTN202611060005', ['gross_weight' => 10.4, 'net_weight' => 9.7])->assertOk();
        $this->step($id, 'SEALING', 'complete')->assertUnprocessable()->assertJsonPath('errors.step.0', '2 carton(s) are not sealed yet.');
        $first = DB::table('asm_packing_cartons')->where('carton_no', 'CTN-20261106-0001')->value('id');
        $this->withToken($this->token('LINE_CHIEF', 'chief2@delta.test'))->postJson("/api/v1/assembly/packing/cartons/{$first}/unseal", ['reason' => 'Short piece'])->assertForbidden();
        $this->as()->postJson("/api/v1/assembly/packing/cartons/{$first}/unseal", ['reason' => 'Short piece found'])->assertOk();
        $this->assertSame(['MARKED', 'Short piece found'], [DB::table('asm_packing_cartons')->where('id', $first)->value('status'), DB::table('asm_packing_cartons')->where('id', $first)->value('unseal_reason')]);
        foreach (['CTN202611060001', 'CTN202611060003', 'CTN202611060004'] as $code) {
            $seal($code)->assertOk();
        }

        // Packing Out refused until every check passes.
        $pre = $this->as()->getJson('/api/v1/assembly/packing/out/preview?code=CTN202611060003')->assertOk()->json('data');
        $this->assertSame([false, 'checks'], [$pre['ok'], $pre['reason']]);
        $this->assertSame(['SEALING'], array_column(array_filter($pre['checks'], fn ($c) => ! $c['ok']), 'step'));
        $this->as()->postJson('/api/v1/assembly/packing/outs', ['packing_no' => 'PKG-20261106-0001', 'final_qty' => 48])->assertUnprocessable()->assertJsonPath('errors.packing_no.0', 'Not every process of PKG-20261106-0001 is complete — it cannot be packed out yet.');
        $this->step($id, 'SEALING', 'complete')->assertOk()->assertJsonPath('data.stage', 'READY_OUT');
        $this->assertSame(['PKG-20261106-0001'], array_column($this->as()->getJson('/api/v1/assembly/packing/out')->json('data.waiting'), 'packing_no'));

        $pre = $this->as()->getJson('/api/v1/assembly/packing/out/preview?code='.$a)->assertOk()->json('data');
        $this->assertSame([true, null, 48, 9, 4, 4], [$pre['ok'], $pre['reason'], $pre['sealed_qty'], count($pre['checks']), $pre['batch']['cartons'], $pre['batch']['sealed_cartons']]);
        $this->as()->postJson('/api/v1/assembly/packing/outs', ['packing_no' => 'PKG-20261106-0001', 'final_qty' => 50])->assertUnprocessable()->assertJsonPath('errors.final_qty.0', 'Final packed (50) is more than the sealed cartons hold (48).');
        $this->as()->postJson('/api/v1/assembly/packing/outs', ['packing_no' => 'pkg-20261106-0001', 'final_qty' => 47])->assertOk()->assertJsonPath('data.final_packed_qty', 47);
        $this->as()->postJson('/api/v1/assembly/packing/outs', ['packing_no' => 'PKG-20261106-0001', 'final_qty' => 47])->assertUnprocessable()->assertJsonPath('errors.packing_no.0', 'PKG-20261106-0001 was already packed out on 2026-11-06 10:00.');
        $this->step($id, 'SEALING', 'reopen', ['reason' => 'late'])->assertUnprocessable();
        $out = $this->as()->getJson('/api/v1/assembly/packing/out')->json('data');
        $this->assertSame([[], ['PKG-20261106-0001']], [$out['waiting'], array_column($out['today'], 'packing_no')]);

        // The dashboard: the flow, the key figures, Ready for Shipment.
        $d = $this->as()->getJson('/api/v1/assembly/packing/dashboard')->assertOk()->json('data');
        $this->assertSame(['qc', 'in', 'FOLDING', 'POLY', 'LABEL', 'ASSORTMENT', 'CARTON', 'MARKING', 'SEALING', 'out', 'ready', 'shipment'], array_column($d['pipeline'], 'key'));
        $this->assertSame([48, 47, 47, 4, 0, 47, 0, 48, 0], [$d['kpis']['today_packing'], $d['kpis']['completed'], $d['kpis']['packing_out_today'], $d['kpis']['total_cartons'], $d['kpis']['pending_packing'],
            $d['kpis']['ready_for_shipment'], $d['kpis']['total_shipped'], $d['kpis']['total_packing_qty'], $d['kpis']['packing_in']]);
        $this->assertSame([[], 'LPP', 47, 4, '2026-12-15'], [$d['active'], $d['ready'][0]['buyer'], $d['ready'][0]['ready_qty'], $d['ready'][0]['cartons'], $d['ready'][0]['ship_date']]);

        // The trace: a carton's and a bundle's history.
        $t = $this->as()->getJson('/api/v1/assembly/packing/trace?code=CTN202611060005')->assertOk()->json('data');
        $this->assertSame(['CARTON', 'CTN-20261106-0005', 'PKG-20261106-0001', 'PACKED_OUT'], [$t['kind'], $t['code'], $t['headline']['batch'], $t['headline']['stage']]);
        $stages = array_column($t['events'], 'stage');
        $this->assertContains('CARTON_MADE', $stages);
        $this->assertContains('CARTON_SEALED', $stages);
        $this->assertSame('PACKED_OUT', end($stages));
        $t = $this->as()->getJson('/api/v1/assembly/packing/trace?code='.$a)->assertOk()->json('data');
        $this->assertSame(['BUNDLE', 'QC_PASS', 'PACKING_IN'], [$t['kind'], $t['events'][0]['stage'], $t['events'][1]['stage']]);
        $this->as()->getJson('/api/v1/assembly/packing/trace?code=NOPE-1')->assertUnprocessable()->assertJsonPath('errors.code.0', 'No packing batch, carton, label or bundle NOPE-1.');
    }

    public function test_the_eleven_reports_and_who_may(): void
    {
        $a = $this->passed('S', 1, 10, 10);
        $b = $this->passed('L', 1, 8, 8);
        $this->packIn($a, 6)->assertCreated();
        $id = DB::table('asm_packing_batches')->value('id');
        foreach (['FOLDING', 'POLY'] as $s) {
            $this->step($id, $s, 'start')->assertOk();
            $this->step($id, $s, 'complete')->assertOk();
        }
        $this->step($id, 'LABEL', 'start')->assertOk();
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/print")->assertOk();
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/labels/verify", ['barcode' => 'LBL20261106000101'])->assertOk();
        $this->step($id, 'LABEL', 'complete')->assertOk();
        $this->step($id, 'ASSORTMENT', 'start')->assertOk();
        $this->step($id, 'ASSORTMENT', 'complete')->assertOk()->assertJsonPath('data.assort_ratio', null);
        $this->step($id, 'CARTON', 'start')->assertOk();
        $this->cartons($id, ['S' => 6], ['gross_weight' => 3.2, 'net_weight' => 2.9])->assertCreated();
        $this->step($id, 'CARTON', 'complete')->assertOk();
        $this->step($id, 'MARKING', 'start')->assertOk();
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/marking", ['destination' => 'Warsaw', 'shipping_mark' => 'LPP'])->assertOk();
        $this->step($id, 'MARKING', 'complete')->assertOk();
        $this->step($id, 'SEALING', 'start')->assertOk();
        $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/seal", ['barcode' => 'CTN202611060001'])->assertOk();
        $this->step($id, 'SEALING', 'complete')->assertOk();
        $this->as()->postJson('/api/v1/assembly/packing/outs', ['packing_no' => 'PKG-20261106-0001', 'final_qty' => 6])->assertOk();

        $run = fn (string $kind, array $q = []) => $this->as()->getJson('/api/v1/assembly/packing/reports?'.http_build_query($q + ['kind' => $kind, 'from' => '2026-11-01', 'to' => '2026-11-06']))->assertOk()->json('data');
        $daily = $run('daily');
        $this->assertSame([['date' => '2026-11-06', 'bundles' => 1, 'packing_in' => 6, 'batches_out' => 1, 'packed_out' => 6, 'cartons_sealed' => 1, 'sealed_qty' => 6]], $daily['rows']);
        $this->assertSame(['key' => 'packing_in', 'numeric' => true], $daily['columns'][2]);
        $this->assertSame([$a, 6, 'FIN01 — Finishing FIN01'], [$run('in')['rows'][0]['bundle_no'], $run('in')['rows'][0]['qty'], $run('in')['rows'][0]['location']]);
        $out = $run('out')['rows'][0];
        $this->assertSame([6, 6, 0, 1, 3.2, 2.9], [$out['packing_in'], $out['final'], $out['balance'], $out['cartons'], $out['gross'], $out['net']]);
        $this->assertSame([['buyer' => 'LPP', 'batches' => 1, 'packing_in' => 6, 'packed_out' => 6, 'cartons' => 1]], $run('buyer')['rows']);
        $this->assertSame([600, 6], [$run('po')['rows'][0]['order_qty'], $run('po')['rows'][0]['packed_out']]);
        $this->assertSame('868MI', $run('style')['rows'][0]['style']);
        $this->assertSame(['LBL20261106000101', 1], [$run('barcode')['rows'][0]['barcode'], $run('barcode')['rows'][0]['printed']]);
        $this->assertSame(['CTN-20261106-0001', 'S:6', 'SEALED'], [$run('carton')['rows'][0]['carton_no'], $run('carton')['rows'][0]['sizes'], $run('carton')['rows'][0]['status']]);
        $this->assertSame(['—', 6], [$run('assortment')['rows'][0]['ratio'], $run('assortment')['rows'][0]['assorted']]);
        $pending = $run('pending');
        $this->assertSame([[$a, 10, 6, 4], [$b, 8, 0, 8]], array_map(fn ($r) => [$r['bundle_no'], $r['qc_pass'], $r['packed'], $r['balance']], $pending['rows']));
        $this->assertSame(12.0, (float) $pending['totals']['balance']);
        $this->assertSame([6, 6], [$run('completed')['rows'][0]['sealed_qty'], $run('completed')['rows'][0]['final']]);
        $this->assertSame([], $run('pending', ['q' => 'H&M'])['rows']);
        $this->assertCount(2, $run('pending', ['q' => '1156'])['rows']);
        $this->assertSame([], $run('daily', ['from' => '2026-10-01', 'to' => '2026-10-31'])['rows']);
        $this->as()->getJson('/api/v1/assembly/packing/reports?kind=daily&from=2026-11-06&to=2026-11-01')->assertUnprocessable();
        $this->as()->getJson('/api/v1/assembly/packing/reports?kind=nope&from=2026-11-01&to=2026-11-06')->assertUnprocessable();

        // Who may: the line chief packs and runs processes but does not supervise; the store manager and the technician do not pack.
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/assembly/packing/ins', $this->loc($b))->assertCreated();
        $this->withToken($chief)->getJson('/api/v1/assembly/packing/dashboard')->assertOk();
        $this->withToken($this->token('STORE_MANAGER', 'store@delta.test'))->getJson('/api/v1/assembly/packing/options')->assertForbidden();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->postJson('/api/v1/assembly/packing/ins', $this->loc($b))->assertForbidden();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $scan = DB::table('asm_packing_ins')->where('bundle_id', DB::table('cut_bundles')->where('bundle_no', $b)->value('id'))->value('id');
        $this->withToken($manager)->deleteJson('/api/v1/assembly/packing/ins/'.$scan)->assertOk();
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

    private function previewIn(string $code): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/packing/in/preview?'.http_build_query(['code' => $code, 'line_id' => $this->fin1->id]));
    }

    private function packIn(string $no, ?int $qty = null, ?ProductionLine $line = null): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/packing/ins', $this->loc($no, $line) + ['qty' => $qty]);
    }

    private function step(string $id, string $step, string $action, array $payload = []): TestResponse
    {
        return $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/steps/{$step}/{$action}", $payload);
    }

    private function cartons(string $id, array $lines, array $more = []): TestResponse
    {
        return $this->as()->postJson("/api/v1/assembly/packing/batches/{$id}/cartons", ['lines' => array_map(fn ($s, $q) => ['size' => $s, 'qty' => $q], array_keys($lines), $lines)] + $more);
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
