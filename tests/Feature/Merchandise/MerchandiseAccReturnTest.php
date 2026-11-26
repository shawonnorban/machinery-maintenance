<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\ProcPostingSetup;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Accessories Supplier Return Challan (AnnotechERP
 * 06/06): the MRR List of finalised receipts with something left to send
 * back; a challan numbered RTN-ACC-2026100001 filled from one, never
 * returning more than came in; finalised, locked and off stock; a receipt
 * sent back in full leaving the list.
 */
class MerchandiseAccReturnTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private mixed $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_return_challan_sends_back_what_a_finalised_receipt_brought_in_and_takes_it_off_stock(): void
    {
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Trims Store', 'code' => 'ST-TRM', 'active' => true])->id;
        $button = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-BTN-01', 'name' => 'Button 18L', 'category' => 'Button', 'uom' => 'PCS', 'active' => true])->id;
        $label = MaterialItem::query()->create(['kind' => 'TRIMS', 'item_code' => 'TR-LBL-01', 'name' => 'Care Label', 'category' => 'Label', 'uom' => 'PCS', 'active' => true])->id;
        $star = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Star Accessories', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $moon = MaterialSupplier::query()->create(['code' => 'SUP-02', 'name' => 'Moon Trims', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->postingSetup();
        $receipt = fn (array $lines, string $date = '2026-10-15') => $this->as()->postJson('/api/v1/merchandise/acc-receipts', ['unit_company_id' => $this->delta->id, 'supplier_id' => $star, 'store_id' => $store, 'mrr_date' => $date,
            'payment_method' => 'CASH', 'source_type' => 'LOCAL', 'challan_no' => 'CH-1', 'challan_date' => $date, 'currency' => 'BDT', 'mrr_type' => 'PO', 'lines' => $lines, 'costs' => []])->assertCreated()->json('data.mrr_no');
        $mrr = $receipt([['item_id' => $button, 'qty' => 100, 'rate' => 1], ['item_id' => $label, 'qty' => 50, 'rate' => 0.2]]);
        $draft = $receipt([['item_id' => $button, 'qty' => 5, 'rate' => 1]]);
        $this->as()->postJson("/api/v1/merchandise/acc-receipts/{$mrr}/finalize")->assertOk();

        // The MRR List: finalised receipts only; Return By: the company's people.
        $options = $this->as()->getJson('/api/v1/merchandise/acc-returns/options')->assertOk()->json('data');
        $this->assertSame([[$mrr, 'CH-1', $star]], array_map(fn ($r) => [$r['mrr_no'], $r['challan_no'], $r['supplier_id']], $options['receipts']));
        $this->assertContains($this->owner->id, array_column($options['users'], 'value'));
        $this->assertSame(['REJECTED', 'EXCESS', 'SHORT_SHIPPED', 'OFF_SHADE', 'SAMPLE_RETURN', 'OTHER'], $options['types']);

        $fill = $this->as()->getJson("/api/v1/merchandise/acc-returns/receipts/{$mrr}")->assertOk()->json('data');
        $this->assertSame([$star, $store, $this->delta->id], [$fill['supplier_id'], $fill['store_id'], $fill['unit_company_id']]);
        $this->assertEquals([['Button 18L', 'PCS', 100], ['Care Label', 'PCS', 50]], array_map(fn ($l) => [$l['description'], $l['uom'], $l['qty']], $fill['lines']));
        $this->as()->getJson("/api/v1/merchandise/acc-returns/receipts/{$draft}")->assertNotFound();
        [$btnLine] = array_column($fill['lines'], 'mrr_line_id');

        $this->as()->postJson('/api/v1/merchandise/acc-returns', ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors([
            'return_type' => 'Return Type is required.', 'unit_company_id' => 'Company is required.', 'supplier_id' => 'Supplier is required.', 'mrr_no' => 'MRR No is required.',
            'challan_date' => 'Challan Date is required.', 'store_id' => 'Returning Store is required.', 'return_by' => 'Return By is required.',
        ]);
        $head = ['return_type' => 'REJECTED', 'unit_company_id' => $this->delta->id, 'supplier_id' => $star, 'mrr_no' => $mrr, 'challan_date' => '2026-10-18', 'store_id' => $store, 'return_by' => $this->owner->id, 'vehicle_no' => ' DHK-11-2233 '];
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'mrr_no' => $draft, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['mrr_no' => 'Pick a finalised receipt from the MRR List.']);
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'supplier_id' => $moon, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id' => "Receipt {$mrr} came from another supplier."]);
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'challan_date' => '2026-10-14', 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['challan_date' => 'Challan Date cannot be before the receipt (15-Oct-2026).']);
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'A return challan with no lines is not a return challan.']);
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'lines' => [['mrr_line_id' => $btnLine, 'description' => 'Button 18L', 'qty' => 101], ['qty' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Row 1: only 100 is left to return on receipt {$mrr}.", 'lines.1.description' => 'Row 2: Description is required.']);

        $saved = $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'lines' => [['mrr_line_id' => $btnLine, 'description' => 'Button 18L', 'uom' => 'PCS', 'qty' => 30, 'reason' => 'Broken'], ['description' => 'Packing carton', 'qty' => 2]]])
            ->assertCreated()->json('data');
        $this->assertEquals(['RTN-ACC-2026100001', 'DRAFT', true, $mrr, 'DHK-11-2233', 2], [$saved['challan_no'], $saved['status'], $saved['editable'], $saved['mrr_no'], $saved['vehicle_no'], count($saved['lines'])]);
        $this->assertEquals([70, 50], array_column($this->as()->getJson("/api/v1/merchandise/acc-returns/receipts/{$mrr}")->json('data.lines'), 'qty'));
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'lines' => [['mrr_line_id' => $btnLine, 'description' => 'Button 18L', 'qty' => 71]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => "Row 1: only 70 is left to return on receipt {$mrr}."]);

        // A draft still counts as stock; finalised, the 30 buttons are gone.
        $this->assertEquals(100, $this->stockOfButtons($button));
        $final = $this->as()->postJson('/api/v1/merchandise/acc-returns/RTN-ACC-2026100001/finalize')->assertOk()->json('data');
        $this->assertSame(['FINALIZED', false], [$final['status'], $final['editable']]);
        $this->assertEquals(70, $this->stockOfButtons($button));
        $locked = 'This return challan is finalised — it can no longer be changed or removed.';
        $this->as()->putJson('/api/v1/merchandise/acc-returns/RTN-ACC-2026100001', [...$head, 'lines' => [['description' => 'X', 'qty' => 1]]])->assertUnprocessable()->assertJsonValidationErrors(['status' => $locked]);
        $this->as()->deleteJson('/api/v1/merchandise/acc-returns/RTN-ACC-2026100001')->assertUnprocessable()->assertJsonValidationErrors(['status' => $locked]);

        // Everything else back: the receipt leaves the MRR List.
        $rest = $this->as()->getJson("/api/v1/merchandise/acc-returns/receipts/{$mrr}")->json('data.lines');
        $this->as()->postJson('/api/v1/merchandise/acc-returns', [...$head, 'return_type' => 'EXCESS', 'lines' => $rest])->assertCreated()->assertJsonPath('data.challan_no', 'RTN-ACC-2026100002');
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/acc-returns/options')->json('data.receipts'));

        $records = $this->as()->getJson('/api/v1/merchandise/acc-returns')->assertOk()->json('data');
        $this->assertSame([['RTN-ACC-2026100002', false, $mrr, 'EXCESS', 'Delta Apparels Ltd', 'Star Accessories', 'Trims Store'], ['RTN-ACC-2026100001', true, $mrr, 'REJECTED', 'Delta Apparels Ltd', 'Star Accessories', 'Trims Store']],
            array_map(fn ($r) => [$r['challan_no'], $r['finalized'], $r['mrr_no'], $r['return_type'], $r['company'], $r['supplier'], $r['store']], $records));

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/acc-returns')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/acc-returns', [...$head, 'lines' => []])->assertForbidden();
    }

    /** What an indent for buttons finds in store. */
    private function stockOfButtons(string $button): float
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'Office '.uniqid(), 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $order = Order::query()->first() ?? $this->order($office);

        return (float) $this->as()->postJson('/api/v1/merchandise/acc-requisitions', ['req_date' => '2026-10-21', 'order_id' => $order->id, 'addition_for' => uniqid(), 'lines' => [['item_id' => $button, 'required_qty' => 1]]])
            ->assertCreated()->json('data.lines.0.stock_qty');
    }

    private function order(string $office): Order
    {
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $id = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($id)->update(['status' => 'CONFIRMED']);

        return Order::query()->findOrFail($id);
    }

    private function postingSetup(): void
    {
        $ledger = fn (string $code) => AccAccount::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => "Ledger {$code}", 'type' => 'ASSET', 'postable' => true, 'active' => true])->id;
        ProcPostingSetup::query()->create(['company_id' => $this->delta->id, 'unit_company_id' => $this->delta->id, 'voucher_type' => 'JV', 'inventory_ledger_id' => $ledger('1120'), 'vat_ledger_id' => $ledger('1210'),
            'payable_ledger_id' => $ledger('2100'), 'tds_ledger_id' => $ledger('2110'), 'vds_ledger_id' => $ledger('2120'), 'active' => true]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
