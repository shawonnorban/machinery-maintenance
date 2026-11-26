<?php

declare(strict_types=1);

namespace Tests\Feature\SalesShipment;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Sales & Shipment → Shipment (AnnotechERP 21/05): a planned shipment
 * booked, its containers recorded, invoiced and packed (never beyond what
 * it carries; carton runs from 1, never overlapping, gross not below net),
 * its documents handed over (with the scan attached); its stage following
 * the paperwork; confirmed only once every readiness check passes — the
 * final invoices written as shipped against the order — unconfirmed,
 * closed and reopened only by an administrator; and who may.
 */
class SalesShipmentShipmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $bdt;

    private string $so;

    private string $shp = 'SHP-2026-000001';

    /** @var list<string> */
    private array $lines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $buyer = MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => 'SUP-1', 'name' => 'Arafah Knit Composite', 'supplier_type' => 'LOCAL_NON_EPZ', 'address' => 'Gazipur', 'active' => true])->id;
        $this->bdt = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $this->bdt, 'company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 110, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $item = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'PL-01', 'name' => 'Poly Bag', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true])->id;
        $this->so = $this->as()->postJson('/api/v1/sales-shipment/orders', [
            'search_for' => 'SALES_ORDER', 'so_type' => 'GENERAL', 'customer_id' => $buyer, 'receive_date' => '2026-11-01', 'program_by' => 'Rahim', 'program_to' => 'Karim', 'bill_to_id' => $buyer,
            'delivery_target_date' => '2026-11-20', 'same_as_bill_to' => true, 'payment_method' => 'LC', 'payment_term' => 'At Sight', 'currency_id' => $this->bdt, 'conv_rate' => 110, 'mode_of_shipment' => 'By Truck',
            'po_wo_no' => 'WO-1', 'shipment_date' => '2026-11-25', 'lines' => [['sl_no' => 1, 'buyer' => 'H&M', 'item_id' => $item, 'gmt_color' => 'Navy', 'rate' => 2]],
            'sizes' => [['sl_no' => 1, 'size' => 'M', 'qty' => 500], ['sl_no' => 1, 'size' => 'L', 'qty' => 300]],
        ])->assertCreated()->json('data.so_no');
        foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
            $this->as()->postJson("/api/v1/sales-shipment/orders/{$this->so}/{$s}")->assertOk();
        }
        $sizes = DB::table('ss_sales_order_sizes')->orderBy('sort_order')->pluck('id');
        $this->as()->postJson('/api/v1/sales-shipment/shipments', ['so_no' => $this->so, 'plan_date' => '2026-11-07', 'shipment_date' => '2026-11-25', 'shipment_mode' => 'Sea', 'destination_port' => 'Hamburg',
            'lines' => [['size_id' => $sizes[0], 'qty' => 400, 'carton_qty' => 20], ['size_id' => $sizes[1], 'qty' => 300, 'carton_qty' => 15]]])->assertCreated();
        $this->lines = DB::table('ss_shipment_lines')->orderBy('sort_order')->pluck('id')->all();
    }

    public function test_bookings_and_containers_follow_the_shipment_and_move_its_stage(): void
    {
        $booking = ['shipment_no' => $this->shp, 'booking_date' => '2026-11-07', 'forwarder' => 'Expo Freight', 'shipping_line' => 'Maersk', 'vessel_name' => 'MAERSK KOWLOON', 'voyage_no' => '245W',
            'etd' => '2026-11-24', 'eta' => '2026-12-20', 'port_of_loading' => 'Chattogram', 'container_type' => "40' HC", 'container_qty' => 1, 'status' => 'REQUESTED'];
        $this->as()->postJson('/api/v1/sales-shipment/bookings', ['eta' => '2026-11-20'] + $booking)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.eta_before_etd')]);
        $this->as()->postJson('/api/v1/sales-shipment/bookings', ['forwarder' => null] + $booking)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.bk_forwarder')]);
        $this->as()->postJson('/api/v1/sales-shipment/bookings', $booking)->assertCreated()->assertJsonPath('data.booking_no', 'BKG-2026-000001')->assertJsonPath('data.stage', 'PLANNING');
        $this->act('bookings', 'BKG-2026-000001', 'complete')->assertUnprocessable();
        $this->act('bookings', 'BKG-2026-000001', 'confirm')->assertOk();
        $this->assertSame('BOOKED', $this->stage());
        $row = $this->as()->getJson('/api/v1/sales-shipment/bookings')->json('data.0');
        $this->assertSame(['MAERSK KOWLOON / 245W', "1 × 40' HC", 'CONFIRMED'], [$row['vessel'], $row['containers'], $row['status']]);

        $ct = ['shipment_no' => $this->shp, 'booking_no' => 'BKG-2026-000001', 'container_no' => 'msku1234567', 'seal_no' => 'SL-99', 'gross_weight' => 4200, 'net_weight' => 3900, 'cbm' => 61.5, 'carton_qty' => 35];
        $this->as()->postJson('/api/v1/sales-shipment/containers', ['container_no' => 'MSK123'] + $ct)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ct_no')]);
        $this->as()->postJson('/api/v1/sales-shipment/containers', $ct)->assertCreated()->assertJsonPath('data.container_no', 'MSKU1234567');
        $this->as()->postJson('/api/v1/sales-shipment/containers', $ct)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ct_twice', ['no' => 'MSKU1234567', 'shipment' => $this->shp])]);
        $c = $this->as()->getJson('/api/v1/sales-shipment/containers')->json('data.0');
        $this->assertSame(["40' HC", 'MAERSK KOWLOON / 245W', '2026-11-24', 61.5], [$c['type'], $c['vessel'], $c['etd'], (float) $c['cbm']]);
        $this->as()->putJson("/api/v1/sales-shipment/containers/{$this->shp}/MSKU1234567", ['seal_no' => 'SL-100'] + $ct)->assertOk();
        $this->act('bookings', 'BKG-2026-000001', 'cancel')->assertOk();
        $this->assertSame('PLANNING', $this->stage());
    }

    public function test_invoice_and_packing_stay_within_the_shipment_and_lock_its_plan(): void
    {
        $inv = fn (float $m, float $l = 0) => ['shipment_no' => $this->shp, 'invoice_date' => '2026-11-08', 'currency_id' => $this->bdt, 'consignee' => "H&M Hennes\nHamburg", 'shipment_mode' => 'Sea',
            'lines' => array_values(array_filter([['shipment_line_id' => $this->lines[0], 'qty' => $m, 'carton_qty' => 20], $l ? ['shipment_line_id' => $this->lines[1], 'qty' => $l, 'unit_price' => 2.5] : null]))];
        $src = $this->as()->getJson("/api/v1/sales-shipment/invoices/source/{$this->shp}")->assertOk()->json('data');
        $this->assertSame(['At Sight', 'Hamburg', 400.0, 2.0], [$src['payment_terms'], $src['port'], (float) $src['lines'][0]['balance'], (float) $src['lines'][0]['rate']]);
        $this->as()->postJson('/api/v1/sales-shipment/invoices', $inv(401))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/invoices', ['consignee' => null] + $inv(10))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.si_consignee')]);
        $this->as()->postJson('/api/v1/sales-shipment/invoices', $inv(400, 300))->assertCreated()->assertJsonPath('data.invoice_no', 'INV-2026-000001')->assertJsonPath('data.lines.0.unit_price', 2);
        $this->as()->postJson('/api/v1/sales-shipment/invoices', $inv(1))->assertUnprocessable();
        $row = $this->as()->getJson('/api/v1/sales-shipment/invoices')->json('data.0');
        $this->assertSame([700.0, 1550.0, 'USD', 'H&M Hennes'], [(float) $row['total_qty'], (float) $row['total_amount'], $row['currency'], $row['consignee']]);
        // An invoice on the shipment locks its plan lines.
        $this->as()->putJson("/api/v1/sales-shipment/shipments/{$this->shp}", ['so_no' => $this->so, 'plan_date' => '2026-11-07', 'shipment_date' => '2026-11-25', 'shipment_mode' => 'Sea',
            'lines' => [['size_id' => DB::table('ss_shipment_lines')->where('id', $this->lines[0])->value('size_id'), 'qty' => 10]]])->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.sp_papered', ['no' => $this->shp, 'stage' => __('salesshipment.sp.stage.PLANNING')])]);
        $this->act('invoices', 'INV-2026-000001', 'finalize')->assertOk();
        $this->assertSame('DOCUMENTATION', $this->stage());
        $this->as()->putJson('/api/v1/sales-shipment/invoices/INV-2026-000001', $inv(400, 300))->assertUnprocessable();

        $run = fn (string $line, int $from, int $to, float $per, float $net = 100, float $gross = 110) => ['shipment_line_id' => $line, 'color' => 'Navy', 'carton_from' => $from, 'carton_to' => $to, 'pcs_per_carton' => $per,
            'net_weight' => $net, 'gross_weight' => $gross, 'cbm' => 1.2];
        $pl = fn (array $runs) => ['shipment_no' => $this->shp, 'packing_date' => '2026-11-08', 'invoice_no' => 'INV-2026-000001', 'lines' => $runs];
        $this->as()->postJson('/api/v1/sales-shipment/packing-lists', $pl([$run($this->lines[0], 0, 19, 20)]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.pl_from')]);
        $this->as()->postJson('/api/v1/sales-shipment/packing-lists', $pl([$run($this->lines[0], 1, 20, 20), $run($this->lines[1], 20, 34, 20)]))->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.pl_overlap', ['row' => 2, 'other' => 1])]);
        $this->as()->postJson('/api/v1/sales-shipment/packing-lists', $pl([$run($this->lines[0], 1, 20, 20, 120, 110)]))->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.pl_gross', ['row' => 1])]);
        $this->as()->postJson('/api/v1/sales-shipment/packing-lists', $pl([$run($this->lines[0], 1, 21, 20)]))->assertUnprocessable();
        $this->as()->postJson('/api/v1/sales-shipment/packing-lists', $pl([$run($this->lines[0], 1, 20, 20), $run($this->lines[1], 21, 35, 20)]))->assertCreated()->assertJsonPath('data.packing_no', 'PL-2026-000001');
        $p = $this->as()->getJson('/api/v1/sales-shipment/packing-lists')->json('data.0');
        $this->assertSame([35, 700.0, 'INV-2026-000001'], [$p['cartons'], (float) $p['pcs'], $p['invoice_no']]);
        $this->act('packing-lists', 'PL-2026-000001', 'finalize')->assertOk();
        $this->act('invoices', 'INV-2026-000001', 'cancel')->assertOk();
        $this->assertSame(400.0, (float) $this->as()->getJson("/api/v1/sales-shipment/invoices/source/{$this->shp}")->json('data.lines.0.balance'));
    }

    public function test_confirm_waits_for_every_check_writes_shipped_and_closing_is_reopened_only_by_an_administrator(): void
    {
        $this->paperwork();
        $check = $this->as()->getJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/check")->assertOk()->json('data');
        $this->assertSame(['qty' => true, 'booking' => true, 'container' => true, 'invoice' => true, 'packing' => true, 'documents' => false], $check['checks']);
        $this->as()->postJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/confirm")->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.cf_not_ready', ['no' => $this->shp, 'checks' => __('salesshipment.cf.check.documents')])]);

        $this->as()->postJson('/api/v1/sales-shipment/handovers', ['handover_date' => '2026-11-09', 'shipment_no' => $this->shp, 'invoice_no' => 'INV-2026-000001', 'packing_no' => 'PL-2026-000001', 'exp_no' => 'EXP-11'])
            ->assertCreated()->assertJsonPath('data.handover_no', 'DH-2026-000001');
        $this->assertSame($this->owner->name, DB::table('ss_doc_handovers')->value('submitted_by'));
        $file = $this->as()->post('/api/v1/sales-shipment/handovers/DH-2026-000001/attachment', ['upload' => UploadedFile::fake()->create('docs.pdf', 120, 'application/pdf')], ['Accept' => 'application/json'])->assertOk()->json('data.file_id');
        $this->as()->get("/api/v1/files/{$file}/download")->assertRedirect();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson("/api/v1/files/{$file}/download")->assertForbidden();
        $this->act('handovers', 'DH-2026-000001', 'handed-over')->assertOk();
        $this->act('handovers', 'DH-2026-000001', 'received')->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.dh_receiver', ['no' => 'DH-2026-000001'])]);
        // Documents follow Brickland's settings rule (21/06): the EXP and the B/L or AWB filed.
        $this->assertSame('DOCUMENTATION', $this->stage());
        $doc = fn (string $family, string $type, string $no) => $this->as()->postJson("/api/v1/sales-shipment/export-docs/{$family}", ['shipment_no' => $this->shp, 'doc_type' => $type, 'document_no' => $no, 'document_date' => '2026-11-09'])->assertCreated();
        $doc('export', 'EXP', 'EXP-11');
        $this->assertSame('DOCUMENTATION', $this->stage());
        $doc('blawb', 'BILL_OF_LADING', 'MAEU245W001');
        $this->assertSame('READY', $this->stage());

        $this->withToken($this->token('FACTORY_MANAGER', 'fm1@delta.test'))->postJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/confirm")->assertOk();
        $this->assertSame([400.0, 300.0], DB::table('ss_shipment_lines')->orderBy('sort_order')->pluck('shipped_qty')->map(fn ($v) => (float) $v)->all());
        $order = collect($this->as()->getJson('/api/v1/sales-shipment/orders')->json('data'))->firstWhere('so_no', $this->so);
        $this->assertSame([700.0, 'PARTIAL'], [(float) $order['shipped_qty'], $order['shipment_status']]);
        $this->as()->postJson('/api/v1/sales-shipment/bookings', ['shipment_no' => $this->shp, 'booking_date' => '2026-11-10', 'forwarder' => 'X', 'status' => 'REQUESTED'])->assertUnprocessable();
        $this->act('invoices', 'INV-2026-000001', 'cancel')->assertUnprocessable();

        $this->as()->postJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/unconfirm")->assertOk()->assertJsonPath('data.stage', 'READY');
        $this->assertSame(0.0, (float) DB::table('ss_shipment_lines')->sum('shipped_qty'));
        $this->as()->postJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/confirm")->assertOk();
        $this->as()->postJson("/api/v1/sales-shipment/closings/{$this->shp}/close")->assertOk()->assertJsonPath('data.stage', 'CLOSED');
        $c = $this->as()->getJson('/api/v1/sales-shipment/closings')->json('data.0');
        $this->assertSame([700.0, 1550.0, 'MSKU1234567', 1, 'MAERSK KOWLOON / 245W'], [(float) $c['shipped_qty'], (float) $c['invoice_value'], $c['containers'], $c['documents'], $c['vessel']]);
        $this->as()->postJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/unconfirm")->assertUnprocessable();
        $this->withToken($this->token('FACTORY_MANAGER', 'fm@delta.test'))->postJson("/api/v1/sales-shipment/closings/{$this->shp}/reopen")->assertForbidden();
        $this->as()->postJson("/api/v1/sales-shipment/closings/{$this->shp}/reopen")->assertOk()->assertJsonPath('data.stage', 'CONFIRMED');

        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson('/api/v1/sales-shipment/confirmations-shipment')->assertOk();
        $this->withToken($this->token('AUDITOR', 'audit2@delta.test'))->postJson("/api/v1/sales-shipment/closings/{$this->shp}/close")->assertForbidden();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/sales-shipment/bookings')->assertForbidden();
    }

    /** Booked, a container, a final invoice and a final packing list — everything but the documents. */
    private function paperwork(): void
    {
        $this->as()->postJson('/api/v1/sales-shipment/bookings', ['shipment_no' => $this->shp, 'booking_date' => '2026-11-07', 'forwarder' => 'Expo Freight', 'vessel_name' => 'MAERSK KOWLOON', 'voyage_no' => '245W',
            'etd' => '2026-11-24', 'status' => 'CONFIRMED'])->assertCreated();
        $this->as()->postJson('/api/v1/sales-shipment/containers', ['shipment_no' => $this->shp, 'booking_no' => 'BKG-2026-000001', 'container_no' => 'MSKU1234567'])->assertCreated();
        $this->as()->postJson('/api/v1/sales-shipment/invoices', ['shipment_no' => $this->shp, 'invoice_date' => '2026-11-08', 'currency_id' => $this->bdt, 'consignee' => 'H&M', 'shipment_mode' => 'Sea',
            'lines' => [['shipment_line_id' => $this->lines[0], 'qty' => 400], ['shipment_line_id' => $this->lines[1], 'qty' => 300, 'unit_price' => 2.5]]])->assertCreated();
        $this->act('invoices', 'INV-2026-000001', 'finalize')->assertOk();
        $this->as()->postJson('/api/v1/sales-shipment/packing-lists', ['shipment_no' => $this->shp, 'packing_date' => '2026-11-08', 'lines' => [
            ['shipment_line_id' => $this->lines[0], 'carton_from' => 1, 'carton_to' => 20, 'pcs_per_carton' => 20], ['shipment_line_id' => $this->lines[1], 'carton_from' => 21, 'carton_to' => 35, 'pcs_per_carton' => 20]]])->assertCreated();
        $this->act('packing-lists', 'PL-2026-000001', 'finalize')->assertOk();
        $this->assertSame('DOCUMENTATION', $this->stage());
    }

    private function stage(): string
    {
        return (string) DB::table('ss_shipments')->where('shipment_no', $this->shp)->value('stage');
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
