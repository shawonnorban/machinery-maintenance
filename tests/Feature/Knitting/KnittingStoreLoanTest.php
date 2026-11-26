<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\Identity\Models\User;
use App\Modules\Knitting\Models\KnitNeedleMove;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Loan Issue to Party (AnnotechERP 15/08): a needle or oil
 * loan issue indent, each on its own book and numbers; and the party's
 * return challan against it — never more than the indent issued and has
 * not had back, received only with someone named as counting it in, the
 * paper's quantity filled in where Received Qty was left at zero, posted to
 * the book and taken off what the party owes.
 */
class KnittingStoreLoanTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $needle;

    private string $oil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $c = $this->delta->id;
        $ndl = GsCategory::query()->create(['company_id' => $c, 'code' => 'NDL', 'name' => 'Needle & Sinker', 'active' => true])->id;
        $lub = GsCategory::query()->create(['company_id' => $c, 'code' => 'LUB', 'name' => 'Lubricants', 'active' => true])->id;
        $pcs = GsUnit::query()->create(['company_id' => $c, 'code' => 'PCS', 'name' => 'Pieces', 'active' => true])->id;
        $ltr = GsUnit::query()->create(['company_id' => $c, 'code' => 'LTR', 'name' => 'Litre', 'active' => true])->id;
        $this->needle = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $ndl, 'gs_unit_id' => $pcs, 'item_code' => 'ND-VO-71', 'name' => 'Vo 71.52 needle', 'active' => true])->id;
        $this->oil = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $lub, 'gs_unit_id' => $ltr, 'item_code' => 'OIL-KN-22', 'name' => 'Knitting oil ISO 22', 'active' => true])->id;
        $this->store = GsStore::query()->create(['company_id' => $c, 'code' => 'MAINT', 'name' => 'Maintenance Store', 'active' => true])->id;
        $today = now()->toDateString();
        KnitNeedleMove::query()->create(['book' => 'NEEDLE', 'store_id' => $this->store, 'item_id' => $this->needle, 'move_date' => $today, 'kind' => 'OPENING', 'doc_no' => 'OB-N', 'qty' => 100]);
        KnitNeedleMove::query()->create(['book' => 'OIL', 'store_id' => $this->store, 'item_id' => $this->oil, 'move_date' => $today, 'kind' => 'OPENING', 'doc_no' => 'OB-O', 'qty' => 200]);
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => 'C-901', 'name' => 'Store Keeper', 'nid' => '1234567890', 'mobile' => '01712345678',
            'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();
        $this->employee = DB::table('hr_employees')->where('company_id', $c)->where('card_no', 'C-901')->value('id');
    }

    private string $employee;

    public function test_loan_issue_and_party_return_on_both_books(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $party = MaterialSupplier::query()->create(['code' => 'SUP-80001', 'name' => 'Square Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $other = MaterialSupplier::query()->create(['code' => 'SUP-80002', 'name' => 'Akij Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        // Loan issue indents: each book its own numbers and list.
        $n = $this->issued('NEEDLE', $party, $this->needle, 40, 30);
        $o = $this->issued('OIL', $party, $this->oil, 50, 50);
        $this->assertEquals(["NLR-{$ym}0001", "OLR-{$ym}0001"], [$n['req_no'], $o['req_no']]);
        $this->assertEquals([70.0, 150.0], [NeedleStore::balance($this->store, $this->needle), NeedleStore::balance($this->store, $this->oil)]);
        $this->assertEquals(['NEEDLE', 'OIL'], [KnitNeedleMove::query()->where('doc_no', $n['req_no'])->value('book'), KnitNeedleMove::query()->where('doc_no', $o['req_no'])->value('book')]);
        $this->assertEquals([$o['req_no']], array_column($this->as()->getJson('/api/v1/knitting/needle/loan-reqs?book=OIL&kind=LOAN_ISSUE')->json('data'), 'req_no'));
        $this->as()->getJson('/api/v1/knitting/needle/loan-reqs?book=NEEDLE&kind=LOAN_RETURN')->assertJsonCount(0, 'data');

        // The party's return: against its own indent, never more than it owes.
        $owing = $this->as()->getJson('/api/v1/knitting/needle/loan-returns/needle/owing')->json('data.0');
        $this->assertEquals([$n['req_no'], 30.0], [$owing['value'], $owing['lines'][0]['owes']]);
        $r = ['ret_challan_no' => 'SQ/RET-77', 'ret_challan_date' => $today, 'req_no' => $n['req_no'], 'party_id' => $party, 'store_id' => $this->store,
            'lines' => [['item_id' => $this->needle, 'uom' => 'pcs', 'return_qty' => 35]]];
        $this->as()->postJson('/api/v1/knitting/needle/loan-returns/needle', ['lines' => []] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A return challan with no needles on it brings nothing back. Add a line before saving.');
        $this->as()->postJson('/api/v1/knitting/needle/loan-returns/needle', ['party_id' => $other] + $r)->assertUnprocessable()->assertJsonValidationErrors('req_no');
        $this->as()->postJson('/api/v1/knitting/needle/loan-returns/needle', $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.return_qty' => 'Line 1 hands back more ND-VO-71 · Vo 71.52 needle than the party still owes on the indent (30).']);
        $ret = $this->as()->postJson('/api/v1/knitting/needle/loan-returns/needle', ['lines' => [['return_qty' => 20] + $r['lines'][0]]] + $r)->assertCreated()->json('data');
        $no = $ret['return_no'];
        $this->assertEquals(["NLT-{$ym}0001", 'PCS', 'DRAFT'], [$no, $ret['lines'][0]['uom'], $ret['status']]);
        $this->as()->postJson('/api/v1/knitting/needle/loan-returns/needle', ['req_no' => null, 'lines' => [['return_qty' => 1] + $r['lines'][0]]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors('ret_challan_no');

        // Received only with a receiver; the paper fills Received Qty; posted and taken off what is owed.
        $this->as()->postJson("/api/v1/knitting/needle/loan-returns/needle/{$no}/receive")->assertUnprocessable()->assertJsonValidationErrors('received_by');
        $this->as()->putJson("/api/v1/knitting/needle/loan-returns/needle/{$no}", ['received_by' => $this->employee, 'lines' => [['return_qty' => 20] + $r['lines'][0]]] + $r)->assertOk();
        $got = $this->as()->postJson("/api/v1/knitting/needle/loan-returns/needle/{$no}/receive")->assertOk()->json('data');
        $this->assertEquals(['RECEIVED', $today, 20.0, 'Store Keeper'], [$got['status'], $got['rcv_date'], $got['lines'][0]['received_qty'], $got['receiver']]);
        $this->assertEquals(90.0, NeedleStore::balance($this->store, $this->needle));
        $loan = $this->as()->getJson("/api/v1/knitting/needle/loan-reqs/{$n['req_no']}")->json('data.lines.0');
        $this->assertEquals([20.0, 10.0], [$loan['returned'], $loan['owes']]);
        $row = $this->as()->getJson('/api/v1/knitting/needle/loan-reqs?book=NEEDLE&kind=LOAN_ISSUE')->json('data.0');
        $this->assertEquals([30.0, 20.0, 10.0], [$row['issued'], $row['returned'], $row['owes']]);
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$n['req_no']}", ['lines' => [['id' => $n['lines'][0]['id'], 'issue_qty' => 15]]])->assertUnprocessable()
            ->assertJsonValidationErrors('lines');
        $this->as()->getJson('/api/v1/knitting/needle/loan-returns/needle/owing')->assertJsonPath('data.0.lines.0.owes', 10);

        // Cancelling a received challan takes it back off the book; finalised ones stand.
        $this->as()->postJson("/api/v1/knitting/needle/loan-returns/needle/{$no}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertEquals(70.0, NeedleStore::balance($this->store, $this->needle));

        // The oil book works the same way on its own numbers.
        $oret = $this->as()->postJson('/api/v1/knitting/needle/loan-returns/oil', ['ret_challan_no' => 'SQ/OIL-5', 'ret_challan_date' => $today, 'req_no' => $o['req_no'],
            'party_id' => $party, 'store_id' => $this->store, 'received_by' => $this->employee, 'lines' => [['item_id' => $this->oil, 'return_qty' => 50, 'received_qty' => 48]]])->assertCreated()->json('data');
        $this->assertSame("OLT-{$ym}0001", $oret['return_no']);
        $this->as()->getJson("/api/v1/knitting/needle/loan-returns/needle/{$oret['return_no']}")->assertNotFound();
        $this->as()->postJson("/api/v1/knitting/needle/loan-returns/oil/{$oret['return_no']}/receive")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/loan-returns/oil/{$oret['return_no']}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->assertEquals(198.0, NeedleStore::balance($this->store, $this->oil));
        $this->assertSame('OIL', KnitNeedleMove::query()->where('doc_no', $oret['return_no'])->value('book'));
        $this->as()->postJson("/api/v1/knitting/needle/loan-returns/oil/{$oret['return_no']}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    /** A loan issue indent on a book, verified and issued. */
    private function issued(string $book, string $party, string $item, float $asked, float $issued): array
    {
        $loan = $this->as()->postJson('/api/v1/knitting/needle/loan-reqs', ['book' => $book, 'kind' => 'LOAN_ISSUE', 'req_date' => now()->toDateString(), 'party_id' => $party,
            'store_id' => $this->store, 'lines' => [['item_id' => $item, 'required_qty' => $asked, 'rate' => 5]]])->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/knitting/needle/loan-reqs/{$loan['req_no']}/submit-verify")->assertOk();
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$loan['req_no']}", ['lines' => [['id' => $loan['lines'][0]['id'], 'issue_qty' => $issued]]])->assertOk();

        return $loan;
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
