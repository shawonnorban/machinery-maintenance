<?php

declare(strict_types=1);

namespace Tests\Feature\SalesShipment;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Sales & Shipment → General → Shipment Planning (AnnotechERP 21/04): a
 * confirmed order planned into shipments (SHP-2026-000001 from the
 * settings), never more than it still has unplanned — nor, with Ship
 * Against Delivered on, more than it has net delivered; rewritten while
 * planning, cancelled back onto the order while open; the order's shipment
 * status; a pending shipment stopping the month close — and who may.
 */
class SalesShipmentPlanningTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $buyer;

    private string $store;

    private string $so;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->buyer = MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => 'SUP-1', 'name' => 'Arafah Knit Composite', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $bdt = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $bdt, 'company_id' => $this->delta->id, 'code' => 'BDT', 'name' => 'Bangladeshi Taka', 'exchange_rate' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $item = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'PL-01', 'name' => 'Poly Bag', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true])->id;
        $wh = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $wh->id, 'name' => 'Accessories Store', 'code' => 'ST-ACC', 'active' => true])->id;
        $this->so = $this->as()->postJson('/api/v1/sales-shipment/orders', [
            'search_for' => 'SALES_ORDER', 'so_type' => 'GENERAL', 'customer_id' => $this->buyer, 'receive_date' => '2026-11-01', 'program_by' => 'Rahim', 'program_to' => 'Karim', 'bill_to_id' => $this->buyer,
            'delivery_target_date' => '2026-11-20', 'same_as_bill_to' => true, 'payment_method' => 'CASH', 'currency_id' => $bdt, 'conv_rate' => 1, 'mode_of_shipment' => 'By Van', 'po_wo_no' => 'WO-1',
            'shipment_date' => '2026-11-25', 'incoterm' => 'FOB', 'destination_port' => 'Hamburg',
            'lines' => [['sl_no' => 1, 'buyer' => 'H&M', 'item_id' => $item, 'gmt_color' => 'Navy', 'rate' => 4]],
            'sizes' => [['sl_no' => 1, 'size' => 'M', 'qty' => 500], ['sl_no' => 1, 'size' => 'L', 'qty' => 300]],
        ])->assertCreated()->json('data.so_no');
    }

    public function test_a_confirmed_order_is_planned_within_what_it_has_unplanned_and_cancelled_back(): void
    {
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.not_confirmed', ['no' => $this->so])]);
        $this->confirm();
        $src = $this->as()->getJson("/api/v1/sales-shipment/shipments/source/{$this->so}")->assertOk()->json('data');
        $this->assertSame(['2026-11-25', 'FOB', 'Hamburg', false], [$src['shipment_date'], $src['incoterm'], $src['destination_port'], $src['against_delivered']]);
        [$m, $l] = $src['lines'];
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 501]]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sp_over', ['line' => $m['label'], 'max' => '500'])]);
        $this->as()->postJson('/api/v1/sales-shipment/shipments', ['ex_factory_date' => '2026-11-26'] + $this->body([[$m['size_id'], 10]]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sp_ex_factory')]);
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 0]]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sp_lines')]);
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 300, 15], [$l['size_id'], 300, 10]]))->assertCreated()
            ->assertJsonPath('data.shipment_no', 'SHP-2026-000001')->assertJsonPath('data.stage', 'PLANNING')->assertJsonPath('data.lines.0.rate', 4);
        $this->assertSame(200.0, (float) $this->as()->getJson('/api/v1/sales-shipment/shipments/orders')->json('data.0.balance'));
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 201]]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 200, 10]]))->assertCreated()->assertJsonPath('data.shipment_no', 'SHP-2026-000002');
        $this->as()->putJson('/api/v1/sales-shipment/shipments/SHP-2026-000001', ['port_of_loading' => 'Chattogram'] + $this->body([[$m['size_id'], 300, 15], [$l['size_id'], 250, 9]]))->assertOk()
            ->assertJsonPath('data.port_of_loading', 'Chattogram');

        $row = collect($this->as()->getJson('/api/v1/sales-shipment/shipments')->assertOk()->json('data'))->firstWhere('shipment_no', 'SHP-2026-000001');
        $this->assertSame([550.0, 24, 2200.0, 'Hamburg', 'Sea'], [(float) $row['planned_qty'], $row['cartons'], (float) $row['value'], $row['destination'], $row['mode']]);
        $this->assertSame('PLANNED', collect($this->as()->getJson('/api/v1/sales-shipment/orders')->json('data'))->firstWhere('so_no', $this->so)['shipment_status']);

        // Once a shipment is confirmed (21/05) the plan is locked; cancelling an open one releases it.
        DB::table('ss_shipments')->where('shipment_no', 'SHP-2026-000001')->update(['stage' => 'CONFIRMED']);
        $this->as()->putJson('/api/v1/sales-shipment/shipments/SHP-2026-000001', $this->body([[$m['size_id'], 10]]))->assertUnprocessable();
        $this->as()->deleteJson('/api/v1/sales-shipment/shipments/SHP-2026-000001')->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/shipments/SHP-2026-000001/cancel')->assertUnprocessable();
        DB::table('ss_shipments')->where('shipment_no', 'SHP-2026-000001')->update(['stage' => 'BOOKED']);
        $this->as()->postJson('/api/v1/sales-shipment/shipments/SHP-2026-000001/cancel')->assertOk()->assertJsonPath('data.stage', 'CANCELLED');
        $this->as()->postJson('/api/v1/sales-shipment/shipments/SHP-2026-000001/cancel')->assertUnprocessable();
        $this->assertSame(600.0, (float) $this->as()->getJson('/api/v1/sales-shipment/shipments/orders')->json('data.0.balance'));
        $this->as()->deleteJson('/api/v1/sales-shipment/shipments/SHP-2026-000002')->assertOk();
    }

    public function test_with_ship_against_delivered_a_plan_carries_no_more_than_net_delivered(): void
    {
        $this->confirm();
        $this->as()->putJson('/api/v1/sales-shipment/settings', ['ship_against_delivered' => true, 'require_export_docs' => true, 'due_alert_days' => 7, 'cert_alert_days' => 30,
            'prefix_amendment' => 'SOA', 'prefix_challan_return' => 'DCR', 'prefix_sc_return' => 'SCRT', 'prefix_sc_reconciliation' => 'SCRC', 'prefix_shipment' => 'SHP', 'prefix_booking' => 'BKG',
            'prefix_invoice' => 'INV', 'prefix_packing_list' => 'PL', 'prefix_handover' => 'DH', 'seq_width' => 6])->assertOk();
        $m = $this->as()->getJson("/api/v1/sales-shipment/shipments/source/{$this->so}")->json('data.lines.0');
        $this->assertSame(0.0, (float) $m['balance']);
        $this->as()->postJson('/api/v1/sales-shipment/challans', ['so_no' => $this->so, 'customer_id' => $this->buyer, 'chalan_date' => '2026-11-07', 'store_id' => $this->store, 'delivery_target_date' => '2026-11-20',
            'mode_of_shipment' => 'By Van', 'currency_id' => DB::table('currencies')->value('id'), 'conv_rate' => 1, 'lines' => [['size_id' => $m['size_id'], 'qty' => 120]]])->assertCreated();
        $this->as()->postJson('/api/v1/sales-shipment/challans/DC-20261107-0001/submit')->assertOk();
        $src = $this->as()->getJson("/api/v1/sales-shipment/shipments/source/{$this->so}")->json('data');
        $this->assertSame([true, 120.0], [$src['against_delivered'], (float) $src['lines'][0]['balance']]);
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 121]]))->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.sp_over_delivered', ['line' => $m['label'], 'max' => '120'])]);
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 120]]))->assertCreated();
    }

    public function test_a_pending_shipment_stops_the_month_close_and_who_may(): void
    {
        $this->confirm();
        $m = $this->as()->getJson("/api/v1/sales-shipment/shipments/source/{$this->so}")->json('data.lines.0');
        $this->as()->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 100]]))->assertCreated();
        $items = $this->as()->getJson('/api/v1/sales-shipment/month-close/exceptions?period=2026-11')->json('data.items');
        $this->assertContains(['kind' => 'SHIPMENT_PENDING', 'no' => 'SHP-2026-000001', 'date' => '2026-11-25', 'detail' => 'PLANNING'], $items);

        $keeper = $this->token('STORE_MANAGER', 'sm@delta.test');
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/shipments')->assertOk();
        $this->withToken($keeper)->postJson('/api/v1/sales-shipment/shipments', $this->body([[$m['size_id'], 1]]))->assertForbidden();
        $this->withToken($this->token('FACTORY_MANAGER', 'fm@delta.test'))->postJson('/api/v1/sales-shipment/shipments/SHP-2026-000001/cancel')->assertOk();
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson('/api/v1/sales-shipment/shipments/SHP-2026-000001')->assertOk();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/sales-shipment/shipments')->assertForbidden();
    }

    private function confirm(): void
    {
        foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
            $this->as()->postJson("/api/v1/sales-shipment/orders/{$this->so}/{$s}")->assertOk();
        }
    }

    /** @param list<array{0: string, 1: float|int, 2?: int}> $lines */
    private function body(array $lines): array
    {
        return ['so_no' => $this->so, 'plan_date' => '2026-11-07', 'shipment_date' => '2026-11-25', 'ex_factory_date' => '2026-11-23', 'shipment_mode' => 'Sea', 'destination_port' => 'Hamburg', 'incoterm' => 'FOB',
            'lines' => array_map(fn ($l) => ['size_id' => $l[0], 'qty' => $l[1], 'carton_qty' => $l[2] ?? 0], $lines)];
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
