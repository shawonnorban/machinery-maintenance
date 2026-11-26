<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Dyeing Q.C (AnnotechERP 17/08): check roll inspection — a roll
 * passes with nothing ticked and is held against a department otherwise —
 * rib and collar shade approval, the batch programme completion that waits
 * on every QC gate, and who may do what.
 */
class DyeHouseQualityTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $acid;

    private string $order;

    private string $red;

    private string $machine;

    private string $dept;

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
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Chemical Store', 'code' => 'ST-CHM', 'active' => true])->id;
        $kg = UnitOfMeasure::availableTo($this->delta->id)->where('code', 'KG')->value('id');
        $itemDept = ItemDepartment::query()->create(['company_id' => $this->delta->id, 'code' => 'DYES_CHEMICAL_STORE', 'name' => 'Dyes Chemical Store', 'active' => true])->id;
        $this->acid = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $itemDept, 'item_code' => 'DC-0003', 'name' => 'Acetic Acid', 'uom_id' => $kg, 'active' => true])->id;
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->red = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'RED', 'colour_type' => 'SOLID', 'name' => 'Cherry Red', 'active' => true])->id;
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Dye Building', 'code' => 'BLD-D']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dyeing Floor', 'code' => 'DF-1'])->id;
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->assertCreated()->json('data.id');
        $this->machine = DyeMachine::query()->create(['machine_no' => 'DM-01', 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'origin_country_id' => $country, 'floor_id' => $floor, 'capacity' => 1000, 'capacity_unit' => 'KG', 'efficiency' => 85, 'sl_no' => '01', 'mc_version' => '0', 'prod_status' => 'FREE', 'is_sample' => false, 'active' => true])->id;
        $this->dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $this->dept, 'company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Dyeing', 'code' => 'DYE', 'created_at' => now(), 'updated_at' => now()]);
        $this->ym = now('Asia/Dhaka')->format('Ym');
    }

    public function test_a_roll_passes_with_nothing_ticked_and_is_held_against_a_department_otherwise(): void
    {
        $no = $this->bulkBatch('B-26110001');
        $batch = $this->batchId($no);
        $roll = ['inspection_date' => '2026-11-06', 'batch_id' => $batch, 'roll_no' => 'r-01'];
        $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', $roll)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', "{$no} has not been on a machine yet.");
        $this->as()->getJson('/api/v1/dyehouse/qc/options')->assertJsonCount(0, 'data.batches')->assertJsonCount(20, 'data.defects');
        $this->produce($no);
        $this->as()->getJson('/api/v1/dyehouse/qc/options')->assertJsonPath('data.batches.0.batch_no', $no);
        $this->as()->getJson("/api/v1/dyehouse/batches/{$no}/check-roll")->assertOk()->assertJsonPath('data.machine', 'DM-01')->assertJsonPath('data.lab_dip_ref', 'LD-1')
            ->assertJsonPath('data.colour', 'Cherry Red')->assertJsonPath('data.weight_kg', 300)->assertJsonCount(0, 'data.inspections');
        // Nothing ticked passes; the roll number is kept upper case and is inspected once.
        $first = $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', $roll)->assertCreated()->assertJsonPath('data.inspection_no', "CRI-{$this->ym}0001")
            ->assertJsonPath('data.roll_no', 'R-01')->assertJsonPath('data.result', 'PASSED')->assertJsonPath('data.department', null)->json('data.inspection_no');
        $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', $roll)->assertUnprocessable()->assertJsonPath('errors.roll_no.0', "Roll R-01 of {$no} is inspected already.");
        // Anything ticked holds the roll, and names the department answerable.
        $bad = ['roll_no' => 'R-02', 'defects' => ['SHADE_NOT_OK', 'CREASE_MARK']] + $roll;
        $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', $bad)->assertUnprocessable()->assertJsonPath('errors.department_id.0', 'Name the department answerable for the faults ticked.');
        $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', ['defects' => ['NOT_A_FAULT']] + $bad)->assertUnprocessable()->assertJsonValidationErrors('defects.0');
        $held = $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', ['department_id' => $this->dept] + $bad)->assertCreated()->assertJsonPath('data.result', 'HELD')
            ->assertJsonPath('data.defects', ['CREASE_MARK', 'SHADE_NOT_OK'])->assertJsonPath('data.defect_count', 2)->assertJsonPath('data.department', 'Dyeing')->json('data.inspection_no');
        // Re-inspected with the faults cleared, it passes and drops the department.
        $this->as()->putJson("/api/v1/dyehouse/check-roll-inspections/{$held}", ['inspection_date' => '2026-11-06', 'roll_no' => 'R-02', 'defects' => [], 'department_id' => $this->dept])->assertOk()
            ->assertJsonPath('data.result', 'PASSED')->assertJsonPath('data.department_id', null);
        $this->as()->putJson("/api/v1/dyehouse/check-roll-inspections/{$first}", ['inspection_date' => '2026-11-06', 'roll_no' => 'R-02'])->assertUnprocessable()->assertJsonValidationErrors('roll_no');
        $this->as()->getJson('/api/v1/dyehouse/check-roll-inspections')->assertJsonCount(2, 'data');
        $this->as()->getJson("/api/v1/dyehouse/batches/{$no}/check-roll")->assertJsonCount(2, 'data.inspections');
    }

    public function test_rib_and_collar_shade_is_approved_per_position_in_the_lot(): void
    {
        $no = $this->bulkBatch('B-26110002');
        $batch = $this->batchId($no);
        $s = ['batch_id' => $batch, 'sl_no' => 1, 'status' => 'PENDING', 'rcv_date' => '2026-11-06', 'g_qty' => 120, 'g_roll' => 6, 'fin_qty' => 112.5, 'fin_roll' => 6];
        $this->as()->postJson('/api/v1/erp/dye-shade-approvals', $s)->assertUnprocessable()->assertJsonPath('errors.batch_id.0', "{$no} has not been on a machine yet.");
        $this->produce($no);
        $this->as()->postJson('/api/v1/erp/dye-shade-approvals', ['sl_no' => null] + $s)->assertUnprocessable()->assertJsonPath('errors.sl_no.0', 'SL# is required.');
        // Blank, the lot, buyer, style and shade are the card's.
        $id = $this->as()->postJson('/api/v1/erp/dye-shade-approvals', $s)->assertCreated()->assertJsonPath('data.lot_no', 'L-01')->assertJsonPath('data.buyer', 'LPP')
            ->assertJsonPath('data.colour', 'Cherry Red')->assertJsonPath('data.style_order', '529 / LPP-2026-000008')->assertJsonPath('data.aprv_date', null)->json('data.id');
        $this->as()->postJson('/api/v1/erp/dye-shade-approvals', $s)->assertUnprocessable()->assertJsonPath('errors.sl_no.0', "SL# 1 of {$no} is approved already.");
        $this->as()->postJson('/api/v1/erp/dye-shade-approvals', ['sl_no' => 2, 'aprv_date' => '2026-11-01'] + $s)->assertUnprocessable()->assertJsonValidationErrors('aprv_date');
        // A passed approval left undated is dated today.
        $this->as()->patchJson("/api/v1/erp/dye-shade-approvals/{$id}", ['status' => 'PASSED'])->assertOk()->assertJsonPath('data.status', 'PASSED')
            ->assertJsonPath('data.aprv_date', now('Asia/Dhaka')->toDateString());
        $this->as()->getJson('/api/v1/erp/dye-shade-approvals')->assertJsonCount(1, 'data')->assertJsonPath('data.0.fin_qty', 112.5);
    }

    public function test_a_batch_programme_is_completed_only_once_every_qc_gate_has_passed(): void
    {
        $no = $this->bulkBatch('B-26110003');
        $batch = $this->batchId($no);
        $req = ['req_for' => 'BATCH_PROGRAM', 'req_date' => '2026-11-06', 'req_type' => 'Store Requisition for Batch Program', 'store_id' => $this->store, 'batch_id' => $batch,
            'lines' => [['item_id' => $this->acid, 'required_qty' => 3]]];
        $prog = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', $req)->assertCreated()->json('data.req_no');
        $loose = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['batch_id' => null] + $req)->assertCreated()->json('data.req_no');
        $wash = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'MC_WASH', 'req_type' => 'Store Requisition for M/C Wash'] + $req)->assertCreated()->json('data.req_no');
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$wash}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "{$wash} is not a batch programme or re-process requisition.");
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$loose}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "{$loose} names no batch, so there is nothing to complete.");

        // Each gate in turn: produced, a check roll inspected, none held, no shade open.
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "{$no} has not been produced yet.");
        $this->produce($no);
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "No check roll of {$no} has been inspected.");
        $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', ['inspection_date' => '2026-11-06', 'batch_id' => $batch, 'roll_no' => 'R-01'])->assertCreated();
        $held = $this->as()->postJson('/api/v1/dyehouse/check-roll-inspections', ['inspection_date' => '2026-11-06', 'batch_id' => $batch, 'roll_no' => 'R-02', 'defects' => ['PATCHES'], 'department_id' => $this->dept])
            ->assertCreated()->json('data.inspection_no');
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "{$no} has rolls on hold (R-02) — they must pass first.");
        $this->as()->putJson("/api/v1/dyehouse/check-roll-inspections/{$held}", ['inspection_date' => '2026-11-06', 'roll_no' => 'R-02', 'defects' => []])->assertOk();
        $shade = $this->as()->postJson('/api/v1/erp/dye-shade-approvals', ['batch_id' => $batch, 'sl_no' => 1, 'status' => 'HOLD_DYEING'])->assertCreated()->json('data.id');
        $row = collect($this->as()->getJson('/api/v1/dyehouse/batch-completions')->assertOk()->json('data'))->firstWhere('req_no', $prog);
        $this->assertSame(['shade', false, 'OPEN', 1], [$row['gates']['failing'], $row['ready'], $row['status'], $row['gates']['open_shades']]);
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "{$no} has 1 rib or collar shade(s) not passed yet.");
        $this->as()->patchJson("/api/v1/erp/dye-shade-approvals/{$shade}", ['status' => 'PASSED'])->assertOk();
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertCreated()->assertJsonPath('data.status', 'COMPLETED');
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertUnprocessable()->assertJsonPath('errors.req_no.0', "{$prog} is completed already.");

        // A roll sent back from dyeing and finishing shows on the register.
        $this->as()->postJson('/api/v1/erp/dye-check-roll-returns', ['req_date' => '2026-11-06', 'reprocess_type' => 'RE_WASH', 'store_id' => $this->store, 'batch_id' => $batch, 'qty_kg' => 10])->assertCreated();
        $this->as()->postJson("/api/v1/dyehouse/check-roll-returns/CRR-{$this->ym}0001/back")->assertOk();
        $row = collect($this->as()->getJson('/api/v1/dyehouse/batch-completions')->json('data'))->firstWhere('req_no', $prog);
        $this->assertSame(['COMPLETED', true, $no], [$row['status'], $row['back_from_df'], $row['batch_no']]);
        $this->assertNull(collect($this->as()->getJson('/api/v1/dyehouse/batch-completions')->json('data'))->firstWhere('req_no', $wash));
    }

    public function test_a_run_that_by_passed_check_roll_needs_no_inspection(): void
    {
        $no = $this->bulkBatch('B-26110004');
        $prog = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'BATCH_PROGRAM', 'req_date' => '2026-11-06', 'req_type' => 'Store Requisition for Batch Program',
            'store_id' => $this->store, 'batch_id' => $this->batchId($no), 'lines' => [['item_id' => $this->acid, 'required_qty' => 3]]])->assertCreated()->json('data.req_no');
        $this->produce($no, true);
        $this->as()->getJson("/api/v1/dyehouse/batches/{$no}/check-roll")->assertJsonPath('data.bypass_check_roll', true);
        $this->as()->postJson("/api/v1/dyehouse/batch-completions/{$prog}")->assertCreated();
    }

    public function test_who_may_inspect_and_complete(): void
    {
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->getJson('/api/v1/dyehouse/qc/options')->assertOk();
        $this->withToken($manager)->getJson('/api/v1/erp/dye-shade-approvals')->assertOk();
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/dyehouse/check-roll-inspections')->assertForbidden();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/dyehouse/batch-completions')->assertForbidden();
        $this->withToken($technician)->postJson('/api/v1/dyehouse/batch-completions/IR-DC-0')->assertForbidden();
    }

    /** A bulk card keyed in by hand on the order, 300 kg, lot L-01: its number. */
    private function bulkBatch(string $no): string
    {
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'MB-'.substr($no, -4), 'manual_ref_date' => '2026-11-04', 'order_id' => $this->order, 'colour_id' => $this->red,
            'batch_no' => $no, 'batch_date' => '2026-11-04', 'card_qty' => 300, 'lot_no' => 'L-01'])->assertCreated();

        return $no;
    }

    private function batchId(string $no): string
    {
        return DB::table('dye_batches')->where('batch_no', $no)->value('id');
    }

    private function produce(string $no, bool $bypass = false): void
    {
        $this->as()->postJson("/api/v1/dyehouse/batches/{$no}/production", ['production_date' => '2026-11-05', 'liquor_ratio' => '1:8', 'machine_id' => $this->machine, 'model_combo' => 'Body',
            'lab_dip_ref' => 'LD-1', 'bypass_check_roll' => $bypass, 'loads' => [['machine_id' => $this->machine, 'load_time' => '2026-11-05T08:00', 'unload_time' => '2026-11-05T12:00', 'status' => 'UNLOADED']]])->assertCreated();
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
