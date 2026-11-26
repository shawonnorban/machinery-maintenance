<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\AccReceiptLine;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Approved Material & Follow-Up (AnnotechERP 08/06): an
 * approved line handed to a merchandiser is followed up step by step — the
 * received quantities adding up, never past the requirement — and a receipt
 * raised as a draft goods receipt on Merchandise Desk (purchased-fabric for
 * fabric, accessories for an accessory), which that desk opens and saves as
 * its own. A monitor sees every line read-only; Delivery Tracking lists them
 * with the next follow-up.
 */
class ApprovedMaterialFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finished Fabric Store', 'code' => 'ST-FAB', 'active' => true])->id;
    }

    public function test_a_merchandiser_follows_up_a_line_to_in_house_and_raises_draft_receipts(): void
    {
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        [$pr, $fabricLine, $trimLine] = $this->approved($merch);

        // Only the lines handed to the caller; a monitor sees them all.
        $mine = $this->as($merch)->getJson('/api/v1/material-procurement/assigned-materials')->assertOk()->json('data');
        $this->assertSame([$fabricLine, $trimLine], array_column($mine['rows'], 'line_id'));
        $first = $mine['rows'][0];
        $this->assertEquals([$pr, 'Poly Plan Ltd', 'USD', 2.5, 100, 0, 100, 'APPROVED', $merch->name, true], [$first['pr_no'], $first['supplier'], $first['price']['currency'], $first['price']['unit_price'], $first['req_qty'], $first['received'], $first['balance'], $first['status'], $first['followup'], $first['mine']]);
        $this->assertSame([], $this->as()->getJson('/api/v1/material-procurement/assigned-materials')->json('data.rows'));
        $this->assertCount(2, $this->as()->getJson('/api/v1/material-procurement/assigned-materials?scope=all')->assertOk()->json('data.rows'));
        $this->as($merch)->getJson('/api/v1/material-procurement/assigned-materials?scope=all')->assertForbidden();

        $detail = $this->as($merch)->getJson("/api/v1/material-procurement/assigned-materials/{$fabricLine}")->assertOk()->json('data');
        $this->assertSame(['QT-'.now()->format('Y').'-00001', 'Poly Plan Ltd', 14, $merch->name], [$detail['approval']['qt_no'], $detail['approval']['supplier'], $detail['approval']['lead_time_days'], $detail['approval']['merchandiser']]);
        $this->assertSame(['FAB-001', 'Cotton Jersey', '160 / 60"'], [$detail['material']['item_code'], $detail['material']['item'], $detail['material']['gsm_width']]);
        $this->assertNull($detail['order']);

        $url = "/api/v1/material-procurement/assigned-materials/{$fabricLine}/followups";
        // A monitor only looks; validation as Brickland words it.
        $this->as()->postJson($url, ['followup_date' => '2026-10-23', 'status' => 'SUPPLIER_CONFIRMED'])->assertUnprocessable()->assertJsonPath('errors.line.0', 'Only the merchandiser this material was handed to logs its follow-ups.');
        $this->as($merch)->postJson($url, [])->assertUnprocessable()->assertJsonPath('errors.followup_date.0', 'Follow-up date is required.')->assertJsonPath('errors.status.0', 'Current status is required.');
        $this->as($merch)->postJson($url, ['followup_date' => '2026-10-23', 'status' => 'SUPPLIER_CONFIRMED', 'contact_person' => 'Md. Akter', 'supplier_response' => 'Production starts Monday', 'next_followup_date' => '2026-10-27'])->assertCreated()
            ->assertJsonPath('data.status', 'SUPPLIER_CONFIRMED')->assertJsonPath('data.next_followup', '2026-10-27');
        $this->as($merch)->postJson($url, ['followup_date' => '2026-10-28', 'status' => 'PARTIALLY_RECEIVED', 'received_qty' => 120])->assertUnprocessable()->assertJsonPath('errors.received_qty.0', 'Received qty cannot be more than the balance (100 KG).');
        $after = $this->as($merch)->postJson($url, ['followup_date' => '2026-10-28', 'status' => 'PARTIALLY_RECEIVED', 'received_qty' => 40, 'actual_delivery' => '2026-10-28'])->assertCreated()->json('data');
        $this->assertEquals([40, 60, 'PARTIALLY_RECEIVED', 2], [$after['received'], $after['balance'], $after['status'], count($after['followups'])]);
        $this->assertSame([false, true], array_column($after['followups'], 'can_raise_grn'));
        // Material Received: the requisition's author (here also the approver) hears it once; nothing for a follow-up that received nothing.
        $this->assertSame(["Cotton Jersey on {$pr}: 40 of 100 KG received."], Notification::query()->withoutGlobalScopes()->where('event_type', 'MP_MATERIAL_RECEIVED')->where('user_id', $this->owner->id)->pluck('body')->all());
        $this->assertSame(0, Notification::query()->withoutGlobalScopes()->where('event_type', 'MP_MATERIAL_RECEIVED')->where('user_id', $merch->id)->count());

        // Raise GRN: a draft purchased-fabric receipt with the line it came from.
        $receipt = $after['followups'][1]['id'];
        $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$receipt}/grn", [])->assertUnprocessable()
            ->assertJsonPath('errors.unit_company_id.0', 'Company is required.')->assertJsonPath('errors.store_id.0', 'Receiving store is required.');
        $grn = $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$receipt}/grn", ['unit_company_id' => $this->delta->id, 'store_id' => $this->store, 'challan_no' => 'CH-501'])->assertCreated()->json('data');
        $this->assertSame(['MRR-IMF-'.now()->format('Ym').'0001', 'FABRIC'], [$grn['grn_no'], $grn['grn_type']]);
        $mrr = PurchasedFabricMrr::query()->where('mrr_no', $grn['grn_no'])->sole();
        $this->assertEquals(['DRAFT', 'LC', 'LOCAL', 'USD', 100.0, "From Material Procurement {$pr} / QT-".now()->format('Y').'-00001'], [$mrr->status, $mrr->payment_method, $mrr->source_type, $mrr->currency, $mrr->net_amount, $mrr->remarks]);
        $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$receipt}/grn", ['unit_company_id' => $this->delta->id, 'store_id' => $this->store])->assertUnprocessable()
            ->assertJsonPath('errors.followup.0', "A goods receipt is already raised for this delivery: {$grn['grn_no']}.");
        $nothing = $after['followups'][0]['id'];
        $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$nothing}/grn", ['unit_company_id' => $this->delta->id, 'store_id' => $this->store])->assertUnprocessable()
            ->assertJsonPath('errors.followup.0', 'Nothing was received in this follow-up, so there is no goods receipt to raise.');

        // Merchandise Desk opens the draft and saves it as its own, the order-less line kept.
        $draft = $this->as()->getJson("/api/v1/merchandise/pfab-mrrs/{$grn['grn_no']}")->assertOk()->json('data');
        $this->assertEquals([null, $fabricLine, 'Cotton Jersey', 40, 2.5, 'KG'], [$draft['lines'][0]['po_line_id'], $draft['lines'][0]['mp_requisition_line_id'], $draft['lines'][0]['description'], $draft['lines'][0]['receive_qty'], $draft['lines'][0]['rate'], $draft['lines'][0]['uom']]);
        $line = $draft['lines'][0];
        $this->as()->putJson("/api/v1/merchandise/pfab-mrrs/{$grn['grn_no']}", [
            'unit_company_id' => $this->delta->id, 'supplier_id' => $draft['supplier_id'], 'store_id' => $this->store, 'mrr_date' => now()->toDateString(), 'payment_method' => 'LC', 'source_type' => 'LOCAL',
            'challan_no' => 'CH-501', 'challan_date' => now()->toDateString(), 'currency' => 'USD', 'conv_rate' => 120, 'costs' => [],
            'lines' => [['mp_requisition_line_id' => $line['mp_requisition_line_id'], 'description' => $line['description'], 'uom' => $line['uom'], 'receive_qty' => 40, 'reject_qty' => 2, 'rate' => 2.5]],
        ])->assertOk()->assertJsonPath('data.lines.0.mp_requisition_line_id', $fabricLine)->assertJsonPath('data.lines.0.accept_qty', 38)
            ->assertJsonPath('data.lines.0.gsm', '160');

        // The accessory: a draft accessories receipt on the procurement item.
        $trims = $this->as($merch)->postJson("/api/v1/material-procurement/assigned-materials/{$trimLine}/followups", ['followup_date' => '2026-10-28', 'status' => 'FULLY_RECEIVED', 'received_qty' => 500])->assertCreated()->json('data');
        $grn2 = $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$trims['followups'][0]['id']}/grn", ['unit_company_id' => $this->delta->id, 'store_id' => $this->store])->assertCreated()->json('data');
        $this->assertSame(['MRR-ACC-'.now()->format('Ym').'0001', 'TRIMS'], [$grn2['grn_no'], $grn2['grn_type']]);
        $accLine = AccReceiptLine::query()->where('mrr_id', AccReceipt::query()->where('mrr_no', $grn2['grn_no'])->value('id'))->sole();
        $this->assertEquals([$trimLine, 500, 0.08, 'PCS'], [$accLine->mp_requisition_line_id, $accLine->qty, $accLine->rate, $accLine->uom]);

        // In-house closes the line.
        $this->as($merch)->postJson($url, ['followup_date' => '2026-10-30', 'status' => 'IN_HOUSE', 'received_qty' => 60])->assertCreated()->assertJsonPath('data.closed', true)->assertJsonPath('data.balance', 0);
        $this->as($merch)->postJson($url, ['followup_date' => '2026-10-31', 'status' => 'IN_HOUSE'])->assertUnprocessable()->assertJsonPath('errors.line.0', 'In-house — this line is closed; no more follow-ups are logged on it.');

        // Delivery Tracking: every approved line, with its merchandisers and next follow-up.
        $tracking = collect($this->as()->getJson('/api/v1/material-procurement/delivery-tracking')->assertOk()->json('data.rows'))->keyBy('line_id');
        $this->assertSame(['IN_HOUSE', 100.0, 0.0, $merch->name, $this->owner->name], [$tracking[$fabricLine]['status'], (float) $tracking[$fabricLine]['received'], (float) $tracking[$fabricLine]['balance'], $tracking[$fabricLine]['followup'], $tracking[$fabricLine]['requested_by']]);
        $this->as($merch)->getJson('/api/v1/material-procurement/delivery-tracking')->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/material-procurement/assigned-materials')->assertForbidden();
    }

    public function test_a_price_outside_merchandise_currencies_cannot_be_raised_and_the_review_offers_only_followup_users(): void
    {
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $names = array_column($this->as()->getJson('/api/v1/material-procurement/price-review')->json('data.merchandisers'), 'label');
        $this->assertContains($merch->name, $names);
        // The storekeeper cannot open Assigned Materials, so nothing is handed to them.
        $this->assertNotContains($keeper->name, $names);

        [, $fabricLine] = $this->approved($merch, currency: 'GBP');
        $fu = $this->as($merch)->postJson("/api/v1/material-procurement/assigned-materials/{$fabricLine}/followups", ['followup_date' => '2026-10-28', 'status' => 'PARTIALLY_RECEIVED', 'received_qty' => 10])->assertCreated()->json('data.followups.0.id');
        $this->as($merch)->postJson("/api/v1/material-procurement/followups/{$fu}/grn", ['unit_company_id' => $this->delta->id, 'store_id' => $this->store])->assertUnprocessable()
            ->assertJsonPath('errors.followup.0', 'GBP receipts are not kept on Merchandise Desk; ask for the price in BDT, USD, EUR, CNY or INR.');
    }

    /**
     * A direct requisition of a fabric (100 KG) and an accessory (500 PCS),
     * quoted by Poly Plan Ltd and both approved and handed to the merchandiser.
     *
     * @return array{string, string, string} its number, the fabric line, the accessory line
     */
    private function approved(User $merch, string $currency = 'USD'): array
    {
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-002', 'name' => 'Poly Plan Ltd', 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Fabric'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => 'Poly Desk', 'email' => 'desk@polyplan.test', 'password' => 'Quote-Ready-2026!'])->assertCreated();
        $poly = User::query()->where('email', 'desk@polyplan.test')->firstOrFail();
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'gsm' => '160', 'width' => '60"', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $trim = $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'ACC-001', 'name' => 'Main Label', 'category' => 'Label', 'uom' => 'Pcs'])->assertCreated()->json('data.id');
        $data = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [
            ['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => 100, 'uom' => 'KG', 'req_date' => '2026-11-20'],
            ['kind' => 'TRIMS', 'item_id' => $trim, 'req_qty' => 500, 'uom' => 'PCS', 'req_date' => '2026-11-20'],
        ]])->assertCreated()->json('data');
        [$fabricLine, $trimLine] = array_column($data['lines'], 'id');
        foreach ([[$fabricLine, 100, 2.5], [$trimLine, 500, 0.08]] as [$line, $qty, $price]) {
            $qt = $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$line}/quote", ['quoted_qty' => $qty, 'unit_price' => $price, 'currency' => $currency, 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15'])
                ->assertCreated()->json('data.qt_no');
            $this->as()->postJson('/api/v1/material-procurement/price-review/quotations/'.Quotation::query()->where('qt_no', $qt)->value('id').'/approve', ['handover_to' => $merch->id])->assertOk();
        }

        return [$data['pr_no'], $fabricLine, $trimLine];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
