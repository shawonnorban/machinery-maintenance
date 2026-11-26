<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\AccPurchaseOrderLine;
use App\Modules\Merchandise\Models\BudgetEvent;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Costing & Budget (AnnotechERP 06/09): Fabric Process
 * Rate — rates numbered as they are raised, in BDT per Kg unless said
 * otherwise, and the ones without a fibre combination template waiting for
 * one; and the order budget sheet with the actual costs recorded against it
 * and the workflow that approves and locks it.
 */
class MerchandiseCostingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_fabric_process_rates_are_numbered_and_wait_for_their_template(): void
    {
        $type = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');

        $this->as()->postJson('/api/v1/erp/merch-fab-process-rates', ['category' => 'KNITTING', 'rate' => 1])->assertUnprocessable()
            ->assertJsonPath('errors.description.0', 'Rate Description is required.')->assertJsonValidationErrors(['prod_rate', 'process_loss']);
        $this->as()->postJson('/api/v1/erp/merch-fab-process-rates', ['category' => 'WEAVING', 'description' => 'x', 'rate' => 1, 'prod_rate' => 1, 'process_loss' => 2])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->as()->postJson('/api/v1/erp/merch-fab-process-rates', ['category' => 'DYEING', 'description' => 'x', 'rate' => 1, 'prod_rate' => 1, 'process_loss' => 120])->assertUnprocessable()->assertJsonValidationErrors('process_loss');

        $first = $this->as()->postJson('/api/v1/erp/merch-fab-process-rates', [
            'category' => 'KNITTING', 'fiber_template' => 'COTTON_100', 'count_range' => ' 20/s to 30/s ', 'fabric_type_id' => $type, 'description' => 'SJ 100% Cotton knitting', 'rate' => 0.45, 'prod_rate' => 500, 'process_loss' => 2,
        ])->assertCreated()->json('data');
        $this->assertSame([1, 'BDT', 'Kg', true, false, '20/s to 30/s', 'Single Jersey'], [$first['rate_no'], $first['currency'], $first['per'], $first['active'], $first['is_slub'], $first['count_range'], $first['fabric_type']]);

        $second = $this->as()->postJson('/api/v1/erp/merch-fab-process-rates', ['category' => 'DYEING', 'description' => 'Reactive dyeing, dark', 'rate' => 1.2, 'currency' => 'usd', 'per' => 'KG', 'prod_rate' => 1.1, 'process_loss' => 8, 'is_slub' => true])->assertCreated()->json('data');
        $this->assertSame([2, 'USD', null, true], [$second['rate_no'], $second['currency'], $second['fiber_template'], $second['is_slub']]);

        // A pending rate is given its template in place.
        $this->as()->patchJson("/api/v1/erp/merch-fab-process-rates/{$second['id']}", ['fiber_template' => 'CVC_BLEND'])->assertOk()->assertJsonPath('data.fiber_template', 'CVC_BLEND')->assertJsonPath('data.rate_no', 2);
        $this->assertSame([1, 2], array_column($this->as()->getJson('/api/v1/erp/merch-fab-process-rates')->assertOk()->json('data'), 'rate_no'));
        $this->as()->deleteJson("/api/v1/erp/merch-fab-process-rates/{$first['id']}")->assertSuccessful();
        $this->as()->postJson('/api/v1/erp/merch-fab-process-rates', ['category' => 'AOP', 'description' => 'AOP', 'rate' => 1, 'prod_rate' => 1, 'process_loss' => 1])->assertCreated()->assertJsonPath('data.rate_no', 3);
    }

    public function test_an_embellishment_rate_is_confirmed_once_and_then_closed(): void
    {
        $this->as()->postJson('/api/v1/erp/merch-embellishment-rates', ['type' => 'PRINT', 'rate' => 0.35])->assertUnprocessable()->assertJsonPath('errors.description.0', 'Rate Related Information is required.');
        $this->as()->postJson('/api/v1/erp/merch-embellishment-rates', ['type' => 'FOIL', 'description' => 'x', 'rate' => 1])->assertUnprocessable()->assertJsonValidationErrors('type');

        $rate = $this->as()->postJson('/api/v1/erp/merch-embellishment-rates', ['type' => 'EMBROIDERY', 'description' => ' Left chest logo, 8k stitches ', 'rate' => 0.8])->assertCreated()->json('data');
        $this->assertSame(['BDT', 'Pcs', false, 'Left chest logo, 8k stitches', null], [$rate['currency'], $rate['per'], $rate['confirmed'], $rate['description'], $rate['confirmed_at']]);

        // Still pending, it is rewritten freely; confirmed, it is costed as it stands.
        $this->as()->patchJson("/api/v1/erp/merch-embellishment-rates/{$rate['id']}", ['rate' => 0.9, 'currency' => 'usd'])->assertOk()->assertJsonPath('data.currency', 'USD');
        $confirmed = $this->as()->patchJson("/api/v1/erp/merch-embellishment-rates/{$rate['id']}", ['confirmed' => true])->assertOk()->json('data');
        $this->assertTrue($confirmed['confirmed']);
        $this->assertNotNull($confirmed['confirmed_at']);
        $this->as()->patchJson("/api/v1/erp/merch-embellishment-rates/{$rate['id']}", ['rate' => 1.2])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->assertEquals(0.9, $this->as()->getJson("/api/v1/erp/merch-embellishment-rates/{$rate['id']}")->json('data.rate'));

        // A confirmed rate may still be removed.
        $this->as()->deleteJson("/api/v1/erp/merch-embellishment-rates/{$rate['id']}")->assertSuccessful();
        $this->assertSame([], $this->as()->getJson('/api/v1/erp/merch-embellishment-rates')->json('data'));
    }

    public function test_a_cad_record_keeps_its_consumption_per_size_and_part(): void
    {
        $this->as()->postJson('/api/v1/erp/merch-cad-records', ['category' => 'BULK'])->assertUnprocessable()
            ->assertJsonPath('errors.job_no.0', 'Job/W.Style is required.')->assertJsonPath('errors.record_date.0', 'Date is required.');
        $record = $this->as()->postJson('/api/v1/erp/merch-cad-records', ['category' => 'BULK', 'job_no' => ' 8797098 ', 'record_date' => '2026-09-29'])->assertCreated()->json('data');
        $this->assertSame(['8797098', 'DRAFT', 0, null], [$record['job_no'], $record['status'], $record['sizes'], $record['avg_consumption']]);

        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true])->id;
        $this->assertSame(['Body'], array_column($this->as()->getJson("/api/v1/merchandise/cad-records/{$record['id']}/sizes")->assertOk()->json('data.parts'), 'label'));

        $lines = [['size' => 'XL', 'garment_part_id' => $body, 'consumption' => 0.5, 'uom' => 'YDS'], ['size' => 'L', 'garment_part_id' => $body, 'consumption' => 0.45]];
        $this->as()->putJson("/api/v1/merchandise/cad-records/{$record['id']}/sizes", ['rows' => [$lines[0], ['size' => 'xl '] + $lines[0]]])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->putJson("/api/v1/merchandise/cad-records/{$record['id']}/sizes", ['rows' => [['consumption' => null] + $lines[0]]])->assertUnprocessable();
        $saved = $this->as()->putJson("/api/v1/merchandise/cad-records/{$record['id']}/sizes", ['rows' => $lines])->assertOk()->json('data.rows');
        $this->assertSame([['XL', 'Body', 'YDS'], ['L', 'Body', null]], array_map(fn ($r) => [$r['size'], $r['part'], $r['uom']], $saved));

        $row = $this->as()->getJson("/api/v1/erp/merch-cad-records/{$record['id']}")->assertOk()->json('data');
        $this->assertSame(2, $row['sizes']);
        $this->assertEquals(0.475, $row['avg_consumption']);

        // Removing the record takes its sizes along.
        $this->as()->deleteJson("/api/v1/erp/merch-cad-records/{$record['id']}")->assertSuccessful();
        $this->as()->getJson("/api/v1/merchandise/cad-records/{$record['id']}/sizes")->assertNotFound();
    }

    public function test_an_order_budget_opens_on_the_order_and_is_saved_per_piece(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J1', 'style_id' => $style, 'total_qty' => 1200, 'unit_price' => 2.5, 'currency' => 'usd'])->assertCreated()->json('data');

        // Not yet saved, the sheet opens on the order: its pieces, its price as FOB.
        $fresh = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget")->assertOk()->json('data');
        $this->assertSame([1200, 3000, null, 'USD', $office], [$fresh['order']['order_qty'], (int) $fresh['order']['order_value'], $fresh['budget']['budget_no'], $fresh['budget']['currency'], $fresh['budget']['buyer_office_id']]);
        $this->assertEquals(2.5, $fresh['budget']['overall']['fob']);
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/budgets')->assertOk()->json('data'));

        $payload = [
            'buyer_office_id' => $office, 'currency' => 'usd', 'exchange_rate' => 120, 'remarks' => ' first cut ',
            'lines' => [
                ['section' => 'YARN', 'description' => '30/1 Combed', 'uom' => 'KG', 'qty' => 0.25, 'rate' => 3.2],
                ['section' => 'SEWING_ACC', 'description' => 'Thread', 'qty' => 1, 'rate' => 0.05],
            ],
            'process' => ['knitting' => 0.1, 'dyeing' => 0.3],
            'overall' => ['cm' => 0.9, 'commercial' => 0.05, 'fob' => 2.5],
        ];
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", ['lines' => [['section' => 'YARN', 'qty' => 1, 'rate' => 1]]] + $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.0.description');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", ['currency' => 'US'] + $payload)->assertUnprocessable()->assertJsonValidationErrors('currency');
        $saved = $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", $payload)->assertOk()->json('data.budget');
        $this->assertSame(['BGT-'.now()->format('Y').'-000001', 'USD', 'first cut', 2], [$saved['budget_no'], $saved['currency'], $saved['remarks'], count($saved['lines'])]);
        $this->assertEquals([0.1, 0.0, 0.3], [$saved['process']['knitting'], $saved['process']['aop'], $saved['process']['dyeing']]);

        // Saving again rewrites the same budget under the same number.
        $again = $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", ['lines' => [$payload['lines'][0]]] + $payload)->assertOk()->json('data.budget');
        $this->assertSame([$saved['budget_no'], 1], [$again['budget_no'], count($again['lines'])]);

        $list = $this->as()->getJson('/api/v1/merchandise/budgets')->assertOk()->json('data');
        $this->assertSame([[$saved['budget_no'], 'LPP SPOLKA AKCYJNA OFFICE', '637PK', $order['order_no'], 1200]], array_map(fn ($r) => [$r['budget_no'], $r['buyer_ac'], $r['style_no'], $r['order_no'], $r['order_qty']], $list));
    }

    public function test_a_budget_bill_of_material_loads_from_the_booking_and_the_bom_and_works_qty_from_consumption(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J1', 'style_id' => $style, 'total_qty' => 1200, 'unit_price' => 2.5, 'currency' => 'usd'])->assertCreated()->json('data');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/sizes", ['rows' => [['colour' => 'Navy', 'size' => 'M', 'qty' => 800], ['colour' => 'White', 'size' => 'M', 'qty' => 400]]])->assertOk();
        // A second order of the style, no booking of its own.
        $other = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J2', 'style_id' => $style, 'total_qty' => 600, 'unit_price' => 2.5, 'currency' => 'usd'])->assertCreated()->json('data');
        $navy = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy'])->assertCreated()->json('data.id');
        $fabric = fn (string $code, string $name) => $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => $code, 'name' => $name, 'short_name' => $code, 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        [$sj, $twill, $rib] = [$fabric('SJ', 'Single Jersey'), $fabric('TW', 'Twill'), $fabric('RB', 'Rib')];
        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true, 'is_body' => true])->id;

        // The sheet starts a new line in its group's unit: yarn by the kilo, purchased fabric in yards, the rest by the piece.
        $sheet = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget")->assertOk()->json('data');
        $this->assertSame(['KG', 'YDS', 'PCS', 'PCS'], [$sheet['section_uoms']['YARN'], $sheet['section_uoms']['PURCHASED_FABRIC'], $sheet['section_uoms']['SEWING_ACC'], $sheet['section_uoms']['SPECIAL_ACC']]);
        // Nothing booked and no BOM yet: nothing to load.
        $this->assertSame([], $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget/sources")->assertOk()->json('data.lines'));

        $line = ['dia_unit' => 'INCH', 'dia_type' => 'OPEN', 'all_orders' => false, 'gmt_style_id' => $style, 'all_sizes' => true, 'fab_same_as_gmt' => true, 'add_qty' => 0];
        $this->as()->postJson('/api/v1/merchandise/fabric-bookings?type=BULK', ['booking_date' => '2026-10-15', 'style_id' => $style, 'order_id' => $order['id'], 'currency' => 'USD', 'conv_rate' => 1, 'item_model' => '637PK', 'lines' => [
            // Every colour: 2.4 kg a dozen, +5 % cutting, +10 % process loss.
            ['fabric_kind' => 'KNIT', 'fabric_type_id' => $sj, 'garment_part_id' => $body, 'finish_dia' => 72, 'gsm' => 160, 'consumption' => 2.4, 'cons_unit' => 'KG', 'cons_per' => 'DZ', 'all_colours' => true, 'cutting_wastage' => 5, 'process_loss' => 10, 'rate' => 4] + $line,
            // Navy only (800 of the 1200): 1.2 kg a dozen more, no wastage.
            ['fabric_kind' => 'KNIT', 'fabric_type_id' => $sj, 'garment_part_id' => $body, 'finish_dia' => 72, 'gsm' => 160, 'consumption' => 1.2, 'cons_unit' => 'KG', 'cons_per' => 'DZ', 'all_colours' => false, 'gmt_colour_id' => $navy, 'cutting_wastage' => 0] + $line,
            // Woven twill, bought: 0.25 yds a piece, +4 %, at 2.
            ['fabric_kind' => 'WOVEN', 'fabric_type_id' => $twill, 'finish_dia' => 58, 'consumption' => 0.25, 'cons_unit' => 'YDS', 'cons_per' => 'PCS', 'all_colours' => true, 'cutting_wastage' => 4, 'rate' => 2] + $line,
            // Knit rib booked by length with no GSM: its yarn cannot be put into kilos.
            ['fabric_kind' => 'KNIT', 'fabric_type_id' => $rib, 'finish_dia' => 30, 'consumption' => 0.6, 'cons_unit' => 'YDS', 'cons_per' => 'DZ', 'all_colours' => true, 'cutting_wastage' => 0] + $line,
        ]])->assertCreated();
        $bom = ['order_qty' => 1800, 'addl_pct' => 3, 'lines' => [
            ['item_name' => 'Main Body Fabric', 'cons_type' => 'STYLE', 'consumption' => 0.3, 'cons_unit' => 'KG', 'per_gmt' => 1, 'rate' => 5, 'category' => 'MAIN_FABRIC'],
            ['item_name' => 'Sewing Thread', 'item_code' => 'TH-1', 'cons_type' => 'COLOUR', 'consumption' => 2, 'cons_unit' => 'MTR', 'per_gmt' => 1, 'rate' => 0.01, 'category' => 'SEWING_ACCESSORIES', 'allow_pct' => 10],
            ['item_name' => 'Care Label', 'cons_type' => 'COLOUR_SIZE', 'consumption' => 1, 'cons_unit' => 'PCS', 'rate' => 0.02, 'category' => 'LABELS', 'apply_addl' => true],
            ['item_name' => 'Poly Bag', 'cons_type' => 'STYLE', 'consumption' => 12, 'cons_unit' => 'PCS', 'per_gmt' => 12, 'rate' => 0.05, 'category' => 'PACKAGING'],
        ]];
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", $bom)->assertOk();

        $got = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget/sources")->assertOk()->json('data');
        $pick = fn (array $l) => [$l['section'], $l['description'], $l['uom'], $l['cons_dzn'], $l['wastage_pct'], $l['qty'], $l['rate'], $l['source']];
        $this->assertEquals([
            // Body yarn: 240 kg (all) + 80 kg (Navy) = 320 kg over 1200 pieces = 3.2 kg a dozen; with wastage 357.2 kg, +11.625 %.
            ['YARN', 'Yarn for Single Jersey · Body', 'KG', 3.2, 11.625, 0.2977, 0, 'BOOKING'],
            ['PURCHASED_FABRIC', 'Twill', 'YDS', 3, 4, 0.26, 2, 'BOOKING'],
            ['YARN', 'Yarn for Rib', 'YDS', 0.6, 0, 0.05, 0, 'BOOKING'],
            // The BOM's main fabric is left out: the booking gave the fabric.
            ['SEWING_ACC', 'Sewing Thread (TH-1)', 'MTR', 24, 10, 2.2, 0.01, 'BOM'],
            ['SEWING_ACC', 'Care Label', 'PCS', 12, 3, 1.03, 0.02, 'BOM'],
            ['FINISHING_ACC', 'Poly Bag', 'PCS', 12, 0, 1, 0.05, 'BOM'],
        ], array_map($pick, $got['lines']));
        $this->assertSame(['Rib is booked in YDS without GSM and width, so its yarn stays in YDS.'], $got['notes']);
        $this->assertSame([['BFB-00001'], 'BOM-000001', 1, 'DRAFT'], [$got['bookings'], $got['bom']['bom_no'], $got['bom']['version'], $got['bom']['status']]);

        // The other order has no booking line of its own, so the BOM's main fabric comes in as purchased fabric.
        $theirs = $this->as()->getJson("/api/v1/merchandise/orders/{$other['id']}/budget/sources")->assertOk()->json('data');
        $this->assertSame([[], 'PURCHASED_FABRIC', 'Main Body Fabric', 'KG'], [$theirs['bookings'], $theirs['lines'][0]['section'], $theirs['lines'][0]['description'], $theirs['lines'][0]['uom']]);
        $this->assertEquals([3.6, 0.3, 5], [$theirs['lines'][0]['cons_dzn'], $theirs['lines'][0]['qty'], $theirs['lines'][0]['rate']]);

        // Saved, a line with Cons/Dzn has its Qty worked out whatever was sent; a typed line keeps its Qty.
        $payload = ['currency' => 'USD', 'exchange_rate' => 110, 'process' => [], 'overall' => ['fob' => 2.5], 'lines' => [
            ['qty' => 99] + $got['lines'][0],
            ['section' => 'SPECIAL_ACC', 'description' => 'Hanger', 'uom' => 'PCS', 'qty' => 1, 'rate' => 0.08],
        ]];
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", ['lines' => [['section' => 'YARN', 'description' => 'x', 'rate' => 1]]] + $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", ['lines' => [['source' => 'CAD'] + $payload['lines'][1]]] + $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.0.source');
        $saved = $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", $payload)->assertOk()->json('data.budget.lines');
        $this->assertEquals([[3.2, 11.625, 0.2977, 'BOOKING'], [null, null, 1, null]], array_map(fn ($l) => [$l['cons_dzn'], $l['wastage_pct'], $l['qty'], $l['source']], $saved));
    }

    public function test_an_accessory_line_takes_its_item_from_the_master_and_its_rate_from_the_supplier(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J1', 'style_id' => $style, 'total_qty' => 1200, 'unit_price' => 2.5, 'currency' => 'usd'])->assertCreated()->json('data');
        $item = fn (string $code, string $name) => MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => $code, 'name' => $name, 'category' => 'Label', 'uom' => 'PCS', 'active' => true])->id;
        [$care, $hanger, $poly, $button] = [$item('TR-LBL-CARE', 'Care Label'), $item('TR-HNG', 'Hanger'), $item('TR-PLY', 'Poly Bag'), $item('TR-BTN', 'Button')];
        $supplier = fn (string $code, string $name) => MaterialSupplier::query()->create(['code' => $code, 'name' => $name, 'supplier_type' => 'LOCAL', 'active' => true])->id;
        [$koyo, $apex, $pack] = [$supplier('KOYO', 'Koyo Trims'), $supplier('APX', 'Apex Labels'), $supplier('DPK', 'Delta Pack')];

        $pr = PurchaseRequisition::query()->create(['pr_no' => 'PR-1', 'req_type' => 'TRIMS', 'req_date' => '2026-11-01']);
        $quote = function (string $itemId, string $supplierId, string $status, array $prices) use ($pr): void {
            static $n = 0;
            $line = PurchaseRequisitionLine::query()->create(['requisition_id' => $pr->id, 'kind' => 'TRIMS', 'item_id' => $itemId, 'req_qty' => 1000, 'uom' => 'PCS', 'req_date' => '2026-11-10']);
            $q = Quotation::query()->create(['qt_no' => 'QT-'.(++$n), 'requisition_line_id' => $line->id, 'supplier_id' => $supplierId, 'status' => $status]);
            foreach ($prices as $i => [$price, $currency]) {
                QuotationRevision::query()->create(['quotation_id' => $q->id, 'revision' => $i + 1, 'quoted_qty' => 1000, 'unit_price' => $price, 'currency' => $currency, 'lead_time_days' => 7, 'expected_delivery' => '2026-11-20', 'submitted_at' => '2026-11-0'.($i + 2)]);
            }
        };
        // Care label: Koyo approved (its latest revision counts), Apex still open — but Apex also sold it on a PO, in taka.
        $quote($care, $koyo, 'APPROVED', [[0.05, 'USD'], [0.045, 'USD']]);
        $quote($care, $apex, 'PENDING', [[0.04, 'USD']]);
        $po = AccPurchaseOrder::query()->create(['po_no' => 'APO-1', 'po_date' => '2026-11-05', 'supplier_id' => $apex, 'payment_method' => 'CASH', 'currency' => 'BDT', 'payment_term' => 'CASH', 'mode_of_shipment' => 'ROAD', 'delivery_date' => '2026-11-20']);
        AccPurchaseOrderLine::query()->create(['po_id' => $po->id, 'item_id' => $care, 'qty' => 1000, 'unit_price' => 4.2]);
        // Poly bag quoted only in euro: no price in dollars to take.
        $quote($poly, $pack, 'PENDING', [[0.1, 'EUR']]);
        $quote($button, $koyo, 'APPROVED', [[6, 'BDT']]);

        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['addl_pct' => 3, 'lines' => [
            ['item_name' => 'Care Label Satin', 'item_code' => 'tr-lbl-care', 'consumption' => 1, 'cons_unit' => 'PCS', 'rate' => 0.06, 'category' => 'LABELS', 'allow_pct' => 2, 'supplier' => 'apx'],
            ['item_name' => 'Hanger', 'consumption' => 1, 'cons_unit' => 'PCS', 'rate' => 0.08, 'category' => 'ACCESSORIES'],
            ['item_name' => 'Poly Bag', 'item_code' => 'TR-PLY', 'consumption' => 12, 'per_gmt' => 12, 'cons_unit' => 'PCS', 'rate' => 0.05, 'category' => 'PACKAGING', 'supplier' => 'Delta Pack'],
        ]])->assertOk();

        // The sheet offers the trims master and the suppliers.
        $sheet = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget")->assertOk()->json('data');
        $this->assertSame(['Button (TR-BTN)', 'Care Label (TR-LBL-CARE)', 'Hanger (TR-HNG)', 'Poly Bag (TR-PLY)'], array_column($sheet['catalog']['items'], 'label'));
        $this->assertSame(['Apex Labels', 'Delta Pack', 'Koyo Trims'], array_column($sheet['catalog']['suppliers'], 'label'));

        // Picking the care label: its code, its BOM consumption, and every supplier's price in dollars at 120 taka.
        $got = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget/item?item_id={$care}&currency=USD&rate=120")->assertOk()->json('data');
        $this->assertSame(['TR-LBL-CARE', 'PCS'], [$got['item']['code'], $got['item']['uom']]);
        $this->assertEquals(['cons_dzn' => 12, 'wastage_pct' => 2, 'rate' => 0.06], $got['bom']);
        $this->assertEquals([[$koyo, 0.045, 'QUOTATION', 'APPROVED'], [$apex, 0.035, 'PO', null]], array_map(fn ($o) => [$o['supplier_id'], $o['price'], $o['source'], $o['status']], $got['offers']));
        // The BOM names Apex (by its code), and Apex has a price: that is the line's supplier and rate.
        $this->assertEquals(['supplier_id' => $apex, 'rate' => 0.035], $got['pick']);
        // An item not on the BOM takes the best price there is.
        $btn = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget/item?item_id={$button}&currency=USD&rate=120")->assertOk()->json('data');
        $this->assertNull($btn['bom']);
        $this->assertEquals(['supplier_id' => $koyo, 'rate' => 0.05], $btn['pick']);
        $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget/item?item_id=01hzzzzzzzzzzzzzzzzzzzzzzz")->assertUnprocessable()->assertJsonValidationErrors('item_id');

        // Loading the BOM matches each accessory to the master and prices it from its supplier (the order is in dollars at 1).
        $lines = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget/sources")->assertOk()->json('data.lines');
        $this->assertEquals([
            ['SEWING_ACC', $care, 'TR-LBL-CARE', 'Care Label', $apex, 4.2],
            // Hanger matched by its name; nobody has priced it, so the BOM's rate stays.
            ['GENERAL_ACC', $hanger, 'TR-HNG', 'Hanger', null, 0.08],
            // Delta Pack is named but its euro price cannot be taken: the supplier stays, the BOM's rate with it.
            ['FINISHING_ACC', $poly, 'TR-PLY', 'Poly Bag', $pack, 0.05],
        ], array_map(fn ($l) => [$l['section'], $l['item_id'], $l['item_code'], $l['description'], $l['supplier_id'], $l['rate']], $lines));

        // Saved, the line keeps its item (code and name from the master) and its supplier.
        $payload = ['currency' => 'USD', 'exchange_rate' => 120, 'process' => [], 'overall' => ['fob' => 2.5], 'lines' => [
            ['section' => 'SEWING_ACC', 'item_id' => $care, 'description' => '', 'uom' => null, 'cons_dzn' => 12, 'wastage_pct' => 2, 'rate' => 0.035, 'supplier_id' => $apex],
        ]];
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", ['lines' => [['item_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz'] + $payload['lines'][0]]] + $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.0.item_id');
        $saved = $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", $payload)->assertOk()->json('data.budget.lines.0');
        $this->assertEquals([$care, 'TR-LBL-CARE', 'Care Label', 'PCS', $apex, 1.02, 0.035], [$saved['item_id'], $saved['item_code'], $saved['description'], $saved['uom'], $saved['supplier_id'], $saved['qty'], $saved['rate']]);
    }

    public function test_actual_costs_are_recorded_against_the_budget_group_by_group(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J1', 'style_id' => $this->style($buyer, $office, '637PK'), 'total_qty' => 100, 'unit_price' => 3])->assertCreated()->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order}/budget", [
            'currency' => 'USD', 'exchange_rate' => 1, 'lines' => [['section' => 'YARN', 'description' => 'Yarn', 'qty' => 0.5, 'rate' => 2], ['section' => 'YARN', 'description' => 'Slub', 'qty' => 0.1, 'rate' => 1]],
            'process' => ['knitting' => 0.12], 'overall' => ['cm' => 0.8, 'fob' => 3],
        ])->assertOk();
        $budget = $this->as()->getJson('/api/v1/merchandise/budgets')->json('data.0.id');

        $yarn = $this->as()->getJson("/api/v1/merchandise/budgets/{$budget}/actuals/yarn_trims")->assertOk()->json('data.rows');
        $this->assertSame(['yarn', 'sewing_acc', 'finishing_acc', 'general_acc', 'special_acc'], array_column($yarn, 'head'));
        $this->assertEquals([1.1, 0.0], [$yarn[0]['budget'], $yarn[0]['actual']]);
        $this->assertEquals(0.12, $this->as()->getJson("/api/v1/merchandise/budgets/{$budget}/actuals/fabric_process")->json('data.rows.1.budget'));
        $this->as()->getJson("/api/v1/merchandise/budgets/{$budget}/actuals/sampling")->assertNotFound();

        $this->as()->putJson("/api/v1/merchandise/budgets/{$budget}/actuals/cmt", ['actual' => ['cm' => -1]])->assertUnprocessable()->assertJsonValidationErrors('actual.cm');
        $cmt = $this->as()->putJson("/api/v1/merchandise/budgets/{$budget}/actuals/cmt", ['actual' => ['cm' => 0.85, 'wash' => 0.1]])->assertOk()->json('data.rows');
        $this->assertEquals([[0.8, 0.85], [0.0, 0.0], [0.0, 0.1]], [[$cmt[0]['budget'], $cmt[0]['actual']], [$cmt[1]['budget'], $cmt[1]['actual']], [$cmt[3]['budget'], $cmt[3]['actual']]]);

        // One group's save leaves the others as they were.
        $this->as()->putJson("/api/v1/merchandise/budgets/{$budget}/actuals/yarn_trims", ['actual' => ['yarn' => 1.25]])->assertOk();
        $this->assertEquals(0.85, $this->as()->getJson("/api/v1/merchandise/budgets/{$budget}/actuals/cmt")->json('data.rows.0.actual'));

        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson("/api/v1/merchandise/budgets/{$budget}/actuals/cmt")->assertOk();
        $this->as($manager)->putJson("/api/v1/merchandise/budgets/{$budget}/actuals/cmt", ['actual' => ['cm' => 1]])->assertForbidden();
        $this->as($manager)->putJson("/api/v1/merchandise/orders/{$order}/budget", ['currency' => 'USD', 'exchange_rate' => 1, 'lines' => [], 'process' => [], 'overall' => []])->assertForbidden();
    }

    public function test_a_budget_sheet_is_approved_and_locked_then_unlocked_on_the_workflow(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J-77', 'style_id' => $this->style($buyer, $office, '637PK'), 'total_qty' => 100, 'unit_price' => 3, 'ship_date' => '2026-12-10'])->assertCreated()->json('data');
        Order::query()->whereKey($order['id'])->update(['po_no' => 'PO-9']); // set on Order Confirmation
        $payload = ['currency' => 'USD', 'exchange_rate' => 1, 'lines' => [['section' => 'YARN', 'description' => 'Yarn', 'qty' => 0.5, 'rate' => 2]], 'process' => ['dyeing' => 0.25], 'overall' => ['cm' => 0.8, 'fob' => 3]];
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", $payload)->assertOk();

        $row = $this->as()->getJson('/api/v1/merchandise/budget-workflow')->assertOk()->json('data.0');
        $this->assertSame(['PENDING', 'LPP', 'J-77', 'PO-9', '637PK', '2026-12-10', 100, null], [$row['status'], $row['buyer'], $row['job_no'], $row['po_no'], $row['style_no'], $row['ship_date'], $row['order_qty'], $row['approved_at']]);
        $this->assertEquals([2.05, 3.0], [$row['cost'], $row['fob']]);

        // A viewer reads the route; only an approver moves a sheet along it.
        $manager = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        $this->as($manager)->getJson('/api/v1/merchandise/budget-workflow')->assertOk();
        $this->as($manager)->postJson("/api/v1/merchandise/budgets/{$row['id']}/approval", ['move' => 'approve'])->assertForbidden();

        $this->as()->postJson("/api/v1/merchandise/budgets/{$row['id']}/approval", ['move' => 'unlock', 'note' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('move');
        $this->as()->postJson("/api/v1/merchandise/budgets/{$row['id']}/approval", ['move' => 'approve'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->as()->postJson("/api/v1/merchandise/budgets/{$row['id']}/approval", ['move' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors('move');

        // Approved, the sheet is locked for costing; actual costs are still recorded against it.
        $sheet = $this->as()->getJson("/api/v1/merchandise/orders/{$order['id']}/budget")->assertOk()->json('data.budget');
        $this->assertNotNull($sheet['approved_at']);
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", $payload)->assertUnprocessable()->assertJsonValidationErrors('budget');
        $this->as()->putJson("/api/v1/merchandise/budgets/{$row['id']}/actuals/cmt", ['actual' => ['cm' => 0.9]])->assertOk();
        $approved = $this->as()->getJson('/api/v1/merchandise/budget-workflow')->json('data.0');
        $this->assertSame(['APPROVED', 'APPROVED'], [$approved['status'], $approved['last_action']]);
        $this->assertNotNull($approved['approved_by']);

        // Unlocking says why, and opens the sheet to revision again.
        $this->as()->postJson("/api/v1/merchandise/budgets/{$row['id']}/approval", ['move' => 'unlock'])->assertUnprocessable()->assertJsonPath('errors.note.0', 'Say why the budget sheet is being unlocked.');
        $this->as()->postJson("/api/v1/merchandise/budgets/{$row['id']}/approval", ['move' => 'unlock', 'note' => ' CM rate revised '])->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->as()->putJson("/api/v1/merchandise/orders/{$order['id']}/budget", $payload)->assertOk();
        $unlocked = $this->as()->getJson('/api/v1/merchandise/budget-workflow')->json('data.0');
        $this->assertSame(['PENDING', 'UNLOCKED', 'CM rate revised', null], [$unlocked['status'], $unlocked['last_action'], $unlocked['last_note'], $unlocked['approved_by']]);
        $this->assertSame(2, BudgetEvent::query()->where('budget_id', $row['id'])->count());
    }

    public function test_costing_is_closed_to_a_technician_and_read_only_to_a_manager(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/erp/merch-fab-process-rates')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/erp/merch-fab-process-rates')->assertOk();
        $this->as($manager)->postJson('/api/v1/erp/merch-fab-process-rates', ['category' => 'AOP', 'description' => 'AOP', 'rate' => 1, 'prod_rate' => 1, 'process_loss' => 1])->assertForbidden();
    }

    private function buyer(string $code, string $name): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => "{$name} Office", 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => $code, 'name' => $name, 'short_name' => $code, 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');

        return [$buyer, $office];
    }

    private function style(string $buyer, string $office, string $ref): string
    {
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');

        return $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => $ref, 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
