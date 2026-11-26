<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\FabricPlanning\Models\FpBatchPlan;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Batch (Sample) (AnnotechERP 17/04): a sample drawn off a bulk
 * batch onto a card of its own; the sample grey indent, filed on a sample
 * plan, and its issue off the bulk batch's rolls; the Batch and Program
 * Lists; a sample batch's production with its loads, one per day; the
 * finishing check, only on a batch that has run and once a day; and who may
 * do what.
 */
class DyeHouseSampleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $buyer;

    private string $order;

    private string $red;

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
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->red = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'RED', 'colour_type' => 'SOLID', 'name' => 'Cherry Red', 'active' => true])->id;
        $this->ym = now('Asia/Dhaka')->format('Ym');
        // The bulk card a sample is drawn off, with grey on it in the batch store.
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'MB-0001', 'manual_ref_date' => '2026-11-02', 'order_id' => $this->order, 'colour_id' => $this->red,
            'batch_no' => 'B-26110001', 'lot_no' => 'L-1', 'batch_date' => '2026-11-03', 'card_qty' => 300])->assertCreated();
    }

    public function test_sample_off_a_bulk_batch_indent_issue_and_lists(): void
    {
        $bulk = DyeBatch::query()->where('batch_no', 'B-26110001')->value('id');
        $t = ['transfer_date' => '2026-11-04', 'batch_id' => $bulk, 'new_batch_no' => 's-26110001', 'lot_no' => 'SM', 'batch_date' => '2026-11-04', 'batch_qty' => 0];
        $this->as()->postJson('/api/v1/erp/dye-sample-transfers', $t)->assertUnprocessable()->assertJsonPath('errors.batch_qty.0', 'Batch Qty must be more than nothing.');
        $this->as()->postJson('/api/v1/erp/dye-sample-transfers', ['batch_qty' => 300] + $t)->assertUnprocessable()->assertJsonPath('errors.batch_qty.0', 'A sample is drawn off B-26110001, not the whole of it (300 kg).');
        $id = $this->as()->postJson('/api/v1/erp/dye-sample-transfers', ['batch_qty' => 50] + $t)->assertCreated()
            ->assertJsonPath('data.transfer_no', 'SBB/202611001')->assertJsonPath('data.new_batch_no', 'S-26110001')->assertJsonPath('data.batch_no', 'B-26110001')->json('data.id');
        $sample = DyeBatch::query()->where('batch_no', 'S-26110001')->firstOrFail();
        $this->assertSame(['SAMPLE', 'FROM_BULK', 50.0, $this->red, 'LPP-2026-000008'], [$sample->scope, $sample->source, (float) $sample->card_qty, $sample->colour_id, $sample->order_no]);
        $this->assertSame([250.0, 250.0], [(float) DyeBatch::query()->whereKey($bulk)->value('card_qty'), (float) DyeBatchItem::query()->where('batch_id', $bulk)->sum('qty_kg')]);
        $this->as()->patchJson("/api/v1/erp/dye-sample-transfers/{$id}", ['batch_qty' => 10])->assertUnprocessable()->assertJsonValidationErrors('batch_qty');
        $this->as()->patchJson("/api/v1/erp/dye-sample-transfers/{$id}", ['lot_no' => 'SM-2'])->assertOk();
        $this->assertSame('SM-2', $sample->refresh()->lot_no);

        // A Fabric Batch Plan is bulk; a sample plan takes cards, not plans.
        $code = $this->as()->postJson('/api/v1/erp/dye-batch-plans', ['name' => 'Sample room - week 45', 'plan_date' => '2026-11-04', 'scope' => 'SAMPLE', 'scale_hours' => 2, 'cell_width' => 10])->assertCreated()->json('data.code');
        $fp = FpBatchPlan::query()->create(['bp_no' => 'BP-00001', 'week_start' => '2026-11-07', 'dyeing_method' => 'EXHAUST', 'batches' => 1, 'bp_qty' => 100, 'status' => 'RELEASED']);
        $this->as()->postJson("/api/v1/dyehouse/batch-plans/{$code}/pull", ['fp_batch_plan_id' => $fp->id])->assertUnprocessable()->assertJsonPath('errors.fp_batch_plan_id.0', "A Fabric Batch Plan is bulk — pull it onto a bulk plan, not {$code}.");
        $plan = collect($this->as()->getJson('/api/v1/dyehouse/batch/options')->json('data.plans'))->firstWhere('value', $code)['id'];

        // The sample indent is raised on a sample card, filed on the Schedule and for a company.
        $group = $this->as()->getJson('/api/v1/dyehouse/batch/options')->json('data.rates.0.value');
        $indent = ['batch_id' => $sample->id, 'plan_id' => $plan, 'party_id' => $this->delta->id, 'colour_group_id' => $group, 'proj_rate' => 52, 'store_id' => $this->store];
        $this->as()->postJson('/api/v1/erp/dye-sample-indents', ['batch_id' => $bulk] + $indent)->assertUnprocessable()->assertJsonValidationErrors('batch_id');
        $this->as()->postJson('/api/v1/erp/dye-sample-indents', $indent)->assertCreated()->assertJsonPath('data.req_type', 'SAMPLE')->assertJsonPath('data.req_kg', 50)
            ->assertJsonPath('data.company', 'Delta Apparels Ltd')->assertJsonPath('data.batch_no', 'S-26110001');
        $this->assertSame($plan, $sample->refresh()->plan_id);
        $this->as()->getJson('/api/v1/erp/dye-grey-indents')->assertJsonCount(0, 'data');

        // The sample's grey still lies under the bulk batch's number; its issue comes off those rolls.
        $lot = GreyStore::lotFor(['store_id' => $this->store, 'order_id' => $this->order, 'buyer_id' => $this->buyer, 'fabric' => 'Single Jersey', 'batch_no' => 'B-26110001'], '2026-11-01');
        GreyStore::post('OPENING', 'OB-1', '2026-11-01', [['lot_id' => $lot->id, 'qty' => 80]]);
        $no = 'GFR-'.substr($this->ym, 2).'0001';
        $this->as()->getJson("/api/v1/dyehouse/grey-indents/{$no}/issue")->assertOk()->assertJsonCount(1, 'data.lots')->assertJsonPath('data.batch_no', 'S-26110001');
        $this->as()->postJson("/api/v1/dyehouse/grey-indents/{$no}/issue", ['issue_date' => '2026-11-04', 'lines' => [['lot_id' => $lot->id, 'qty' => 20]]])->assertCreated()->assertJsonPath('data.issue_kg', 20);
        $this->assertSame(60.0, GreyStore::balance($lot->id));

        // The Batch and Program Lists.
        $range = '?from=2026-11-01&to=2026-11-30';
        $this->as()->getJson("/api/v1/dyehouse/sample/lists/batch-list{$range}")->assertOk()->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.plan', $code)->assertJsonPath('data.rows.0.requisition', $no)->assertJsonPath('data.rows.0.buyer', 'LPP');
        $this->as()->getJson("/api/v1/dyehouse/sample/lists/program-list{$range}")->assertJsonPath('data.rows.0.code', $code)->assertJsonPath('data.rows.0.batches', 1);
        $this->as()->getJson("/api/v1/dyehouse/sample/lists/pending-labdip{$range}")->assertJsonCount(1, 'data.rows');
        $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', ['recipe_no' => 'LD-2026-0001', 'option_no' => 1, 'buyer_id' => $this->buyer, 'colour_id' => $this->red])->assertCreated();
        $this->as()->getJson("/api/v1/dyehouse/sample/lists/pending-labdip{$range}")->assertJsonCount(0, 'data.rows');
        $this->as()->getJson("/api/v1/dyehouse/sample/lists/nothing{$range}")->assertNotFound();
    }

    public function test_sample_production_and_finishing_check(): void
    {
        $bulk = DyeBatch::query()->where('batch_no', 'B-26110001')->value('id');
        $draw = fn (string $no) => $this->as()->postJson('/api/v1/erp/dye-sample-transfers', ['transfer_date' => '2026-11-04', 'batch_id' => $bulk, 'new_batch_no' => $no, 'batch_date' => '2026-11-04', 'batch_qty' => 20])->assertCreated();
        $draw('S-26110001');
        $draw('S-26110002');
        $this->as()->postJson('/api/v1/erp/dye-lab-dip-recipes', ['recipe_no' => 'LD-2026-0001', 'option_no' => 2, 'buyer_id' => $this->buyer, 'colour_id' => $this->red, 'fiber_composition' => '100% Cotton'])->assertCreated();

        $this->as()->getJson('/api/v1/dyehouse/production/batches?scope=SAMPLE')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.colour', 'Cherry Red');
        $this->as()->getJson('/api/v1/dyehouse/batches/S-26110001/production')->assertOk()->assertJsonPath('data.colour', 'Cherry Red')->assertJsonPath('data.buyer', 'LPP')
            ->assertJsonPath('data.recipes.0', 'LD-2026-0001 · option 2 · 100% Cotton')->assertJsonCount(1, 'data.items')->assertJsonCount(0, 'data.productions');

        $load = ['load_time' => '2026-11-04T08:00', 'unload_time' => '2026-11-04T11:30', 'load_type' => 'Dyeing', 'status' => 'UNLOADED'];
        $prod = ['production_date' => '2026-11-04', 'weight_kg' => 19.5, 'liquor_ratio' => '1:8', 'model_combo' => 'Solid', 'lab_dip_ref' => 'LD-2026-0001', 'loads' => [$load]];
        $this->as()->postJson('/api/v1/dyehouse/batches/S-26110001/production', ['loads' => []] + $prod)->assertUnprocessable()
            ->assertJsonPath('errors.loads.0', 'A production with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/dyehouse/batches/S-26110001/production', ['liquor_ratio' => '8'] + $prod)->assertUnprocessable()->assertJsonPath('errors.liquor_ratio.0', 'Liqur Ratio is written 1:N (1:8).');
        $this->as()->postJson('/api/v1/dyehouse/batches/S-26110001/production', ['loads' => [['unload_time' => '2026-11-04T07:00'] + $load]] + $prod)->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Load 1: the unload cannot be before the load.'));
        $this->as()->postJson('/api/v1/dyehouse/batches/S-26110001/production', $prod)->assertCreated()->assertJsonCount(1, 'data.productions')
            ->assertJsonPath('data.productions.0.loads.0.unload_time', '2026-11-04T11:30')->assertJsonPath('data.productions.0.liquor_ratio', '1:8');
        // One production a day: the same day again is refused; the existing one is rewritten, with another load.
        $this->as()->postJson('/api/v1/dyehouse/batches/S-26110001/production', $prod)->assertUnprocessable()->assertJsonValidationErrors('production_date');
        $this->as()->postJson('/api/v1/dyehouse/batches/S-26110001/production', ['original_date' => '2026-11-04', 'loads' => [$load, ['load_time' => '2026-11-04T13:00', 'status' => 'RUNNING']]] + $prod)
            ->assertOk()->assertJsonCount(1, 'data.productions')->assertJsonCount(2, 'data.productions.0.loads')->assertJsonPath('data.productions.0.loads.1.load_type', 'Dyeing');

        // The finishing check: only on a batch that has run, once a day.
        $s1 = DyeBatch::query()->where('batch_no', 'S-26110001')->value('id');
        $s2 = DyeBatch::query()->where('batch_no', 'S-26110002')->value('id');
        $this->assertSame(['S-26110001'], array_column($this->as()->getJson('/api/v1/dyehouse/batch/options')->json('data.run_batches'), 'label'));
        $qc = ['batch_id' => $s1, 'qc_date' => '2026-11-05', 'result' => 'REWORK', 'gsm' => 160, 'width_inch' => 72, 'shade_note' => 'Half a shade light'];
        $this->as()->postJson('/api/v1/erp/dye-sample-qcs', ['batch_id' => null] + $qc)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', 'Batch is required.');
        $this->as()->postJson('/api/v1/erp/dye-sample-qcs', ['batch_id' => $s2] + $qc)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', 'S-26110002 has not run yet — there is nothing to inspect.');
        $first = $this->as()->postJson('/api/v1/erp/dye-sample-qcs', $qc)->assertCreated()->assertJsonPath('data.batch_no', 'S-26110001')->assertJsonPath('data.lot_no', null)
            ->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.colour', 'Cherry Red')->json('data.id');
        $this->as()->postJson('/api/v1/erp/dye-sample-qcs', ['result' => 'PASSED'] + $qc)->assertUnprocessable()->assertJsonPath('errors.qc_date.0', 'S-26110001 already has its verdict for 2026-11-05. A re-check is on another day.');
        $this->as()->postJson('/api/v1/erp/dye-sample-qcs', ['qc_date' => '2026-11-06', 'result' => 'PASSED'] + $qc)->assertCreated();
        $this->as()->patchJson("/api/v1/erp/dye-sample-qcs/{$first}", ['shade_note' => 'Half a shade light; re-dyed'])->assertOk()->assertJsonPath('data.shade_note', 'Half a shade light; re-dyed');
        $this->as()->getJson('/api/v1/erp/dye-sample-qcs')->assertJsonCount(2, 'data');
    }

    public function test_who_may_produce_and_check(): void
    {
        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/dyehouse/batches/B-26110001/production')->assertOk();
        $this->withToken($store)->postJson('/api/v1/dyehouse/batches/B-26110001/production', ['production_date' => '2026-11-04'])->assertForbidden();
        $this->withToken($store)->getJson('/api/v1/erp/dye-sample-qcs')->assertOk();
        $this->withToken($store)->postJson('/api/v1/erp/dye-sample-transfers', ['batch_qty' => 1])->assertForbidden();
        $technician = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($technician)->getJson('/api/v1/erp/dye-sample-qcs')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/dyehouse/sample/lists/batch-list?from=2026-11-01')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
