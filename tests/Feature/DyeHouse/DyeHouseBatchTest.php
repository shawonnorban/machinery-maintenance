<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\FabricPlanning\Models\FpBatchPlan;
use App\Modules\FabricPlanning\Models\FpBatchPlanLine;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitGreyTransfer;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Batch (Bulk) (AnnotechERP 17/03): batch cards cut from a
 * released Fabric Batch Plan and laid out on the board; cards by hand, off
 * finished fabric, by a split and by a colour replacement; the grey indent
 * and the batch store's issue off the grey ledger; grey to left-over at
 * batch end; the sub-contract pre-treatment papers; the transfers; and who
 * may do what.
 */
class DyeHouseBatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $leftover;

    private string $buyer;

    private string $order;

    private string $ym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Batch Store', 'code' => 'ST-BAT', 'active' => true])->id;
        $this->leftover = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Left-over Store', 'code' => 'ST-LO', 'active' => true])->id;
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->ym = now('Asia/Dhaka')->format('ym');
    }

    public function test_cards_from_a_released_plan_laid_out_on_the_board(): void
    {
        [$m1, $m2, $sample] = $this->machines();
        $fp = FpBatchPlan::query()->create(['bp_no' => 'BP-00001', 'week_start' => '2026-11-07', 'buyer_id' => $this->buyer, 'order_no' => 'LPP-2026-000008', 'style_job' => '529',
            'dyeing_method' => 'EXHAUST', 'batches' => 2, 'bp_qty' => 875, 'status' => 'PLANNED']);
        FpBatchPlanLine::query()->create(['batch_plan_id' => $fp->id, 'fabric' => 'Single Jersey 160 GSM', 'bp_qty' => 600, 'sort_order' => 10]);
        FpBatchPlanLine::query()->create(['batch_plan_id' => $fp->id, 'fabric' => '1x1 Rib 220 GSM', 'bp_qty' => 275, 'sort_order' => 20]);

        $plan = ['name' => 'November 2026 - Bulk Loading', 'plan_date' => '2026-11-03', 'scope' => 'BULK', 'scale_hours' => 4, 'cell_width' => 70];
        $this->as()->postJson('/api/v1/erp/dye-batch-plans', $plan)->assertUnprocessable()->assertJsonPath('errors.cell_width.0', 'Cell Width(%) must be between 4 and 60.');
        $this->as()->postJson('/api/v1/erp/dye-batch-plans', ['code' => 'BP 1'] + $plan)->assertUnprocessable()->assertJsonValidationErrors('code');
        // Left blank, the code is issued.
        $code = $this->as()->postJson('/api/v1/erp/dye-batch-plans', ['cell_width' => 20] + $plan)->assertCreated()->json('data.code');
        $this->assertSame('PLN-26110001', $code);

        // Only a released plan is cut, and only once; each card takes an even share of every line.
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/pull", ['fp_batch_plan_id' => $fp->id])->assertUnprocessable()->assertJsonPath('errors.fp_batch_plan_id.0', 'BP-00001 is not released yet — only a released Fabric Batch Plan is cut into cards.');
        $fp->update(['status' => 'RELEASED']);
        $this->as()->getJson('/api/v1/dyehouse/batch/options')->assertOk()->assertJsonCount(1, 'data.fp_plans');
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/pull", ['fp_batch_plan_id' => $fp->id])->assertOk()->assertJsonPath('data.pulled', ["B-{$this->ym}0001", "B-{$this->ym}0002"]);
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/pull", ['fp_batch_plan_id' => $fp->id])->assertUnprocessable()->assertJsonPath('errors.fp_batch_plan_id.0', 'BP-00001 has been cut into cards already.');
        $first = DyeBatch::query()->where('batch_no', "B-{$this->ym}0001")->firstOrFail();
        $this->assertSame([437.5, 'LPP-2026-000008', 'PLAN'], [(float) $first->card_qty, $first->order_no, $first->source]);
        $this->assertEquals([300.0, 137.5], DyeBatchItem::query()->where('batch_id', $first->id)->orderBy('sort_order')->pluck('qty_kg')->map(fn ($q) => (float) $q)->all());

        $this->as()->getJson("/api/v1/dyehouse/batch-plans/{$code}/board")->assertOk()->assertJsonCount(2, 'data.machines')->assertJsonCount(2, 'data.waiting')->assertJsonCount(0, 'data.bars')
            ->assertJsonPath('data.window.from', '2026-11-03T00:00')->assertJsonPath('data.window.columns', 5)->assertJsonPath('data.window.to', '2026-11-03T20:00');

        // One batch at a time on a machine, never heavier than it takes, and only the plan's kind of machine.
        $bar = fn (string $no, string $mc, string $start) => ['batch_no' => $no, 'machine_id' => $mc, 'start' => $start, 'hours' => 4];
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/bars", $bar("B-{$this->ym}0001", $m2, '2026-11-03T04:00'))->assertUnprocessable()
            ->assertJsonPath('errors.machine_id.0', "B-{$this->ym}0001 weighs 437.5 kg — more than DM-02 takes (226.8 kg).");
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/bars", $bar("B-{$this->ym}0001", $sample, '2026-11-03T04:00'))->assertUnprocessable()->assertJsonPath('errors.machine_id.0', "SM-01 is not a machine of this plan's S/B.");
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/bars", $bar("B-{$this->ym}0001", $m1, '2026-11-03T04:00'))->assertOk()->assertJsonPath('data.end', '2026-11-03T08:00');
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/bars", $bar("B-{$this->ym}0002", $m1, '2026-11-03T06:00'))->assertUnprocessable()->assertJsonPath('errors.start.0', "DM-01 already runs B-{$this->ym}0001 in those hours.");
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/bars", $bar("B-{$this->ym}0002", $m1, '2026-11-03T08:00'))->assertOk();
        $this->as()->getJson("/api/v1/dyehouse/batch-plans/{$code}/board")->assertJsonCount(2, 'data.bars')->assertJsonCount(0, 'data.waiting')->assertJsonPath('data.bars.0.start', '2026-11-03T04:00');
        // A window that has moved on shows neither bar, and the cards are still not waiting: they have their hours.
        $this->as()->getJson("/api/v1/dyehouse/batch-plans/{$code}/board?from=2026-11-04T00:00")->assertJsonCount(0, 'data.bars')->assertJsonCount(0, 'data.waiting');

        $this->as()->deleteJson("/api/v1/dyehouse/batch-plans/{$code}/bars/B-{$this->ym}0002")->assertOk();
        $this->as()->getJson("/api/v1/dyehouse/batch-plans/{$code}/board")->assertJsonCount(1, 'data.bars')->assertJsonPath('data.waiting.0.batch_no', "B-{$this->ym}0002");
        $this->as()->putJson("/api/v1/dyehouse/batch-plans/{$code}/view", ['scale' => 2, 'width' => 10, 'from' => '2026-11-03T06:00'])->assertOk();
        $this->as()->getJson("/api/v1/dyehouse/batch-plans/{$code}/board")->assertJsonPath('data.window.columns', 10)->assertJsonPath('data.window.to', '2026-11-04T02:00');

        $plans = collect($this->as()->getJson('/api/v1/erp/dye-batch-plans')->json('data'));
        $this->assertEquals([2, 875, 1], [$plans[0]['batches'], $plans[0]['qty'], $plans[0]['laid_out']]);
        $this->as()->deleteJson('/api/v1/erp/dye-batch-plans/'.$plans[0]['id'])->assertUnprocessable();
    }

    public function test_cards_by_hand_off_finished_fabric_split_and_colour_replaced(): void
    {
        [$m1] = $this->machines();
        $red = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'RED', 'colour_type' => 'SOLID', 'name' => 'Cherry Red', 'active' => true])->id;
        $navy = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'NVY', 'colour_type' => 'SOLID', 'name' => 'Navy', 'active' => true])->id;

        $manual = ['manual_ref_no' => 'MB-0001', 'manual_ref_date' => '2026-11-02', 'order_id' => $this->order, 'colour_id' => $red, 'batch_no' => ' b-26080099 ', 'lot_no' => 'L-1', 'batch_date' => '2026-11-03', 'card_qty' => 0];
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', $manual)->assertUnprocessable()->assertJsonPath('errors.card_qty.0', 'Batch Qty must be more than nothing.');
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['card_qty' => 300] + $manual)->assertCreated()
            ->assertJsonPath('data.batch_no', 'B-26080099')->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.style', '529')->assertJsonPath('data.order_no', 'LPP-2026-000008');
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'MB-0002', 'batch_no' => 'B-26080099', 'card_qty' => 10] + $manual)->assertUnprocessable()
            ->assertJsonPath('errors.batch_no.0', 'Batch B-26080099 is already on file.');

        // Off finished fabric: the number is issued and the requisition is on the store named.
        $this->as()->postJson('/api/v1/erp/dye-finished-batches', ['card_qty' => 50, 'store_id' => $this->store])->assertUnprocessable()->assertJsonPath('errors.colour_id.0', 'Color is required.');
        $this->as()->postJson('/api/v1/erp/dye-finished-batches', ['colour_id' => $red, 'card_qty' => 50, 'store_id' => $this->store, 'dye_machine_id' => $m1])->assertCreated()
            ->assertJsonPath('data.batch_no', "B-{$this->ym}0001")->assertJsonPath('data.requisitions', "GFR-{$this->ym}0001")->assertJsonPath('data.machine', 'DM-01');

        // A split leaves something on the original, moves no more than it carries, and writes the new card in step.
        $card = $this->as()->getJson('/api/v1/dyehouse/batches/B-26080099')->assertOk()->json('data');
        $item = $card['items'][0]['id'];
        $split = ['plan_qty' => 100, 'card_qty' => 100, 'lot_no' => 'L-2', 'machine_id' => $m1, 'lines' => [['item_id' => $item, 'qty_kg' => 100, 'rolls' => 0]]];
        $this->as()->postJson('/api/v1/dyehouse/batches/B-26080099/split', ['lines' => []] + $split)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A split with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/dyehouse/batches/B-26080099/split', ['card_qty' => 300] + $split)->assertUnprocessable()->assertJsonValidationErrors('card_qty');
        $this->as()->postJson('/api/v1/dyehouse/batches/B-26080099/split', ['lines' => [['item_id' => $item, 'qty_kg' => 400]]] + $split)->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty_kg');
        $done = $this->as()->postJson('/api/v1/dyehouse/batches/B-26080099/split', $split)->assertCreated()->json('data');
        $this->assertEquals(["B-{$this->ym}0002", 100, 'L-2', 'DM-01', 100], [$done['new']['batch_no'], $done['new']['card_qty'], $done['new']['lot_no'], $done['new']['machine'], $done['new']['items'][0]['qty_kg']]);
        $this->assertEquals([200, 200, 200], [$done['old']['card_qty'], $done['old']['plan_qty'], $done['old']['items'][0]['qty_kg']]);

        // A colour replacement: a new card in the new shade, no heavier than the old, which is replaced.
        $old = DyeBatch::query()->where('batch_no', 'B-26080099')->value('id');
        $transfer = ['batch_id' => $old, 'transfer_date' => '2026-11-04', 'new_batch_no' => 'B-26110500', 'batch_date' => '2026-11-04', 'batch_qty' => 250, 'colour_id' => $navy];
        $this->as()->postJson('/api/v1/erp/dye-colour-transfers', ['batch_qty' => 0] + $transfer)->assertUnprocessable()->assertJsonPath('errors.batch_qty.0', 'Batch Qty must be more than nothing.');
        $this->as()->postJson('/api/v1/erp/dye-colour-transfers', $transfer)->assertUnprocessable()->assertJsonPath('errors.batch_qty.0', 'Batch Qty cannot be more than B-26080099 carries (200 kg).');
        $this->as()->postJson('/api/v1/erp/dye-colour-transfers', ['batch_qty' => 200] + $transfer)->assertCreated()->assertJsonPath('data.transfer_no', 'CTR-26110001')->assertJsonPath('data.batch_no', 'B-26080099');
        $new = DyeBatch::query()->where('batch_no', 'B-26110500')->firstOrFail();
        $this->assertSame(['COLOUR', $navy, 200.0, 'REPLACED'], [$new->source, $new->colour_id, (float) DyeBatchItem::query()->where('batch_id', $new->id)->sum('qty_kg'), DyeBatch::query()->whereKey($old)->value('status')]);
        $this->as()->postJson('/api/v1/erp/dye-colour-transfers', ['new_batch_no' => 'B-26110501'] + $transfer)->assertUnprocessable()->assertJsonValidationErrors('batch_id');
    }

    public function test_indent_issue_and_grey_to_leftover(): void
    {
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'MB-0001', 'manual_ref_date' => '2026-11-02', 'order_id' => $this->order, 'batch_no' => 'B-26110001', 'batch_date' => '2026-11-03', 'card_qty' => 120])->assertCreated();
        $batch = DyeBatch::query()->where('batch_no', 'B-26110001')->value('id');
        $group = $this->as()->getJson('/api/v1/dyehouse/batch/options')->json('data.rates.0.value');
        // Grey on the batch in the batch store, on two lines.
        $roll = GreyStore::lotFor(['store_id' => $this->store, 'order_id' => $this->order, 'buyer_id' => $this->buyer, 'fabric' => 'Single Jersey', 'roll_no' => 'R-1', 'batch_no' => 'B-26110001'], '2026-11-01');
        $loose = GreyStore::lotFor(['store_id' => $this->store, 'order_id' => $this->order, 'buyer_id' => $this->buyer, 'fabric' => '1x1 Rib', 'batch_no' => 'B-26110001'], '2026-11-01');
        GreyStore::post('OPENING', 'OB-1', '2026-11-01', [['lot_id' => $roll->id, 'qty' => 60], ['lot_id' => $loose->id, 'qty' => 80]]);

        $indent = ['batch_id' => $batch, 'colour_group_id' => $group, 'proj_rate' => 52, 'store_id' => $this->store];
        $this->as()->postJson('/api/v1/erp/dye-grey-indents', ['batch_id' => null] + $indent)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', 'Batch# is required.');
        $this->as()->postJson('/api/v1/erp/dye-grey-indents', $indent)->assertCreated()->assertJsonPath('data.req_no', "GFR-{$this->ym}0001")->assertJsonPath('data.req_kg', 120)->assertJsonPath('data.items', 1)
            ->assertJsonPath('data.batch_no', 'B-26110001');
        $this->as()->postJson('/api/v1/erp/dye-grey-indents', $indent)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', "B-26110001 is already called off on GFR-{$this->ym}0001.");

        $no = "GFR-{$this->ym}0001";
        $this->as()->getJson("/api/v1/dyehouse/grey-indents/{$no}/issue")->assertOk()->assertJsonCount(2, 'data.lots');
        $this->as()->postJson("/api/v1/dyehouse/grey-indents/{$no}/issue", ['issue_date' => '2026-11-03', 'lines' => [['lot_id' => $loose->id, 'qty' => 90]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'More than the line holds (80 kg).'));
        $this->as()->postJson("/api/v1/dyehouse/grey-indents/{$no}/issue", ['issue_date' => '2026-11-03', 'lines' => [['lot_id' => $loose->id, 'qty' => 30]]])->assertCreated()
            ->assertJsonPath('data.issue_kg', 30)->assertJsonCount(1, 'data.issues');
        $this->assertSame(50.0, GreyStore::balance($loose->id));
        $this->as()->postJson("/api/v1/dyehouse/grey-indents/{$no}/issue", ['issue_date' => '2026-11-03', 'lines' => [['lot_id' => $loose->id, 'qty' => 50], ['lot_id' => $roll->id, 'qty' => 60]]])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', "More than {$no} asks — 90 kg is left to issue.");
        $this->assertSame(30.0, (float) collect($this->as()->getJson('/api/v1/erp/dye-grey-indents')->json('data'))->first()['issue_kg']);

        // Batch end: what is left on the batch goes to left-over, as Knit Production's own movement.
        $this->as()->getJson('/api/v1/dyehouse/batch-leftover')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.batch_status', 'OPEN');
        $this->as()->postJson('/api/v1/dyehouse/batch-leftover', ['store_id' => $this->store, 'transfer_date' => '2026-11-05', 'lines' => [['lot_id' => $loose->id, 'qty' => 20]]])->assertUnprocessable()
            ->assertJsonPath('errors.store_id.0', 'The left-over store is the one the fabric is in already.');
        $made = $this->as()->postJson('/api/v1/dyehouse/batch-leftover', ['store_id' => $this->leftover, 'transfer_date' => '2026-11-05', 'lines' => [['lot_id' => $loose->id, 'qty' => 20], ['lot_id' => $roll->id, 'qty' => 60]]])
            ->assertCreated()->json('data.transfers');
        $t = KnitGreyTransfer::query()->where('transfer_no', $made[0])->firstOrFail();
        $this->assertSame(['BATCH_TO_LEFTOVER', 'RECEIVED', $this->leftover], [$t->kind, $t->status, $t->to_store_id]);
        // Each line gave what was asked, and both landed off the batch.
        $this->assertSame([30.0, 0.0], [GreyStore::balance($loose->id), GreyStore::balance($roll->id)]);
        $landed = collect(GreyStore::lots($this->leftover));
        $this->assertSame([80.0, [null]], [round($landed->sum('balance'), 3), $landed->pluck('batch_no')->unique()->values()->all()]);
    }

    public function test_sub_contract_pre_treatment_papers(): void
    {
        $arafah = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Arafah Heat Set', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $other = MaterialSupplier::query()->create(['code' => 'SUP-2', 'name' => 'Rupa Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        $this->as()->postJson('/api/v1/erp/dye-pt-programmes', ['supplier_id' => $arafah])->assertUnprocessable()->assertJsonPath('errors.issue_date.0', 'Issue Date is required.');
        $this->as()->postJson('/api/v1/erp/dye-pt-programmes', ['supplier_id' => $arafah, 'issue_date' => '2026-11-03', 'exp_delivery_date' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('exp_delivery_date');
        $p1 = $this->as()->postJson('/api/v1/erp/dye-pt-programmes', ['supplier_id' => $arafah, 'issue_date' => '2026-11-03', 'gate_pass_no' => 'GP-0001'])->assertCreated()->assertJsonPath('data.program_no', 'DSC-PT-26110001')->json('data.id');
        $p2 = $this->as()->postJson('/api/v1/erp/dye-pt-programmes', ['supplier_id' => $other, 'issue_date' => '2026-11-03'])->assertCreated()->json('data.id');

        $this->as()->postJson('/api/v1/erp/dye-pt-challans', ['challan_date' => '2026-11-04'])->assertUnprocessable()->assertJsonPath('errors.supplier_id.0', 'Supplier is required.');
        $this->as()->postJson('/api/v1/erp/dye-pt-challans', ['supplier_id' => $arafah, 'challan_date' => '2026-11-04', 'programme_ids' => "{$p1},{$p2}"])->assertUnprocessable()
            ->assertJsonPath('errors.programme_ids.0', 'DSC-PT-26110002 was raised for another party.');
        $challan = $this->as()->postJson('/api/v1/erp/dye-pt-challans', ['supplier_id' => $arafah, 'challan_date' => '2026-11-04', 'programme_ids' => $p1])->assertCreated()
            ->assertJsonPath('data.challan_no', 'CLN-PT-26110001')->assertJsonPath('data.programs', 1)->json('data.id');
        $this->as()->getJson('/api/v1/erp/dye-pt-programmes')->assertJsonPath('data.1.challan_no', 'CLN-PT-26110001')->assertJsonPath('data.1.challan_date', '2026-11-04');
        $this->as()->deleteJson("/api/v1/erp/dye-pt-programmes/{$p1}")->assertUnprocessable();

        $this->as()->postJson('/api/v1/erp/dye-pt-receipts', ['issue_date' => '2026-11-06'])->assertUnprocessable()->assertJsonPath('errors.supplier_id.0', 'S/C Party is required.');
        $this->as()->postJson('/api/v1/erp/dye-pt-receipts', ['supplier_id' => $other, 'issue_date' => '2026-11-06', 'challan_id' => $challan])->assertUnprocessable()
            ->assertJsonPath('errors.challan_id.0', 'Challan CLN-PT-26110001 was not sent to this party.');
        $this->as()->postJson('/api/v1/erp/dye-pt-receipts', ['supplier_id' => $arafah, 'transfer_party_id' => $other, 'issue_date' => '2026-11-06', 'qc_no' => 'QC-0001', 'challan_id' => $challan])->assertCreated()
            ->assertJsonPath('data.mrr_no', 'MRR-PT-26110001')->assertJsonPath('data.program_list', 'DSC-PT-26110001')->assertJsonPath('data.transfer_party', 'Rupa Processing');
        $this->as()->deleteJson("/api/v1/erp/dye-pt-challans/{$challan}")->assertUnprocessable();

        $bill = ['supplier_id' => $arafah, 'bill_no' => '22647', 'bill_date' => '2026-11-10', 'status' => 'DRAFT'];
        $this->as()->postJson('/api/v1/erp/dye-pt-bills', $bill)->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Amount is required.');
        $this->as()->postJson('/api/v1/erp/dye-pt-bills', ['amount' => 18500] + $bill)->assertCreated();
        $this->as()->postJson('/api/v1/erp/dye-pt-bills', ['amount' => 18500] + $bill)->assertUnprocessable()->assertJsonPath('errors.bill_no.0', 'Bill 22647 is already filed for this party under that revision.');
        $this->as()->postJson('/api/v1/erp/dye-pt-bills', ['amount' => 18000, 'revision_no' => 1, 'ref_bill_no' => '99999'] + $bill)->assertUnprocessable()->assertJsonPath('errors.ref_bill_no.0', "99999 is not one of this party's bills.");
        $this->as()->postJson('/api/v1/erp/dye-pt-bills', ['amount' => 18000, 'revision_no' => 1, 'ref_bill_no' => '22647', 'revision_descr' => 'Rate corrected'] + $bill)->assertCreated()->assertJsonPath('data.amount', 18000);
    }

    public function test_transfers_and_who_may_do_what(): void
    {
        $other = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000009', 'job_no' => '530', 'style_no' => '530', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 800, 'ship_date' => '2026-12-20', 'status' => 'CONFIRMED'])->id;
        $t = ['transfer_date' => '2026-11-03', 'trans_type' => 'BULK_TO_BULK', 'buyer_id' => $this->buyer, 'from_order_id' => $this->order, 'to_order_id' => $this->order, 'status' => '10'];
        $this->as()->postJson('/api/v1/erp/dye-grey-order-transfers', $t)->assertUnprocessable()->assertJsonPath('errors.to_order_id.0', 'To Order# must differ from From Order#.');
        $this->as()->postJson('/api/v1/erp/dye-grey-order-transfers', ['to_order_id' => $other, 'remarks' => 'B-26070197 > B-26071068'] + $t)->assertCreated()
            ->assertJsonPath('data.transfer_no', 'GF/OTR/26110001')->assertJsonPath('data.to_order', 'LPP-2026-000009 — 530');

        $l = ['transfer_date' => '2026-11-03', 'reason' => 'BATCH_END', 'source' => 'Batch B-26080001', 'order_id' => $this->order, 'qty' => 0, 'status' => 'DRAFT'];
        $this->as()->postJson('/api/v1/erp/dye-leftover-transfers', $l)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Trns Qty must be more than nothing.');
        $this->as()->postJson('/api/v1/erp/dye-leftover-transfers', ['qty' => 42.5] + $l)->assertCreated()->assertJsonPath('data.transfer_no', 'BLT-26110001')->assertJsonPath('data.buyer', 'LPP')
            ->assertJsonPath('data.style', '529')->assertJsonPath('data.order_no', 'LPP-2026-000008');

        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/erp/dye-grey-indents')->assertOk();
        $this->withToken($store)->getJson('/api/v1/dyehouse/batch/options')->assertOk();
        $this->withToken($store)->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'X'])->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/dyehouse/grey-indents/GFR-1/issue', ['issue_date' => '2026-11-03', 'lines' => []])->assertNotFound();
        $this->withToken($store)->postJson('/api/v1/dyehouse/batch-leftover', [])->assertForbidden();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->postJson('/api/v1/erp/dye-batch-plans', ['name' => 'X'])->assertUnprocessable();
        $this->withToken($manager)->postJson('/api/v1/dyehouse/grey-indents/GFR-1/issue', [])->assertNotFound();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/erp/dye-manual-batches')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/dyehouse/batch-leftover')->assertForbidden();
    }

    /** Two bulk machines (1 000 kg and 500 lb) and a sample one. @return list<string> */
    private function machines(): array
    {
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->assertCreated()->json('data.id');
        $unit = TenantFixture::factory($this->delta, 'Dye Unit', 'FAC02');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Dye Building', 'code' => 'BLD-D']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dyeing Floor', 'code' => 'DF-1'])->id;
        $base = ['machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'], 'origin_country_id' => $country, 'floor_id' => $floor,
            'efficiency' => 85, 'sl_no' => '01', 'mc_version' => '0', 'prod_status' => 'FREE', 'active' => true];

        return [
            DyeMachine::query()->create(['machine_no' => 'DM-01', 'capacity' => 1000, 'capacity_unit' => 'KG', 'is_sample' => false] + $base)->id,
            DyeMachine::query()->create(['machine_no' => 'DM-02', 'capacity' => 500, 'capacity_unit' => 'LBS', 'is_sample' => false] + $base)->id,
            DyeMachine::query()->create(['machine_no' => 'SM-01', 'capacity' => 25, 'capacity_unit' => 'KG', 'is_sample' => true] + $base)->id,
        ];
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
