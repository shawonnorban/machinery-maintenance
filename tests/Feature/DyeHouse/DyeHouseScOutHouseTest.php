<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → S/C Dyeing (AnnotechERP 17/09): an out-house programme and its
 * shades, grey called off against them and issued, the challan that
 * despatches programmes, the floor and store receipts that bring them back
 * (the store never past the floor) and complete them, the bill and its
 * revisions, and who may do what.
 */
class DyeHouseScOutHouseTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $party;

    private string $other;

    private string $buyer;

    private string $order;

    private string $red;

    private string $navy;

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
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Grey Fabric Store', 'code' => 'ST-GRY', 'active' => true])->id;
        $this->party = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Pacific Dyeing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->other = MaterialSupplier::query()->create(['code' => 'SUP-2', 'name' => 'Rupa Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->red = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'RED', 'colour_type' => 'SOLID', 'name' => 'Cherry Red', 'active' => true])->id;
        $this->navy = Colour::query()->create(['company_id' => $this->delta->id, 'code' => 'NVY', 'colour_type' => 'SOLID', 'name' => 'Navy', 'active' => true])->id;
        $this->ym = now('Asia/Dhaka')->format('Ym');
    }

    public function test_programme_shades_are_called_off_and_issued_no_further_than_they_ask(): void
    {
        $p = ['issue_date' => '2026-11-06', 'supplier_id' => $this->party, 'store_id' => $this->store, 'status' => 'DRAFT',
            'shades' => [['buyer_id' => $this->buyer, 'order_id' => $this->order, 'colour_id' => $this->red, 'req_kg' => 300], ['colour_id' => $this->navy, 'req_kg' => 200]]];
        $this->as()->postJson('/api/v1/dyehouse/sc-programmes', ['shades' => []] + $p)->assertUnprocessable()->assertJsonPath('errors.shades.0', 'A programme with no shades dyes nothing. Add at least one.');
        $this->as()->postJson('/api/v1/dyehouse/sc-programmes', ['exp_delivery_date' => '2026-11-01'] + $p)->assertUnprocessable()->assertJsonValidationErrors('exp_delivery_date');
        $no = "DF-SC-OH-{$this->ym}0001";
        $prog = $this->as()->postJson('/api/v1/dyehouse/sc-programmes', $p)->assertCreated()->assertJsonPath('data.program_no', $no)->assertJsonPath('data.req_kg', 500)
            ->assertJsonPath('data.shades.0.style', '529')->assertJsonPath('data.shades.0.colour', 'Cherry Red')->assertJsonPath('data.supplier', 'Pacific Dyeing')->json('data');
        // A draft is not live: nothing is called off against it.
        $rq = ['req_date' => '2026-11-06', 'program_no' => $no, 'store_id' => $this->store, 'lines' => [['shade_id' => $prog['shades'][0]['id'], 'req_kg' => 120]]];
        $this->as()->postJson('/api/v1/dyehouse/sc-requisitions', $rq)->assertUnprocessable()->assertJsonPath('errors.program_no.0', "{$no} is not live — it is a draft, completed or cancelled.");
        $put = ['status' => 'SUBMITTED', 'shades' => array_map(fn ($s) => ['id' => $s['id'], 'buyer_id' => $s['buyer_id'], 'order_id' => $s['order_id'], 'colour_id' => $s['colour_id'], 'req_kg' => $s['req_kg']], $prog['shades'])] + $p;
        $this->as()->putJson("/api/v1/dyehouse/sc-programmes/{$no}", $put)->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->getJson('/api/v1/dyehouse/sc/options')->assertJsonCount(1, 'data.programmes');

        $req = $this->as()->postJson('/api/v1/dyehouse/sc-requisitions', $rq)->assertCreated()->assertJsonPath('data.req_no', "DF-SC-RQ-{$this->ym}0001")
            ->assertJsonPath('data.batch_no', 'DF-SC-B-'.now('Asia/Dhaka')->format('ym').'0001')->assertJsonPath('data.req_kg', 120)->assertJsonPath('data.status', 'WAITING')->json('data');
        $this->as()->postJson('/api/v1/dyehouse/sc-requisitions', ['lines' => [['shade_id' => $prog['shades'][0]['id'], 'req_kg' => 181]]] + $rq)->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => ($e['lines.0.req_kg'][0] ?? null) === 'Line 1: only 180 kg of that shade is left to call off.');
        // A shade called off stays, and cannot ask for less than was called off.
        $shrunk = $put;
        $shrunk['shades'][0]['req_kg'] = 100;
        $this->as()->putJson("/api/v1/dyehouse/sc-programmes/{$no}", $shrunk)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['shades.0.req_kg'][0] ?? null) === 'Shade 1 cannot ask for less than the 120 kg already called off.');
        $dropped = $put;
        array_shift($dropped['shades']);
        $this->as()->putJson("/api/v1/dyehouse/sc-programmes/{$no}", $dropped)->assertUnprocessable()->assertJsonPath('errors.shades.0', 'A shade with 120 kg called off against it stays on the programme.');

        // The store issues in part, then in full.
        $line = $req['lines'][0]['id'];
        $this->as()->postJson("/api/v1/dyehouse/sc-requisitions/{$req['req_no']}/issue", ['lines' => [['id' => $line, 'qty' => 121]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');
        $this->as()->postJson("/api/v1/dyehouse/sc-requisitions/{$req['req_no']}/issue", ['lines' => [['id' => $line, 'qty' => 70]]])->assertOk()->assertJsonPath('data.status', 'PARTIAL');
        $this->as()->putJson("/api/v1/dyehouse/sc-requisitions/{$req['req_no']}", array_diff_key($rq, ['program_no' => 1]))->assertUnprocessable()->assertJsonValidationErrors('req_no');
        $this->as()->postJson("/api/v1/dyehouse/sc-requisitions/{$req['req_no']}/issue", ['lines' => [['id' => $line, 'qty' => 50]]])->assertOk()->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.issued_kg', 120);
        $this->as()->getJson('/api/v1/dyehouse/sc-requisitions')->assertJsonPath('data.0.supplier', 'Pacific Dyeing')->assertJsonPath('data.0.buyer_ids', [$this->buyer]);
    }

    public function test_challan_despatches_and_receipts_bring_back_no_more_than_the_floor_passed(): void
    {
        $a = $this->programme($this->party, 300);
        $b = $this->programme($this->party, 100);
        $c = $this->programme($this->other, 50);
        $ch = ['challan_date' => '2026-11-07', 'supplier_id' => $this->party, 'store_id' => $this->store, 'gate_pass_no' => 'GP-77', 'programmes' => [$a, $c]];
        $this->as()->postJson('/api/v1/dyehouse/sc-challans', $ch)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['programmes.1'][0] ?? null) === "{$c} is another party's programme.");
        $challan = $this->as()->postJson('/api/v1/dyehouse/sc-challans', ['programmes' => [$a]] + $ch)->assertCreated()->assertJsonPath('data.challan_no', "DF-SC-CH-{$this->ym}0001")
            ->assertJsonPath('data.programmes', [$a])->json('data.challan_no');
        $this->as()->getJson("/api/v1/dyehouse/sc-programmes/{$a}")->assertJsonPath('data.status', 'DESPATCHED')->assertJsonPath('data.challan_no', $challan);
        $this->as()->postJson('/api/v1/dyehouse/sc-challans', ['programmes' => [$a]] + $ch)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['programmes.0'][0] ?? null) === "{$a} is on another challan already.");
        // Rewritten while nothing has come back: a programme taken off goes back to submitted.
        $this->as()->putJson("/api/v1/dyehouse/sc-challans/{$challan}", ['programmes' => [$b]] + $ch)->assertOk()->assertJsonPath('data.programmes', [$b]);
        $this->as()->getJson("/api/v1/dyehouse/sc-programmes/{$a}")->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/dyehouse/sc-challans/{$challan}", ['programmes' => [$a, $b]] + $ch)->assertOk();

        // The floor first; the store never past what the floor received.
        $rc = ['kind' => 'STORE', 'issue_date' => '2026-11-10', 'challan_no' => $challan, 'batch_no' => 'SC-01', 'lines' => [['program_no' => $a, 'qty_kg' => 100]]];
        $this->as()->postJson('/api/v1/dyehouse/sc-receipts', $rc)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['lines.0.qty_kg'][0] ?? null) === "Only 0 kg of {$a} has been received on the floor and not yet booked in.");
        $this->as()->postJson('/api/v1/dyehouse/sc-receipts', ['kind' => 'FLOOR', 'issue_date' => '2026-11-01'] + $rc)->assertUnprocessable()->assertJsonValidationErrors('issue_date');
        $this->as()->postJson('/api/v1/dyehouse/sc-receipts', ['kind' => 'FLOOR', 'lines' => [['program_no' => $c, 'qty_kg' => 5]]] + $rc)->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => ($e['lines.0.program_no'][0] ?? null) === "{$c} is not on challan {$challan}.");
        $floor = $this->as()->postJson('/api/v1/dyehouse/sc-receipts', ['kind' => 'FLOOR', 'qc_no' => 'QC-9', 'lines' => [['program_no' => $a, 'qty_kg' => 298.5], ['program_no' => $b, 'qty_kg' => 100]]] + $rc)
            ->assertCreated()->assertJsonPath('data.mrr_no', "DF-SC-FR-{$this->ym}0001")->assertJsonPath('data.qc_no', 'QC-9')->assertJsonPath('data.batch_no', null)->assertJsonPath('data.qty_kg', 398.5)->json('data.mrr_no');
        $this->as()->putJson("/api/v1/dyehouse/sc-challans/{$challan}", ['programmes' => [$a]] + $ch)->assertUnprocessable()->assertJsonValidationErrors('challan_no');
        $this->as()->postJson('/api/v1/dyehouse/sc-receipts', ['lines' => [['program_no' => $a, 'qty_kg' => 299]]] + $rc)->assertUnprocessable();
        $store = $this->as()->postJson('/api/v1/dyehouse/sc-receipts', ['lines' => [['program_no' => $a, 'qty_kg' => 298.5], ['program_no' => $b, 'qty_kg' => 100]]] + $rc)->assertCreated()
            ->assertJsonPath('data.mrr_no', "DF-SC-MRR-{$this->ym}0001")->assertJsonPath('data.batch_no', 'SC-01')->json('data.mrr_no');
        // Booked back in full completes a programme; short of it, it stays despatched.
        $this->as()->getJson("/api/v1/dyehouse/sc-programmes/{$b}")->assertJsonPath('data.status', 'COMPLETED')->assertJsonPath('data.store_kg', 100);
        $this->as()->getJson("/api/v1/dyehouse/sc-programmes/{$a}")->assertJsonPath('data.status', 'DESPATCHED')->assertJsonPath('data.floor_kg', 298.5);
        $this->as()->getJson('/api/v1/dyehouse/sc-receipts?kind=FLOOR')->assertJsonCount(1, 'data')->assertJsonPath('data.0.programmes', [$a, $b]);
        $this->as()->putJson("/api/v1/dyehouse/sc-receipts/{$store}", ['issue_date' => '2026-11-10', 'lines' => [['program_no' => $a, 'qty_kg' => 290]]])->assertOk()->assertJsonPath('data.qty_kg', 290);
        $this->assertNotSame($floor, $store);
    }

    public function test_bills_carry_their_revision_trail(): void
    {
        $bill = ['bill_date' => '2026-11-12', 'supplier_id' => $this->party, 'amount' => 45000, 'status' => 'WAITING_STORE'];
        $this->as()->postJson('/api/v1/erp/dye-sc-bills', ['amount' => 0] + $bill)->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Amount must be more than nothing.');
        $first = $this->as()->postJson('/api/v1/erp/dye-sc-bills', $bill)->assertCreated()->assertJsonPath('data.bill_no', "DF-SC-BL-{$this->ym}0001")->assertJsonPath('data.revision_no', 0)->json('data');
        $this->as()->postJson('/api/v1/erp/dye-sc-bills', ['supplier_id' => $this->other, 'ref_bill_id' => $first['id']] + $bill)->assertUnprocessable()
            ->assertJsonPath('errors.ref_bill_id.0', "A revision names one of the same party's other bills.");
        $rev = $this->as()->postJson('/api/v1/erp/dye-sc-bills', ['ref_bill_id' => $first['id'], 'amount' => 43500, 'revision_descr' => 'Short weight on 2 rolls'] + $bill)->assertCreated()
            ->assertJsonPath('data.revision_no', 1)->assertJsonPath('data.ref_bill_no', $first['bill_no'])->json('data');
        $this->as()->postJson('/api/v1/erp/dye-sc-bills', ['ref_bill_id' => $rev['id'], 'amount' => 43000] + $bill)->assertCreated()->assertJsonPath('data.revision_no', 2);
        $this->as()->patchJson("/api/v1/erp/dye-sc-bills/{$first['id']}", ['status' => 'CANCELLED'])->assertOk();
        $this->as()->patchJson("/api/v1/erp/dye-sc-bills/{$first['id']}", ['amount' => 1])->assertUnprocessable()->assertJsonPath('errors.status.0', "{$first['bill_no']} is completed or cancelled — it reads only.");
    }

    public function test_who_may_raise_and_issue(): void
    {
        $no = $this->programme($this->party, 100);
        $req = $this->as()->postJson('/api/v1/dyehouse/sc-requisitions', ['req_date' => '2026-11-06', 'program_no' => $no, 'store_id' => $this->store,
            'lines' => [['shade_id' => $this->as()->getJson("/api/v1/dyehouse/sc-programmes/{$no}")->json('data.shades.0.id'), 'req_kg' => 40]]])->assertCreated()->json('data');
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/dyehouse/sc-requisitions')->assertOk();
        $this->withToken($store)->postJson('/api/v1/dyehouse/sc-programmes', [])->assertForbidden();
        $this->withToken($store)->postJson("/api/v1/dyehouse/sc-requisitions/{$req['req_no']}/issue", ['lines' => [['id' => $req['lines'][0]['id'], 'qty' => 40]]])->assertOk();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->getJson('/api/v1/dyehouse/sc-programmes')->assertOk();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/dyehouse/sc-challans')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/erp/dye-sc-bills')->assertForbidden();
    }

    /** A submitted programme for a party with one shade of so many kilos: its number. */
    private function programme(string $party, float $kg): string
    {
        return $this->as()->postJson('/api/v1/dyehouse/sc-programmes', ['issue_date' => '2026-11-06', 'supplier_id' => $party, 'store_id' => $this->store, 'status' => 'SUBMITTED',
            'shades' => [['colour_id' => $this->red, 'req_kg' => $kg]]])->assertCreated()->json('data.program_no');
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
