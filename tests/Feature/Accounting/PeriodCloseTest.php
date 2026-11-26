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
 * Finance & Ledger → Period Close (AnnotechERP 23/04): stock value closing
 * and its worksheet, bank reconciliation and its balances, fiscal year
 * closing — and what a closed year or a cleared bank line refuses.
 */
class PeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private const S = '/api/v1/accounting/period/stock';

    private const B = '/api/v1/accounting/period/bank';

    private const F = '/api/v1/accounting/period/fiscal-years';

    private const V = '/api/v1/accounting/vouchers';

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));
    }

    public function test_stock_value_closing_one_figure_per_code_and_month(): void
    {
        $opt = $this->as()->getJson(self::S.'/options')->assertOk()->json('data');
        $this->assertContains('2026', array_column($opt['years'], 'value'));

        // The worksheet lists the stock heads (the Inventories group, none being classed) and writes only what changed.
        $heads = $this->as()->getJson(self::S.'/worksheet?year=2026&month=10')->assertOk()->json('data');
        $this->assertEquals(['1020101', 'Raw Materials - Yarn', null], [$heads[0]['code'], $heads[0]['particular'], $heads[0]['closing']]);
        $this->assertGreaterThanOrEqual(5, count($heads));
        $rows = [['code' => '1020101', 'particular' => 'Raw Materials - Yarn', 'closing' => 250000], ['code' => '1020103', 'particular' => 'Accessories Stock', 'closing' => 80000], ['code' => '1020104', 'particular' => 'Work In Process', 'closing' => null]];
        $this->as()->putJson(self::S.'/worksheet', ['year' => 2026, 'month' => 10, 'rows' => $rows])->assertOk()->assertJsonPath('data.changed', 2);
        $this->as()->putJson(self::S.'/worksheet', ['year' => 2026, 'month' => 10, 'rows' => $rows])->assertOk()->assertJsonPath('data.changed', 0);
        $rows[1]['closing'] = 82500;
        $this->as()->putJson(self::S.'/worksheet', ['year' => 2026, 'month' => 10, 'rows' => $rows])->assertOk()->assertJsonPath('data.changed', 1);
        $this->assertEquals(82500, collect($this->as()->getJson(self::S.'/worksheet?year=2026&month=10')->json('data'))->firstWhere('code', '1020103')['closing']);

        // New stock closing: the same trio replaces the figure rather than adding a second.
        $this->as()->postJson(self::S, ['year' => 2026, 'month' => 10, 'code' => 'X-1', 'particular' => 'Spare yarn'])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.sc_closing')]);
        $this->as()->postJson(self::S, ['year' => 2026, 'month' => 13, 'code' => 'X-1', 'particular' => 'Spare yarn', 'closing' => 5])->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->as()->postJson(self::S, ['year' => 2026, 'month' => 10, 'code' => 'X-1', 'particular' => 'Spare yarn', 'closing' => 500])->assertCreated();
        $this->as()->postJson(self::S, ['year' => 2026, 'month' => 10, 'code' => 'X-1', 'particular' => 'Spare yarn', 'closing' => 700])->assertCreated();
        $list = collect($this->as()->getJson(self::S)->assertOk()->json('data'));
        $this->assertEquals([3, 700, 'Oct 2026'], [$list->count(), $list->firstWhere('code', 'X-1')['closing'], $list->first()['period']]);
        $this->assertContains('X-1', array_column($this->as()->getJson(self::S.'/worksheet?year=2026&month=10')->json('data'), 'code'));
        $x = $list->firstWhere('code', 'X-1')['id'];
        $this->as()->putJson(self::S."/{$x}", ['particular' => 'Spare yarn (dyed)', 'closing' => 650])->assertOk();
        $this->as()->deleteJson(self::S."/{$x}")->assertOk();
        $this->assertCount(2, $this->as()->getJson(self::S)->json('data'));
        $this->withToken($this->token(TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test')))->postJson(self::S, ['year' => 2026, 'month' => 10, 'code' => 'X-2', 'particular' => 'A', 'closing' => 1])->assertForbidden();
    }

    public function test_bank_reconciliation_and_fiscal_year_closing(): void
    {
        $c = $this->delta->id;
        $opt = $this->as()->getJson(self::V.'/options')->json('data');
        $acc = collect($opt['accounts']);
        $bankLedger = $acc->first(fn ($a) => str_starts_with($a['label'], '1020402'))['value'];
        $cash = $acc->first(fn ($a) => str_starts_with($a['label'], '1020401'))['value'];
        $revenue = DB::table('acc_accounts')->where('company_id', $c)->where('type', 'REVENUE')->where('postable', true)->value('id');
        $expense = DB::table('acc_accounts')->where('company_id', $c)->where('type', 'EXPENSE')->where('postable', true)->value('id');
        $bank = strtolower((string) Str::ulid());
        $txBank = strtolower((string) Str::ulid());
        DB::table('tx_banks')->insert(['id' => $txBank, 'company_id' => $c, 'code' => 'SONALI', 'name' => 'Sonali Bank', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('acc_bank_accounts')->insert(['id' => $bank, 'company_id' => $c, 'bank_id' => $txBank, 'account_no' => '0111-7654321', 'account_name' => 'Delta Operating', 'account_type' => 'CURRENT', 'currency' => 'BDT', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $voucher = function (string $date, array $lines) {
            $id = $this->as()->postJson(self::V, ['commit' => true, 'voucher_date' => $date, 'voucher_type' => 'JV', 'action_status' => 'APPROVED', 'lines' => $lines])->assertCreated()->json('data.id');
            $this->as()->postJson(self::V."/{$id}/post")->assertOk();

            return $id;
        };
        $sale = $voucher('2026-11-05', [['account_id' => $bankLedger, 'debit' => 1000, 'ref_no' => 'CHQ-1'], ['account_id' => $revenue, 'credit' => 1000]]);
        $voucher('2026-11-06', [['account_id' => $expense, 'debit' => 300], ['account_id' => $bankLedger, 'credit' => 300]]);

        // A posted line on a bank-class account is brought onto an account's reconciliation by hand.
        $cand = $this->as()->getJson(self::B.'/candidates')->assertOk()->json('data');
        $this->assertCount(2, $cand);
        $saleLine = DB::table('acc_voucher_lines')->where('voucher_id', $sale)->where('account_id', $bankLedger)->value('id');
        $this->as()->postJson(self::B, ['bank_account_id' => $bank])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.br_line_required')]);
        $this->as()->postJson(self::B, ['voucher_line_id' => $saleLine, 'bank_account_id' => $bank, 'cleared' => true])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.br_date', ['no' => 'JV-2026-000001'])]);
        $this->as()->postJson(self::B, ['voucher_line_id' => $saleLine, 'bank_account_id' => $bank, 'bank_date' => '2026-11-01'])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.br_before', ['no' => 'JV-2026-000001'])]);
        $this->as()->postJson(self::B, ['voucher_line_id' => $saleLine, 'bank_account_id' => $bank])->assertCreated();
        $this->as()->postJson(self::B, ['voucher_line_id' => $saleLine, 'bank_account_id' => $bank])->assertUnprocessable();
        $this->assertCount(1, $this->as()->getJson(self::B.'/candidates')->json('data'));

        // Linking the ledger brings its other lines on by themselves; the balances follow what is cleared.
        DB::table('acc_bank_accounts')->where('id', $bank)->update(['ledger_id' => $bankLedger]);
        $board = collect($this->as()->getJson(self::B.'/board')->assertOk()->json('data'))->firstWhere('id', $bank);
        $this->assertEquals([2, 700, 0, 700, 'CHQ-1'], [$board['count'], $board['book'], $board['bank'], $board['not_in_bank'], $board['lines'][0]['cheque_no']]);
        $rows = collect($board['lines'])->map(fn ($l) => ['voucher_line_id' => $l['voucher_line_id'], 'cheque_no' => $l['cheque_no'], 'bank_date' => '2026-11-08', 'cleared' => $l['dr'] > 0, 'remarks' => null])->all();
        $this->as()->putJson(self::B."/accounts/{$bank}", ['rows' => $rows])->assertOk();
        $board = collect($this->as()->getJson(self::B.'/board')->json('data'))->firstWhere('id', $bank);
        $this->assertEquals([700, 1000, -300], [$board['book'], $board['bank'], $board['not_in_bank']]);
        $this->assertCount(2, $this->as()->getJson(self::B)->json('data'));

        // A voucher the bank has cleared cannot be unposted; a cleared line stays on.
        $this->as()->postJson(self::V."/{$sale}/unpost")->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.bank_cleared', ['no' => 'JV-2026-000001'])]);
        $this->as()->deleteJson(self::B."/{$saleLine}")->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.br_cleared_keep')]);

        // Fiscal year closing: not while a voucher waits; then counted, its result kept, and the year shut.
        $fy = DB::table('acc_fiscal_years')->where('company_id', $c)->where('name', 'FY2026-27')->value('id');
        $waiting = $this->as()->postJson(self::V, ['commit' => true, 'voucher_date' => '2026-11-07', 'voucher_type' => 'JV', 'action_status' => 'CREATE_VOUCHER', 'lines' => [['account_id' => $cash, 'debit' => 5], ['account_id' => $revenue, 'credit' => 5]]])->json('data.id');
        $this->as()->postJson(self::F, ['fiscal_year_id' => $fy])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.fy_waiting', ['fy' => 'FY2026-27', 'n' => 1])]);
        $this->as()->deleteJson(self::V."/{$waiting}")->assertOk();
        $this->assertContains($fy, array_column($this->as()->getJson(self::F.'/options')->json('data.periods'), 'value'));
        $this->as()->postJson(self::F, ['fiscal_year_id' => $fy, 'remarks' => 'Year end'])->assertCreated()->assertJsonPath('data.vouchers', 2)->assertJsonPath('data.balance', 700);
        $row = $this->as()->getJson(self::F)->json('data.0');
        $this->assertEquals([2, 700, 'Year end'], [$row['vouchers'], $row['balance'], $row['remarks']]);
        $this->assertNotContains($fy, array_column($this->as()->getJson(self::F.'/options')->json('data.periods'), 'value'));
        $this->as()->postJson(self::F, ['fiscal_year_id' => $fy])->assertUnprocessable();
        $this->as()->postJson(self::V, ['commit' => true, 'voucher_date' => '2026-12-01', 'voucher_type' => 'JV', 'action_status' => 'CREATE_VOUCHER', 'lines' => [['account_id' => $cash, 'debit' => 5], ['account_id' => $revenue, 'credit' => 5]]])
            ->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.fy_closed', ['fy' => 'FY2026-27'])]);
        $this->as()->putJson(self::S.'/worksheet', ['year' => 2026, 'month' => 12, 'rows' => [['code' => '1020101', 'particular' => 'Yarn', 'closing' => 1]]])->assertUnprocessable();

        // Removing the closing reopens the year.
        $this->as()->deleteJson(self::F."/{$row['id']}")->assertOk();
        $this->assertFalse((bool) DB::table('acc_fiscal_years')->where('id', $fy)->value('closed'));
        $this->withToken($this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test')))->getJson(self::F)->assertForbidden();
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
