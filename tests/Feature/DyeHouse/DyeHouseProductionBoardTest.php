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
 * Dye House → Production Board (AnnotechERP 17/07): bulk batch production
 * with its floor fields and loads, the manual load times, machine wash output
 * on a wash requisition, direct and general fabric bookings and the trims
 * programme, check roll parameters, the dyeing & finishing closing that stops
 * an order's production, and who may do what.
 */
class DyeHouseProductionBoardTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $acid;

    private string $buyer;

    private string $order;

    private string $red;

    private string $machine;

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
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->red = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'RED', 'colour_type' => 'SOLID', 'name' => 'Cherry Red', 'active' => true])->id;
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Dye Building', 'code' => 'BLD-D']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Dyeing Floor', 'code' => 'DF-1'])->id;
        $lists = collect($this->as()->getJson('/api/v1/erp/dye-machine-lists')->json('data'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '156', 'name' => 'China'])->assertCreated()->json('data.id');
        $this->machine = DyeMachine::query()->create(['machine_no' => 'DM-01', 'machine_type_id' => $lists->firstWhere('name', 'Soft Flow')['id'], 'brand_id' => $lists->firstWhere('name', 'Fongs')['id'],
            'origin_country_id' => $country, 'floor_id' => $floor, 'capacity' => 1000, 'capacity_unit' => 'KG', 'efficiency' => 85, 'sl_no' => '01', 'mc_version' => '0', 'prod_status' => 'FREE', 'is_sample' => false, 'active' => true])->id;
        $this->ym = now('Asia/Dhaka')->format('Ym');
    }

    public function test_bulk_production_names_its_floor_fields_and_its_loads_are_timed_by_hand(): void
    {
        $no = $this->bulkBatch('B-26110001');
        $this->as()->getJson('/api/v1/dyehouse/production/batches?scope=BULK')->assertOk()->assertJsonPath('data.0.batch_no', $no);
        $p = ['production_date' => '2026-11-05', 'liquor_ratio' => '1:8', 'loads' => [['machine_id' => $this->machine, 'load_time' => '2026-11-05T08:00', 'unload_time' => '2026-11-05T13:30', 'check_roll_at' => '2026-11-05T14:00', 'status' => 'UNLOADED']]];
        // Bulk names its machine, model/combo and lab dip; sample does not have to.
        $this->as()->postJson("/api/v1/dyehouse/batches/{$no}/production", $p)->assertUnprocessable()
            ->assertJsonPath('errors.machine_id.0', 'M/C No is required on a bulk production.')->assertJsonPath('errors.model_combo.0', 'Model/Combo is required on a bulk production.')
            ->assertJsonPath('errors.lab_dip_ref.0', 'Lab-dip # is required on a bulk production.');
        $p += ['machine_id' => $this->machine, 'model_combo' => 'Body + Rib', 'lab_dip_ref' => 'LD-2611-004', 'bypass_check_roll' => true, 'scouring' => true, 'remarks' => 'First run'];
        $this->as()->postJson("/api/v1/dyehouse/batches/{$no}/production", $p)->assertCreated()
            ->assertJsonPath('data.productions.0.bypass_check_roll', true)->assertJsonPath('data.productions.0.scouring', true)
            ->assertJsonPath('data.productions.0.loads.0.check_roll_at', '2026-11-05T14:00');
        $this->as()->getJson('/api/v1/dyehouse/productions?scope=BULK')->assertOk()->assertJsonPath('data.0.batch_no', $no)->assertJsonPath('data.0.machine', 'DM-01')
            ->assertJsonPath('data.0.duration', '5h 30m')->assertJsonPath('data.0.last_status', 'UNLOADED')->assertJsonPath('data.0.weight_kg', 300);
        $this->as()->getJson('/api/v1/dyehouse/productions?scope=SAMPLE')->assertJsonCount(0, 'data');

        // Manual Daily: only the two times are written, and a machine cannot come off before it went on.
        $load = $this->as()->getJson('/api/v1/dyehouse/production-loads')->assertOk()->assertJsonPath('data.0.minutes', 330)->json('data.0');
        $this->as()->putJson('/api/v1/dyehouse/production-loads', ['loads' => []])->assertUnprocessable()->assertJsonPath('errors.loads.0', 'Nothing has been changed.');
        $this->as()->putJson('/api/v1/dyehouse/production-loads', ['loads' => [['id' => $load['id'], 'load_time' => '2026-11-05T09:00', 'unload_time' => '2026-11-05T08:00']]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => ($e['loads.0.unload_time'][0] ?? null) === "A load of {$no} came off the machine before it went on.");
        $this->as()->putJson('/api/v1/dyehouse/production-loads', ['loads' => [['id' => $load['id'], 'load_time' => '2026-11-05T07:30', 'unload_time' => '2026-11-05T13:30']]])->assertOk()->assertJsonPath('data.saved', 1);
        $this->as()->getJson('/api/v1/dyehouse/production-loads')->assertJsonPath('data.0.minutes', 360)->assertJsonPath('data.0.load_time', '2026-11-05T07:30')->assertJsonPath('data.0.machine', 'DM-01');
    }

    public function test_machine_wash_output_records_the_dose_once_per_wash_requisition(): void
    {
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['store_id' => $this->store, 'ob_date' => now('Asia/Dhaka')->toDateString(), 'item_id' => $this->acid, 'source' => 'LOCAL',
            'lot_no' => 'AA-1', 'total_qty' => 40, 'uom' => 'KG'])->assertCreated();
        $req = ['req_date' => '2026-11-05', 'store_id' => $this->store, 'machine_id' => $this->machine, 'lines' => [['item_id' => $this->acid, 'required_qty' => 2.5, 'uom' => 'KG']]];
        $wash = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'MC_WASH', 'req_type' => 'Store Requisition for M/C Wash'] + $req)->assertCreated()->json('data.req_no');
        $batch = $this->as()->postJson('/api/v1/dyehouse/chem-requisitions', ['req_for' => 'BATCH_PROGRAM', 'req_type' => 'Store Requisition for Batch Program'] + $req)->assertCreated()->json('data.req_no');
        $this->as()->getJson('/api/v1/dyehouse/production-board/options')->assertOk()->assertJsonCount(1, 'data.wash_requisitions')->assertJsonPath('data.wash_requisitions.0.value', $wash);
        $this->as()->getJson("/api/v1/dyehouse/wash-productions/{$batch}")->assertUnprocessable()->assertJsonPath('errors.requisition_id.0', "{$batch} is not a machine wash requisition.");
        // The sheet reads off the requisition, with what the sub-store holds now.
        $this->as()->getJson("/api/v1/dyehouse/wash-productions/{$wash}")->assertOk()->assertJsonPath('data.requisition.machine', 'DM-01')->assertJsonPath('data.production', null)
            ->assertJsonPath('data.requisition.lines.0.sub_stock', 40)->assertJsonPath('data.requisition.lines.0.central_stock', 0);
        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => DB::table('factories')->where('company_id', $this->delta->id)->value('id'),
            'name' => 'Dyeing', 'code' => 'DYE', 'created_at' => now(), 'updated_at' => now()]);
        $w = ['production_date' => '2026-11-05', 'party_id' => $this->delta->id, 'department_id' => $dept, 'machine_id' => $this->machine, 'lines' => [['item_id' => $this->acid, 'kg' => 0, 'gm' => 0]]];
        $this->as()->putJson("/api/v1/dyehouse/wash-productions/{$wash}", ['department_id' => null] + $w)->assertUnprocessable()->assertJsonPath('errors.department_id.0', 'Department is required.');
        $this->as()->putJson("/api/v1/dyehouse/wash-productions/{$wash}", $w)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['lines.0.kg'][0] ?? null) === 'Line 1 doses nothing — give its kilos, grams or milligrams.');
        // Three scales as written, one total.
        $this->as()->putJson("/api/v1/dyehouse/wash-productions/{$wash}", ['lines' => [['item_id' => $this->acid, 'kg' => 2, 'gm' => 450, 'mg' => 500]]] + $w)->assertCreated()
            ->assertJsonPath('data.production.lines.0.total_kg', 2.4505)->assertJsonPath('data.production.lines.0.gm', 450);
        $this->as()->putJson("/api/v1/dyehouse/wash-productions/{$wash}", ['lines' => [['item_id' => $this->acid, 'kg' => 2.5]], 'remarks' => 'Recounted'] + $w)->assertOk()
            ->assertJsonPath('data.production.remarks', 'Recounted')->assertJsonCount(1, 'data.production.lines');
        $this->as()->getJson('/api/v1/dyehouse/production-board/options')->assertJsonCount(0, 'data.wash_requisitions');
        $this->as()->getJson('/api/v1/dyehouse/wash-productions')->assertJsonCount(1, 'data')->assertJsonPath('data.0.req_no', $wash)->assertJsonPath('data.0.total_kg', 2.5)
            ->assertJsonPath('data.0.department', 'Dyeing');
    }

    public function test_fabric_bookings_and_trim_programmes(): void
    {
        $other = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'ROLY', 'name' => 'Roly SL', 'short_name' => 'ROLY', 'pi_address' => 'Valencia', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $b = ['kind' => 'DIRECT', 'prod_type' => 'BULK', 'prog_date' => '2026-11-05', 'status' => 'DRAFT', 'lines' => [['fabric' => 'S/J 160 GSM', 'colour_id' => $this->red, 'qty_kg' => 420]]];
        $this->as()->postJson('/api/v1/dyehouse/fabric-bookings', $b)->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', 'Buyer A/C is required.')->assertJsonPath('errors.order_id.0', 'Style/Ord# is required.');
        $this->as()->postJson('/api/v1/dyehouse/fabric-bookings', ['buyer_id' => $other, 'order_id' => $this->order] + $b)->assertUnprocessable()->assertJsonPath('errors.order_id.0', 'That style is not one of the buyer picked.');
        $this->as()->postJson('/api/v1/dyehouse/fabric-bookings', ['buyer_id' => $this->buyer, 'order_id' => $this->order, 'lines' => []] + $b)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A booking with no fabric on it books nothing. Add at least one line.');
        $no = $this->as()->postJson('/api/v1/dyehouse/fabric-bookings', ['buyer_id' => $this->buyer, 'order_id' => $this->order] + $b)->assertCreated()
            ->assertJsonPath('data.booking_no', "DFB-{$this->ym}0001")->assertJsonPath('data.req_type', 'Dyeing')->assertJsonPath('data.style_order', '529 / LPP-2026-000008')
            ->assertJsonPath('data.qty_kg', 420)->assertJsonPath('data.lines.0.colour', 'Cherry Red')->json('data.booking_no');
        // A general booking names no order; it is numbered apart.
        $this->as()->postJson('/api/v1/dyehouse/fabric-bookings', ['kind' => 'GENERAL', 'req_type' => 'Re-dyeing'] + $b)->assertCreated()->assertJsonPath('data.booking_no', "GFB-{$this->ym}0001")
            ->assertJsonPath('data.req_type', 'Re-dyeing');
        $this->as()->getJson('/api/v1/dyehouse/fabric-bookings?kind=DIRECT')->assertJsonCount(1, 'data');
        $put = ['buyer_id' => $this->buyer, 'order_id' => $this->order] + array_diff_key($b, ['kind' => 1]);
        $this->as()->putJson("/api/v1/dyehouse/fabric-bookings/{$no}", ['status' => 'CLOSED'] + $put)->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->as()->putJson("/api/v1/dyehouse/fabric-bookings/{$no}", $put)->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} is closed or cancelled — it reads only.");

        $t = ['prod_type' => 'SAMPLE', 'buyer_id' => $this->buyer, 'order_id' => $this->order, 'prog_date' => '2026-11-05', 'status' => 'SUBMITTED', 'lines' => [['item' => 'Neck tape 12mm', 'colour_id' => $this->red]]];
        $this->as()->postJson('/api/v1/dyehouse/trim-programmes', $t)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['lines.0.yds'][0] ?? null) === 'Line 1 needs its yards or its kilos.');
        $this->as()->postJson('/api/v1/dyehouse/trim-programmes', ['lines' => [['item' => 'Neck tape 12mm', 'colour_id' => $this->red, 'yds' => 1500, 'qty_kg' => 6.5], ['item' => 'Draw cord', 'yds' => 800]]] + $t)->assertCreated()
            ->assertJsonPath('data.programme_no', "TDP-{$this->ym}0001")->assertJsonPath('data.yds', 2300)->assertJsonPath('data.items', 2)->assertJsonPath('data.lines.1.qty_kg', null);
    }

    public function test_check_roll_parameters_one_per_buyer_and_fabric(): void
    {
        $p = ['gsm_tol_pct' => 5, 'width_tol_pct' => 3, 'shade_delta_e' => 1];
        $this->as()->postJson('/api/v1/erp/dye-check-roll-params', ['gsm_tol_pct' => null] + $p)->assertUnprocessable()->assertJsonPath('errors.gsm_tol_pct.0', 'GSM Tolerance is required.');
        $this->as()->postJson('/api/v1/erp/dye-check-roll-params', $p)->assertCreated()->assertJsonPath('data.active', true)->assertJsonPath('data.buyer', null);
        $this->as()->postJson('/api/v1/erp/dye-check-roll-params', $p)->assertUnprocessable()->assertJsonPath('errors.fabric_type_id.0', 'That buyer and fabric type already have a parameter.');
        $this->as()->postJson('/api/v1/erp/dye-check-roll-params', ['buyer_id' => $this->buyer, 'shade_delta_e' => 0.8, 'shrink_length_pct' => 5] + $p)->assertCreated()
            ->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.shade_delta_e', 0.8);
        $this->as()->postJson('/api/v1/erp/dye-check-roll-params', ['shade_delta_e' => 25] + $p)->assertUnprocessable()->assertJsonValidationErrors('shade_delta_e');
        $this->as()->getJson('/api/v1/erp/dye-check-roll-params')->assertJsonCount(2, 'data');
    }

    public function test_closing_an_order_stops_its_production(): void
    {
        $no = $this->bulkBatch('B-26110002');
        $this->as()->postJson('/api/v1/dyehouse/fabric-bookings', ['kind' => 'DIRECT', 'prod_type' => 'BULK', 'prog_date' => '2026-11-05', 'status' => 'APPROVED', 'buyer_id' => $this->buyer, 'order_id' => $this->order,
            'lines' => [['fabric' => 'S/J 160 GSM', 'qty_kg' => 500]]])->assertCreated();
        $p = ['production_date' => '2026-11-05', 'liquor_ratio' => '1:8', 'machine_id' => $this->machine, 'model_combo' => 'Body', 'lab_dip_ref' => 'LD-1',
            'loads' => [['machine_id' => $this->machine, 'load_time' => '2026-11-05T08:00', 'status' => 'RUNNING']]];
        $this->as()->postJson("/api/v1/dyehouse/batches/{$no}/production", $p)->assertCreated();
        $this->as()->getJson('/api/v1/dyehouse/df-closings')->assertOk()->assertJsonPath('data.0.order_no', 'LPP-2026-000008')->assertJsonPath('data.0.scope', 'BULK')
            ->assertJsonPath('data.0.status', 'RUNNING')->assertJsonPath('data.0.req_qty', 300)->assertJsonPath('data.0.booked_kg', 500)->assertJsonPath('data.0.ship_month', '2026-12');
        // Refused while a batch is still on a machine.
        $this->as()->postJson('/api/v1/dyehouse/df-closings', ['order_no' => 'LPP-2026-000008', 'scope' => 'BULK'])->assertUnprocessable()
            ->assertJsonPath('errors.order_no.0', "LPP-2026-000008 still has a batch on a machine ({$no}) — unload it first.");
        $this->as()->postJson('/api/v1/dyehouse/df-closings', ['order_no' => 'LPP-2026-000008', 'scope' => 'SAMPLE'])->assertUnprocessable()->assertJsonValidationErrors('order_no');
        $this->as()->postJson("/api/v1/dyehouse/batches/{$no}/production", ['original_date' => '2026-11-05', 'loads' => [['machine_id' => $this->machine, 'load_time' => '2026-11-05T08:00', 'unload_time' => '2026-11-05T12:00', 'status' => 'UNLOADED']]] + $p)->assertOk();
        $this->as()->postJson('/api/v1/dyehouse/df-closings', ['order_no' => 'LPP-2026-000008', 'scope' => 'BULK'])->assertCreated()->assertJsonPath('data.status', 'CLOSED');
        $this->as()->postJson('/api/v1/dyehouse/df-closings', ['order_no' => 'LPP-2026-000008', 'scope' => 'BULK'])->assertUnprocessable()->assertJsonPath('errors.order_no.0', 'LPP-2026-000008 is closed already.');
        // The closed card takes no more production, on the sheet or the manual grid.
        $this->as()->postJson("/api/v1/dyehouse/batches/{$no}/production", ['production_date' => '2026-11-06'] + $p)->assertUnprocessable()
            ->assertJsonPath('errors.batch_no.0', "{$no} is closed out of dyeing and finishing — it takes no more production.");
        $load = $this->as()->getJson('/api/v1/dyehouse/production-loads')->assertJsonPath('data.0.closed', true)->json('data.0.id');
        $this->as()->putJson('/api/v1/dyehouse/production-loads', ['loads' => [['id' => $load, 'load_time' => '2026-11-05T07:00', 'unload_time' => '2026-11-05T12:00']]])->assertUnprocessable();
        $this->as()->getJson('/api/v1/dyehouse/df-closings')->assertJsonPath('data.0.status', 'CLOSED');
        $this->as()->getJson('/api/v1/dyehouse/production/batches?scope=BULK')->assertJsonCount(0, 'data');
    }

    public function test_who_may_record_and_close(): void
    {
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->getJson('/api/v1/dyehouse/production-board/options')->assertOk();
        $this->withToken($manager)->postJson('/api/v1/erp/dye-check-roll-params', ['gsm_tol_pct' => 5, 'width_tol_pct' => 3, 'shade_delta_e' => 1])->assertCreated();
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/dyehouse/production-board/options')->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/dyehouse/df-closings', ['order_no' => 'LPP-2026-000008', 'scope' => 'BULK'])->assertForbidden();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/dyehouse/production-loads')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/dyehouse/fabric-bookings?kind=DIRECT')->assertForbidden();
    }

    /** A bulk card keyed in by hand on the order, 300 kg: its number. */
    private function bulkBatch(string $no): string
    {
        $this->as()->postJson('/api/v1/erp/dye-manual-batches', ['manual_ref_no' => 'MB-'.substr($no, -4), 'manual_ref_date' => '2026-11-04', 'order_id' => $this->order, 'colour_id' => $this->red,
            'batch_no' => $no, 'batch_date' => '2026-11-04', 'card_qty' => 300])->assertCreated();

        return $no;
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
