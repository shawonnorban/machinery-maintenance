<?php

declare(strict_types=1);

namespace Tests\Feature\TradeExport;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trade & Export → Operations (AnnotechERP 22/02): the export paper chain —
 * the PI (lines must make its amount past Draft), the L/C (PIs and orders of
 * its buyer, the value matching the orders or the difference ignored), the
 * commercial invoice (prefix + auto number, inside the order and L/C with
 * tolerance), the bill lodgement of final invoices, the bill of exchange (or
 * an advance, its distribution balanced), early and final realisation — and
 * who may.
 */
class TradeExportOperationsTest extends TestCase
{
    use RefreshDatabase;

    private const P = '/api/v1/tradeexport/ops';

    private Company $delta;

    private User $owner;

    private string $buyer;

    private string $other;

    private string $branch;

    private string $country;

    private string $applicant;

    private string $order;

    private string $otherOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $c = $this->delta->id;
        $stamp = ['company_id' => $c, 'created_at' => now(), 'updated_at' => now()];
        $id = fn () => (string) Str::ulid();
        $this->country = $id();
        DB::table('origin_countries')->insert($stamp + ['id' => $this->country, 'code' => 'DE', 'name' => 'Germany', 'active' => true]);
        $buyer = function (string $code, string $name) use ($stamp, $id) {
            $b = $id();
            DB::table('buyers')->insert($stamp + ['id' => $b, 'code' => $code, 'name' => $name, 'short_name' => $code, 'pi_address' => 'Gdansk', 'registered_from' => '2020-01-01', 'country_id' => $this->country, 'active' => true]);

            return $b;
        };
        $this->buyer = $buyer('LPP', 'LPP SA');
        $this->other = $buyer('ROLY', 'Roly');
        DB::table('currencies')->insert($stamp + ['id' => $id(), 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 119.5, 'active' => true]);
        $bank = $id();
        DB::table('tx_banks')->insert($stamp + ['id' => $bank, 'code' => 'SBL', 'name' => 'Sonali Bank', 'is_local' => true, 'active' => true]);
        $this->branch = $id();
        DB::table('tx_bank_branches')->insert($stamp + ['id' => $this->branch, 'bank_id' => $bank, 'code' => 'AGR', 'name' => 'Agrabad', 'active' => true]);
        $this->applicant = $id();
        DB::table('tx_lc_applicants')->insert($stamp + ['id' => $this->applicant, 'buyer_id' => $this->buyer, 'name' => 'LPP Importer', 'address' => 'Gdansk', 'active' => true]);
        $order = function (string $no, string $buyerId) use ($stamp, $id) {
            $o = $id();
            DB::table('merch_orders')->insert($stamp + ['id' => $o, 'order_no' => $no, 'job_no' => $no, 'style_no' => 'ST-'.$no, 'buyer_id' => $buyerId, 'order_type' => 'BULK_NEW', 'order_date' => '2026-10-01', 'total_qty' => 1000, 'unit' => 'PCS', 'unit_price' => 2, 'currency' => 'USD', 'status' => 'CONFIRMED']);
            DB::table('merch_order_sizes')->insert([$stamp + ['id' => $id(), 'order_id' => $o, 'colour' => 'Navy', 'size' => 'M', 'qty' => 600, 'sort_order' => 0], $stamp + ['id' => $id(), 'order_id' => $o, 'colour' => 'Navy', 'size' => 'L', 'qty' => 400, 'sort_order' => 1]]);

            return $o;
        };
        $this->order = $order('LPP-1', $this->buyer);
        $this->otherOrder = $order('ROLY-1', $this->other);
    }

    public function test_a_pi_adds_up_and_moves_from_draft_to_submit(): void
    {
        $opt = $this->as()->getJson(self::P.'/options')->assertOk()->json('data');
        $this->assertEquals(['Sonali Bank — Agrabad'], array_column($opt['branches'], 'label'));
        $this->assertContains('LC at 30 days (LC-30)', array_column($opt['refs']['PAYMENT_TERM'], 'label'));

        $this->as()->postJson(self::P.'/pis', $this->pi(['status' => 'SUBMITTED', 'pi_amount' => 999]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.pi_total', ['total' => '2,000.00', 'amount' => '999.00'])]);
        $this->as()->postJson(self::P.'/pis', $this->pi(['discount_type' => 'AMOUNT', 'discount_value' => 2001]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.pi_discount')]);
        $p = $this->as()->postJson(self::P.'/pis', $this->pi(['advance_pct' => 10, 'discount_type' => 'PERCENT', 'discount_value' => 5]))->assertCreated()->json('data');
        $this->assertEquals([2000, 200, 100, 1900, 'DRAFT'], [$p['total_amount'], $p['advance_amount'], $p['discount_amount'], $p['net_amount'], $p['status']]);
        $this->as()->postJson(self::P.'/pis', $this->pi())->assertUnprocessable();

        $row = $this->as()->getJson(self::P.'/pis')->json('data.0');
        $this->assertEquals(['PI-1', false, 1900, 'Germany'], [$row['pi_no'], $row['final'], $row['net_amount'], $row['country']]);
        $this->as()->putJson(self::P."/pis/{$p['id']}", $this->pi(['status' => 'COMPLETE']))->assertOk();
        $this->as()->putJson(self::P."/pis/{$p['id']}", $this->pi())->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'PI-1'])]);
        $this->as()->deleteJson(self::P."/pis/{$p['id']}")->assertUnprocessable();
    }

    public function test_an_lc_links_its_buyers_pis_and_orders_and_matches_their_value(): void
    {
        $pi = $this->submittedPi();
        $draftPi = $this->as()->postJson(self::P.'/pis', $this->pi(['pi_no' => 'PI-DRAFT']))->assertCreated()->json('data.id');

        $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => []]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_pi')]);
        $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => [$draftPi]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_pi_status', ['no' => 'PI-DRAFT'])]);
        $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => [$pi], 'expiry_date' => '2026-10-01']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_expiry')]);
        $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => [$pi], 'last_shipment_date' => '2027-06-01']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_ship')]);
        $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => [$pi], 'orders' => [['order_id' => $this->otherOrder, 'qty' => 1000, 'unit_price' => 2]]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_order_buyer', ['no' => 'ROLY-1'])]);

        // A draft may differ from its orders; past Draft it may not, unless the difference is ignored.
        $id = $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => [$pi], 'lc_value' => 1500]))->assertCreated()->assertJsonPath('data.po_value', 2000)->json('data.id');
        $this->as()->putJson(self::P."/lcs/{$id}", $this->lc(['pis' => [$pi], 'lc_value' => 1500, 'status' => 'SUBMITTED']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_diff', ['lc' => '1,500.00', 'po' => '2,000.00'])]);
        $this->as()->putJson(self::P."/lcs/{$id}", $this->lc(['pis' => [$pi], 'lc_value' => 1500, 'status' => 'SUBMITTED', 'ignore_difference' => true]))->assertOk();
        $this->as()->postJson(self::P.'/lcs', $this->lc(['lc_no' => 'LC-2', 'pis' => [$pi]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_pi_taken', ['no' => 'PI-1', 'lc' => 'LC-1'])]);

        $row = $this->as()->getJson(self::P.'/lcs')->json('data.0');
        $this->assertEquals(['LC-1', 'SUBMITTED', 1500, 'LC at 30 days', 0], [$row['lc_no'], $row['status'], $row['lc_value'], $row['payment_term_label'], $row['amendment_no']]);
        $body = $this->as()->getJson(self::P."/lcs/{$id}")->assertOk()->json('data');
        $this->assertEquals(['PI-1', 'LPP-1'], [$body['pi_rows'][0]['pi_no'], $body['orders'][0]['order_no']]);
    }

    public function test_the_paper_chain_from_invoice_to_realisation(): void
    {
        $lc = $this->submittedLc();
        $src = $this->as()->getJson(self::P."/invoices/source/{$lc}")->assertOk()->json('data');
        $this->assertEquals([1000, 0, 2], [$src['orders'][0]['po_qty'], $src['orders'][0]['done_qty'], $src['orders'][0]['unit_price']]);
        $this->assertEquals('Sonali Bank — Agrabad', $src['lc']['receiving_bank']);

        // Inside the order and the L/C, tolerance (3%) included; the prefix numbers the invoice.
        $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 1031))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.inv_qty', ['no' => 'LPP-1', 'max' => '1,030.00'])]);
        $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 600, ['size_breakdown' => [['colour' => 'Navy', 'size' => 'M', 'qty' => 500]]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.inv_breakdown', ['no' => 'LPP-1', 'sum' => '500.00', 'qty' => '600.00'])]);
        $inv = $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 600, ['size_breakdown' => [['colour' => 'Navy', 'size' => 'M', 'qty' => 600]]], ['penalty_amount' => 50, 'agency_commission' => 20, 'sales_discount' => 30]))->assertCreated()->assertJsonPath('data.invoice_no', 'BCL-0001')->json('data');
        $this->assertEquals([1200, 1100], [$inv['invoiced_amount'], $inv['net_amount']]);
        $second = $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 300))->assertCreated()->assertJsonPath('data.invoice_no', 'BCL-0002')->json('data.id');
        $this->assertEquals(900, $this->as()->getJson(self::P."/invoices/source/{$lc}")->json('data.orders.0.done_qty'));
        $this->assertEquals(1800, $this->as()->getJson(self::P."/invoices/source/{$lc}")->json('data.total_bill'));

        // An L/C with invoices keeps its buyer and its invoiced value, and can still be completed.
        $pis = $this->as()->getJson(self::P."/lcs/{$lc}")->json('data.pis');
        $this->as()->putJson(self::P."/lcs/{$lc}", $this->lc(['pis' => $pis, 'buyer_id' => $this->other, 'orders' => [], 'status' => 'DRAFT']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_in_use', ['no' => 'LC-1'])]);
        $this->as()->putJson(self::P."/lcs/{$lc}", $this->lc(['pis' => $pis, 'lc_value' => 1000, 'status' => 'DRAFT']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_below', ['no' => 'LC-1', 'amount' => '1,800.00'])]);
        $this->as()->putJson(self::P."/lcs/{$lc}", $this->lc(['pis' => $pis, 'status' => 'COMPLETE']))->assertOk()->assertJsonPath('data.locked', true);
        $this->as()->putJson(self::P."/lcs/{$lc}", $this->lc(['pis' => $pis, 'status' => 'COMPLETE']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'LC-1'])]);

        // Only final invoices go on a bill, and each on one bill.
        $bill = ['bill_no' => 'EB-1', 'bill_date' => '2026-11-08', 'charge' => 500, 'invoices' => [$inv['id']]];
        $this->as()->postJson(self::P.'/lodgements', $bill)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.bill_not_final', ['no' => 'BCL-0001'])]);
        $this->as()->postJson(self::P."/invoices/{$inv['id']}/final")->assertOk();
        $this->as()->postJson(self::P."/invoices/{$second}/final")->assertOk();
        $this->as()->putJson(self::P."/invoices/{$inv['id']}", $this->invoice($lc, 10))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'BCL-0001'])]);
        $this->assertCount(2, $this->as()->getJson(self::P.'/lodgements/invoices')->json('data'));
        $eb = $this->as()->postJson(self::P.'/lodgements', $bill)->assertCreated()->json('data.id');
        $this->as()->postJson(self::P.'/lodgements', ['bill_no' => 'EB-2'] + $bill)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.bill_taken', ['no' => 'BCL-0001', 'bill' => 'EB-1'])]);
        $this->assertCount(1, $this->as()->getJson(self::P.'/lodgements/invoices')->json('data'));
        $file = $this->as()->post(self::P."/lodgements/{$eb}/attachment", ['upload' => UploadedFile::fake()->create('eb.pdf', 80, 'application/pdf')], ['Accept' => 'application/json'])->assertOk()->json('data.file_id');
        $this->as()->get("/api/v1/files/{$file}/download")->assertRedirect();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson("/api/v1/files/{$file}/download")->assertForbidden();
        $row = $this->as()->getJson(self::P.'/lodgements')->json('data.0');
        $this->assertEquals(['EB-1', 'BCL-0001 [—]', 500, 'eb.pdf'], [$row['bill_no'], $row['invoices'], $row['charge'], $row['file_name']]);

        // The bill of exchange: the L/C's final invoices, within what is left on it.
        $src = $this->as()->getJson(self::P."/boes/source/{$lc}")->assertOk()->json('data');
        $this->assertEquals([2000, 2], [$src['balance'], count($src['invoices'])]);
        $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => []]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.bill_invoices')]);
        $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => [$inv['id']], 'bill_amount' => 2001]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.boe_balance', ['lc' => 'LC-1', 'max' => '2,000.00'])]);
        $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => [$inv['id']], 'due_date' => '2026-11-01']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.boe_due')]);
        $boe = $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => [$inv['id']]]))->assertCreated()->json('data.id');
        $this->assertEquals(1, count($this->as()->getJson(self::P."/boes/source/{$lc}?except=")->json('data.invoices')));

        // Realisation waits for a final bill; a bill is finally realised once.
        $real = ['type' => 'EARLY', 'realized_no' => 'RZ-1', 'boe_id' => $boe, 'realized_date' => '2026-11-08', 'bank_bill_type' => 'FDBP', 'bank_name' => 'Sonali Bank', 'export_currency' => 'USD', 'local_currency' => 'BDT', 'rate_ods' => 119, 'invoice_amount' => 1100];
        $this->as()->postJson(self::P.'/realisations', $real)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.real_boe', ['no' => 'BE-1'])]);
        $this->as()->postJson(self::P."/boes/{$boe}/final")->assertOk();
        $this->assertSame('BBR-1 — BE-1', $this->as()->getJson(self::P.'/realisations/bills')->json('data.0.label'));
        $acc = $this->accounts();
        $this->as()->postJson(self::P.'/realisations', $real + ['distribution' => [['account_id' => $acc, 'dr_cr' => 'DR', 'currency' => 'BDT', 'amount' => 100], ['account_id' => $acc, 'dr_cr' => 'CR', 'currency' => 'BDT', 'amount' => 90]]])->assertUnprocessable()->assertJsonFragment([__('tradeop.v.unbalanced', ['dr' => '100.00', 'cr' => '90.00'])]);
        $early = $this->as()->postJson(self::P.'/realisations', $real + ['distribution' => [['account_id' => $acc, 'dr_cr' => 'DR', 'currency' => 'BDT', 'amount' => 100], ['account_id' => $acc, 'dr_cr' => 'CR', 'currency' => 'BDT', 'amount' => 100]]])->assertCreated()->json('data');
        $this->assertEquals([130900, 50], [$early['invoice_amount_bdt'], $early['penalty_amount']]);
        $this->as()->postJson(self::P.'/realisations', ['realized_no' => 'RZ-2'] + $real)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.real_twice', ['no' => 'BE-1', 'real' => 'RZ-1'])]);
        $row = $this->as()->getJson(self::P.'/boes')->json('data.0');
        $this->assertEquals([1100, 0, '2026-11-08'], [$row['negotiated'], $row['remaining'], $row['received_date']]);

        $final = ['type' => 'FINAL', 'realized_no' => 'RZ-F', 'rate_ttc' => 120, 'collection_amount' => 1050, 'fbc_amount' => 15] + $real;
        $f = $this->as()->postJson(self::P.'/realisations', $final)->assertCreated()->json('data.id');
        $this->assertFalse($this->invoiceRow('BCL-0001')['bill_collected']);
        $this->as()->postJson(self::P."/realisations/{$f}/final")->assertOk();
        $this->assertTrue($this->invoiceRow('BCL-0001')['bill_collected']);
        $this->assertFalse($this->invoiceRow('BCL-0002')['bill_collected']);
        $this->assertSame('BE-1', $this->invoiceRow('BCL-0001')['boe_no']);
        $this->as()->postJson(self::P.'/realisations', ['realized_no' => 'RZ-3'] + $final)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.real_twice', ['no' => 'BE-1', 'real' => 'RZ-F'])]);
        $this->assertCount(0, $this->as()->getJson(self::P.'/realisations/bills')->json('data'));
        $row = $this->as()->getJson(self::P.'/boes')->json('data.0');
        $this->assertEquals([1050, 50], [$row['negotiated'], $row['remaining']]);
    }

    public function test_an_advance_against_export_needs_its_link_and_a_balanced_distribution(): void
    {
        $pi = $this->submittedPi();
        $acc = $this->accounts();
        $body = $this->boe(null, ['bill_no' => 'ADV-1', 'is_advance' => true, 'bank_bill_type' => 'IP', 'link_type' => 'PI']);
        $this->as()->postJson(self::P.'/boes', $body)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.boe_link')]);
        $this->as()->postJson(self::P.'/boes', ['bank_bill_type' => 'FDBP', 'pi_id' => $pi] + $body)->assertUnprocessable();
        $this->as()->postJson(self::P.'/boes', ['pi_id' => $pi, 'distribution' => [['account_id' => $acc, 'dr_cr' => 'DR', 'currency' => 'USD', 'amount' => 10]]] + $body)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.unbalanced', ['dr' => '10.00', 'cr' => '0.00'])]);
        $this->as()->postJson(self::P.'/boes', ['pi_id' => $pi, 'distribution' => [['account_id' => $acc, 'dr_cr' => 'DR', 'currency' => 'USD', 'amount' => 10], ['account_id' => $acc, 'dr_cr' => 'CR', 'currency' => 'USD', 'amount' => 10]]] + $body)->assertCreated()->assertJsonPath('data.is_advance', true)->assertJsonPath('data.lc_id', null)->assertJsonCount(2, 'data.distribution');
    }

    public function test_cm_payment_receipts_bill_dates_and_margin_closing(): void
    {
        $lc = $this->submittedLc();
        $inv = $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 500))->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/invoices/{$inv}/final")->assertOk();
        $boe = $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => [$inv], 'bill_amount' => 1000]))->assertCreated()->json('data.id');

        // CM Payment Receive: only on a final bill, never past its amount, every receipt on the bill.
        $cm = ['boe_id' => $boe, 'bank_bill_type' => 'FDBP', 'amount' => 600, 'receive_date' => '2026-11-20', 'exchange_rate' => 119, 'dfc_margin' => 12];
        $this->as()->postJson(self::P.'/cm-receipts', $cm)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.real_boe', ['no' => 'BE-1'])]);
        $this->as()->postJson(self::P."/boes/{$boe}/final")->assertOk();
        $this->assertSame('BE-1 · BBR-1', $this->as()->getJson(self::P.'/cm-receipts/bills')->json('data.0.label'));
        $first = $this->as()->postJson(self::P.'/cm-receipts', $cm)->assertCreated()->assertJsonPath('data.receipt_no', 'CMR-2026-000001')->json('data.id');
        $this->as()->postJson(self::P.'/cm-receipts', ['amount' => 401] + $cm)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.cm_amount', ['no' => 'BE-1', 'left' => '400.00'])]);
        $this->as()->postJson(self::P.'/cm-receipts', ['amount' => 400, 'receive_date' => '2026-11-25'] + $cm)->assertCreated();
        $src = $this->as()->getJson(self::P."/cm-receipts/source/{$boe}")->assertOk()->json('data');
        $this->assertEquals([1000, 24, 2, 'BCL-0001', 'LC-1'], [$src['received'], $src['dfc'], count($src['receipts']), $src['invoices'], $src['lc_no']]);
        $row = collect($this->as()->getJson(self::P.'/cm-receipts')->json('data'))->firstWhere('receipt_no', 'CMR-2026-000001');
        $this->assertEquals(['BE-1', 'FDBP', 1000, 'LC-1', '2026-11-20'], [$row['bill_no'], $row['bank_bill_type'], $row['bill_amount'], $row['lc_no'], $row['receive_date']]);
        $this->assertSame('2026-11-25', $this->as()->getJson(self::P.'/boes')->json('data.0.received_date'));
        $this->as()->postJson(self::P."/cm-receipts/{$first}/final")->assertOk();
        $this->as()->putJson(self::P."/cm-receipts/{$first}", $cm)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'CMR-2026-000001'])]);
        $this->as()->deleteJson(self::P."/cm-receipts/{$first}")->assertUnprocessable();

        // Bill Adjustment: the bill's submit and payment dates at the bank, set by hand.
        $this->assertSame('BE-1', $this->as()->getJson(self::P."/boes/dates/{$lc}")->assertOk()->json('data.0.bill_no'));
        $this->as()->putJson(self::P.'/boes/dates', ['rows' => [['id' => $boe, 'bank_submit_date' => '2026-11-01']]])->assertUnprocessable()->assertJsonFragment([__('tradeop.v.bd_submit', ['no' => 'BE-1'])]);
        $this->as()->putJson(self::P.'/boes/dates', ['rows' => [['id' => $boe, 'bank_submit_date' => '2026-11-10', 'payment_recv_date' => '2026-11-09']]])->assertUnprocessable()->assertJsonFragment([__('tradeop.v.bd_paid', ['no' => 'BE-1'])]);
        $this->as()->putJson(self::P.'/boes/dates', ['rows' => [['id' => $boe, 'bank_submit_date' => '2026-11-10', 'payment_recv_date' => '2026-11-30']]])->assertOk();
        $row = $this->as()->getJson(self::P.'/boes')->json('data.0');
        $this->assertEquals(['2026-11-10', '2026-11-30'], [$row['submit_date'], $row['received_date']]);

        // Margin Closing: an advance (IP) bill carrying its accounting type, its distribution balanced.
        $acc = $this->accounts();
        $mc = $this->boe(null, ['bill_no' => 'MC-1', 'is_advance' => true, 'bank_bill_type' => 'IP', 'margin_type' => 'COMMISSION', 'bill_amount' => 50]);
        $this->as()->postJson(self::P.'/boes', ['is_advance' => false, 'bank_bill_type' => 'FDBP', 'lc_id' => $lc, 'invoices' => [$inv]] + $mc)->assertUnprocessable();
        $this->as()->postJson(self::P.'/boes', ['distribution' => [['account_id' => $acc, 'dr_cr' => 'DR', 'currency' => 'USD', 'amount' => 50]]] + $mc)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.unbalanced', ['dr' => '50.00', 'cr' => '0.00'])]);
        $this->as()->postJson(self::P.'/boes', $mc)->assertCreated()->assertJsonPath('data.margin_type', 'COMMISSION');
        $m = $this->as()->getJson(self::P.'/margin-closings')->assertOk()->json('data');
        $this->assertEquals([1, 'MC-1', 'Sonali Bank', 'COMMISSION', 50], [count($m), $m[0]['bill_no'], $m[0]['bank'], $m[0]['margin_type'], $m[0]['amount']]);
    }

    public function test_cash_incentive_claims_and_the_lcs_prc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));
        $lc = $this->submittedLc();
        $inv = $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 500))->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/invoices/{$inv}/final")->assertOk();
        $boe = $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => [$inv], 'bill_amount' => 1000]))->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/boes/{$boe}/final")->assertOk();
        $real = $this->as()->postJson(self::P.'/realisations', ['type' => 'FINAL', 'realized_no' => 'RZ-1', 'boe_id' => $boe, 'realized_date' => '2026-11-08', 'bank_bill_type' => 'FDBP', 'bank_name' => 'Sonali Bank', 'export_currency' => 'USD', 'local_currency' => 'BDT', 'rate_ods' => 118, 'rate_ttc' => 119, 'invoice_amount' => 800, 'collection_amount' => 800])->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/realisations/{$real}/final")->assertOk();

        // The L/C searched by number brings its invoices, realisation and the days left to claim.
        $this->assertNull($this->as()->getJson(self::P.'/cash-incentives/credit?lc=ZZZ')->assertOk()->json('data'));
        $c = $this->as()->getJson(self::P.'/cash-incentives/credit?lc=lc-1')->assertOk()->json('data');
        $this->assertEquals(['LC-1', 'Gdansk', 'Sonali Bank', 1000, 800, 80, 118, '2026-11-08', __('tradeop.ci_days_left', ['n' => 178]), []], [$c['lc_no'], $c['destination'], $c['bank'], $c['invoice_total'], $c['realized'], $c['realized_pct'], $c['min_rate'], $c['latest_realized'], $c['countdown'], $c['claims']]);

        // A claim: its part once per type, its serial once, dates in order, finalized only once collected.
        $claim = ['lc_id' => $lc, 'cia_type' => 'REGULAR', 'claim_part_no' => 1, 'claim_sl_no' => 'CIA-1', 'application_date' => '2026-11-10', 'claim_amount' => 40, 'claim_currency' => 'USD'];
        $this->as()->postJson(self::P.'/cash-incentives', ['claim_part_no' => null] + $claim)->assertUnprocessable();
        $this->as()->postJson(self::P.'/cash-incentives', ['doc_sub_date' => '2026-11-09'] + $claim)->assertUnprocessable();
        $this->as()->postJson(self::P.'/cash-incentives', ['final' => true] + $claim)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ci_collect')]);
        $c = $this->as()->postJson(self::P.'/cash-incentives', $claim)->assertCreated()->json('data');
        $this->assertEquals([1, 'CIA-1', 4, 40], [count($c['claims']), $c['claims'][0]['claim_sl_no'], $c['claims'][0]['cia_rate'], $c['claim_total']]);
        $this->as()->postJson(self::P.'/cash-incentives', ['claim_sl_no' => 'CIA-2'] + $claim)->assertUnprocessable();
        $this->as()->postJson(self::P.'/cash-incentives', ['claim_part_no' => 2] + $claim)->assertUnprocessable();
        $this->as()->postJson(self::P.'/cash-incentives', ['cia_type' => 'SPECIAL', 'claim_sl_no' => 'CIA-2'] + $claim)->assertCreated();
        $id = $c['claims'][0]['id'];
        $c = $this->as()->putJson(self::P."/cash-incentives/{$id}", ['certificate_no' => 'AUD-9', 'audit_cert_date' => '2026-11-20', 'audit_amount' => 4500, 'collection_date' => '2026-11-25', 'final' => true] + $claim)->assertOk()->json('data');
        $this->assertEquals([4500, 'AUD-9', true], [$c['audit_total'], $c['claims'][0]['certificate_no'], $c['claims'][0]['final']]);
        $this->as()->putJson(self::P."/cash-incentives/{$id}", $claim)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'CIA-1'])]);
        $row = collect($this->as()->getJson(self::P.'/cash-incentives')->assertOk()->json('data'))->firstWhere('claim_sl_no', 'CIA-1');
        $this->assertEquals(['LC-1', 'REGULAR', '2026-11-25', 4500], [$row['lc_no'], $row['cia_type'], $row['collection_date'], $row['audit_amount']]);

        // The PRC lives on the L/C: its number with a date, the day it was entered, its document.
        $this->as()->post(self::P."/lcs/{$lc}/prc/attachment", ['upload' => UploadedFile::fake()->create('prc.pdf', 40, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->as()->putJson(self::P."/lcs/{$lc}/prc", ['prc_no' => 'PRC-1'])->assertUnprocessable();
        $this->as()->putJson(self::P."/lcs/{$lc}/prc", ['prc_no' => 'PRC-1', 'prc_date' => '2026-11-09'])->assertOk()->assertJsonPath('data.prc_entry_date', '2026-11-10');
        $file = $this->as()->post(self::P."/lcs/{$lc}/prc/attachment", ['upload' => UploadedFile::fake()->create('prc.pdf', 40, 'application/pdf')], ['Accept' => 'application/json'])->assertOk()->json('data.prc_file_id');
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson("/api/v1/files/{$file}/download")->assertForbidden();
        $c = $this->as()->getJson(self::P.'/cash-incentives/credit?lc=LC-1')->json('data');
        $this->assertEquals(['PRC-1', '2026-11-09', 'prc.pdf'], [$c['prc_no'], $c['prc_date'], $c['prc_file_name']]);
        $this->assertSame('PRC-1', $this->as()->getJson(self::P."/lcs/{$lc}")->json('data.prc_no'));
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->postJson(self::P.'/cash-incentives', ['claim_sl_no' => 'CIA-9'] + $claim)->assertForbidden();
    }

    public function test_the_trade_and_export_reports(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));
        $lc = $this->submittedLc();
        $inv = $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 500))->assertCreated()->json('data.id');
        $this->as()->postJson(self::P.'/invoices', $this->invoice($lc, 100, [], ['ex_factory_date' => '2026-11-01']))->assertCreated();
        $this->as()->postJson(self::P."/invoices/{$inv}/final")->assertOk();
        $boe = $this->as()->postJson(self::P.'/boes', $this->boe($lc, ['invoices' => [$inv], 'bill_amount' => 1000]))->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/boes/{$boe}/final")->assertOk();
        $real = ['boe_id' => $boe, 'realized_date' => '2026-11-09', 'bank_bill_type' => 'FDBP', 'bank_name' => 'Sonali Bank', 'export_currency' => 'USD', 'local_currency' => 'BDT', 'rate_ods' => 118, 'invoice_amount' => 1000];
        $early = $this->as()->postJson(self::P.'/realisations', ['type' => 'EARLY', 'realized_no' => 'RZ-E'] + $real)->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/realisations/{$early}/final")->assertOk();
        $final = $this->as()->postJson(self::P.'/realisations', ['type' => 'FINAL', 'realized_no' => 'RZ-F', 'rate_ttc' => 119, 'collection_amount' => 980] + $real)->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/realisations/{$final}/final")->assertOk();

        $opt = $this->as()->getJson(self::P.'/reports/options')->assertOk()->json('data');
        $this->assertEquals([31, 'export-invoice', 'LC-1'], [count($opt['reports']), $opt['reports'][0]['kind'], $opt['lcs'][0]['label']]);
        $run = fn (string $kind, array $q = []) => $this->as()->postJson(self::P.'/reports/run', $q + ['kind' => $kind, 'from' => '2026-01-01', 'to' => '2026-12-31']);

        // Every report runs, the import side with nothing to show.
        foreach ($opt['reports'] as $r) {
            $run($r['kind'])->assertOk();
        }

        // Export Invoice: its boxes narrow the list; a box the report does not read is ignored.
        $rows = $run('export-invoice')->json('data.rows');
        $this->assertEquals([2, 'BCL-0001', 1000, 'BE-1'], [count($rows), $rows[0]['invoice_no'], $rows[0]['net_amount'], $rows[0]['boe_no']]);
        $this->assertCount(1, $run('export-invoice', ['xf_from' => '2026-11-03'])->json('data.rows'));
        $this->assertCount(1, $run('export-invoice', ['bill_type' => 'FDBP'])->json('data.rows'));
        $this->assertCount(0, $run('export-invoice', ['bill_type' => 'FDBC'])->json('data.rows'));
        $this->assertCount(0, $run('export-invoice', ['on_board' => 'WITH'])->json('data.rows'));
        $this->assertCount(2, $run('export-invoice', ['negotiated' => 'NO'])->json('data.rows'));
        $this->assertCount(1, $run('export-invoice', ['invoice' => 'bcl-0002'])->json('data.rows'));
        $this->assertEquals(1200, $run('export-invoice')->json('data.totals.net_amount'));
        $this->assertCount(1, $run('export-lc-list', ['invoice' => 'nothing'])->json('data.rows'));

        // A bill realised early and then finally counts once.
        $row = $run('lc-wise-realization')->json('data.rows.0');
        $this->assertEquals([1200, 1000, 1000, 980, 200], [$row['exported'], $row['billed'], $row['realized'], $row['collected'], $row['pending']]);
        $row = $run('cost-against-realization')->json('data.rows.0');
        $this->assertEquals(['RZ-F', 1000, 980, 20, 2], [$row['realized_no'], $row['invoice_amount'], $row['collected'], $row['cost'], $row['cost_pct']]);
        $row = $run('export-payment-followup')->json('data.rows');
        $this->assertEquals([__('tradeop.rp.status.REALISED'), __('tradeop.rp.status.NOT_BILLED')], [$row[0]['status'], $row[1]['status']]);
        $row = $run('short-shipment-status')->json('data.rows.0');
        $this->assertEquals([1000, 600, 400, 40, 800], [$row['lc_qty'], $row['shipped'], $row['short_qty'], $row['short_pct'], $row['short_value']]);
        $this->assertSame('Nov 2026', $run('monthly-export-summary')->json('data.rows.0.month'));

        // Date windows: in order and within a year; the auditor reads, the storekeeper does not.
        $run('export-invoice', ['from' => '2026-12-01', 'to' => '2026-11-01'])->assertUnprocessable()->assertJsonFragment([__('tradeop.v.rp_range')]);
        $run('export-invoice', ['from' => '2025-01-01', 'to' => '2026-12-31'])->assertUnprocessable();
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->postJson(self::P.'/reports/run', ['kind' => 'bblc-summary'])->assertOk();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->getJson(self::P.'/reports/options')->assertForbidden();
    }

    public function test_who_may(): void
    {
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson(self::P.'/lcs')->assertOk();
        $this->withToken($this->token('AUDITOR', 'audit2@delta.test'))->postJson(self::P.'/pis', $this->pi())->assertForbidden();
        $this->withToken($this->token('FACTORY_MANAGER', 'fm@delta.test'))->postJson(self::P.'/pis', $this->pi())->assertCreated();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->getJson(self::P.'/options')->assertForbidden();
    }

    private function accounts(): string
    {
        $id = (string) Str::ulid();
        DB::table('acc_accounts')->insert(['id' => $id, 'company_id' => $this->delta->id, 'code' => '1001', 'name' => 'Bank', 'type' => 'ASSET', 'postable' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function pi(array $over = []): array
    {
        return $over + ['pi_no' => 'PI-1', 'buyer_id' => $this->buyer, 'buyer_attention' => 'Ms Nowak', 'pi_date' => '2026-11-01', 'pi_amount' => 2000, 'currency' => 'USD', 'discount_type' => 'AMOUNT', 'status' => 'DRAFT',
            'lines' => [['item_group' => 'Garments', 'description' => 'Knit tee', 'qty' => 1000, 'uom' => 'PCS', 'unit_price' => 2]]];
    }

    private function submittedPi(): string
    {
        return $this->as()->postJson(self::P.'/pis', $this->pi(['status' => 'SUBMITTED']))->assertCreated()->json('data.id');
    }

    private function lc(array $over = []): array
    {
        return $over + ['kind' => 'LC', 'lc_no' => 'LC-1', 'buyer_id' => $this->buyer, 'issue_date' => '2026-11-01', 'expiry_date' => '2027-03-01', 'place_of_expiry_id' => $this->country, 'currency' => 'USD', 'sender_branch_id' => $this->branch, 'receiving_branch_id' => $this->branch, 'advising_branch_id' => $this->branch,
            'inco_term' => 'FOB', 'port_of_loading' => 'SIN', 'port_of_discharge' => 'Gdansk', 'last_shipment_date' => '2027-02-01', 'notify_party' => 'LPP Logistics', 'applicant_id' => $this->applicant, 'payment_term' => 'LC-30', 'lc_value' => 2000, 'status' => 'DRAFT', 'credit_tolerance_pct' => 3,
            'orders' => [['order_id' => $this->order, 'qty' => 1000, 'unit_price' => 2]]];
    }

    private function submittedLc(): string
    {
        return $this->as()->postJson(self::P.'/lcs', $this->lc(['pis' => [$this->submittedPi()], 'status' => 'SUBMITTED']))->assertCreated()->json('data.id');
    }

    private function invoice(string $lc, float $qty, array $line = [], array $over = []): array
    {
        return $over + ['lc_id' => $lc, 'invoice_prefix' => 'BCL-', 'invoice_date' => '2026-11-05', 'currency' => 'USD', 'exp_no' => 'EXP-77', 'exp_part_no' => 3, 'exp_date' => '2026-11-05', 'ex_factory_date' => '2026-11-04', 'handover_date' => '2026-11-06', 'payment_term' => 'LC-30', 'port_of_loading' => 'SIN', 'port_of_discharge' => 'CMB', 'delivery_country_id' => $this->country, 'shipment_mode' => 'SEA', 'applicant_id' => $this->applicant,
            'lines' => [$line + ['order_id' => $this->order, 'unit' => 'PCS', 'inv_qty' => $qty, 'unit_price' => 2, 'ctn_qty' => 30]]];
    }

    private function boe(?string $lc, array $over = []): array
    {
        return $over + ['bill_no' => 'BE-1', 'lc_id' => $lc, 'buyer_id' => $this->buyer, 'applicant_id' => $this->applicant, 'bill_date' => '2026-11-08', 'bill_amount' => 1100, 'currency' => 'USD', 'local_rate' => 119, 'issuing_branch_id' => $this->branch, 'advising_branch_id' => $this->branch, 'receiving_branch_id' => $this->branch, 'bank_bill_type' => 'FDBP', 'bank_bill_ref_no' => 'BBR-1', 'due_date' => '2026-12-08'];
    }

    private function invoiceRow(string $no): array
    {
        return collect($this->as()->getJson(self::P.'/invoices')->json('data'))->firstWhere('invoice_no', $no);
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
