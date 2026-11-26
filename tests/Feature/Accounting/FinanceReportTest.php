<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finance & Ledger → Reports (AnnotechERP 23/07): the reports read off the
 * posted ledger — books, bills, trial balance, statements, cost centres,
 * receipts and payments, the audit trail — the run history they leave, and
 * who may run or remove them.
 */
class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    private const V = '/api/v1/accounting/vouchers';

    private const R = '/api/v1/accounting/reports';

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $acc = [];

    private string $cc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));

        $this->as()->getJson(self::V.'/options')->assertOk();
        $this->acc = DB::table('acc_accounts')->where('company_id', $this->delta->id)->pluck('id', 'code')->all();
        $this->cc = strtolower((string) Str::ulid());
        DB::table('acc_cost_centers')->insert(['id' => $this->cc, 'company_id' => $this->delta->id, 'code' => 'CC1', 'name' => 'Knitting Floor', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        // Cash sale in August; an export invoice billed (B-1) in September and part-collected through the bank in
        // October; factory maintenance paid in cash, charged to a cost centre; and one draft never committed.
        $this->posted('CRV', '2026-08-05', [['1020401', 1000, 0], ['4010103', 0, 1000]]);
        $this->posted('JV', '2026-09-01', [['1020201', 5000, 0, 'B-1'], ['4010101', 0, 5000]]);
        $this->posted('BRV', '2026-10-01', [['1020402', 2000, 0], ['1020201', 0, 2000, 'B-1']]);
        $this->posted('CPV', '2026-10-15', [['5020104', 300, 0, null, $this->cc], ['1020401', 0, 300]]);
        $this->as()->postJson(self::V, $this->voucher('JV', '2026-11-01', [['5030104', 50, 0], ['1020401', 0, 50]]) + ['commit' => false])->assertCreated();
    }

    public function test_the_statement_reports_read_the_posted_ledger(): void
    {
        $opt = $this->as()->getJson(self::R.'/options?screen=statements')->assertOk()->json('data');
        $this->assertCount(22, $opt['reports']);
        $this->assertSame(['company', 'account', 'bill_no', 'from', 'to'], $opt['form']);
        $this->assertCount(2, $this->as()->getJson(self::R.'/options?screen=ledger-cc')->json('data.reports'));

        // Trial balance: the period's movement balances, and so do the closing sides.
        $tb = $this->report('ACC-TRIAL-BALANCE', ['from' => '2026-07-01', 'to' => '2026-11-10']);
        $this->assertEquals([8300.0, 8300.0], [$tb['totals']['debit'], $tb['totals']['credit']]);
        $this->assertEquals($tb['totals']['closing_dr'], $tb['totals']['closing_cr']);

        // Bills: B-1 billed 5,000, 2,000 collected — partial; nothing is paid in full.
        $bills = $this->report('ACC-BILL-STATUS', ['account' => $this->acc['1020201'], 'to' => '2026-11-10']);
        $this->assertCount(1, $bills['rows']);
        $this->assertEquals(['B-1', 5000.0, 2000.0, 3000.0, __('accounting.vo.rp.opt.bill_status.PARTIAL')], [$bills['rows'][0]['bill_no'], $bills['rows'][0]['billed'], $bills['rows'][0]['settled'], $bills['rows'][0]['outstanding'], $bills['rows'][0]['status']]);
        $this->assertCount(0, $this->report('ACC-BILL-STATUS', ['bill_status' => 'PAID'])['rows']);
        $this->assertEquals([3000.0], array_slice(array_column($this->report('ACC-BILL-LEDGER', ['bill_no' => 'b-1'])['rows'], 'balance'), -1));

        // Cash book from October: brought forward 1,000; the maintenance paid out, against what it was for.
        $cash = $this->report('ACC-CASH-BOOK', ['from' => '2026-10-01', 'to' => '2026-11-10']);
        $this->assertEquals([1000.0, 700.0], [$cash['rows'][0]['balance'], $cash['rows'][1]['balance']]);
        $this->assertSame('Factory Maintenance', $cash['rows'][1]['particulars']);
        $this->assertEquals(300.0, $cash['totals']['payment']);

        // Monthly ledger of cash: August in, October out.
        $monthly = $this->report('ACC-MONTHLY-LEDGER', ['account' => $this->acc['1020401'], 'from_month' => '2026-08', 'to_month' => '2026-10']);
        $this->assertEquals([['Aug/2026', 0.0, 1000.0, 1000.0], ['Oct/2026', 1000.0, 300.0, 700.0]], [[$monthly['rows'][0]['month'], $monthly['rows'][0]['opening'], $monthly['rows'][0]['debit'], $monthly['rows'][0]['closing']], [$monthly['rows'][2]['month'], $monthly['rows'][2]['opening'], $monthly['rows'][2]['credit'], $monthly['rows'][2]['closing']]]);

        // Income statement and balance sheet: 6,000 earned less 300 spent; the sheet balances on it.
        $income = collect($this->report('ACC-INCOME-STATEMENT', ['to' => '2026-11-10'])['summary'])->pluck('value')->all();
        $this->assertEquals([6000.0, 300.0, 5700.0], $income);
        $sheet = collect($this->report('ACC-BALANCE-SHEET', ['to' => '2026-11-10'])['summary'])->pluck('value')->all();
        $this->assertEquals([5700.0, 5700.0, 0.0], $sheet);

        // Subsidiary ledger at the control layer: cash & equivalents and receivables, rolled up.
        $sub = collect($this->report('ACC-SUBSIDIARY', ['layer' => 'CONTROL', 'account' => $this->acc['102']])['rows'])->keyBy('code');
        $this->assertEquals([2700.0, 3000.0], [$sub['10204']['closing'], $sub['10202']['closing']]);

        // The draft is only in the draft day book; the chart lists every head.
        $this->assertCount(2, $this->report('ACC-DAY-BOOK-DRAFT', [])['rows']);
        $this->assertCount(8, $this->report('ACC-DAY-BOOK', [])['rows']);
        $this->assertCount(count($this->acc), $this->report('ACC-CHART', [])['rows']);
        $this->assertSame(['BRV' => 1, 'CPV' => 1, 'CRV' => 1, 'JV' => 1], collect($this->report('ACC-VOUCHER-SUM', [])['rows'])->pluck('vouchers', 'voucher_type')->all());
        $this->assertCount(1, $this->report('ACC-VOUCHER-STATEMENT', ['posted' => 'NO'])['rows']);

        // A From after the To, or a report from another screen, is refused.
        $this->as()->postJson(self::R.'/run', ['screen' => 'statements', 'report' => 'ACC-DAY-BOOK', 'from' => '2026-11-01', 'to' => '2026-10-01'])->assertUnprocessable();
        $this->as()->postJson(self::R.'/run', ['screen' => 'misc', 'report' => 'ACC-TRIAL-BALANCE'])->assertUnprocessable();
    }

    public function test_cost_centre_receipt_and_audit_reports(): void
    {
        $byLedger = $this->report('ACC-LEDGER-CC', ['account' => $this->acc['5020104']], 'ledger-cc');
        $this->assertSame(['account', 'cost_center'], array_slice(array_column($byLedger['columns'], 'key'), 0, 2));
        $this->assertEquals(['CC1 — Knitting Floor', 300.0], [$byLedger['rows'][0]['cost_center'], $byLedger['rows'][0]['net']]);
        $byCentre = $this->report('ACC-LEDGER-CC', ['cost_center' => $this->cc], 'cc-ledger');
        $this->assertSame('cost_center', $byCentre['columns'][0]['key']);
        $this->assertCount(1, $this->report('ACC-CC-MOVEMENT', ['cost_center' => $this->cc], 'cc-ledger')['rows']);

        $money = $this->report('ACC-RECEIVE-PAYMENT', ['from' => '2026-07-01', 'to' => '2026-11-10'], 'receive-payment');
        $this->assertEquals([3000.0, 300.0, 2700.0], collect($money['summary'])->pluck('value')->all());

        $audit = $this->report('ACC-VOUCHER-AUDIT', ['from' => '2026-11-10', 'to' => '2026-11-10'], 'misc');
        $this->assertGreaterThan(0, count($audit['rows']));
    }

    public function test_the_run_history_and_who_may_run_or_remove(): void
    {
        $this->report('ACC-TRIAL-BALANCE', ['from' => '2026-07-01', 'to' => '2026-11-10'], 'statements', true);
        $this->report('ACC-BILL-STATUS', ['account' => $this->acc['1020201'], 'bill_status' => 'PAID', 'from' => '2026-07-01', 'to' => '2026-11-10']);
        $this->report('ACC-CHART', []);
        $this->report('ACC-RECEIVE-PAYMENT', ['from' => '2026-07-01', 'to' => '2026-11-10'], 'receive-payment');

        $runs = $this->as()->getJson(self::R.'/runs?screen=statements')->assertOk()->json('data');
        $this->assertCount(3, $runs);
        $bill = collect($runs)->firstWhere('report_code', 'ACC-BILL-STATUS');
        $this->assertSame(__('accounting.vo.rp.p.account').': 1020201 — Accounts Receivable - Export · '.__('accounting.vo.rp.p.bill_status').': '.__('accounting.vo.rp.opt.bill_status.PAID'), $bill['filters']);
        $this->assertEquals([0, '2026-07-01', '2026-11-10', $this->owner->name], [$bill['rows'], $bill['from'], $bill['to'], $bill['by']]);
        $this->assertTrue(collect($runs)->firstWhere('report_code', 'ACC-TRIAL-BALANCE')['excel']);
        // A period after every run leaves only the run that covered no period (the chart).
        $this->assertSame(['ACC-CHART'], array_column($this->as()->getJson(self::R.'/runs?screen=statements&from=2027-01-01')->json('data'), 'report_code'));
        $this->assertCount(1, $this->as()->getJson(self::R.'/runs?screen=receive-payment')->json('data'));

        $auditor = $this->withToken($this->token(TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test')));
        $auditor->postJson(self::R.'/run', ['screen' => 'statements', 'report' => 'ACC-CHART'])->assertOk();
        $auditor->deleteJson(self::R."/runs/{$bill['id']}")->assertForbidden();
        $this->withToken($this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test')))->getJson(self::R.'/options?screen=statements')->assertForbidden();
        $this->as()->deleteJson(self::R."/runs/{$bill['id']}")->assertOk();
        $this->assertCount(3, $this->as()->getJson(self::R.'/runs?screen=statements')->json('data'));
    }

    /** @param list<array{0: string, 1: float|int, 2: float|int, 3?: ?string, 4?: ?string}> $lines */
    private function voucher(string $type, string $date, array $lines): array
    {
        return ['voucher_date' => $date, 'voucher_type' => $type, 'narration' => "{$type} {$date}", 'action_status' => 'CREATE_VOUCHER',
            'lines' => array_map(fn ($l) => ['account_id' => $this->acc[$l[0]], 'debit' => $l[1], 'credit' => $l[2], 'bill_no' => $l[3] ?? null, 'cost_center_id' => $l[4] ?? null], $lines)];
    }

    private function posted(string $type, string $date, array $lines): void
    {
        $v = $this->voucher($type, $date, $lines) + ['commit' => true];
        $id = $this->as()->postJson(self::V, $v)->assertCreated()->json('data.id');
        $this->as()->putJson(self::V."/{$id}", ['action_status' => 'APPROVED'] + $v)->assertOk();
        $this->as()->postJson(self::V."/{$id}/post")->assertOk();
    }

    private function report(string $report, array $q, string $screen = 'statements', bool $excel = false): array
    {
        return $this->as()->postJson(self::R.'/run', ['screen' => $screen, 'report' => $report, 'excel' => $excel] + $q)->assertOk()->json('data');
    }

    private function token(User $user): string
    {
        return app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken($this->token($this->owner));
    }
}
