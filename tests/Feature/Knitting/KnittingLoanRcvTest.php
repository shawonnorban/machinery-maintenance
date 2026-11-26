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
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Loan Rcv from Party (AnnotechERP 15/09): borrowed stock
 * received on the store's own MRR posts to its book only on Verify; the
 * loan-return indent sending it back never returns more than was borrowed
 * from that party (its opening Loan Received included); and a receipt's
 * Returned / Outstanding follow those returns, oldest receipt first.
 */
class KnittingLoanRcvTest extends TestCase
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
        $pcs = GsUnit::query()->create(['company_id' => $c, 'code' => 'PCS', 'name' => 'Pieces', 'active' => true])->id;
        $this->needle = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $ndl, 'gs_unit_id' => $pcs, 'item_code' => 'ND-VO-71', 'name' => 'Vo 71.52 needle', 'active' => true])->id;
        $this->oil = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $ndl, 'gs_unit_id' => $pcs, 'item_code' => 'OIL-22', 'name' => 'Knitting oil', 'active' => true])->id;
        $this->store = GsStore::query()->create(['company_id' => $c, 'code' => 'MAINT', 'name' => 'Maintenance Store', 'active' => true])->id;
    }

    public function test_borrowed_receipt_and_return_issue(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $party = MaterialSupplier::query()->create(['code' => 'SUP-90001', 'name' => 'Square Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', ['ob_date' => $today, 'party_id' => $party, 'item_id' => $this->needle, 'loan_received' => 10])->assertCreated();

        // The borrowed receipt: payment method and source required; L/C only on an import; posts on Verify.
        $r = ['mrr_date' => $today, 'party_id' => $party, 'store_id' => $this->store, 'pay_method' => 'ADJUSTMENT', 'source' => 'Party loan',
            'challan_no' => 'SQ/LN-41', 'lines' => [['item_id' => $this->needle, 'uom' => 'pcs', 'receive_qty' => 50, 'rate' => 9]]];
        $this->as()->postJson('/api/v1/knitting/needle/loan-receipts/needle', ['lines' => []] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A challan with no needles on it moves nothing. Add a line before saving.');
        $this->as()->postJson('/api/v1/knitting/needle/loan-receipts/needle', ['pay_method' => null, 'source' => null] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['pay_method', 'source']);
        $this->as()->postJson('/api/v1/knitting/needle/loan-receipts/needle', ['lc_no' => 'LC-1'] + $r)->assertUnprocessable()->assertJsonValidationErrors('lc_no');
        $one = $this->as()->postJson('/api/v1/knitting/needle/loan-receipts/needle', $r)->assertCreated()->json('data.mrr_no');
        $two = $this->as()->postJson('/api/v1/knitting/needle/loan-receipts/needle', ['challan_no' => 'SQ/LN-42', 'lines' => [['receive_qty' => 30] + $r['lines'][0]]] + $r)->assertCreated()->json('data.mrr_no');
        $this->assertEquals(["NLB-{$ym}0001", "NLB-{$ym}0002"], [$one, $two]);
        $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/needle/{$one}/verify")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$one} is Draft, so it cannot be verified.");
        foreach ([$one, $two] as $no) {
            $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/needle/{$no}/submit")->assertOk();
            $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/needle/{$no}/verify")->assertOk()->assertJsonPath('data.status', 'VERIFIED');
        }
        $this->assertEquals(80.0, NeedleStore::balance($this->store, $this->needle));
        $this->assertSame('LOAN_IN', KnitNeedleMove::query()->where('doc_no', $one)->value('kind'));
        $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/needle/{$one}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');

        // Sending it back: never more than was borrowed (10 opening + 80 received).
        $q = $this->as()->postJson('/api/v1/knitting/needle/loan-reqs', ['book' => 'NEEDLE', 'kind' => 'LOAN_RETURN', 'req_date' => $today, 'party_id' => $party,
            'store_id' => $this->store, 'lines' => [['item_id' => $this->needle, 'required_qty' => 100]]])->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/knitting/needle/loan-reqs/{$q['req_no']}/submit-verify")->assertOk();
        $w = fn (float $n) => ['lines' => [['id' => $q['lines'][0]['id'], 'issue_qty' => $n]]];
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$q['req_no']}", $w(95))->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Only 90 ND-VO-71 · Vo 71.52 needle has been borrowed from this party and not yet sent back.');
        $this->as()->putJson("/api/v1/knitting/needle/loan-reqs/{$q['req_no']}", $w(40))->assertOk()->assertJsonPath('data.status', 'PARTLY_ISSUED');
        $this->assertEquals(40.0, NeedleStore::balance($this->store, $this->needle));

        // Returned is set against the opening first, then the receipts oldest first.
        $rows = collect($this->as()->getJson('/api/v1/knitting/needle/loan-receipts/needle')->json('data'))->keyBy('mrr_no');
        $this->assertEquals([30.0, 20.0, 0.0, 30.0], [$rows[$one]['returned'], $rows[$one]['outstanding'], $rows[$two]['returned'], $rows[$two]['outstanding']]);
        $this->assertEquals(30.0, $this->as()->getJson("/api/v1/knitting/needle/loan-receipts/needle/{$one}")->json('data.lines.0.returned_qty'));
        $this->as()->getJson('/api/v1/knitting/needle/loan-reqs?book=NEEDLE&kind=LOAN_RETURN')->assertJsonCount(1, 'data');

        // The oil book: its own numbers and ledger.
        $oil = $this->as()->postJson('/api/v1/knitting/needle/loan-receipts/oil', ['lines' => [['item_id' => $this->oil, 'receive_qty' => 20]]] + $r)->assertCreated()->json('data.mrr_no');
        $this->assertSame("OLB-{$ym}0001", $oil);
        $this->as()->getJson("/api/v1/knitting/needle/loan-receipts/needle/{$oil}")->assertNotFound();
        $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/oil/{$oil}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/oil/{$oil}/verify")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/oil/{$oil}/accounts")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/loan-receipts/oil/{$oil}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame('OIL', KnitNeedleMove::query()->where('doc_no', $oil)->value('book'));
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
