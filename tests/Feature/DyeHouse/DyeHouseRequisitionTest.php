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
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Requisition (AnnotechERP 17/05): dye and chemical requisitions
 * — the lines they ask for, a batch naming what it is for, the store's
 * 10-Issue off the chemical store's stock, the approval a re-process waits on — check-roll returns sent
 * back for re-processing, dye machine downtime, and who may do what.
 */
class DyeHouseRequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $acid;

    private string $soda;

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
        $dept = ItemDepartment::query()->create(['company_id' => $this->delta->id, 'code' => 'DYES_CHEMICAL_STORE', 'name' => 'Dyes Chemical Store', 'active' => true])->id;
        $this->acid = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dept, 'item_code' => 'DC-0003', 'name' => 'Acetic Acid', 'uom_id' => $kg, 'active' => true])->id;
        $this->soda = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dept, 'item_code' => 'DC-0006', 'name' => 'Soda Ash', 'uom_id' => $kg, 'active' => true])->id;
        $this->ym = now('Asia/Dhaka')->format('Ym');
        // 10-Issue takes off the chemical store's stock (17/06): the sub-store opens with a lot of each.
        foreach ([[$this->acid, 'AA-01'], [$this->soda, 'SA-01']] as [$item, $lot]) {
            $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['store_id' => $this->store, 'ob_date' => now('Asia/Dhaka')->startOfMonth()->toDateString(), 'item_id' => $item,
                'source' => 'LOCAL', 'lot_no' => $lot, 'total_qty' => 100, 'uom' => 'KG', 'rate_bdt' => 150])->assertCreated();
        }
    }

    public function test_batch_programme_requisition_and_its_issue(): void
    {
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $red = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'RED', 'colour_type' => 'SOLID', 'name' => 'Cherry Red', 'active' => true])->id;
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'MB-0001', 'manual_ref_date' => '2026-11-04', 'order_id' => $order, 'colour_id' => $red,
            'batch_no' => 'B-26110001', 'batch_date' => '2026-11-04', 'card_qty' => 300])->assertCreated();
        $batch = $this->as()->getJson('/api/v1/dyehouse/batch/options')->assertOk()->assertJsonCount(2, 'data.chemicals')->json('data.batches.0');
        $this->assertSame([$red, $buyer, $order], [$batch['colour_id'], $batch['buyer_id'], $batch['order_id']]);

        $req = ['req_for' => 'BATCH_PROGRAM', 'req_date' => '2026-11-05', 'req_type' => 'Store Requisition for Batch Program', 'store_id' => $this->store, 'batch_id' => $batch['value'],
            'lines' => [['item_id' => $this->acid, 'required_qty' => 4.5, 'uom' => 'kg'], ['item_id' => $this->soda, 'required_qty' => 12, 'uom' => 'KG', 'remarks' => 'Fixation']]];
        $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['lines' => []] + $req)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A requisition with no items asks the store for nothing. Add at least one line.');
        $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['store_id' => null] + $req)->assertUnprocessable()->assertJsonPath('errors.store_id.0', 'From Store is required.');
        $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['lines' => [['item_id' => $this->acid, 'required_qty' => 0]]] + $req)->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Every line needs a required quantity above nothing.'));
        $no = "IR-DC-{$this->ym}0001";
        // The batch names the shade, buyer and order; the total is the lines added up; the unit is the master's code.
        $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', $req)->assertCreated()->assertJsonPath('data.req_no', $no)->assertJsonPath('data.approval', 'NOT_REQUIRED')
            ->assertJsonPath('data.status', 'WAITING')->assertJsonPath('data.colour', 'Cherry Red')->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.batch_no', 'B-26110001')
            ->assertJsonPath('data.required_qty', 16.5)->assertJsonPath('data.items', 2)->assertJsonPath('data.lines.0.uom', 'KG')->assertJsonPath('data.lines.1.item', 'Soda Ash (DC-0006)');
        $this->as()->putJson("/api/v1/dyehouse/chem-requisitions/{$no}", ['remarks' => 'Second load'] + $req)->assertUnprocessable()->assertJsonValidationErrors('req_for');
        $this->as()->putJson("/api/v1/dyehouse/chem-requisitions/{$no}", ['remarks' => 'Second load'] + array_diff_key($req, ['req_for' => 1]))->assertOk()->assertJsonPath('data.remarks', 'Second load');
        $this->as()->getJson('/api/v1/dyehouse/chem-requisitions?for=BATCH_PROGRAM')->assertJsonCount(1, 'data');
        $this->as()->getJson('/api/v1/dyehouse/chem-requisitions?for=MC_WASH')->assertJsonCount(0, 'data');

        // 10-Issue releases every line in full, once; an issued requisition is neither rewritten nor deleted.
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$no}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED')->assertJsonPath('data.issued_qty', 16.5)->assertJsonPath('data.balance_qty', 0);
        $this->assertEquals([95.5, 88], collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->sortBy('lot_no')->pluck('stock')->values()->all());
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$no}/issue")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->putJson("/api/v1/dyehouse/chem-requisitions/{$no}", array_diff_key($req, ['req_for' => 1]))->assertUnprocessable()->assertJsonValidationErrors('req_no');
        $this->as()->deleteJson("/api/v1/dyehouse/chem-requisitions/{$no}")->assertUnprocessable();

        // The Print desk raises any kind; a waiting one may be deleted.
        $wash = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'MC_WASH', 'req_type' => 'Store Requisition for M/C Wash', 'batch_id' => null] + $req)->assertCreated()->json('data.req_no');
        $this->assertSame("IR-DC-{$this->ym}0002", $wash);
        $this->as()->deleteJson("/api/v1/dyehouse/chem-requisitions/{$wash}")->assertOk();
    }

    public function test_re_process_waits_on_the_approval_desk(): void
    {
        $req = ['req_for' => 'REPROCESS', 'req_date' => '2026-11-05', 'req_type' => 'Store Requisition for Re-Process', 'store_id' => $this->store,
            'lines' => [['item_id' => $this->acid, 'required_qty' => 5, 'uom' => 'KG']]];
        $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', $req)->assertUnprocessable()->assertJsonPath('errors.process_type.0', 'Process Type is required for a re-process.');
        $first = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['process_type' => 'RE_DYEING'] + $req)->assertCreated()->assertJsonPath('data.approval', 'PENDING')->json('data.req_no');
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$first}/issue")->assertUnprocessable()
            ->assertJsonPath('errors.approval.0', "{$first} waits on the approval desk — the store cannot issue against it yet.");
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$first}/approve")->assertOk()->assertJsonPath('data.approval', 'APPROVED')->assertJsonPath('data.status', 'WAITING');
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$first}/approve")->assertUnprocessable()->assertJsonValidationErrors('approval');
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$first}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');

        $second = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['process_type' => 'STRIPPING'] + $req)->assertCreated()->json('data.req_no');
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$second}/reject")->assertOk()->assertJsonPath('data.approval', 'REJECTED')->assertJsonPath('data.status', 'CANCELLED');
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$second}/issue")->assertUnprocessable();
        $this->as()->putJson("/api/v1/dyehouse/chem-requisitions/{$second}", array_diff_key(['process_type' => 'STRIPPING'] + $req, ['req_for' => 1]))->assertUnprocessable();
        $this->as()->postJson("/api/v1/dyehouse/chem-requisitions/{$second}/maybe")->assertNotFound();
    }

    public function test_check_roll_returns_and_machine_downtime(): void
    {
        $r = ['req_date' => '2026-11-05', 'not_ok_date' => '2026-11-07', 'reprocess_type' => 'RE_MATCHING', 'store_id' => $this->store, 'qty_kg' => 18.5, 'defect' => 'Rib shade not OK against body'];
        $this->as()->postJson('/api/v1/erp/dye-check-roll-returns', $r)->assertUnprocessable()->assertJsonPath('errors.not_ok_date.0', 'Not Ok Date cannot be after the Req Date.');
        $this->as()->postJson('/api/v1/erp/dye-check-roll-returns', ['qty_kg' => 0, 'not_ok_date' => '2026-11-02'] + $r)->assertUnprocessable()->assertJsonPath('errors.qty_kg.0', 'Qty must be more than nothing.');
        $id = $this->as()->postJson('/api/v1/erp/dye-check-roll-returns', ['not_ok_date' => '2026-11-02'] + $r)->assertCreated()
            ->assertJsonPath('data.return_no', "CRR-{$this->ym}0001")->assertJsonPath('data.waited', 3)->assertJsonPath('data.sent_back', false)->json('data.id');
        $this->as()->patchJson("/api/v1/erp/dye-check-roll-returns/{$id}", ['defect' => 'Rib two shades light'])->assertOk();
        $this->as()->postJson("/api/v1/dyehouse/check-roll-returns/CRR-{$this->ym}0001/back")->assertOk()->assertJsonPath('data.sent_back', true);
        $this->as()->postJson("/api/v1/dyehouse/check-roll-returns/CRR-{$this->ym}0001/back")->assertUnprocessable();
        $this->as()->patchJson("/api/v1/erp/dye-check-roll-returns/{$id}", ['defect' => 'Changed'])->assertUnprocessable()->assertJsonPath('errors.return_no.0', "CRR-{$this->ym}0001 has been sent back — it reads only.");
        $this->as()->deleteJson("/api/v1/erp/dye-check-roll-returns/{$id}")->assertUnprocessable();

        $unit = TenantFixture::factory($this->delta, 'Dye Unit', 'FAC02');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Dye Building', 'code' => 'BLD-D']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dyeing Floor', 'code' => 'DF-1'])->id;
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->assertCreated()->json('data.id');
        $machine = DyeMachine::query()->create(['machine_no' => 'DM-01', 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'origin_country_id' => $country, 'floor_id' => $floor, 'capacity' => 1000, 'capacity_unit' => 'KG', 'efficiency' => 85, 'sl_no' => '01', 'mc_version' => '0', 'prod_status' => 'FREE', 'is_sample' => false, 'active' => true])->id;
        $log = ['dye_machine_id' => $machine, 'log_date' => '2026-11-05', 'report_time' => '2026-11-05T09:15', 'problem_time' => '2026-11-05T09:00', 'defect_type' => 'Pump seal', 'status' => 'RESOLVED'];
        $this->as()->postJson('/api/v1/erp/dye-machine-maintenance', $log)->assertUnprocessable()->assertJsonPath('errors.resolved_time.0', 'A resolved job says when the machine ran again — give the Resolved Time.');
        $this->as()->postJson('/api/v1/erp/dye-machine-maintenance', ['resolved_time' => '2026-11-05T08:00'] + $log)->assertUnprocessable()->assertJsonPath('errors.resolved_time.0', 'Resolved Time cannot be before the Problem Time.');
        $this->as()->postJson('/api/v1/erp/dye-machine-maintenance', ['resolved_time' => '2026-11-05T12:30'] + $log)->assertCreated()
            ->assertJsonPath('data.downtime', '3h 30m')->assertJsonPath('data.machine', 'DM-01')->assertJsonPath('data.report_time', '2026-11-05T09:15');
        $this->as()->postJson('/api/v1/erp/dye-machine-maintenance', ['status' => 'IN_PROGRESS', 'defect_type' => 'Heat exchanger leak'] + $log)->assertCreated()->assertJsonPath('data.downtime', null);
    }

    public function test_who_may_raise_approve_and_issue(): void
    {
        $no = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'REPROCESS', 'process_type' => 'RE_WASH', 'req_date' => '2026-11-05', 'req_type' => 'Store Requisition for Re-Process',
            'store_id' => $this->store, 'lines' => [['item_id' => $this->acid, 'required_qty' => 2]]])->assertCreated()->json('data.req_no');
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/dyehouse/chem-requisitions')->assertOk();
        $this->withToken($store)->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'MC_WASH'])->assertForbidden();
        $this->withToken($store)->postJson("/api/v1/dyehouse/chem-requisitions/{$no}/approve")->assertForbidden();
        $this->withToken($store)->postJson("/api/v1/dyehouse/chem-requisitions/{$no}/issue")->assertUnprocessable();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->postJson("/api/v1/dyehouse/chem-requisitions/{$no}/approve")->assertOk();
        $this->withToken($store)->postJson("/api/v1/dyehouse/chem-requisitions/{$no}/issue")->assertOk();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/dyehouse/chem-requisitions')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/erp/dye-machine-maintenance')->assertForbidden();
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
