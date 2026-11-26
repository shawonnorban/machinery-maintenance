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
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02): a sales order
 * booked with its lines, sizes and particulars and taken through the
 * approval chain; challans against it never beyond what it owes plus its
 * overage, handed to the gate, delivered or cancelled back; returns on a
 * delivered challan putting the balance back once confirmed; an amendment
 * (one open at a time, never below what has gone out) applied as the next
 * version; a check bill verified, approved and paid, or rejected back; the
 * S/C programme; delivery completion; the month close and its lock — and
 * who may.
 */
class SalesShipmentOrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $buyer;

    private string $other;

    private string $bdt;

    private string $poly;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $party = fn (string $code, string $name, string $address) => MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'supplier_type' => 'LOCAL_NON_EPZ', 'address' => $address, 'active' => true])->id;
        $this->buyer = $party('SUP-1', 'Arafah Knit Composite', 'Gazipur');
        $this->other = $party('SUP-2', 'Bestex Washing', 'Narayanganj');
        $this->bdt = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $this->bdt, 'company_id' => $this->delta->id, 'code' => 'BDT', 'name' => 'Bangladeshi Taka', 'exchange_rate' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->poly = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'PL-01', 'name' => 'Poly Bag', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true])->id;
        $wh = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $wh->id, 'name' => 'Accessories Store', 'code' => 'ST-ACC', 'active' => true])->id;
    }

    public function test_a_sales_order_is_booked_rewritten_while_a_draft_and_checked(): void
    {
        $options = $this->as()->getJson('/api/v1/sales-shipment/options')->assertOk();
        $this->assertSame(['Carton Dimension', 'Poly Thickness', 'Print Placement'], array_column($options->json('data.particulars'), 'label'));
        $this->assertSame('Arafah Knit Composite (SUP-1)', $options->json('data.customers.0.label'));

        $this->as()->postJson('/api/v1/sales-shipment/orders', ['lines' => []] + $this->orderBody())->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.so_lines')]);
        $this->as()->postJson('/api/v1/sales-shipment/orders', ['shipment_date' => '2026-11-01'] + $this->orderBody())->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ship_before_receive')]);
        $this->as()->postJson('/api/v1/sales-shipment/orders', ['sizes' => [['sl_no' => 9, 'size' => 'M', 'qty' => 10]]] + $this->orderBody())->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sl_unknown', ['sl' => 9])]);
        $this->as()->postJson('/api/v1/sales-shipment/orders', ['sizes' => [['sl_no' => 1, 'size' => 'S', 'qty' => 100]]] + $this->orderBody())->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sl_no_size', ['sl' => 2])]);

        $so = $this->as()->postJson('/api/v1/sales-shipment/orders', $this->orderBody())->assertCreated()
            ->assertJsonPath('data.so_no', 'SO-20261107-0001')->assertJsonPath('data.approval_status', 'DRAFT')->assertJsonPath('data.lead_time_days', 13)
            ->assertJsonPath('data.delivery_to_id', $this->buyer)->assertJsonPath('data.delivery_address', 'Gazipur')->assertJsonPath('data.particulars.0.value', '12 x 16 in');
        $this->assertCount(3, $so->json('data.sizes'));
        $this->as()->putJson('/api/v1/sales-shipment/orders/SO-20261107-0001', ['program_by' => 'Rahim (rev)'] + $this->orderBody())->assertOk()->assertJsonPath('data.program_by', 'Rahim (rev)');
        $this->assertSame(3, DB::table('ss_sales_order_sizes')->count());

        $row = $this->as()->getJson('/api/v1/sales-shipment/orders')->assertOk()->json('data.0');
        $this->assertSame(['so_qty' => 1000.0, 'due_qty' => 1000.0, 'total_value' => 3700.0, 'challan_status' => 'WAITING', 'ref_buyer' => 'H&M'],
            ['so_qty' => (float) $row['so_qty'], 'due_qty' => (float) $row['due_qty'], 'total_value' => (float) $row['total_value'], 'challan_status' => $row['challan_status'], 'ref_buyer' => $row['ref_buyer']]);

        $this->step('SO-20261107-0001', 'submit')->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/orders/SO-20261107-0001', $this->orderBody())->assertUnprocessable();
    }

    public function test_the_approval_chain_and_who_takes_each_step(): void
    {
        $no = $this->order(confirm: false);
        $this->step($no, 'check')->assertUnprocessable();
        $this->step($no, 'submit')->assertOk();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->postJson("/api/v1/sales-shipment/orders/{$no}/check")->assertForbidden();
        $this->step($no, 'check')->assertOk();
        $this->step($no, 'return-to-draft')->assertOk();
        $this->assertNull(DB::table('ss_sales_orders')->where('so_no', $no)->value('submitted_at'));
        foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
            $this->step($no, $s)->assertOk();
        }
        $row = collect($this->as()->getJson('/api/v1/sales-shipment/confirmations')->assertOk()->json('data'))->firstWhere('so_no', $no);
        $this->assertSame('CONFIRMED', $row['approval_status']);
        $this->assertStringContainsString($this->owner->name, $row['confirmed']);
        $this->step($no, 'return-to-draft')->assertUnprocessable();

        $this->challan($no, 100);
        $this->step($no, 'close-order')->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.close_open_challans', ['no' => $no])]);
        $this->act('challans', 'DC-20261107-0001', 'cancel');
        $this->step($no, 'close-order')->assertOk();
        $this->assertSame('CLOSED', DB::table('ss_sales_orders')->where('so_no', $no)->value('state'));
    }

    public function test_challans_stay_within_what_the_order_owes_and_returns_put_it_back(): void
    {
        $no = $this->order();
        $src = collect($this->as()->getJson("/api/v1/sales-shipment/challans/source/{$no}")->assertOk()->json('data.lines'));
        $s = $src->firstWhere('ordered', 500);
        $this->assertEqualsWithDelta(525.0, (float) $s['allowed'], 0.001);

        $this->as()->postJson('/api/v1/sales-shipment/challans', $this->challanBody($no, [[$s['size_id'], 526]]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/challans', $this->challanBody($no, [[$s['size_id'], 300]]))->assertCreated()
            ->assertJsonPath('data.chalan_no', 'DC-20261107-0001')->assertJsonPath('data.gate_pass_no', 'GP-20261107-0001');
        // A draft already holds its 300: a second challan gets 225 at most.
        $this->as()->postJson('/api/v1/sales-shipment/challans', $this->challanBody($no, [[$s['size_id'], 226]]))->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.dc_over', ['line' => $s['label'], 'max' => '225'])]);
        $this->act('challans', 'DC-20261107-0001', 'deliver')->assertUnprocessable();
        $this->act('challans', 'DC-20261107-0001', 'submit')->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/challans/DC-20261107-0001', $this->challanBody($no, [[$s['size_id'], 10]]))->assertUnprocessable();
        $this->act('challans', 'DC-20261107-0001', 'deliver')->assertOk();

        $list = collect($this->as()->getJson('/api/v1/sales-shipment/orders')->json('data'))->firstWhere('so_no', $no);
        $this->assertEqualsWithDelta(300.0, (float) $list['delivery_qty'], 0.001);
        $this->assertSame('PARTIAL', $list['challan_status']);
        $this->assertSame('DC-20261107-0001', $list['chalan_no']);

        $rsrc = $this->as()->getJson('/api/v1/sales-shipment/challan-returns/source/DC-20261107-0001')->assertOk()->json('data.lines.0');
        $ret = ['chalan_no' => 'DC-20261107-0001', 'return_date' => '2026-11-07', 'reason' => 'Quality Issue', 'lines' => [['challan_line_id' => $rsrc['challan_line_id'], 'qty' => 301]]];
        $this->as()->postJson('/api/v1/sales-shipment/challan-returns', $ret)->assertUnprocessable();
        $ret['lines'][0]['qty'] = 40;
        $this->as()->postJson('/api/v1/sales-shipment/challan-returns', ['reason' => null] + $ret)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.dr_reason')]);
        $this->as()->postJson('/api/v1/sales-shipment/challan-returns', $ret)->assertCreated()->assertJsonPath('data.return_no', 'DCR-2026-000001');
        // A draft return changes nothing on the order.
        $this->assertEqualsWithDelta(225.0, (float) collect($this->as()->getJson("/api/v1/sales-shipment/challans/source/{$no}")->json('data.lines'))->firstWhere('size_id', $s['size_id'])['allowed'], 0.001);
        $this->act('challan-returns', 'DCR-2026-000001', 'confirm')->assertOk();
        $this->assertEqualsWithDelta(265.0, (float) collect($this->as()->getJson("/api/v1/sales-shipment/challans/source/{$no}")->json('data.lines'))->firstWhere('size_id', $s['size_id'])['allowed'], 0.001);

        $done = collect($this->as()->getJson('/api/v1/sales-shipment/completion')->assertOk()->json('data'))->firstWhere('so_no', $no);
        $this->assertSame([300.0, 40.0, 260.0, 740.0, 'PARTIAL', 6], [(float) $done['delivered'], (float) $done['returned'], (float) $done['net_delivered'], (float) $done['balance'], $done['status'], $done['days']]);

        // Cancelling a challan at the gate releases what it held.
        $this->challan($no, 200, $s['size_id']);
        $this->act('challans', 'DC-20261107-0002', 'submit')->assertOk();
        $this->act('challans', 'DC-20261107-0002', 'cancel')->assertOk();
        $this->assertEqualsWithDelta(265.0, (float) collect($this->as()->getJson("/api/v1/sales-shipment/challans/source/{$no}")->json('data.lines'))->firstWhere('size_id', $s['size_id'])['allowed'], 0.001);
        $this->as()->deleteJson('/api/v1/sales-shipment/challans/DC-20261107-0002')->assertUnprocessable();
    }

    public function test_an_amendment_is_one_at_a_time_never_below_what_went_out_and_applied_as_a_version(): void
    {
        $no = $this->order();
        $src = $this->as()->getJson("/api/v1/sales-shipment/amendments/source/{$no}")->assertOk()->json('data');
        $big = collect($src['sizes'])->firstWhere('qty', 500);
        $this->challan($no, 200, $big['size_id']);
        $body = ['so_no' => $no, 'amend_date' => '2026-11-07', 'reason' => 'Buyer revised the M size', 'revised_target_date' => '2026-11-25', 'revised_shipment_date' => '2026-11-30',
            'lines' => [['size_id' => $big['size_id'], 'revised_qty' => 150, 'revised_rate' => 4]]];
        $this->as()->postJson('/api/v1/sales-shipment/amendments', $body)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.am_floor', ['line' => $big['label'], 'floor' => '200'])]);
        $body['lines'][0]['revised_qty'] = 600;
        $this->as()->postJson('/api/v1/sales-shipment/amendments', $body)->assertCreated()->assertJsonPath('data.amend_no', 'SOA-2026-000001')->assertJsonPath('data.version', 1);
        $this->as()->postJson('/api/v1/sales-shipment/amendments', $body)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.am_open', ['no' => $no, 'amend' => 'SOA-2026-000001'])]);

        $this->act('amendments', 'SOA-2026-000001', 'apply')->assertUnprocessable();
        $this->act('amendments', 'SOA-2026-000001', 'submit')->assertOk();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->postJson('/api/v1/sales-shipment/amendments/SOA-2026-000001/approve')->assertForbidden();
        $this->act('amendments', 'SOA-2026-000001', 'approve')->assertOk();
        $this->act('amendments', 'SOA-2026-000001', 'apply')->assertOk()->assertJsonPath('data.state', 'APPLIED');

        $order = DB::table('ss_sales_orders')->where('so_no', $no)->first();
        $this->assertSame([1, '2026-11-25', '2026-11-30'], [(int) $order->version, substr((string) $order->delivery_target_date, 0, 10), substr((string) $order->shipment_date, 0, 10)]);
        $size = DB::table('ss_sales_order_sizes')->where('id', $big['size_id'])->first();
        $this->assertSame([600.0, 4.0], [(float) $size->qty, (float) $size->rate]);
        $snap = json_decode((string) DB::table('ss_amendments')->where('amend_no', 'SOA-2026-000001')->value('snapshot'), true);
        $this->assertSame(500.0, (float) collect($snap['sizes'])->firstWhere('id', $big['size_id'])['qty']);
        $row = $this->as()->getJson('/api/v1/sales-shipment/amendments')->assertOk()->json('data.0');
        $this->assertSame([1000.0, 1100.0], [(float) $row['prev_qty'], (float) $row['revised_qty']]);
        $this->assertTrue(collect($this->as()->getJson('/api/v1/sales-shipment/orders')->json('data'))->firstWhere('so_no', $no)['is_revised']);
    }

    public function test_a_check_bill_is_verified_approved_paid_or_rejected_back(): void
    {
        $no = $this->order();
        $this->challan($no, 300);
        $this->act('challans', 'DC-20261107-0001', 'submit');
        $this->as()->getJson('/api/v1/sales-shipment/bills/source/DC-20261107-0001')->assertOk();
        $line = $this->as()->getJson('/api/v1/sales-shipment/bills/source/DC-20261107-0001')->json('data.0');
        $body = fn (float $qty, array $extra = []) => $extra + ['customer_id' => $this->buyer, 'currency_id' => $this->bdt, 'loc_exchange_rate' => 1, 'bill_date' => '2026-11-07', 'deduction' => 50,
            'remarks' => 'Bill [BILL_NO] of [BILL_DATE]', 'action_status' => '68', 'lines' => [['challan_line_id' => $line['challan_line_id'], 'qty' => $qty, 'rate' => 4]]];
        $this->as()->postJson('/api/v1/sales-shipment/bills', $body(100))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.cb_not_delivered')]);
        $this->act('challans', 'DC-20261107-0001', 'deliver');
        $this->as()->postJson('/api/v1/sales-shipment/bills', $body(301))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/bills', $body(100, ['customer_id' => $this->other]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/bills', $body(10, ['deduction' => 41]))->assertUnprocessable();
        $bill = $this->as()->postJson('/api/v1/sales-shipment/bills', $body(200))->assertCreated()
            ->assertJsonPath('data.bill_no', 'CB-20261107-0001')->assertJsonPath('data.state', 'DRAFT')->assertJsonPath('data.remarks', 'Bill CB-20261107-0001 of 07-Nov-2026');
        $this->assertSame([800.0, 750.0], [(float) $bill->json('data.gross'), (float) $bill->json('data.net')]);
        $this->assertSame('PARTIALLY_BILLED', $this->as()->getJson('/api/v1/sales-shipment/challans')->json('data.0.bill_status'));
        $this->as()->postJson('/api/v1/sales-shipment/bills', $body(101))->assertUnprocessable();

        $this->act('bills', 'CB-20261107-0001', 'verify')->assertUnprocessable();
        $this->as()->putJson('/api/v1/sales-shipment/bills/CB-20261107-0001', $body(300, ['action_status' => '69']))->assertOk()->assertJsonPath('data.state', 'WAITING_FOR_APPROVAL');
        $this->assertSame('BILLED', $this->as()->getJson('/api/v1/sales-shipment/challans')->json('data.0.bill_status'));
        $this->act('bills', 'CB-20261107-0001', 'verify')->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/bills/CB-20261107-0001', $body(300))->assertUnprocessable();
        $this->act('bills', 'CB-20261107-0001', 'reject')->assertOk();
        $this->assertSame('WAITING_FOR_BILL', $this->as()->getJson('/api/v1/sales-shipment/challans')->json('data.0.bill_status'));
        $this->as()->putJson('/api/v1/sales-shipment/bills/CB-20261107-0001', $body(300, ['action_status' => '69']))->assertOk();
        foreach (['verify', 'approve', 'pay'] as $s) {
            $this->act('bills', 'CB-20261107-0001', $s)->assertOk();
        }
        $row = $this->as()->getJson('/api/v1/sales-shipment/bills')->json('data.0');
        $this->assertSame(['PAID', true, 1150.0], [$row['state'], $row['finalized'], (float) $row['net_amount']]);
        $this->assertStringContainsString($this->owner->name, $row['verified_by']);
    }

    public function test_the_sc_programme_goes_out_against_a_confirmed_order(): void
    {
        $draft = $this->order(confirm: false);
        $body = fn (string $so, array $lines) => ['so_no' => $so, 'party_id' => $this->other, 'sc_date' => '2026-11-07', 'target_date' => '2026-11-12', 'process' => 'Printing', 'lines' => $lines];
        $this->as()->postJson('/api/v1/sales-shipment/sc-programmes', $body($draft, []))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.not_confirmed', ['no' => $draft])]);
        $no = $this->order();
        $src = $this->as()->getJson("/api/v1/sales-shipment/sc-programmes/source/{$no}")->assertOk()->json('data');
        $this->as()->postJson('/api/v1/sales-shipment/sc-programmes', $body($no, [['size_id' => $src[0]['size_id'], 'qty' => 0]]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sc_lines')]);
        $this->as()->postJson('/api/v1/sales-shipment/sc-programmes', $body($no, [['size_id' => $src[0]['size_id'], 'qty' => 99999]]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/sc-programmes', $body($no, [['size_id' => $src[0]['size_id'], 'qty' => 100, 'rate' => 0.5], ['size_id' => $src[1]['size_id'], 'qty' => 0]]))
            ->assertCreated()->assertJsonPath('data.sc_no', 'SC-20261107-0001')->assertJsonCount(1, 'data.lines');
        $this->assertSame('SC-20261107-0001', collect($this->as()->getJson('/api/v1/sales-shipment/orders')->json('data'))->firstWhere('so_no', $no)['sc_nos']);
    }

    public function test_the_month_close_lists_exceptions_refuses_them_and_locks_the_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00', 'Asia/Dhaka'));
        $no = $this->order(date: '2026-10-20', target: '2026-10-28');
        $this->challan($no, 100, null, '2026-10-21');
        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Asia/Dhaka'));

        $ex = $this->as()->getJson('/api/v1/sales-shipment/month-close/exceptions?period=Oct/2026')->assertOk()->json('data');
        $this->assertSame(['UNDELIVERED', 'OPEN_CHALLAN'], array_column($ex['items'], 'kind'));
        $this->as()->getJson('/api/v1/sales-shipment/month-close/exceptions?period=October')->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/month-close', ['period' => '2026-12'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.mc_future')]);
        $this->as()->postJson('/api/v1/sales-shipment/month-close', ['period' => '2026-10'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.mc_exceptions', ['n' => 2])]);
        $manager = $this->token('FACTORY_MANAGER', 'fm@delta.test');
        $this->withToken($manager)->postJson('/api/v1/sales-shipment/month-close', ['period' => '2026-10', 'override' => true])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.mc_admin')]);
        $this->as()->postJson('/api/v1/sales-shipment/month-close', ['period' => 'Oct/2026', 'override' => true, 'remarks' => 'Audit cut-off'])->assertCreated()->assertJsonPath('data.orders', 1)->assertJsonPath('data.overridden', true);
        $this->as()->postJson('/api/v1/sales-shipment/month-close', ['period' => '2026-10'])->assertUnprocessable();

        $this->as()->postJson('/api/v1/sales-shipment/orders', ['receive_date' => '2026-10-30', 'delivery_target_date' => '2026-11-10', 'shipment_date' => '2026-11-15'] + $this->orderBody())
            ->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.month_closed', ['period' => 'Oct/2026'])]);
        $this->act('challans', 'DC-20261021-0001', 'submit')->assertUnprocessable();
        $row = $this->as()->getJson('/api/v1/sales-shipment/month-close')->assertOk()->json('data.0');
        $this->assertSame(['Oct/2026', 1, $this->owner->name], [$row['label'], $row['orders'], $row['overridden_by']]);

        $this->as()->deleteJson('/api/v1/sales-shipment/month-close/2026-10')->assertOk();
        $this->act('challans', 'DC-20261021-0001', 'submit')->assertOk();
    }

    public function test_who_may(): void
    {
        $no = $this->order();
        $keeper = $this->token('STOREKEEPER', 'keeper@delta.test');
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/orders')->assertOk();
        $this->withToken($keeper)->postJson('/api/v1/sales-shipment/orders', $this->orderBody())->assertForbidden();
        $this->withToken($keeper)->postJson('/api/v1/sales-shipment/challans', $this->challanBody($no, [[DB::table('ss_sales_order_sizes')->value('id'), 10]]))->assertCreated();
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/bills')->assertForbidden();
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/month-close')->assertForbidden();
        $auditor = $this->token('AUDITOR', 'audit@delta.test');
        foreach (['orders', 'confirmations', 'amendments', 'challans', 'challan-returns', 'bills', 'completion'] as $path) {
            $this->withToken($auditor)->getJson("/api/v1/sales-shipment/{$path}")->assertOk();
        }
        $this->withToken($auditor)->postJson("/api/v1/sales-shipment/orders/{$no}/close-order")->assertForbidden();
        $viewer = $this->token('VIEWER', 'viewer@delta.test');
        $this->withToken($viewer)->getJson('/api/v1/sales-shipment/orders')->assertOk();
        $this->withToken($viewer)->postJson('/api/v1/sales-shipment/orders', $this->orderBody())->assertForbidden();
        $this->withToken($viewer)->postJson("/api/v1/sales-shipment/orders/{$no}/close-order")->assertForbidden();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/sales-shipment/challans')->assertForbidden();
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function orderBody(string $date = '2026-11-07', string $target = '2026-11-20'): array
    {
        return [
            'search_for' => 'SALES_ORDER', 'so_type' => 'GENERAL', 'customer_id' => $this->buyer, 'receive_date' => $date, 'program_by' => 'Rahim', 'program_to' => 'Karim', 'bill_to_id' => $this->buyer,
            'delivery_target_date' => $target, 'same_as_bill_to' => true, 'payment_method' => 'CASH', 'currency_id' => $this->bdt, 'conv_rate' => 1, 'mode_of_shipment' => 'By Van', 'po_wo_no' => 'WO-7781',
            'additional_qty_pct' => 5, 'shipment_date' => '2026-11-25', 'is_poly_item' => true, 'item_id' => $this->poly,
            'lines' => [
                ['sl_no' => 1, 'buyer' => 'H&M', 'order_ref' => 'ORD-11', 'style_ref' => 'ST-1', 'item_id' => $this->poly, 'gmt_color' => 'Navy', 'rate' => 4],
                ['sl_no' => 2, 'buyer' => 'H&M', 'order_ref' => 'ORD-11', 'style_ref' => 'ST-1', 'item_id' => $this->poly, 'gmt_color' => 'Black', 'rate' => 3],
            ],
            'sizes' => [['sl_no' => 1, 'size' => 'M', 'qty' => 500], ['sl_no' => 1, 'size' => 'L', 'qty' => 200], ['sl_no' => 2, 'size' => 'M', 'qty' => 300]],
            'particulars' => [['sl_no' => 1, 'particular_id' => DB::table('ss_particulars')->where('name', 'Carton Dimension')->value('id') ?? $this->particular(), 'value' => '12 x 16 in']],
        ];
    }

    private function particular(): string
    {
        $this->as()->getJson('/api/v1/sales-shipment/options');

        return (string) DB::table('ss_particulars')->where('name', 'Carton Dimension')->value('id');
    }

    /** Books an order (confirmed unless told otherwise) and returns its number. */
    private function order(bool $confirm = true, string $date = '2026-11-07', string $target = '2026-11-13'): string
    {
        $no = $this->as()->postJson('/api/v1/sales-shipment/orders', $this->orderBody($date, $target))->assertCreated()->json('data.so_no');
        if ($confirm) {
            foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
                $this->step($no, $s)->assertOk();
            }
        }

        return $no;
    }

    /** @param list<array{0: string, 1: float|int}> $lines */
    private function challanBody(string $so, array $lines, string $date = '2026-11-07'): array
    {
        return ['so_no' => $so, 'customer_id' => $this->buyer, 'chalan_date' => $date, 'store_id' => $this->store, 'delivery_target_date' => '2026-11-20', 'mode_of_shipment' => 'By Truck',
            'currency_id' => $this->bdt, 'conv_rate' => 1, 'carrier_name' => 'Karim Transport', 'lines' => array_map(fn ($l) => ['size_id' => $l[0], 'qty' => $l[1]], $lines)];
    }

    private function challan(string $so, float $qty, ?string $sizeId = null, string $date = '2026-11-07'): void
    {
        $sizeId ??= (string) DB::table('ss_sales_order_sizes as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->where('o.so_no', $so)->where('s.qty', 500)->value('s.id');
        $this->as()->postJson('/api/v1/sales-shipment/challans', $this->challanBody($so, [[$sizeId, $qty]], $date))->assertCreated();
    }

    private function step(string $no, string $action): TestResponse
    {
        return $this->as()->postJson("/api/v1/sales-shipment/orders/{$no}/{$action}");
    }

    private function act(string $kind, string $no, string $action): TestResponse
    {
        return $this->as()->postJson("/api/v1/sales-shipment/{$kind}/{$no}/{$action}");
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
