<?php

declare(strict_types=1);

namespace Tests\Feature\DyeHouse;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dye House → Chemical Store (AnnotechERP 17/06): the lot-wise dye and
 * chemical book — opening balances, receipts counted in once finalised and
 * returns sent back against them, issues taken from the named lot or the
 * soonest to expire, transfers landing in the receiving store, loans both
 * ways kept within what is owed, the comparative statement's workflow, the
 * month closing that locks a month, and who may do what.
 */
class DyeHouseChemStoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $central;

    private string $sub;

    private string $acid;

    private string $soda;

    private string $ym;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->central = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Chemical Store', 'code' => 'ST-CHM', 'active' => true])->id;
        $this->sub = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Dyeing Floor', 'code' => 'ST-DYE', 'active' => true])->id;
        $kg = UnitOfMeasure::availableTo($this->delta->id)->where('code', 'KG')->value('id');
        $dept = ItemDepartment::query()->create(['company_id' => $this->delta->id, 'code' => 'DYES_CHEMICAL_STORE', 'name' => 'Dyes Chemical Store', 'active' => true])->id;
        $this->acid = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dept, 'item_code' => 'DC-0003', 'name' => 'Acetic Acid', 'uom_id' => $kg, 'active' => true])->id;
        $this->soda = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dept, 'item_code' => 'DC-0006', 'name' => 'Soda Ash', 'uom_id' => $kg, 'active' => true])->id;
        $this->ym = now('Asia/Dhaka')->format('Ym');
        $this->today = now('Asia/Dhaka')->toDateString();
    }

    public function test_opening_balance_books_each_lot_into_stock(): void
    {
        $ob = ['store_id' => $this->central, 'ob_date' => $this->today, 'item_id' => $this->acid, 'source' => 'IMPORT', 'pack_qty' => 4, 'qty_per_pack' => 25, 'loose_qty' => 3.5, 'uom' => 'kg', 'rate_bdt' => 180];
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', $ob)->assertUnprocessable()->assertJsonPath('errors.lot_no.0', 'Lot# is required — or tick Auto? to have the store give one.');
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['lot_no' => 'x', 'pack_qty' => null, 'loose_qty' => null] + $ob)->assertUnprocessable()->assertJsonValidationErrors('total_qty');
        // Left blank, the total is packs × qty per pack plus the loose; the lot number is kept upper case; the unit is the master's code.
        $id = $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['lot_no' => ' aa-2611 '] + $ob)->assertCreated()->assertJsonPath('data.total_qty', 103.5)
            ->assertJsonPath('data.lot_no', 'AA-2611')->assertJsonPath('data.uom', 'KG')->assertJsonPath('data.value', 18630)->assertJsonPath('data.item', 'Acetic Acid (DC-0003)')->json('data.id');
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['lot_no' => 'AA-2611'] + $ob)->assertUnprocessable()
            ->assertJsonPath('errors.lot_no.0', "Lot AA-2611 of this item is on this store's opening already — the same lot twice would be counted twice.");
        // Ticked Auto?, the store numbers the drum; a counted total differing from the packs is kept as counted.
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['auto_lot' => true, 'item_id' => $this->soda, 'total_qty' => 48] + $ob)->assertCreated()
            ->assertJsonPath('data.lot_no', 'OB'.now('Asia/Dhaka')->format('ym').'0001')->assertJsonPath('data.total_qty', 48);
        $stock = collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->assertOk()->json('data'))->keyBy('lot_no');
        $this->assertEquals([103.5, 48], [$stock['AA-2611']['stock'], $stock['OB'.now('Asia/Dhaka')->format('ym').'0001']['stock']]);
        $this->assertSame('Chemical Store (ST-CHM)', $stock['AA-2611']['store']);

        // A recount before anything has moved rewrites the opening; once the lot has moved, it stays as counted.
        $this->as()->patchJson("/api/v1/erp/dye-chem-openings/{$id}", ['total_qty' => 100])->assertOk();
        $this->assertEquals(100, collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->firstWhere('lot_no', 'AA-2611')['stock']);
        $this->issueWash([['item_id' => $this->acid, 'required_qty' => 10]], true);
        $this->as()->patchJson("/api/v1/erp/dye-chem-openings/{$id}", ['total_qty' => 90])->assertUnprocessable()->assertJsonPath('errors.lot_no.0', 'Lot AA-2611 has moved since it was opened, so its opening stays as counted.');
        $this->as()->deleteJson("/api/v1/erp/dye-chem-openings/{$id}")->assertUnprocessable();
    }

    public function test_receipts_count_in_once_finalised_and_returns_go_back_against_them(): void
    {
        $sup = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Huntsman BD', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $rc = ['kind' => 'PURCHASE', 'doc_date' => $this->today, 'store_id' => $this->central, 'supplier_id' => $sup, 'source' => 'IMPORT', 'challan_no' => 'CH-77', 'lc_no' => 'LC-0912', 'pay_method' => 'L/C at sight',
            'lines' => [['item_id' => $this->acid, 'lot_no' => 'L-1', 'exp_date' => '2027-06-30', 'qty' => 200, 'uom' => 'KG', 'rate_bdt' => 175], ['item_id' => $this->soda, 'lot_no' => 'S-1', 'qty' => 500, 'uom' => 'KG', 'rate_bdt' => 60]]];
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['lines' => []] + $rc)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A document with no items moves nothing. Add at least one line.');
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['source' => null] + $rc)->assertUnprocessable()->assertJsonPath('errors.source.0', 'Source is required.');
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['lines' => [['item_id' => $this->acid, 'qty' => 5]]] + $rc)->assertUnprocessable()->assertJsonValidationErrors('lines.0.lot_no');
        $mrr = "MRR-{$this->ym}0001";
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', $rc)->assertCreated()->assertJsonPath('data.doc_no', $mrr)->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.items', 2)->assertJsonPath('data.qty', 700)->assertJsonPath('data.value', 65000)->assertJsonPath('data.finalized', false);
        // A draft books nothing in, and is rewritten freely.
        $this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->assertJsonCount(0, 'data');
        $this->as()->putJson("/api/v1/dyehouse/chem-receipts/{$mrr}", ['remarks' => 'Two drums dented'] + array_diff_key($rc, ['kind' => 1]))->assertOk()->assertJsonPath('data.remarks', 'Two drums dented');
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$mrr}/verify")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$mrr}/finalise")->assertOk()->assertJsonPath('data.status', 'RECEIVED')->assertJsonPath('data.finalized', true);
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$mrr}/finalise")->assertUnprocessable();
        $this->as()->putJson("/api/v1/dyehouse/chem-receipts/{$mrr}", array_diff_key($rc, ['kind' => 1]))->assertUnprocessable()->assertJsonPath('errors.doc_no.0', "{$mrr} is past draft — it reads only.");
        $stock = collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->keyBy('lot_no');
        $this->assertEquals([200, 175, '2027-06-30', 500], [$stock['L-1']['stock'], $stock['L-1']['rate_bdt'], $stock['L-1']['exp_date'], $stock['S-1']['stock']]);
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$mrr}/verify")->assertOk()->assertJsonPath('data.status', 'VERIFIED')->assertJsonPath('data.accounted', true);

        // A second receipt, left a draft, cannot be returned against.
        $draft = $this->as()->postJson('/api/v1/dyehouse/chem-receipts', $rc)->assertCreated()->json('data.doc_no');
        $rt = ['return_date' => $this->today, 'mrr_no' => $draft, 'return_type' => 'DAMAGE', 'lines' => []];
        $this->as()->postJson('/api/v1/dyehouse/chem-returns', $rt)->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->as()->postJson('/api/v1/dyehouse/chem-returns', ['lines' => [['receipt_line_id' => 'x', 'qty' => 1]]] + $rt)->assertUnprocessable()
            ->assertJsonPath('errors.mrr_no.0', "{$draft} is not a finalised purchase receipt — nothing came in on it to send back.");
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$draft}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        // The return names the receipt's lots, at most what is left of each; it comes off stock only once finalised.
        $source = $this->as()->getJson("/api/v1/dyehouse/chem-receipts/{$mrr}/return-source")->assertOk()->assertJsonPath('data.supplier', 'Huntsman BD')->json('data.lines');
        $line = collect($source)->firstWhere('lot_no', 'L-1')['id'];
        $rt = ['mrr_no' => $mrr, 'lines' => [['receipt_line_id' => $line, 'qty' => 201]]] + $rt;
        $this->as()->postJson('/api/v1/dyehouse/chem-returns', $rt)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['lines.0.qty'][0] ?? null) === 'Only 200 of Acetic Acid (DC-0003) lot L-1 is left to send back.');
        $no = "RTN-{$this->ym}0001";
        $this->as()->postJson('/api/v1/dyehouse/chem-returns', ['lines' => [['receipt_line_id' => $line, 'qty' => 20, 'remarks' => 'Dented']]] + $rt)->assertCreated()
            ->assertJsonPath('data.return_no', $no)->assertJsonPath('data.store', 'Chemical Store (ST-CHM)')->assertJsonPath('data.supplier', 'Huntsman BD')->assertJsonPath('data.lines.0.lot_no', 'L-1');
        $this->as()->getJson("/api/v1/dyehouse/chem-receipts/{$mrr}/return-source")->assertJsonPath('data.lines.0.returnable', 180);
        $this->assertEquals(200, collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->firstWhere('lot_no', 'L-1')['stock']);
        $this->as()->postJson("/api/v1/dyehouse/chem-returns/{$no}/finalise")->assertOk()->assertJsonPath('data.status', 'FINALIZED')->assertJsonPath('data.finalized', true);
        $this->assertEquals(180, collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->firstWhere('lot_no', 'L-1')['stock']);
        $this->as()->postJson("/api/v1/dyehouse/chem-returns/{$no}/cancel")->assertUnprocessable();
        $this->as()->getJson('/api/v1/dyehouse/chem-returns')->assertJsonCount(1, 'data')->assertJsonPath('data.0.mrr_no', $mrr);
    }

    public function test_issues_take_the_soonest_to_expire_first_and_transfers_land_in_the_receiving_store(): void
    {
        $this->opening($this->central, $this->acid, 'LATE', 50, '2027-12-31', 200);
        $this->opening($this->central, $this->acid, 'SOON', 30, '2027-01-31', 160);
        $this->opening($this->central, $this->soda, 'S-1', 10, null, 60);

        // Asked for more than the store holds, the issue is refused naming what is there.
        $wash = $this->issueWash([['item_id' => $this->acid, 'required_qty' => 45], ['item_id' => $this->soda, 'required_qty' => 12]]);
        $lines = $this->as()->getJson("/api/v1/dyehouse/chem-issues/{$wash}")->assertOk()->assertJsonPath('data.status', 'WAITING')->assertJsonPath('data.required_qty', 57)
            ->assertJsonPath('data.lines.0.in_store', 80)->json('data.lines');
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$wash}/issue", ['lines' => [['id' => $lines[1]['id'], 'qty' => 12]]])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Only 10 of Soda Ash (DC-0006) is in Chemical Store — 12 was asked.');
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$wash}/issue", ['lines' => [['id' => $lines[0]['id'], 'qty' => 46]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty');
        // What is given may fall short of what was asked; a line naming no lot takes the soonest to expire first.
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$wash}/issue", ['lines' => [['id' => $lines[0]['id'], 'qty' => 45], ['id' => $lines[1]['id'], 'qty' => 10]]])->assertOk()
            ->assertJsonPath('data.status', 'PARTIAL')->assertJsonPath('data.issued_qty', 55)->assertJsonPath('data.balance_qty', 2);
        $stock = collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->keyBy('lot_no');
        $this->assertEquals(35, $stock['LATE']['stock']);
        $this->assertFalse(isset($stock['SOON']) || isset($stock['S-1']));
        $this->as()->putJson("/api/v1/dyehouse/chem-issues/{$wash}", ['issue_date' => $this->today, 'req_type' => 'x', 'store_id' => $this->central, 'lines' => [['item_id' => $this->acid, 'required_qty' => 1]]])
            ->assertUnprocessable()->assertJsonPath('errors.issue_no.0', "{$wash} has been verified or issued against — it reads only.");
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$wash}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonPath('data.balance_qty', 0);

        // A transfer waits on verification, then lands what it issues in the receiving store, lot for lot.
        $st = ['kind' => 'TRANSFER', 'issue_date' => $this->today, 'req_type' => 'Central to Sub', 'store_id' => $this->central, 'to_store_id' => $this->central, 'lines' => [['item_id' => $this->acid, 'lot_no' => 'late', 'required_qty' => 20]]];
        $this->as()->postJson('/api/v1/dyehouse/chem-issues', $st)->assertUnprocessable()->assertJsonPath('errors.to_store_id.0', 'A transfer runs between two different stores.');
        $no = $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['to_store_id' => $this->sub] + $st)->assertCreated()->assertJsonPath('data.issue_no', "REQ-ST-{$this->ym}0001")
            ->assertJsonPath('data.status', 'WAITING_VERIFICATION')->assertJsonPath('data.lines.0.lot_no', 'LATE')->json('data.issue_no');
        $line = $this->as()->getJson("/api/v1/dyehouse/chem-issues/{$no}")->json('data.lines.0.id');
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$no}/issue", ['lines' => [['id' => $line, 'qty' => 20]]])->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} waits on verification — the store cannot issue against it yet.");
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$no}/verify")->assertOk()->assertJsonPath('data.status', 'VERIFIED');
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$no}/issue", ['gate_pass' => 'GP-1102', 'lines' => [['id' => $line, 'qty' => 20]]])->assertOk()
            ->assertJsonPath('data.status', 'ISSUED')->assertJsonPath('data.gate_pass', 'GP-1102');
        $stock = collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'));
        $this->assertEquals([15, 20], [$stock->where('store', 'Chemical Store (ST-CHM)')->firstWhere('lot_no', 'LATE')['stock'], $stock->where('store', 'Dyeing Floor (ST-DYE)')->firstWhere('lot_no', 'LATE')['stock']]);
        $this->assertSame('2027-12-31', $stock->where('store', 'Dyeing Floor (ST-DYE)')->firstWhere('lot_no', 'LATE')['exp_date']);

        // A sale prices every line; a batch issue names its batch's machine.
        $sale = ['kind' => 'SALE', 'issue_date' => $this->today, 'req_type' => 'Store Requisition for Sales', 'store_id' => $this->central, 'lines' => [['item_id' => $this->acid, 'required_qty' => 5]]];
        $this->as()->postJson('/api/v1/dyehouse/chem-issues', $sale)->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'lines.0.rate_bdt']);
        $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['kind' => 'WASH', 'to_store_id' => $this->sub] + $sale)->assertUnprocessable()->assertJsonValidationErrors('to_store_id');
    }

    public function test_loans_both_ways_stay_within_what_is_owed(): void
    {
        $lender = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Pacific Dyeing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $borrower = MaterialSupplier::query()->create(['code' => 'SUP-2', 'name' => 'Rupa Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $lo = ['ob_date' => $this->today, 'supplier_id' => $lender, 'item_id' => $this->acid, 'uom' => 'KG'];
        $this->as()->postJson('/api/v1/erp/dye-chem-loan-openings', $lo)->assertUnprocessable()->assertJsonPath('errors.loan_received.0', 'Give the Loan Received, the Loan Issued, or both.');
        $id = $this->as()->postJson('/api/v1/erp/dye-chem-loan-openings', ['loan_received' => 15] + $lo)->assertCreated()->assertJsonPath('data.supplier', 'Pacific Dyeing')->json('data.id');
        $this->as()->postJson('/api/v1/erp/dye-chem-loan-openings', ['loan_received' => 5] + $lo)->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->as()->patchJson("/api/v1/erp/dye-chem-loan-openings/{$id}", ['is_final' => true])->assertOk();
        $this->as()->patchJson("/api/v1/erp/dye-chem-loan-openings/{$id}", ['loan_received' => 16])->assertUnprocessable()
            ->assertJsonPath('errors.is_final.0', 'This loan position is final — both sides agreed it, so it is no longer edited.');
        $this->as()->postJson('/api/v1/erp/dye-chem-loan-openings', ['supplier_id' => $borrower, 'loan_issued' => 4] + $lo)->assertCreated();

        // Borrowed in on a loan receipt: stock in once finalised, and we owe the lender that much more.
        $in = ['kind' => 'LOAN_IN', 'doc_date' => $this->today, 'store_id' => $this->central, 'source' => 'LOCAL', 'lines' => [['item_id' => $this->acid, 'lot_no' => 'PD-9', 'qty' => 25, 'uom' => 'KG']]];
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', $in)->assertUnprocessable()->assertJsonPath('errors.supplier_id.0', 'Supplier is required.');
        $mrr = $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['supplier_id' => $lender] + $in)->assertCreated()->assertJsonPath('data.doc_no', "MRR-DC-{$this->ym}0001")->json('data.doc_no');
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$mrr}/finalise")->assertOk();

        // Going back to the lender: no more than the 15 opening and the 25 borrowed.
        $back = ['kind' => 'LOAN_RETURN', 'issue_date' => $this->today, 'req_type' => 'Store Requisition for Loan Return', 'store_id' => $this->central, 'supplier_id' => $lender,
            'lines' => [['item_id' => $this->acid, 'required_qty' => 41]]];
        $this->as()->postJson('/api/v1/dyehouse/chem-issues', $back)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'We owe Pacific Dyeing only 40 of Acetic Acid (DC-0003).');
        $lr = $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['lines' => [['item_id' => $this->acid, 'required_qty' => 25]]] + $back)->assertCreated()->assertJsonPath('data.issue_no', "IS-LR-{$this->ym}0001")->json('data');
        $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['lines' => [['item_id' => $this->acid, 'required_qty' => 16]]] + $back)->assertUnprocessable();
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$lr['issue_no']}/issue", ['lines' => [['id' => $lr['lines'][0]['id'], 'qty' => 25]]])->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->assertJsonCount(0, 'data');

        // Lent out to a party, and back from it: no more back than it owes.
        $this->opening($this->central, $this->soda, 'S-1', 30, null, 60);
        $out = $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['kind' => 'LOAN_OUT', 'issue_date' => $this->today, 'req_type' => 'Store Requisition for Loan Issue', 'store_id' => $this->central, 'supplier_id' => $borrower,
            'lines' => [['item_id' => $this->soda, 'required_qty' => 10]]])->assertCreated()->assertJsonPath('data.issue_no', "IS-LN-{$this->ym}0001")->json('data');
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$out['issue_no']}/issue", ['lines' => [['id' => $out['lines'][0]['id'], 'qty' => 10]]])->assertOk();
        $lb = ['kind' => 'LOAN_BACK', 'doc_date' => $this->today, 'store_id' => $this->central, 'supplier_id' => $borrower, 'return_type' => 'PARTIAL', 'received_by' => 'Store keeper',
            'lines' => [['item_id' => $this->soda, 'lot_no' => 'S-1', 'qty' => 7]]];
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['return_type' => null] + $lb)->assertUnprocessable()->assertJsonPath('errors.return_type.0', 'Return Type is required.');
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['lines' => [['item_id' => $this->acid, 'lot_no' => 'X', 'qty' => 5]]] + $lb)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Rupa Processing owes us only 4 of Acetic Acid (DC-0003).');
        $rtn = $this->as()->postJson('/api/v1/dyehouse/chem-receipts', $lb)->assertCreated()->assertJsonPath('data.doc_no', "RTN-LP-{$this->ym}0001")->assertJsonPath('data.received_by', 'Store keeper')->json('data.doc_no');
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['lines' => [['item_id' => $this->soda, 'lot_no' => 'S-1', 'qty' => 8]]] + $lb)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Rupa Processing owes us only 3 of Soda Ash (DC-0006).');
        $this->as()->postJson("/api/v1/dyehouse/chem-receipts/{$rtn}/finalise")->assertOk();
        $this->assertEquals(27, collect($this->as()->getJson('/api/v1/dyehouse/chem-store/stock')->json('data'))->firstWhere('lot_no', 'S-1')['stock']);
    }

    public function test_comparative_statement_runs_draft_to_closed(): void
    {
        $a = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Huntsman BD', 'supplier_type' => 'FOREIGN', 'active' => true])->id;
        $b = MaterialSupplier::query()->create(['code' => 'SUP-2', 'name' => 'Archroma BD', 'supplier_type' => 'FOREIGN', 'active' => true])->id;
        $cs = ['cs_date' => $this->today, 'mou_code' => 'MOU-26', 'lines' => [
            ['item_id' => $this->acid, 'qty' => 500, 'uom' => 'KG', 'quotes' => [['supplier_id' => $a, 'rate_usd' => 1.45], ['supplier_id' => $b, 'rate_usd' => 1.38]]],
            ['item_id' => $this->soda, 'qty' => 1000, 'uom' => 'KG', 'quotes' => [['supplier_id' => $a, 'rate_usd' => 0.52]]],
        ]];
        $this->as()->postJson('/api/v1/dyehouse/chem-cs', $cs)->assertUnprocessable()->assertJsonPath('errors.usd_rate.0', 'Quotes in dollars need the USD-2-BDT rate to be priced in taka.');
        $cs['usd_rate'] = 122;
        $cs['lines'][1]['chosen_supplier_id'] = $b;
        $this->as()->postJson('/api/v1/dyehouse/chem-cs', $cs)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => ($e['lines.1.chosen_supplier_id'][0] ?? null) === 'Row 2: the chosen supplier has not quoted for that item.');
        $cs['lines'][1]['chosen_supplier_id'] = $a;
        $no = "CS-{$this->ym}0001";
        $this->as()->postJson('/api/v1/dyehouse/chem-cs', $cs)->assertCreated()->assertJsonPath('data.cs_no', $no)->assertJsonPath('data.quotes', 2)->assertJsonPath('data.items', 2)
            ->assertJsonPath('data.lines.0.quotes.1.rate_bdt', 168.36)->assertJsonPath('data.suppliers.1.label', 'Archroma BD');
        // Every item needs its supplier chosen before it is submitted.
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/submit")->assertUnprocessable()->assertJsonPath('errors.lines.0', "{$no} has an item with no quote or no supplier chosen — finish those before submitting.");
        $cs['lines'][0]['chosen_supplier_id'] = $b;
        $this->as()->putJson("/api/v1/dyehouse/chem-cs/{$no}", $cs)->assertOk()->assertJsonPath('data.lines.0.chosen', 'Archroma BD');
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/approve")->assertUnprocessable();
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/dyehouse/chem-cs/{$no}", $cs)->assertUnprocessable()->assertJsonValidationErrors('cs_no');
        // Rejected, it is reworked (back to draft) and goes round again.
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/reject", ['note' => 'Ask Archroma for a better soda rate'])->assertOk()->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.decision_note', 'Ask Archroma for a better soda rate');
        $this->as()->putJson("/api/v1/dyehouse/chem-cs/{$no}", $cs)->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.decision_note', null);
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/submit")->assertOk();
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->as()->postJson("/api/v1/dyehouse/chem-cs/{$no}/maybe")->assertNotFound();
        $this->as()->getJson('/api/v1/dyehouse/chem-cs')->assertJsonCount(1, 'data')->assertJsonPath('data.0.mou_code', 'MOU-26');
    }

    public function test_month_closing_lists_open_paper_and_then_locks_the_month(): void
    {
        $prev = now('Asia/Dhaka')->subMonthNoOverflow();
        $day = $prev->copy()->day(10)->toDateString();
        $label = $prev->format('M/Y');
        $this->opening($this->central, $this->acid, 'L-1', 40, null, 150, $day);
        $wash = $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['kind' => 'WASH', 'issue_date' => $day, 'req_type' => 'Store Requisition for M/C Wash & Finishing', 'store_id' => $this->central,
            'lines' => [['item_id' => $this->acid, 'required_qty' => 4]]])->assertCreated()->json('data');

        $this->as()->postJson('/api/v1/dyehouse/chem-closings', ['period' => 'Smarch/2026'])->assertUnprocessable()->assertJsonPath('errors.period.0', '"Smarch/2026" names no month — write 2026-07 or Jul/2026.');
        $this->as()->postJson('/api/v1/dyehouse/chem-closings', ['period' => now('Asia/Dhaka')->addMonthNoOverflow()->format('Y-m')])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->as()->postJson('/api/v1/dyehouse/chem-closings', ['period' => $label])->assertUnprocessable()
            ->assertJsonPath('errors.period.0', "{$label} still has 1 open paper(s) — finish those first: {$wash['issue_no']} ({$day})");
        $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$wash['issue_no']}/issue", ['issue_date' => $day, 'lines' => [['id' => $wash['lines'][0]['id'], 'qty' => 4]]])->assertOk();

        $this->as()->postJson('/api/v1/dyehouse/chem-closings', ['period' => $prev->format('Y-m'), 'remarks' => 'Counted with accounts'])->assertCreated()
            ->assertJsonPath('data.lots', 1)->assertJsonPath('data.closing_qty', 36)->assertJsonPath('data.closing_value', 5400);
        $this->as()->postJson('/api/v1/dyehouse/chem-closings', ['period' => $label])->assertUnprocessable()->assertJsonPath('errors.period.0', "{$label} is closed already.");
        $this->as()->getJson('/api/v1/dyehouse/chem-closings')->assertJsonPath('data.0.period_label', $label)->assertJsonPath('data.0.remarks', 'Counted with accounts');
        // The struck month takes no more movement.
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['store_id' => $this->central, 'ob_date' => $day, 'item_id' => $this->soda, 'source' => 'LOCAL', 'lot_no' => 'S-9', 'total_qty' => 5])
            ->assertUnprocessable()->assertJsonPath('errors.ob_date.0', $prev->format('F Y').' is closed — the chemical store takes no more movement in it.');
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['kind' => 'PURCHASE', 'doc_date' => $day, 'store_id' => $this->central, 'source' => 'LOCAL', 'lines' => [['item_id' => $this->soda, 'lot_no' => 'S-9', 'qty' => 5]]])
            ->assertUnprocessable()->assertJsonValidationErrors('doc_date');
        $this->as()->postJson('/api/v1/dyehouse/chem-receipts', ['kind' => 'PURCHASE', 'doc_date' => $this->today, 'store_id' => $this->central, 'source' => 'LOCAL', 'lines' => [['item_id' => $this->soda, 'lot_no' => 'S-9', 'qty' => 5]]])->assertCreated();
    }

    public function test_who_may_raise_issue_approve_and_close(): void
    {
        $this->opening($this->central, $this->acid, 'L-1', 40, null, 150);
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $wash = $this->issueWash([['item_id' => $this->acid, 'required_qty' => 4]]);
        $line = $this->as()->getJson("/api/v1/dyehouse/chem-issues/{$wash}")->json('data.lines.0.id');
        $this->withToken($manager)->postJson("/api/v1/dyehouse/chem-issues/{$wash}/issue", ['lines' => [['id' => $line, 'qty' => 4]]])->assertForbidden();
        $this->withToken($store)->postJson("/api/v1/dyehouse/chem-issues/{$wash}/issue", ['lines' => [['id' => $line, 'qty' => 4]]])->assertOk();
        $this->withToken($store)->getJson('/api/v1/dyehouse/chem-store/stock')->assertOk();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->postJson('/api/v1/dyehouse/chem-cs/CS-0/approve')->assertForbidden();
        $this->withToken($manager)->postJson('/api/v1/dyehouse/chem-closings', ['period' => now('Asia/Dhaka')->subYear()->format('Y-m')])->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/dyehouse/chem-closings', ['period' => now('Asia/Dhaka')->subYear()->format('Y-m')])->assertCreated();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/dyehouse/chem-store/stock')->assertForbidden();
        $this->withToken($technician)->getJson('/api/v1/erp/dye-chem-openings')->assertForbidden();
    }

    private function opening(string $store, string $item, string $lot, float $qty, ?string $exp, float $rate, ?string $date = null): void
    {
        $this->as()->postJson('/api/v1/erp/dye-chem-openings', ['store_id' => $store, 'ob_date' => $date ?? $this->today, 'item_id' => $item, 'source' => 'LOCAL', 'lot_no' => $lot,
            'exp_date' => $exp, 'total_qty' => $qty, 'uom' => 'KG', 'rate_bdt' => $rate])->assertCreated();
    }

    /** A machine wash issue raised (and, given, issued in full): its number. */
    private function issueWash(array $lines, bool $issue = false): string
    {
        $row = $this->as()->postJson('/api/v1/dyehouse/chem-issues', ['kind' => 'WASH', 'issue_date' => $this->today, 'req_type' => 'Store Requisition for M/C Wash & Finishing', 'store_id' => $this->central, 'lines' => $lines])
            ->assertCreated()->json('data');
        if ($issue) {
            $this->as()->postJson("/api/v1/dyehouse/chem-issues/{$row['issue_no']}/issue", ['lines' => array_map(fn ($l) => ['id' => $l['id'], 'qty' => $l['required_qty']], $row['lines'])])->assertOk();
        }

        return $row['issue_no'];
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
