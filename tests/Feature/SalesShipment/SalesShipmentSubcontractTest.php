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
 * Sales & Shipment → Sub-Contract (AnnotechERP 21/03): work put out on an
 * S/C programme's lines (never more than the contract owes), delivered,
 * finalized or cancelled; receipts back against a delivered chalan, good
 * and rejected, never more than is still out; returns sent back to the
 * party moving the balance once confirmed; what is pending and overdue;
 * the reconciliation that squares and closes a chalan, reopened only by an
 * administrator — and who may.
 */
class SalesShipmentSubcontractTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $buyer;

    private string $printer;

    private string $bdt;

    private string $store;

    private string $sc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $party = fn (string $code, string $name) => MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->buyer = $party('SUP-1', 'Arafah Knit Composite');
        $this->printer = $party('SUB-1', 'Square Printers');
        $this->bdt = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $this->bdt, 'company_id' => $this->delta->id, 'code' => 'BDT', 'name' => 'Bangladeshi Taka', 'exchange_rate' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $item = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'PL-01', 'name' => 'Poly Bag', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true])->id;
        $wh = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $wh->id, 'name' => 'Accessories Store', 'code' => 'ST-ACC', 'active' => true])->id;

        $so = $this->as()->postJson('/api/v1/sales-shipment/orders', [
            'search_for' => 'SALES_ORDER', 'so_type' => 'GENERAL', 'customer_id' => $this->buyer, 'receive_date' => '2026-11-01', 'program_by' => 'Rahim', 'program_to' => 'Karim', 'bill_to_id' => $this->buyer,
            'delivery_target_date' => '2026-11-20', 'same_as_bill_to' => true, 'payment_method' => 'CASH', 'currency_id' => $this->bdt, 'conv_rate' => 1, 'mode_of_shipment' => 'By Van', 'po_wo_no' => 'WO-1',
            'shipment_date' => '2026-11-25', 'lines' => [['sl_no' => 1, 'buyer' => 'H&M', 'style_ref' => 'ST-1', 'item_id' => $item, 'gmt_color' => 'Navy', 'rate' => 4]],
            'sizes' => [['sl_no' => 1, 'size' => 'M', 'qty' => 500], ['sl_no' => 1, 'size' => 'L', 'qty' => 300]],
        ])->assertCreated()->json('data.so_no');
        foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
            $this->as()->postJson("/api/v1/sales-shipment/orders/{$so}/{$s}")->assertOk();
        }
        $sizes = DB::table('ss_sales_order_sizes')->orderBy('sort_order')->pluck('id');
        $this->sc = $this->as()->postJson('/api/v1/sales-shipment/sc-programmes', ['so_no' => $so, 'party_id' => $this->printer, 'sc_date' => '2026-11-02', 'target_date' => '2026-11-10', 'process' => 'Printing',
            'lines' => [['size_id' => $sizes[0], 'qty' => 400, 'rate' => 0.5], ['size_id' => $sizes[1], 'qty' => 200, 'rate' => 0.5]]])->assertCreated()->json('data.sc_no');
    }

    public function test_work_goes_out_within_the_contract_and_is_delivered_finalized_or_cancelled(): void
    {
        $contracts = $this->as()->getJson('/api/v1/sales-shipment/sc-deliveries/contracts')->assertOk()->json('data.0');
        $this->assertSame([$this->sc, 600.0, 'Printing'], [$contracts['sc_no'], (float) $contracts['due'], $contracts['process']]);
        $lines = $this->as()->getJson("/api/v1/sales-shipment/sc-deliveries/source/{$this->sc}")->assertOk()->json('data.lines');
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', $this->deliveryBody([[$lines[0]['programme_line_id'], 401]]))->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.scd_over', ['line' => $lines[0]['label'], 'max' => '400'])]);
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', ['delivery_target_date' => '2026-11-01'] + $this->deliveryBody([[$lines[0]['programme_line_id'], 10]]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', $this->deliveryBody([[$lines[0]['programme_line_id'], 300], [$lines[1]['programme_line_id'], 200]]))->assertCreated()
            ->assertJsonPath('data.chalan_no', 'SCD-20261107-0001')->assertJsonPath('data.gate_pass_no', 'SGP-20261107-0001')->assertJsonPath('data.lines.0.rate', 0.5);
        // The draft holds its 300: a second chalan may send 100 at most.
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', $this->deliveryBody([[$lines[0]['programme_line_id'], 101]]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', $this->deliveryBody([[$lines[0]['programme_line_id'], 100]]))->assertCreated();
        $this->act('sc-deliveries', 'SCD-20261107-0002', 'cancel')->assertOk();
        $this->assertSame(100.0, (float) $this->as()->getJson('/api/v1/sales-shipment/sc-deliveries/contracts')->json('data.0.due'));

        $this->act('sc-deliveries', 'SCD-20261107-0001', 'finalize')->assertUnprocessable();
        $this->act('sc-deliveries', 'SCD-20261107-0001', 'deliver')->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/sc-deliveries/SCD-20261107-0001', ['carrier_name' => 'Rev'] + $this->deliveryBody([[$lines[0]['programme_line_id'], 300]]))->assertOk()->assertJsonPath('data.carrier_name', 'Rev');
        $this->act('sc-deliveries', 'SCD-20261107-0001', 'finalize')->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/sc-deliveries/SCD-20261107-0001', $this->deliveryBody([[$lines[0]['programme_line_id'], 300]]))->assertUnprocessable();
        $this->act('sc-deliveries', 'SCD-20261107-0001', 'cancel')->assertUnprocessable();
        $this->as()->deleteJson('/api/v1/sales-shipment/sc-deliveries/SCD-20261107-0001')->assertUnprocessable();
        $row = collect($this->as()->getJson('/api/v1/sales-shipment/sc-deliveries')->json('data'))->firstWhere('chalan_no', 'SCD-20261107-0001');
        $this->assertSame([true, true, 300.0, 300.0], [$row['finalized'], $row['is_revised'], (float) $row['total_qty'], (float) $row['out_qty']]);
    }

    public function test_receipts_come_back_good_or_rejected_never_beyond_what_is_out_and_returns_send_it_back(): void
    {
        $line = $this->delivered(300);
        $src = $this->as()->getJson('/api/v1/sales-shipment/sc-receipts/source/SCD-20261107-0001')->assertOk()->json('data.lines.0');
        $this->assertSame([300.0, 300.0], [(float) $src['sent'], (float) $src['balance']]);
        $body = fn (float $q, float $rj, array $extra = []) => $extra + ['chalan_no' => 'SCD-20261107-0001', 'mrr_date' => '2026-11-08', 'store_id' => $this->store, 'customer_id' => $this->printer,
            'receive_type' => 'FINISHED_GOODS', 'lines' => [['delivery_line_id' => $line, 'qty' => $q, 'rejected_qty' => $rj]]];
        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka'));
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', $body(290, 11))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.scr_over', ['line' => $src['label'], 'max' => '300'])]);
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', $body(10, 0, ['mrr_date' => '2026-11-06']))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', $body(0, 0))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.scr_lines')]);
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', $body(240, 20))->assertCreated()->assertJsonPath('data.mrr_no', 'SCR-20261108-0001');
        $this->assertEqualsWithDelta(40.0, (float) $this->as()->getJson('/api/v1/sales-shipment/sc-deliveries/outstanding')->json('data.0.out'), 0.001);
        $this->as()->putJson('/api/v1/sales-shipment/sc-receipts/SCR-20261108-0001', $body(250, 20))->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/sc-deliveries/SCD-20261107-0001', $this->deliveryBody([[$this->programmeLine(), 300]]))->assertUnprocessable();

        // The 20 rejected go back for rework: only a confirmed return moves the balance.
        $rl = $this->as()->getJson('/api/v1/sales-shipment/sc-returns/source/SCR-20261108-0001')->assertOk()->json('data.lines.0');
        $this->assertSame([250.0, 20.0, 270.0], [(float) $rl['received'], (float) $rl['rejected'], (float) $rl['returnable']]);
        $ret = ['mrr_no' => 'SCR-20261108-0001', 'return_date' => '2026-11-08', 'reason' => 'Rework', 'lines' => [['receipt_line_id' => $rl['receipt_line_id'], 'qty' => 271]]];
        $this->as()->postJson('/api/v1/sales-shipment/sc-returns', $ret)->assertUnprocessable();
        $ret['lines'][0]['qty'] = 20;
        $this->as()->postJson('/api/v1/sales-shipment/sc-returns', ['reason' => null] + $ret)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sct_reason')]);
        $this->as()->postJson('/api/v1/sales-shipment/sc-returns', $ret)->assertCreated()->assertJsonPath('data.return_no', 'SCRT-2026-000001');
        $this->assertEqualsWithDelta(30.0, (float) $this->as()->getJson('/api/v1/sales-shipment/sc-deliveries/outstanding')->json('data.0.out'), 0.001);
        $this->act('sc-receipts', 'SCR-20261108-0001', 'cancel')->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.scr_has_returns', ['no' => 'SCR-20261108-0001'])]);
        $this->act('sc-returns', 'SCRT-2026-000001', 'confirm')->assertOk();
        $this->assertEqualsWithDelta(50.0, (float) $this->as()->getJson('/api/v1/sales-shipment/sc-deliveries/outstanding')->json('data.0.out'), 0.001);

        $p = collect($this->as()->getJson('/api/v1/sales-shipment/sc-pending')->assertOk()->json('data'))->first();
        $this->assertSame([300.0, 250.0, 20.0, 20.0, 50.0, 'PENDING', 0], [(float) $p['delivered'], (float) $p['received'], (float) $p['rejected'], (float) $p['returned'], (float) $p['pending'], $p['status'], $p['overdue']]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00', 'Asia/Dhaka'));
        $p = collect($this->as()->getJson('/api/v1/sales-shipment/sc-pending')->json('data'))->first();
        $this->assertSame(['OVERDUE', 3], [$p['status'], $p['overdue']]);

        $this->act('sc-receipts', 'SCR-20261108-0001', 'finalize')->assertOk();
        $this->as()->putJson('/api/v1/sales-shipment/sc-receipts/SCR-20261108-0001', $body(250, 20))->assertUnprocessable();
        $this->as()->getJson('/api/v1/sales-shipment/sc-receipts')->assertOk()->assertJsonPath('data.0.received_qty', 250)->assertJsonPath('data.0.sc_type', 'Printing');
    }

    public function test_a_reconciliation_squares_closes_and_only_an_administrator_reopens(): void
    {
        $line = $this->delivered(300);
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', ['chalan_no' => 'SCD-20261107-0001', 'mrr_date' => '2026-11-07', 'store_id' => $this->store, 'customer_id' => $this->printer,
            'receive_type' => 'FINISHED_GOODS', 'lines' => [['delivery_line_id' => $line, 'qty' => 280, 'rejected_qty' => 5]]])->assertCreated();
        $recon = fn (float $short, array $extra = []) => $extra + ['chalan_no' => 'SCD-20261107-0001', 'recon_date' => '2026-11-07', 'lines' => [['delivery_line_id' => $line, 'short_qty' => $short]]];
        $this->as()->postJson('/api/v1/sales-shipment/sc-reconciliations', $recon(16))->assertUnprocessable();
        $r = $this->as()->postJson('/api/v1/sales-shipment/sc-reconciliations', $recon(0))->assertCreated()->assertJsonPath('data.recon_no', 'SCRC-2026-000001')->assertJsonPath('data.state', 'PENDING')
            ->assertJsonPath('data.lines.0.balance', 15);
        $this->as()->postJson('/api/v1/sales-shipment/sc-reconciliations', $recon(15))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.scc_open', ['no' => 'SCD-20261107-0001', 'recon' => 'SCRC-2026-000001'])]);
        $this->act('sc-reconciliations', 'SCRC-2026-000001', 'reconcile')->assertUnprocessable();
        $this->as()->putJson('/api/v1/sales-shipment/sc-reconciliations/SCRC-2026-000001', $recon(15))->assertOk()->assertJsonPath('data.state', 'SHORT')->assertJsonPath('data.lines.0.balance', 0);
        $this->act('sc-reconciliations', 'SCRC-2026-000001', 'reconcile')->assertOk()->assertJsonPath('data.state', 'RECONCILED');
        $this->assertTrue((bool) DB::table('ss_sc_deliveries')->value('closed'));
        $row = $this->as()->getJson('/api/v1/sales-shipment/sc-reconciliations')->json('data.0');
        $this->assertSame([300.0, 280.0, 5.0, 15.0, 0.0], [(float) $row['delivered'], (float) $row['received'], (float) $row['rejected'], (float) $row['short'], (float) $row['balance']]);

        // A closed chalan takes no receipt, its pending line counts as completed, and the reconciliation is locked.
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', ['chalan_no' => 'SCD-20261107-0001', 'mrr_date' => '2026-11-07', 'store_id' => $this->store, 'customer_id' => $this->printer,
            'receive_type' => 'LEFT_OVER', 'lines' => [['delivery_line_id' => $line, 'qty' => 1]]])->assertUnprocessable();
        $this->assertSame(['COMPLETED', 0.0], [$this->as()->getJson('/api/v1/sales-shipment/sc-pending')->json('data.0.status'), (float) $this->as()->getJson('/api/v1/sales-shipment/sc-pending')->json('data.0.pending')]);
        $this->as()->putJson('/api/v1/sales-shipment/sc-reconciliations/SCRC-2026-000001', $recon(0))->assertUnprocessable();
        $this->as()->getJson('/api/v1/sales-shipment/sc-deliveries/outstanding')->assertOk()->assertJsonCount(0, 'data');

        $manager = $this->token('FACTORY_MANAGER', 'fm@delta.test');
        $this->withToken($manager)->postJson('/api/v1/sales-shipment/sc-reconciliations/SCRC-2026-000001/reopen')->assertForbidden();
        $this->act('sc-reconciliations', 'SCRC-2026-000001', 'reopen')->assertOk()->assertJsonPath('data.state', 'SHORT');
        $this->assertFalse((bool) DB::table('ss_sc_deliveries')->value('closed'));
    }

    public function test_a_draft_sc_return_stops_the_month_close_and_who_may(): void
    {
        $line = $this->delivered(100);
        $this->as()->postJson('/api/v1/sales-shipment/sc-receipts', ['chalan_no' => 'SCD-20261107-0001', 'mrr_date' => '2026-11-07', 'store_id' => $this->store, 'customer_id' => $this->printer,
            'receive_type' => 'REJECTION', 'lines' => [['delivery_line_id' => $line, 'qty' => 0, 'rejected_qty' => 10]]])->assertCreated();
        $rl = $this->as()->getJson('/api/v1/sales-shipment/sc-returns/source/SCR-20261107-0001')->json('data.lines.0');
        $this->as()->postJson('/api/v1/sales-shipment/sc-returns', ['mrr_no' => 'SCR-20261107-0001', 'return_date' => '2026-11-07', 'reason' => 'Rejection Return', 'lines' => [['receipt_line_id' => $rl['receipt_line_id'], 'qty' => 10]]])->assertCreated();
        $kinds = array_column($this->as()->getJson('/api/v1/sales-shipment/month-close/exceptions?period=2026-11')->json('data.items'), 'kind');
        $this->assertContains('DRAFT_SC_RETURN', $kinds);

        $keeper = $this->token('STOREKEEPER', 'keeper@delta.test');
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/sc-pending')->assertOk();
        $this->withToken($keeper)->postJson('/api/v1/sales-shipment/sc-returns/SCRT-2026-000001/confirm')->assertOk();
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/options')->assertOk();
        $auditor = $this->token('AUDITOR', 'audit@delta.test');
        foreach (['sc-deliveries', 'sc-receipts', 'sc-pending', 'sc-returns', 'sc-reconciliations'] as $path) {
            $this->withToken($auditor)->getJson("/api/v1/sales-shipment/{$path}")->assertOk();
        }
        $this->withToken($auditor)->postJson('/api/v1/sales-shipment/sc-deliveries/SCD-20261107-0001/finalize')->assertForbidden();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/sales-shipment/sc-pending')->assertForbidden();
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function programmeLine(int $i = 0): string
    {
        return (string) DB::table('ss_sc_programme_lines')->orderByDesc('qty')->skip($i)->value('id');
    }

    /** @param list<array{0: string, 1: float|int}> $lines */
    private function deliveryBody(array $lines): array
    {
        return ['sc_no' => $this->sc, 'customer_id' => $this->printer, 'chalan_date' => '2026-11-07', 'store_id' => $this->store, 'delivery_target_date' => '2026-11-10', 'mode_of_shipment' => 'By Van',
            'carrier_name' => 'Karim Transport', 'lines' => array_map(fn ($l) => ['programme_line_id' => $l[0], 'qty' => $l[1]], $lines)];
    }

    /** One chalan of the 400-piece line, delivered; returns its delivery line id. */
    private function delivered(float $qty): string
    {
        $this->as()->postJson('/api/v1/sales-shipment/sc-deliveries', $this->deliveryBody([[$this->programmeLine(), $qty]]))->assertCreated();
        $this->act('sc-deliveries', 'SCD-20261107-0001', 'deliver')->assertOk();

        return (string) DB::table('ss_sc_delivery_lines')->value('id');
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
