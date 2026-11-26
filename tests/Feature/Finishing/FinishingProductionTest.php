<?php

declare(strict_types=1);

namespace Tests\Feature\Finishing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
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
 * Finishing Line → DF Production (AnnotechERP 18/02): a dyed batch received
 * onto the floor once, the machine-wise production sheet saved as a draft and
 * posted to move each batch to the stage its machine and process are mapped
 * to, the Process-Wise Delivery board, a batch returned for re-processing,
 * the amendment of a posted line — and who may do what.
 */
class FinishingProductionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    /** @var array<string, string> code => id */
    private array $process = [];

    private string $stenter;

    private string $compactor;

    private string $dryer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => Factory::query()->first()->id, 'name' => 'Finishing', 'code' => 'B1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FN-1']);

        $look = $this->as()->getJson('/api/v1/finishing/lookups')->assertOk()->json('data');
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id');
        $machine = fn (string $no, string $name, string $type) => $this->as()->postJson('/api/v1/erp/fin-machines', ['machine_no' => $no, 'name' => $name, 'machine_type' => $type, 'origin_country_id' => $country, 'brand_id' => $look['brands'][2]['value'], 'mou' => 'KG', 'status' => 'RUNNING', 'floor_id' => $this->floor->id])->assertCreated()->json('data.id');
        $this->stenter = $machine('ST-01', 'Stenter 1', 'STENTER');
        $this->compactor = $machine('CP-01', 'Compactor 1', 'COMPACTOR');
        $this->dryer = $machine('DR-01', 'Dryer 1', 'DRYER');
        $this->process = collect($this->as()->getJson('/api/v1/erp/fin-process-types')->json('data'))->pluck('id', 'code')->all();
        $map = fn (string $mc, string $code, string $dia, string $from, string $to) => $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $mc, 'process_id' => $this->process[$code], 'dia_type' => $dia, 'sequence' => 1, 'batch_status' => $from, 'next_status' => $to])->assertCreated();
        $map($this->stenter, 'DF-ST', 'OPEN', 'AWAITING_LOAD', 'STENTERING');
        $map($this->compactor, 'DF-CMP', 'BOTH', 'STENTERING', 'COMPACTING');
        $map($this->dryer, 'DF-DRY', 'TUBE', 'AWAITING_LOAD', 'DRYING');
    }

    public function test_a_dyed_batch_is_received_onto_the_floor_once(): void
    {
        $grey = $this->batch('B-26110001', dyedOn: null);
        $dyed = $this->batch('B-26110002', dyedOn: '2026-11-04');
        $r = ['receive_date' => '2026-11-05', 'batch_id' => $dyed, 'dia_type' => 'OPEN', 'challan_no' => ' dh-101 ', 'challan_qty_kg' => 100, 'challan_rolls' => 6, 'recv_qty_kg' => 98.5, 'recv_rolls' => 6, 'floor_id' => $this->floor->id];
        $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['batch_id' => $grey] + $r)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', 'Batch B-26110001 has not been through a dyeing machine yet.');
        $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-03'] + $r)->assertUnprocessable()->assertJsonPath('errors.receive_date.0', 'Batch B-26110002 was first dyed on 2026-11-04 — it cannot be received before that.');
        $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['recv_qty_kg' => 0] + $r)->assertUnprocessable()->assertJsonPath('errors.recv_qty_kg.0', 'Received Qty (Kg) must be more than nothing.');
        $this->assertSame(['B-26110002'], array_map(fn ($b) => explode(' · ', $b['label'])[0], $this->floorOptions()['receivable']));

        $id = $this->as()->postJson('/api/v1/erp/fin-batch-receipts', $r)->assertCreated()
            ->assertJsonPath('data.receipt_no', 'FBR-2026110001')->assertJsonPath('data.stage', 'AWAITING_LOAD')->assertJsonPath('data.status', 'ON_FLOOR')->assertJsonPath('data.booking_type', 'IN_HOUSE')
            ->assertJsonPath('data.challan_no', 'DH-101')->assertJsonPath('data.diff_kg', -1.5)->assertJsonPath('data.batch_no', 'B-26110002')->assertJsonPath('data.floor', 'Finishing')->json('data.id');
        $this->as()->postJson('/api/v1/erp/fin-batch-receipts', $r)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', 'Batch B-26110002 is on the floor already, under FBR-2026110001.');
        $this->assertSame([], $this->floorOptions()['receivable']);
        // A sample batch books as Sample unless told otherwise.
        $sample = $this->batch('S-26110001', dyedOn: '2026-11-04', scope: 'SAMPLE');
        $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['batch_id' => $sample, 'dia_type' => 'TUBE'] + $r)->assertCreated()->assertJsonPath('data.booking_type', 'SAMPLE')->assertJsonPath('data.receipt_no', 'FBR-2026110002');
        // Nothing done on it yet: it may be edited and removed.
        $this->as()->patchJson("/api/v1/erp/fin-batch-receipts/{$id}", ['recv_qty_kg' => 99, 'stage' => 'DELIVERED'])->assertOk()->assertJsonPath('data.recv_qty_kg', 99)->assertJsonPath('data.stage', 'AWAITING_LOAD');
        $this->as()->deleteJson("/api/v1/erp/fin-batch-receipts/{$id}")->assertNoContent();
        $this->assertSame(1, count($this->floorOptions()['receivable']));
    }

    public function test_a_sheet_is_saved_as_a_draft_and_posting_moves_each_batch(): void
    {
        $open = $this->receive($this->batch('B-26110011', dyedOn: '2026-11-04'), 'OPEN');
        $tube = $this->receive($this->batch('B-26110012', dyedOn: '2026-11-04'), 'TUBE');
        $row = fn (array $over = []) => $over + ['receipt_id' => $open, 'machine_id' => $this->stenter, 'process_id' => $this->process['DF-ST'], 'input_kg' => 100, 'output_kg' => 98, 'rolls' => 6, 'fin_gsm' => 180, 'fin_dia' => 72, 'load_time' => '2026-11-06T08:00', 'unload_time' => '2026-11-06T09:30'];
        $s = ['line' => 'OPEN', 'production_date' => '2026-11-06', 'shift' => 'DAY', 'floor_id' => $this->floor->id];

        $this->as()->postJson('/api/v1/finishing/production-sheets', $s + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A sheet with no lines posts nothing — add at least one.');
        $bad = $this->as()->postJson('/api/v1/finishing/production-sheets', $s + ['lines' => [
            $row(['receipt_id' => $tube]),
            $row(['machine_id' => $this->dryer, 'process_id' => $this->process['DF-DRY']]),
            $row(['output_kg' => 101]),
            $row(['unload_time' => '2026-11-06T07:00']),
        ]])->assertUnprocessable();
        $this->assertSame('Row 1: batch B-26110012 is received as Tube — it does not go through the Open Line.', $bad->json('errors')['lines.0.receipt_id'][0]);
        $this->assertSame('Row 2: that machine is not mapped to that process on the Open Line.', $bad->json('errors')['lines.1.process_id'][0]);
        $this->assertSame('Row 3: output cannot be more than the input.', $bad->json('errors')['lines.2.output_kg'][0]);
        $this->assertSame('Row 4: the unload time cannot be before the load time.', $bad->json('errors')['lines.3.unload_time'][0]);
        $this->as()->postJson('/api/v1/finishing/production-sheets', ['production_date' => '2026-11-04'] + $s + ['lines' => [$row()]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => $e['lines.0.receipt_id'][0] === 'Row 1: batch B-26110011 was received on 2026-11-05, after this sheet\'s date.');

        // Compacting is listed first but came off its machine last: posting follows the unload times.
        $lines = [
            $row(['machine_id' => $this->compactor, 'process_id' => $this->process['DF-CMP'], 'input_kg' => 98, 'output_kg' => 95, 'load_time' => '2026-11-06T10:00', 'unload_time' => '2026-11-06T11:00']),
            $row(),
        ];
        $no = $this->as()->postJson('/api/v1/finishing/production-sheets', $s + ['lines' => $lines])->assertCreated()
            ->assertJsonPath('data.sheet_no', 'DFP-2026110001')->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.lines.0.load_time', '2026-11-06T10:00')
            ->assertJsonPath('data.lines.1.machine', 'ST-01 — Stenter 1')->json('data.sheet_no');
        $this->as()->putJson("/api/v1/finishing/production-sheets/{$no}", ['line' => 'TUBE'] + $s + ['lines' => $lines])->assertUnprocessable()->assertJsonValidationErrors('line');
        $this->as()->putJson("/api/v1/finishing/production-sheets/{$no}", ['shift' => 'NIGHT', 'lines' => $lines] + array_diff_key($s, ['line' => 1]))->assertOk()->assertJsonPath('data.shift', 'NIGHT')->assertJsonCount(2, 'data.lines');
        $reg = $this->as()->getJson('/api/v1/finishing/production-sheets')->assertOk()->json('data.0');
        $this->assertEquals([1, 198, 193, 5, 2.53], [$reg['batches'], $reg['input_kg'], $reg['output_kg'], $reg['loss_kg'], $reg['loss_pct']]);

        $this->as()->postJson("/api/v1/finishing/production-sheets/{$no}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');
        $card = collect($this->as()->getJson('/api/v1/erp/fin-batch-receipts')->json('data'))->firstWhere('id', $open);
        $this->assertSame('COMPACTING', $card['stage']);
        $this->assertSame(['RECEIPT:AWAITING_LOAD', 'SHEET:STENTERING', 'SHEET:COMPACTING'], DB::table('fin_process_deliveries')->where('receipt_id', $open)->orderBy('delivered_at')->get()->map(fn ($d) => "{$d->source}:{$d->to_stage}")->all());
        // Posted: it reads only, and its batch's receipt stays.
        $this->as()->putJson("/api/v1/finishing/production-sheets/{$no}", array_diff_key($s, ['line' => 1]) + ['lines' => $lines])->assertUnprocessable()->assertJsonPath('errors.sheet_no.0', 'DFP-2026110001 is posted and reads only — correct it through the Amendment.');
        $this->as()->deleteJson("/api/v1/finishing/production-sheets/{$no}")->assertUnprocessable();
        $this->as()->postJson("/api/v1/finishing/production-sheets/{$no}/post")->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/erp/fin-batch-receipts/{$open}")->assertUnprocessable()->assertJsonPath('errors.receipt_no.0', 'Production has been entered against FBR-2026110001, so it stays.');
        $this->as()->patchJson("/api/v1/erp/fin-batch-receipts/{$open}", ['batch_id' => $this->batch('B-26110013', dyedOn: '2026-11-04')])->assertUnprocessable()->assertJsonValidationErrors('batch_id');
        // A draft is removed whole.
        $draft = $this->as()->postJson('/api/v1/finishing/production-sheets', ['line' => 'TUBE'] + $s + ['lines' => [$row(['receipt_id' => $tube, 'machine_id' => $this->dryer, 'process_id' => $this->process['DF-DRY']])]])->assertCreated()->json('data.sheet_no');
        $this->as()->deleteJson("/api/v1/finishing/production-sheets/{$draft}")->assertOk();
        $this->assertSame(1, DB::table('fin_production_sheets')->count());
    }

    public function test_the_floor_board_and_passing_a_batch_on(): void
    {
        $open = $this->receive($this->batch('B-26110021', dyedOn: '2026-11-04', colour: 'Navy'), 'OPEN');
        $this->receive($this->batch('B-26110022', dyedOn: '2026-11-04', colour: 'Red'), 'TUBE');
        $sheet = $this->as()->postJson('/api/v1/finishing/production-sheets', ['line' => 'OPEN', 'production_date' => '2026-11-06', 'shift' => 'DAY', 'lines' => [
            ['receipt_id' => $open, 'machine_id' => $this->stenter, 'process_id' => $this->process['DF-ST'], 'input_kg' => 100, 'output_kg' => 99, 'load_time' => '2026-11-06T08:00', 'unload_time' => '2026-11-06T09:00'],
        ]])->assertCreated()->json('data.sheet_no');
        $this->as()->postJson("/api/v1/finishing/production-sheets/{$sheet}/post")->assertOk();

        $board = $this->as()->getJson('/api/v1/finishing/delivery-board')->assertOk()->json('data');
        $this->assertSame(['on_floor' => 2, 'on_hold' => 0, 'ready' => 0], $board['tiles']);
        $row = collect($board['rows'])->firstWhere('batch_no', 'B-26110021');
        $this->assertSame(['STENTERING', 'Stentering (ST)', 'ST-01 — Stenter 1'], [$row['stage'], $row['last_process'], $row['last_machine']]);
        $this->assertGreaterThan(0, $row['spent_minutes']);
        $this->assertSame(['Navy', 'Red'], $board['choices']['colours']);
        $this->as()->getJson('/api/v1/finishing/delivery-board?dia=TUBE')->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.batch_no', 'B-26110022');
        $this->as()->getJson('/api/v1/finishing/delivery-board?batch=0021&colour=nav')->assertJsonCount(1, 'data.rows');

        $no = $row['receipt_no'];
        $this->as()->postJson("/api/v1/finishing/delivery-board/{$no}/deliver", ['to_stage' => 'DELIVERED'])->assertUnprocessable()->assertJsonPath('errors.to_stage.0', 'Delivered and At Sub-Contract are set by their own desks, not here.');
        $this->as()->postJson("/api/v1/finishing/delivery-board/{$no}/deliver", ['to_stage' => 'STENTERING'])->assertUnprocessable()->assertJsonPath('errors.to_stage.0', 'The batch is at that stage already.');
        $this->as()->postJson("/api/v1/finishing/delivery-board/{$no}/deliver", ['to_stage' => 'READY_FOR_DELIVERY', 'remarks' => 'QC passed'])->assertOk()->assertJsonPath('data.stage', 'READY_FOR_DELIVERY');
        $this->as()->getJson('/api/v1/finishing/delivery-board')->assertJsonPath('data.tiles', ['on_floor' => 1, 'on_hold' => 0, 'ready' => 1]);
        $this->as()->getJson('/api/v1/finishing/delivery-board?stage=READY_FOR_DELIVERY')->assertJsonCount(1, 'data.rows');
        $this->assertSame('STENTERING', DB::table('fin_process_deliveries')->where('source', 'BOARD')->value('from_stage'));

        $pdf = $this->as()->get('/api/v1/finishing/delivery-board/export/pdf')->assertOk();
        $this->assertStringStartsWith('application/pdf', $pdf->headers->get('Content-Type'));
        $this->as()->get('/api/v1/finishing/delivery-board/export/xlsx')->assertOk();
    }

    public function test_a_batch_returned_for_reprocessing_leaves_the_floor_and_comes_back(): void
    {
        $batch = $this->batch('B-26110031', dyedOn: '2026-11-04');
        $card = $this->receive($batch, 'OPEN');
        $r = ['return_date' => '2026-11-06', 'receipt_id' => $card, 'reason' => 'SHADE', 'qty_kg' => 100];
        $this->as()->postJson('/api/v1/erp/fin-reprocess-returns', ['qty_kg' => 120] + $r)->assertUnprocessable()->assertJsonPath('errors.qty_kg.0', 'No more than the 100 kg received can be returned.');
        $this->as()->postJson('/api/v1/erp/fin-reprocess-returns', ['return_date' => '2026-11-04'] + $r)->assertUnprocessable()->assertJsonPath('errors.return_date.0', 'A return cannot be dated before its receipt FBR-2026110001.');
        $this->as()->postJson('/api/v1/erp/fin-reprocess-returns', ['reason' => 'OTHER'] + $r)->assertUnprocessable()->assertJsonPath('errors.remarks.0', 'Say what the other reason is in Remarks.');

        // Returned and taken back before anything else happens: the card is on the floor again.
        $first = $this->as()->postJson('/api/v1/erp/fin-reprocess-returns', $r)->assertCreated()->assertJsonPath('data.return_no', 'FRP-2026110001')->assertJsonPath('data.batch_no', 'B-26110031')->json('data.id');
        $this->as()->deleteJson("/api/v1/erp/fin-reprocess-returns/{$first}")->assertNoContent();
        $this->assertSame('ON_FLOOR', DB::table('fin_batch_receipts')->where('id', $card)->value('status'));

        // The number given up by the undone return is given again.
        $ret = $this->as()->postJson('/api/v1/erp/fin-reprocess-returns', ['reason' => 'GSM'] + $r)->assertCreated()->assertJsonPath('data.return_no', 'FRP-2026110001')->json('data.id');
        $this->assertSame('RETURNED', DB::table('fin_batch_receipts')->where('id', $card)->value('status'));
        $this->as()->postJson('/api/v1/erp/fin-reprocess-returns', $r)->assertUnprocessable()->assertJsonPath('errors.receipt_id.0', 'FBR-2026110001 is not on the floor.');
        $this->as()->patchJson("/api/v1/erp/fin-batch-receipts/{$card}", ['remarks' => 'x'])->assertUnprocessable()->assertJsonPath('errors.receipt_no.0', 'FBR-2026110001 was returned for re-processing and reads only.');
        $this->as()->getJson('/api/v1/finishing/delivery-board')->assertJsonCount(0, 'data.rows');
        $this->as()->postJson('/api/v1/finishing/production-sheets', ['line' => 'OPEN', 'production_date' => '2026-11-06', 'shift' => 'DAY', 'lines' => [
            ['receipt_id' => $card, 'machine_id' => $this->stenter, 'process_id' => $this->process['DF-ST'], 'input_kg' => 100, 'output_kg' => 99],
        ]])->assertUnprocessable()->assertJsonPath('errors', fn ($e) => $e['lines.0.receipt_id'][0] === 'Row 1: batch B-26110031 is not on the floor.');
        $this->as()->patchJson("/api/v1/erp/fin-reprocess-returns/{$ret}", ['receipt_id' => $this->receive($this->batch('B-26110032', dyedOn: '2026-11-04'), 'OPEN')])->assertUnprocessable()->assertJsonValidationErrors('receipt_id');

        // Re-dyed, it is received again — and the return then stays.
        $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-08', 'batch_id' => $batch, 'dia_type' => 'OPEN', 'recv_qty_kg' => 97])->assertCreated()->assertJsonPath('data.receipt_no', 'FBR-2026110003');
        $this->as()->deleteJson("/api/v1/erp/fin-reprocess-returns/{$ret}")->assertUnprocessable()->assertJsonPath('errors.return_no.0', 'The batch of FRP-2026110001 has been received again (FBR-2026110003), so the return stays.');
    }

    public function test_a_posted_line_is_amended_with_its_old_figures_kept(): void
    {
        $card = $this->receive($this->batch('B-26110041', dyedOn: '2026-11-04'), 'OPEN');
        $sheet = $this->as()->postJson('/api/v1/finishing/production-sheets', ['line' => 'OPEN', 'production_date' => '2026-11-06', 'shift' => 'DAY', 'lines' => [
            ['receipt_id' => $card, 'machine_id' => $this->stenter, 'process_id' => $this->process['DF-ST'], 'input_kg' => 100, 'output_kg' => 98],
        ]])->assertCreated()->json('data');
        $line = $sheet['lines'][0]['id'];
        $a = ['amend_date' => '2026-11-07', 'line_id' => $line, 'new_input_kg' => 100, 'new_output_kg' => 96.5, 'reason' => 'Scale re-read'];
        $this->as()->postJson('/api/v1/erp/fin-production-amendments', $a)->assertUnprocessable()->assertJsonPath('errors.line_id.0', 'DFP-2026110001 is still a draft — edit it on the production entry instead.');
        $this->assertSame([], $this->floorOptions()['posted_lines']);
        $this->as()->postJson("/api/v1/finishing/production-sheets/{$sheet['sheet_no']}/post")->assertOk();
        $this->assertCount(1, $this->floorOptions()['posted_lines']);

        $this->as()->postJson('/api/v1/erp/fin-production-amendments', ['new_output_kg' => 98] + $a)->assertUnprocessable()->assertJsonPath('errors.new_input_kg.0', 'Nothing has changed — the line already reads so.');
        $this->as()->postJson('/api/v1/erp/fin-production-amendments', ['new_output_kg' => 101] + $a)->assertUnprocessable()->assertJsonPath('errors.new_output_kg.0', 'Output (Kg) cannot be more than the input.');
        $this->as()->postJson('/api/v1/erp/fin-production-amendments', ['new_process_id' => $this->process['DF-DRY']] + $a)->assertUnprocessable()->assertJsonPath('errors.new_process_id.0', 'That machine is not mapped to that process on the Open Line.');
        $this->as()->postJson('/api/v1/erp/fin-production-amendments', ['amend_date' => '2026-11-05'] + $a)->assertUnprocessable()->assertJsonValidationErrors('amend_date');
        $id = $this->as()->postJson('/api/v1/erp/fin-production-amendments', ['new_machine_id' => $this->compactor, 'new_process_id' => $this->process['DF-CMP']] + $a)->assertCreated()
            ->assertJsonPath('data.amend_no', 'FPA-2026110001')->assertJsonPath('data.old_output_kg', 98)->assertJsonPath('data.new_output_kg', 96.5)
            ->assertJsonPath('data.old_machine', 'ST-01 — Stenter 1')->assertJsonPath('data.new_machine', 'CP-01 — Compactor 1')->assertJsonPath('data.new_process', 'Open Compacting (OC)')
            ->assertJsonPath('data.sheet_no', 'DFP-2026110001')->assertJsonPath('data.batch_no', 'B-26110041')->json('data.id');
        $this->as()->getJson("/api/v1/finishing/production-sheets/{$sheet['sheet_no']}")->assertJsonPath('data.lines.0.output_kg', 96.5)->assertJsonPath('data.lines.0.machine', 'CP-01 — Compactor 1');
        $this->as()->getJson('/api/v1/finishing/production-sheets')->assertJsonPath('data.0.loss_kg', 3.5);
        // An amendment stands as made.
        $this->as()->patchJson("/api/v1/erp/fin-production-amendments/{$id}", $a + ['new_output_kg' => 97])->assertUnprocessable()->assertJsonPath('errors.amend_no.0', 'FPA-2026110001 stands as made — a further correction is a further amendment.');
        $this->as()->deleteJson("/api/v1/erp/fin-production-amendments/{$id}")->assertStatus(405);
    }

    public function test_who_may_run_the_floor(): void
    {
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/erp/fin-batch-receipts')->assertOk();
        $this->withToken($store)->getJson('/api/v1/finishing/delivery-board')->assertOk();
        $this->withToken($store)->postJson('/api/v1/finishing/production-sheets', ['line' => 'OPEN'])->assertForbidden();
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-05', 'batch_id' => $this->batch('B-26110051', dyedOn: '2026-11-04'), 'dia_type' => 'OPEN', 'recv_qty_kg' => 50])->assertCreated();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/finishing/production-sheets')->assertForbidden();
    }

    /** A dye batch card, dyed on the day given (or never). */
    private function batch(string $no, ?string $dyedOn, string $scope = 'BULK', ?string $colour = null): string
    {
        $id = (string) Str::ulid();
        $cid = null;
        if ($colour) {
            DB::table('colours')->insert(['id' => $cid = (string) Str::ulid(), 'company_id' => $this->delta->id, 'name' => $colour, 'colour_type' => 'SOLID', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('dye_batches')->insert(['id' => $id, 'company_id' => $this->delta->id, 'batch_no' => $no, 'batch_date' => '2026-11-01', 'scope' => $scope, 'source' => 'MANUAL', 'colour_id' => $cid, 'plan_qty' => 100, 'card_qty' => 100, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        if ($dyedOn) {
            DB::table('dye_productions')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'batch_id' => $id, 'production_date' => $dyedOn, 'liquor_ratio' => '1:8', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function receive(string $batch, string $dia): string
    {
        return $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-05', 'batch_id' => $batch, 'dia_type' => $dia, 'recv_qty_kg' => 100, 'recv_rolls' => 6])->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function floorOptions(): array
    {
        return $this->as()->getJson('/api/v1/finishing/production/options')->assertOk()->json('data');
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
