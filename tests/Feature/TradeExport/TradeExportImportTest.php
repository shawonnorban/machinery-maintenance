<?php

declare(strict_types=1);

namespace Tests\Feature\TradeExport;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
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
 * Trade & Export → Import (AnnotechERP 22/03), and the two declarations of
 * Operations (22/02) that read it: the supplier's PI built from its POs, the
 * B2B L/C inside the export L/C's % BB Limit, the customs entry of goods
 * received, the import bill acceptance, the UD over B2B L/Cs and the transfer
 * of entitlement — and who may.
 */
class TradeExportImportTest extends TestCase
{
    use RefreshDatabase;

    private const P = '/api/v1/tradeexport/ops';

    private Company $delta;

    private User $owner;

    private string $buyer;

    private string $supplier;

    private string $other;

    private string $branch;

    private string $country;

    private string $applicant;

    private string $order;

    private string $poLine;

    private string $po;

    private array $stamp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $c = $this->delta->id;
        $this->stamp = $s = ['company_id' => $c, 'created_at' => now(), 'updated_at' => now()];
        $this->country = $this->id();
        DB::table('origin_countries')->insert($s + ['id' => $this->country, 'code' => 'DE', 'name' => 'Germany', 'active' => true]);
        $this->buyer = $this->id();
        DB::table('buyers')->insert($s + ['id' => $this->buyer, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2020-01-01', 'country_id' => $this->country, 'active' => true]);
        $sup = function (string $code, string $name) use ($s) {
            $id = $this->id();
            DB::table('mp_suppliers')->insert($s + ['id' => $id, 'code' => $code, 'name' => $name, 'supplier_type' => 'FOREIGN', 'active' => true]);

            return $id;
        };
        $this->supplier = $sup('SUP-1', 'YKK');
        $this->other = $sup('SUP-2', 'Coats');
        DB::table('currencies')->insert($s + ['id' => $this->id(), 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 119.5, 'active' => true]);
        $bank = $this->id();
        DB::table('tx_banks')->insert($s + ['id' => $bank, 'code' => 'SBL', 'name' => 'Sonali Bank', 'is_local' => true, 'active' => true]);
        $this->branch = $this->id();
        DB::table('tx_bank_branches')->insert($s + ['id' => $this->branch, 'bank_id' => $bank, 'code' => 'AGR', 'name' => 'Agrabad', 'active' => true]);
        $this->applicant = $this->id();
        DB::table('tx_lc_applicants')->insert($s + ['id' => $this->applicant, 'buyer_id' => $this->buyer, 'name' => 'LPP Importer', 'address' => 'Gdansk', 'active' => true]);
        $this->order = $this->id();
        DB::table('merch_orders')->insert($s + ['id' => $this->order, 'order_no' => 'LPP-1', 'job_no' => 'LPP-1', 'style_no' => 'ST-1', 'buyer_id' => $this->buyer, 'order_type' => 'BULK_NEW', 'order_date' => '2026-10-01', 'total_qty' => 1000, 'unit' => 'PCS', 'unit_price' => 2, 'currency' => 'USD', 'status' => 'CONFIRMED']);

        // An accessories PO of 1000 PCS at 0.5, 600 of them received on a final MRR.
        $wh = Warehouse::query()->create(['company_id' => $c, 'factory_id' => $unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $store = Store::query()->create(['company_id' => $c, 'warehouse_id' => $wh->id, 'name' => 'Accessories Store', 'code' => 'ST-ACC', 'active' => true])->id;
        $item = $this->id();
        DB::table('mp_items')->insert($s + ['id' => $item, 'kind' => 'TRIMS', 'item_code' => 'ZP-1', 'name' => 'Zipper', 'category' => 'Zipper', 'uom' => 'PCS', 'active' => true]);
        $this->po = $this->id();
        DB::table('merch_acc_pos')->insert($s + ['id' => $this->po, 'po_no' => 'PO-ACC-1', 'po_date' => '2026-10-05', 'supplier_id' => $this->supplier, 'currency' => 'USD', 'inco_term' => 'FOB', 'payment_term' => 'LC-30', 'beneficiary_company_id' => $c, 'billed_to_company_id' => $c, 'source' => 'MANUAL', 'payment_method' => 'LC', 'delivery_date' => '2026-11-01', 'mode_of_shipment' => 'Sea']);
        $this->poLine = $this->id();
        DB::table('merch_acc_po_lines')->insert($s + ['id' => $this->poLine, 'po_id' => $this->po, 'item_id' => $item, 'uom' => 'PCS', 'qty' => 1000, 'unit_price' => 0.5, 'sort_order' => 0]);
        $mrr = $this->id();
        DB::table('merch_acc_mrrs')->insert($s + ['id' => $mrr, 'mrr_no' => 'MRR-1', 'mrr_type' => 'PO', 'unit_company_id' => $c, 'supplier_id' => $this->supplier, 'mrr_date' => '2026-11-02', 'payment_method' => 'LC', 'source_type' => 'IMPORT', 'challan_no' => 'CH-77', 'challan_date' => '2026-11-02', 'store_id' => $store, 'currency' => 'USD', 'po_id' => $this->po, 'status' => 'FINALIZED']);
        DB::table('merch_acc_mrr_lines')->insert($s + ['id' => $this->id(), 'mrr_id' => $mrr, 'item_id' => $item, 'po_line_id' => $this->poLine, 'uom' => 'PCS', 'qty' => 600, 'rate' => 0.5, 'amount' => 300, 'sort_order' => 0]);
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }

    public function test_a_pi_comes_from_the_suppliers_pos_and_a_line_stops_at_what_the_po_has_left(): void
    {
        $this->assertSame(['PO-ACC-1'], array_column($this->as()->getJson(self::P.'/imp-pis/pos?supplier='.$this->supplier)->assertOk()->json('data'), 'po_no'));
        $this->assertSame([], $this->as()->getJson(self::P.'/imp-pis/pos?supplier='.$this->other)->json('data'));
        $line = $this->as()->getJson(self::P."/imp-pis/po-lines/ACC/{$this->po}")->assertOk()->json('data.0');
        $this->assertEquals(['Zipper', 1000, 1000, 'PO-ACC-1'], [$line['item'], $line['po_qty'], $line['balance'], $line['po_no']]);

        $this->as()->postJson(self::P.'/imp-pis', $this->pi(1001))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ipi_qty', ['po' => 'PO-ACC-1', 'item' => 'Zipper', 'left' => '1,000.00'])]);
        $this->as()->postJson(self::P.'/imp-pis', $this->pi(600, ['lines' => []]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ipi_lines')]);
        $p = $this->as()->postJson(self::P.'/imp-pis', $this->pi(600))->assertCreated()->assertJsonPath('data.tracking_no', 'IPI-2026-000001')->json('data');
        $this->assertEquals(300, $p['net_amount']);
        $this->assertEquals(400, $this->as()->getJson(self::P."/imp-pis/po-lines/ACC/{$this->po}")->json('data.0.balance'));
        $this->assertEquals(1000, $this->as()->getJson(self::P."/imp-pis/po-lines/ACC/{$this->po}?except={$p['id']}")->json('data.0.balance'));
        $this->as()->postJson(self::P.'/imp-pis', $this->pi(401, ['pi_no' => 'P-2']))->assertUnprocessable();

        // Approval needs the lines to make the amount.
        $this->as()->putJson(self::P."/imp-pis/{$p['id']}", $this->pi(600, ['pi_amount' => 999]))->assertOk();
        $this->as()->postJson(self::P."/imp-pis/{$p['id']}/approve")->assertUnprocessable()->assertJsonFragment([__('tradeop.v.pi_total', ['total' => '300.00', 'amount' => '999.00'])]);
        $this->as()->putJson(self::P."/imp-pis/{$p['id']}", $this->pi(600))->assertOk();
        $this->as()->postJson(self::P."/imp-pis/{$p['id']}/approve")->assertOk();
        $this->as()->putJson(self::P."/imp-pis/{$p['id']}", $this->pi(600))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'IP-1'])]);
        $row = $this->as()->getJson(self::P.'/imp-pis')->json('data.0');
        $this->assertEquals(['APPROVED', 'PO-ACC-1', 'YKK', '2026-11-08'], [$row['status'], $row['po_no'], $row['supplier'], $row['approved_on']]);
    }

    public function test_the_b2b_chain_from_lc_to_ud_and_transfer(): void
    {
        $exp = $this->exportLc(['bb_limit_pct' => 50]); // BB limit: 1,000 on an L/C of 2,000.
        $pi = $this->approvedPi();

        // The L/C: approved PIs of its supplier, inside the BB limit.
        $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['status' => 'SUBMITTED']))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ilc_pi')]);
        $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['supplier_id' => $this->other, 'pis' => [$pi]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ilc_pi_supplier', ['no' => 'IP-1'])]);
        $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['lc_value' => 1001, 'pis' => [$pi]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ilc_bb', ['lc' => 'LC-1', 'pct' => 50, 'left' => '1,000.00'])]);
        $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['expire_date' => '2026-10-01', 'pis' => [$pi]]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_expiry')]);
        $this->as()->postJson(self::P.'/imp-lcs', $this->lc(null, ['pis' => [$pi]]))->assertUnprocessable();
        $lc = $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['pis' => [$pi], 'status' => 'SUBMITTED', 'lc_bank_code' => '2701', 'lc_year' => '26', 'lc_code' => '02', 'lc_sl_no' => '0091', 'commodities' => [['name' => 'Zipper', 'qty' => 600, 'unit' => 'PCS']]]))->assertCreated()->assertJsonPath('data.lc_no', '270126020091')->json('data.id');
        $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['ref_no' => 'R-2', 'pis' => [$pi], 'lc_value' => 10]))->assertUnprocessable()->assertJsonFragment([__('tradeop.v.lc_pi_taken', ['no' => 'IP-1', 'lc' => 'R-1'])]);
        $row = $this->as()->getJson(self::P.'/imp-lcs')->json('data.0');
        $this->assertEquals(['270126020091', 'LC-1', 300, 'IP-1', 'YKK'], [$row['lc_no'], $row['export_lc'], $row['lc_value'], $row['pi_no'], $row['supplier']]);
        $b2b = $this->as()->getJson(self::P."/lcs/{$exp}/b2b")->assertOk()->json('data');
        $this->assertEquals([1000, 300, 700, '270126020091'], [$b2b['limit'], $b2b['used'], $b2b['left'], $b2b['rows'][0]['lc_no']]);

        // The customs entry: what was received, each MRR line once.
        $src = $this->as()->getJson(self::P."/entries/source/{$lc}")->assertOk()->json('data.lines.0');
        $this->assertEquals(['MRR-1', 'CH-77', 'IP-1', 'PO-ACC-1', 600, 600, 300], [$src['mrr_no'], $src['challan_no'], $src['pi_no'], $src['po_no'], $src['receive_qty'], $src['balance_qty'], $src['receive_amount']]);
        $be = ['lc_id' => $lc, 'be_no' => 'BE-1', 'be_date' => '2026-11-08', 'bill_amount' => 300, 'currency' => 'USD', 'exchange_rate' => 119, 'custom_code' => 'CHI-CTG', 'custom_name' => 'Karim', 'lines' => [['pi_line_id' => $src['pi_line_id'], 'mrr_no' => 'MRR-1', 'receive_qty' => 601]]];
        $this->as()->postJson(self::P.'/entries', $be)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.be_qty', ['mrr' => 'MRR-1', 'left' => '600.00'])]);
        $this->as()->postJson(self::P.'/entries', ['lines' => []] + $be)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.be_lines')]);
        $be['lines'][0]['receive_qty'] = 500;
        $eid = $this->as()->postJson(self::P.'/entries', $be)->assertCreated()->assertJsonPath('data.equivalent_amount', 35700)->json('data.id');
        $this->assertEquals(100, $this->as()->getJson(self::P."/entries/source/{$lc}")->json('data.lines.0.balance_qty'));
        $this->assertEquals(600, $this->as()->getJson(self::P."/entries/source/{$lc}?except={$eid}")->json('data.lines.0.balance_qty'));
        $file = $this->as()->post(self::P."/entries/{$eid}/attachment", ['upload' => UploadedFile::fake()->create('be.pdf', 40, 'application/pdf')], ['Accept' => 'application/json'])->assertOk()->json('data.file_id');
        $this->as()->get("/api/v1/files/{$file}/download")->assertRedirect();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson("/api/v1/files/{$file}/download")->assertForbidden();
        $this->as()->postJson(self::P."/entries/{$eid}/final")->assertOk();
        $this->as()->putJson(self::P."/entries/{$eid}", $be)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.locked', ['no' => 'BE-1'])]);
        $this->assertEquals(['BE-1', 'Karim', '270126020091'], array_values(array_intersect_key($this->as()->getJson(self::P.'/entries')->json('data.0'), ['be_no' => 1, 'custom_name' => 1, 'lc_no' => 1])));

        // The acceptance: numbered on save, inside the L/C and its 5%.
        $ac = ['lc_id' => $lc, 'invoiced_amount' => 316, 'currency' => 'USD', 'acceptance_date' => '2026-11-08', 'ifdbc_no' => 'IF-1', 'ifdbc_amount' => 300, 'open_date' => '2026-11-08', 'maturity_date' => '2026-12-08', 'payment_type' => 'NA'];
        $this->as()->postJson(self::P.'/acceptances', $ac)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ac_lc', ['lc' => 'R-1', 'left' => '315.00'])]);
        $this->as()->postJson(self::P.'/acceptances', ['invoiced_amount' => 300, 'maturity_date' => '2026-11-01'] + $ac)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ac_maturity')]);
        $this->as()->postJson(self::P.'/acceptances', ['invoiced_amount' => 300, 'final' => true] + $ac)->assertCreated()->assertJsonPath('data.boe_no', 'IBE-2026-000001');
        $s = $this->as()->getJson(self::P."/acceptances/source/{$lc}")->assertOk()->json('data');
        $this->assertEquals([15, 300, 0, 'IF-1', 'IP-1'], [$s['tolerance_amount'], $s['total_bill'], $s['lc_due'], $s['bills'][0]['ifdbc_no'], $s['pis'][0]['pi_no']]);
        $this->assertEquals(300, $this->as()->getJson(self::P.'/imp-lcs')->json('data.0.ifdbc_amount'));

        // The UD over the B2B L/C, each B2B on one UD; the export L/C now reads it.
        $this->as()->postJson(self::P.'/uds', ['ud_no' => 'UD-1', 'ud_date' => '2026-11-08', 'authority' => 'BGMEA', 'lcs' => []])->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ud_lcs')]);
        $this->assertSame('270126020091', $this->as()->getJson(self::P.'/uds/lcs')->json('data.0.b2b'));
        $ud = $this->as()->postJson(self::P.'/uds', ['ud_no' => 'UD-1', 'ud_date' => '2026-11-08', 'authority' => 'BGMEA', 'lcs' => [$lc]])->assertCreated()->assertJsonPath('data.lc_rows.0.export_lc', 'LC-1')->json('data.id');
        $this->as()->postJson(self::P.'/uds', ['ud_no' => 'UD-X', 'ud_date' => '2026-11-08', 'authority' => 'BGMEA', 'lcs' => [$lc]])->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ud_taken', ['lc' => 'R-1', 'ud' => 'UD-1'])]);
        $this->assertCount(0, $this->as()->getJson(self::P.'/uds/lcs')->json('data'));
        $this->assertSame('UD-1', collect($this->as()->getJson(self::P.'/lcs')->json('data'))->firstWhere('lc_no', 'LC-1')['ud_no']);
        $this->assertEquals(['LC-1', 'BGMEA'], array_values(array_intersect_key($this->as()->getJson(self::P.'/uds')->json('data.0'), ['export_lc' => 1, 'authority' => 1])));

        // The transfer: received on the UD, less what was exported and lost.
        $to = $this->as()->postJson(self::P.'/uds', ['ud_no' => 'UD-2', 'ud_date' => '2026-11-08', 'authority' => 'BKMEA', 'lcs' => [$this->secondLc($exp)]])->assertCreated()->json('data.id');
        $this->assertEquals(500, $this->as()->getJson(self::P."/ud-transfers/source?material=Accessories&from={$ud}")->assertOk()->json('data.receive_qty'));
        $this->assertEquals(0, $this->as()->getJson(self::P."/ud-transfers/source?material=Yarn&from={$ud}")->json('data.receive_qty'));
        $t = ['material' => 'Accessories', 'from_ud_id' => $ud, 'to_ud_id' => $to, 'export_qty' => 300, 'loss_pct' => 10, 'transfer_qty' => 171, 'status' => 'DRAFT'];
        $this->as()->postJson(self::P.'/ud-transfers', ['to_ud_id' => $ud] + $t)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ud_same')]);
        $this->as()->postJson(self::P.'/ud-transfers', $t)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ud_balance', ['left' => '170.00'])]);
        $tid = $this->as()->postJson(self::P.'/ud-transfers', ['transfer_qty' => 100, 'status' => 'APPROVED'] + $t)->assertCreated()->assertJsonPath('data.loss_qty', 30)->assertJsonPath('data.balance_qty', 170)->json('data.id');
        $this->as()->putJson(self::P."/ud-transfers/{$tid}", $t)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ud_approved')]);
        $this->assertEquals(100, $this->as()->getJson(self::P."/ud-transfers/source?material=Accessories&from={$ud}")->json('data.transferred'));
        $this->as()->postJson(self::P.'/ud-transfers', ['transfer_qty' => 71] + $t)->assertUnprocessable()->assertJsonFragment([__('tradeop.v.ud_balance', ['left' => '70.00'])]);
        $row = $this->as()->getJson(self::P.'/ud-transfers')->json('data.0');
        $this->assertEquals(['UD-1', 'UD-2', 'Accessories', 100], [$row['from_ud'], $row['to_ud'], $row['material'], $row['transfer_qty']]);
        $this->as()->deleteJson(self::P."/uds/{$ud}")->assertUnprocessable();

        // The reports read the B2B chain (22/06).
        $run = fn (string $kind, array $q = []) => collect($this->as()->postJson(self::P.'/reports/run', $q + ['kind' => $kind, 'from' => '2026-01-01', 'to' => '2026-12-31'])->assertOk()->json('data.rows'));
        $row = $run('statement-of-import-lc')->firstWhere('lc_no', '270126020091');
        $this->assertEquals(['LC-1', 300, 300, 300, 0], [$row['export_lc'], $row['lc_value'], $row['received'], $row['accepted'], $row['paid']]);
        $row = $run('bblc-liability-status')->firstWhere('lc_no', '270126020091');
        $this->assertEquals([300, 0], [$row['liability'], $row['unaccepted']]);
        $row = $run('ifdbc-payment-schedule')->first();
        $this->assertEquals(['IBE-2026-000001', 'IF-1', '2026-12-08', 300, 'YKK'], [$row['boe_no'], $row['ifdbc_no'], $row['maturity_date'], $row['due'], $row['supplier']]);
        $this->assertEquals(300, $run('ifdbc-outstanding')->first()['outstanding']);
        $this->assertCount(0, $run('ifdbc-payment-list'));
        $row = $run('customs-bond')->first();
        $this->assertEquals(['BE-1', 'UD-1', 500, 'MRR-1'], [$row['be_no'], $row['ud_no'], $row['qty'], $row['mrr_no']]);
        $row = $run('b2b-order-summary')->firstWhere('lc_no', '270126020091');
        $this->assertEquals(['IP-1', 'PO-ACC-1', 'LC-1'], [$row['pi_no'], $row['po_no'], $row['export_lc']]);
        $uds = $run('ud-summary')->keyBy('ud_no');
        $this->assertEquals([100, 0, 100], [$uds['UD-1']['transfer_out'], $uds['UD-1']['transfer_in'], $uds['UD-2']['transfer_in']]);
        $this->assertEquals(1000, $run('bblc-summary')->firstWhere('lc_no', 'LC-1')['limit']);
        $this->assertEquals(300, $run('fund-forecast')->firstWhere('month', 'Dec 2026')['outflow']);
    }

    public function test_who_may(): void
    {
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson(self::P.'/imp-lcs')->assertOk();
        $this->withToken($this->token('AUDITOR', 'audit2@delta.test'))->postJson(self::P.'/imp-pis', $this->pi(1))->assertForbidden();
        $this->withToken($this->token('FACTORY_MANAGER', 'fm@delta.test'))->postJson(self::P.'/imp-pis', $this->pi(1))->assertCreated();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->getJson(self::P.'/uds')->assertForbidden();
    }

    private function secondLc(string $exp): string
    {
        $line = ['po_kind' => 'ACC', 'po_line_id' => $this->poLine, 'po_no' => 'PO-ACC-1', 'item' => 'Zipper', 'qty' => 100, 'uom' => 'PCS', 'unit_price' => 0.5];
        $p = $this->as()->postJson(self::P.'/imp-pis', ['supplier_id' => $this->supplier, 'pi_no' => 'IP-2', 'pi_date' => '2026-11-03', 'pi_amount' => 50, 'lines' => [$line]])->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/imp-pis/{$p}/approve")->assertOk();

        return $this->as()->postJson(self::P.'/imp-lcs', $this->lc($exp, ['ref_no' => 'R-2', 'pis' => [$p], 'lc_value' => 50, 'status' => 'SUBMITTED']))->assertCreated()->json('data.id');
    }

    private function pi(float $qty, array $over = []): array
    {
        return $over + ['supplier_id' => $this->supplier, 'pi_no' => 'IP-1', 'pi_date' => '2026-11-03', 'pi_amount' => $qty * 0.5, 'lines' => [['po_kind' => 'ACC', 'po_line_id' => $this->poLine, 'po_no' => 'PO-ACC-1', 'item' => 'Zipper', 'qty' => $qty, 'uom' => 'PCS', 'unit_price' => 0.5]]];
    }

    private function approvedPi(): string
    {
        $id = $this->as()->postJson(self::P.'/imp-pis', $this->pi(600))->assertCreated()->json('data.id');
        $this->as()->postJson(self::P."/imp-pis/{$id}/approve")->assertOk();

        return $id;
    }

    private function exportLc(array $over): string
    {
        $pi = $this->as()->postJson(self::P.'/pis', ['pi_no' => 'EP-1', 'buyer_id' => $this->buyer, 'buyer_attention' => 'Ms N', 'pi_date' => '2026-11-01', 'pi_amount' => 2000, 'currency' => 'USD', 'discount_type' => 'AMOUNT', 'status' => 'SUBMITTED', 'lines' => [['item_group' => 'Garments', 'description' => 'Tee', 'qty' => 1000, 'uom' => 'PCS', 'unit_price' => 2]]])->assertCreated()->json('data.id');

        return $this->as()->postJson(self::P.'/lcs', $over + ['kind' => 'LC', 'lc_no' => 'LC-1', 'buyer_id' => $this->buyer, 'issue_date' => '2026-11-01', 'expiry_date' => '2027-03-01', 'place_of_expiry_id' => $this->country, 'currency' => 'USD', 'sender_branch_id' => $this->branch, 'receiving_branch_id' => $this->branch, 'advising_branch_id' => $this->branch, 'inco_term' => 'FOB', 'port_of_loading' => 'SIN', 'port_of_discharge' => 'Gdansk', 'last_shipment_date' => '2027-02-01', 'notify_party' => 'LPP', 'applicant_id' => $this->applicant, 'payment_term' => 'LC-30', 'lc_value' => 2000, 'status' => 'SUBMITTED', 'pis' => [$pi], 'orders' => [['order_id' => $this->order, 'qty' => 1000, 'unit_price' => 2]]])->assertCreated()->json('data.id');
    }

    private function lc(?string $exp, array $over = []): array
    {
        return $over + ['ref_no' => 'R-1', 'lc_type' => 'BTB', 'commodity_type' => 'ACCESSORIES', 'supplier_id' => $this->supplier, 'buyer_id' => $this->buyer, 'export_lc_id' => $exp, 'sender_branch_id' => $this->branch, 'issue_date' => '2026-11-04', 'expire_date' => '2027-02-01', 'currency' => 'USD', 'inco_term' => 'FOB', 'port_of_loading' => 'CTG', 'port_of_discharge' => 'CTG', 'last_shipment_date' => '2027-01-01', 'lc_value' => 300, 'payment_term' => 'LC-30', 'tenor_days' => 30, 'lc_status' => 'APPROVED', 'status' => 'DRAFT'];
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
