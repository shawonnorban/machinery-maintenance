<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → S/C In-House (AnnotechERP 17/10): programmes taken in from a
 * party, the party's fabric received against them on their own challan, the
 * in-house party rate kept apart from 17/01's out-house rates, and who may do what.
 */
class DyeHouseScInHouseTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $party;

    private string $other;

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
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Grey Fabric Store', 'code' => 'ST-GRY', 'active' => true])->id;
        $this->party = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Ha-Meem Textiles', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->other = MaterialSupplier::query()->create(['code' => 'SUP-2', 'name' => 'Rupa Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000008', 'job_no' => '529', 'style_no' => '529', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->ym = now('Asia/Dhaka')->format('Ym');
    }

    public function test_a_programme_taken_in_names_its_processes_and_its_buyer_follows_the_order(): void
    {
        $p = ['prog_date' => '2026-11-06', 'supplier_id' => $this->party, 'order_id' => $this->order, 'status' => 'SUBMITTED'];
        $this->as()->postJson('/api/v1/erp/dye-sci-programmes', $p)->assertUnprocessable()->assertJsonPath('errors.dyeing.0', 'Tick Dyeing, Dyeing Finishing or both — a programme takes on at least one.');
        $other = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'ROLY', 'name' => 'Roly SL', 'short_name' => 'ROLY', 'pi_address' => 'Valencia', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->as()->postJson('/api/v1/erp/dye-sci-programmes', ['dyeing' => true, 'buyer_id' => $other] + $p)->assertUnprocessable()->assertJsonPath('errors.order_id.0', 'That order is not one of the buyer picked.');
        $id = $this->as()->postJson('/api/v1/erp/dye-sci-programmes', ['dyeing' => true, 'dyeing_finishing' => true] + $p)->assertCreated()
            ->assertJsonPath('data.program_no', "DFSCI-{$this->ym}0001")->assertJsonPath('data.buyer', 'LPP')->assertJsonPath('data.style', '529')->assertJsonPath('data.order_no', 'LPP-2026-000008')
            ->assertJsonPath('data.supplier', 'Ha-Meem Textiles')->json('data.id');
        $this->as()->patchJson("/api/v1/erp/dye-sci-programmes/{$id}", ['dyeing' => false, 'dyeing_finishing' => false])->assertUnprocessable()->assertJsonValidationErrors('dyeing');
        $this->as()->patchJson("/api/v1/erp/dye-sci-programmes/{$id}", ['remarks' => 'Two shades', 'program_no' => 'X-1'])->assertOk()->assertJsonPath('data.program_no', "DFSCI-{$this->ym}0001");
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', ['challan_no' => 'HM-1', 'challan_date' => '2026-11-07', 'programme_id' => $id, 'qty_kg' => 50])->assertCreated();
        $this->as()->deleteJson("/api/v1/erp/dye-sci-programmes/{$id}")->assertUnprocessable()->assertJsonPath('errors.program_no.0', "Fabric has been received against DFSCI-{$this->ym}0001, so it stays.");
    }

    public function test_fabric_is_received_on_the_partys_own_challan(): void
    {
        $prog = $this->as()->postJson('/api/v1/erp/dye-sci-programmes', ['prog_date' => '2026-11-06', 'supplier_id' => $this->party, 'dyeing' => true, 'status' => 'SUBMITTED'])->assertCreated()->json('data.id');
        $rival = $this->as()->postJson('/api/v1/erp/dye-sci-programmes', ['prog_date' => '2026-11-06', 'supplier_id' => $this->other, 'dyeing' => true, 'status' => 'SUBMITTED'])->assertCreated()->json('data.id');
        $r = ['challan_no' => ' hm-7701 ', 'challan_date' => '2026-11-07', 'programme_id' => $prog, 'store_id' => $this->store, 'qty_kg' => 420.5];
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', ['qty_kg' => 0] + $r)->assertUnprocessable()->assertJsonPath('errors.qty_kg.0', 'Qty(Kg) must be more than nothing.');
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', ['challan_date' => '2026-11-01'] + $r)->assertUnprocessable()->assertJsonPath('errors.challan_date.0', "A receipt cannot be dated before its programme DFSCI-{$this->ym}0001.");
        // The party is the programme's; its challan number is kept upper case and taken once.
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', $r)->assertCreated()->assertJsonPath('data.challan_no', 'HM-7701')->assertJsonPath('data.supplier', 'Ha-Meem Textiles')
            ->assertJsonPath('data.store', 'Grey Fabric Store (ST-GRY)')->assertJsonPath('data.program_no', "DFSCI-{$this->ym}0001");
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', $r)->assertUnprocessable()->assertJsonPath('errors.challan_no.0', "The party's challan HM-7701 is received already.");
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', ['programme_id' => $rival] + $r)->assertCreated();
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', ['challan_no' => 'HM-7702', 'qty_kg' => 80] + $r)->assertCreated();
        $this->as()->getJson('/api/v1/erp/dye-sci-programmes')->assertJsonPath('data', fn ($rows) => collect($rows)->firstWhere('id', $prog)['received_kg'] === 500.5);
        // A cancelled programme takes no more.
        $this->as()->patchJson("/api/v1/erp/dye-sci-programmes/{$prog}", ['status' => 'CANCELLED'])->assertOk();
        $this->as()->postJson('/api/v1/erp/dye-sci-receipts', ['challan_no' => 'HM-7703'] + $r)->assertUnprocessable()->assertJsonPath('errors.programme_id.0', "DFSCI-{$this->ym}0001 is cancelled or completed — nothing more is received against it.");
    }

    public function test_the_in_house_party_rate_is_kept_apart_from_out_house_rates(): void
    {
        $q = ['rate_group' => 'YARN_TWIST', 'supplier_id' => $this->party, 'quotation_date' => '2026-11-06', 'currency' => 'BDT', 'status' => 'SUBMITTED', 'lines' => [['head' => 'Dyeing (dark shade)', 'uom' => 'KG', 'rate' => 145]]];
        $no = $this->as()->postJson('/api/v1/dyehouse/sc-quotations', ['side' => 'IN'] + $q)->assertCreated()->assertJsonPath('data.side', 'IN')->json('data.quotation_no');
        $this->as()->postJson('/api/v1/dyehouse/sc-quotations', $q)->assertCreated()->assertJsonPath('data.side', 'OUT');
        $this->as()->postJson('/api/v1/dyehouse/sc-quotations', ['side' => 'SIDEWAYS'] + $q)->assertUnprocessable()->assertJsonValidationErrors('side');
        $this->as()->getJson('/api/v1/dyehouse/sc-quotations?side=IN')->assertJsonCount(1, 'data')->assertJsonPath('data.0.quotation_no', $no);
        $this->as()->getJson('/api/v1/dyehouse/sc-quotations')->assertJsonCount(1, 'data')->assertJsonPath('data.0.side', 'OUT');
        // Addressed by its number; the side is not changed afterwards.
        $this->as()->getJson("/api/v1/dyehouse/sc-quotations/{$no}")->assertOk()->assertJsonPath('data.lines.0.rate', 145);
        $this->as()->putJson("/api/v1/dyehouse/sc-quotations/{$no}", ['side' => 'OUT'] + array_diff_key($q, ['rate_group' => 1]))->assertUnprocessable()->assertJsonValidationErrors('side');
        $this->as()->putJson("/api/v1/dyehouse/sc-quotations/{$no}", ['status' => 'APPROVED'] + array_diff_key($q, ['rate_group' => 1]))->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.side', 'IN');
    }

    public function test_who_may_take_programmes_in(): void
    {
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/erp/dye-sci-programmes')->assertOk();
        $this->withToken($store)->postJson('/api/v1/erp/dye-sci-programmes', ['prog_date' => '2026-11-06'])->assertForbidden();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/erp/dye-sci-receipts')->assertForbidden();
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
