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
 * Finance & Ledger → Cheque Management (AnnotechERP 23/05): a cheque off a
 * book's unused leaf, its lines and what a reference has left, printing
 * once, and cancelling a leaf — and who may do it.
 */
class ChequeManagementTest extends TestCase
{
    use RefreshDatabase;

    private const C = '/api/v1/accounting/cheques';

    private Company $delta;

    private User $owner;

    private string $account;

    private string $other;

    /** @var list<string> */
    private array $leaves = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));

        $c = $this->delta->id;
        $id = fn () => strtolower((string) Str::ulid());
        $stamp = ['created_at' => now(), 'updated_at' => now()];
        $bank = $id();
        DB::table('tx_banks')->insert(['id' => $bank, 'company_id' => $c, 'code' => 'SONALI', 'name' => 'Sonali Bank'] + $stamp);
        [$this->account, $this->other] = [$id(), $id()];
        foreach ([[$this->account, '0111-7654321'], [$this->other, '0222-1111111']] as [$aid, $no]) {
            DB::table('acc_bank_accounts')->insert(['id' => $aid, 'company_id' => $c, 'bank_id' => $bank, 'account_no' => $no, 'account_name' => 'Delta', 'account_type' => 'CURRENT', 'currency' => 'BDT', 'active' => true] + $stamp);
        }
        $book = $id();
        DB::table('acc_cheque_books')->insert(['id' => $book, 'company_id' => $c, 'bank_account_id' => $this->account, 'book_no' => 'CB-01', 'required_leaf' => 3, 'prefix' => 'CHQ', 'no_from' => 1001, 'no_to' => 1003, 'requisition_date' => '2026-11-01', 'receive_date' => '2026-11-02'] + $stamp);
        foreach ([1001, 1002, 1003] as $num) {
            $lid = $id();
            $this->leaves[] = $lid;
            DB::table('acc_cheque_leaves')->insert(['id' => $lid, 'company_id' => $c, 'book_id' => $book, 'number' => $num, 'cheque_no' => "CHQ{$num}", 'status' => 'UNUSED', 'active' => true] + $stamp);
        }
    }

    public function test_a_cheque_off_an_unused_leaf_printed_once(): void
    {
        $opt = $this->as()->getJson(self::C.'/options')->assertOk()->json('data');
        [$payable, $expense] = [$opt['heads'][0]['value'], $opt['heads'][1]['value']];
        $this->assertSame('Sonali Bank', collect($opt['accounts'])->firstWhere('value', $this->account)['bank']);
        $this->assertCount(3, $this->as()->getJson(self::C."/leaves?account={$this->account}")->json('data'));
        $this->assertCount(0, $this->as()->getJson(self::C."/leaves?account={$this->other}")->json('data'));

        $q = ['bank_account_id' => $this->account, 'cheque_leaf_id' => $this->leaves[0], 'issue_date' => '2026-11-10', 'issue_type' => 'BILL_PAYMENT', 'issue_to_head_id' => $payable, 'pay_to' => 'KOYO TRIMS', 'amount' => 1000, 'acc_payee' => true,
            'lines' => [['control_head_id' => $expense, 'issue_ref' => 'MCB-1', 'req_amt' => 1500, 'amount' => 600], ['control_head_id' => $expense, 'issue_for' => 'Freight', 'amount' => 300]]];

        // A leaf of this account, unused; lines within the cheque; a reference within what it has left.
        $this->as()->postJson(self::C, ['lines' => []] + $q)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_lines')]);
        $this->as()->postJson(self::C, ['bank_account_id' => $this->other] + $q)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_leaf')]);
        $this->as()->postJson(self::C, ['amount' => 800] + $q)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_over', ['lines' => '900.00', 'amount' => '800.00'])]);
        $a = $this->as()->postJson(self::C, $q)->assertCreated()->json('data');
        $this->assertEquals(['CHQ-2026-000001', 'CHQ1001', 'MCB-1', false, 'Sonali Bank'], [$a['issue_ref'], $a['cheque_no'], $a['issue_refs'], $a['printed'], $a['bank']]);
        $this->assertSame('ISSUED', DB::table('acc_cheque_leaves')->where('id', $this->leaves[0])->value('status'));
        $this->assertCount(2, $this->as()->getJson(self::C."/leaves?account={$this->account}")->json('data'));

        // The same leaf is never issued twice; the reference has 900 left of 1500.
        $this->as()->postJson(self::C, $q)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_leaf')]);
        $this->assertEquals(600, $this->as()->getJson(self::C.'/used?ref=MCB-1')->json('data.used'));
        $second = ['cheque_leaf_id' => $this->leaves[1], 'amount' => 1000, 'lines' => [['control_head_id' => $expense, 'issue_ref' => 'MCB-1', 'req_amt' => 1500, 'amount' => 901]]] + $q;
        $this->as()->postJson(self::C, $second)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_balance', ['ref' => 'MCB-1', 'left' => '900.00'])]);
        $second['lines'][0]['amount'] = 900;
        $b = $this->as()->postJson(self::C, $second)->assertCreated()->assertJsonPath('data.issue_ref', 'CHQ-2026-000002')->json('data');
        $this->assertEquals(600, $b['lines'][0]['used_amt']);

        // Editing moves the leaf; a removed cheque frees its leaf.
        $this->as()->putJson(self::C."/{$a['id']}", ['cheque_leaf_id' => $this->leaves[2], 'pay_to' => 'KOYO TRIMS SOLUTION'] + $q)->assertOk()->assertJsonPath('data.cheque_no', 'CHQ1003');
        $this->assertSame('UNUSED', DB::table('acc_cheque_leaves')->where('id', $this->leaves[0])->value('status'));
        $this->as()->deleteJson(self::C."/{$b['id']}")->assertOk();
        $this->assertSame('UNUSED', DB::table('acc_cheque_leaves')->where('id', $this->leaves[1])->value('status'));

        // Cheque Print once; then neither changed nor removed.
        $this->as()->postJson(self::C."/{$a['id']}/print")->assertOk()->assertJsonPath('data.printed', true);
        $this->as()->postJson(self::C."/{$a['id']}/print")->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_printed_once', ['no' => 'CHQ-2026-000001'])]);
        $this->as()->putJson(self::C."/{$a['id']}", $q)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_printed', ['no' => 'CHQ-2026-000001'])]);
        $this->as()->deleteJson(self::C."/{$a['id']}")->assertUnprocessable();
        $row = $this->as()->getJson(self::C)->json('data.0');
        $this->assertEquals([true, true, 1000, 'KOYO TRIMS SOLUTION'], [$row['printed'], $row['final'], $row['amount'], $row['pay_to']]);
    }

    public function test_a_leaf_is_cancelled_and_put_back(): void
    {
        $opt = $this->as()->getJson(self::C.'/options')->json('data');
        $q = ['bank_account_id' => $this->account, 'cheque_leaf_id' => $this->leaves[0], 'issue_date' => '2026-11-10', 'issue_type' => 'OTHERS', 'issue_to_head_id' => $opt['heads'][0]['value'], 'pay_to' => 'Someone', 'amount' => 50,
            'lines' => [['control_head_id' => $opt['heads'][1]['value'], 'amount' => 50]]];
        $issue = $this->as()->postJson(self::C, $q)->assertCreated()->json('data');
        $this->as()->postJson(self::C."/{$issue['id']}/print")->assertOk();

        // Unused and issued leaves can be taken out; remarks are required.
        $cand = collect($this->as()->getJson(self::C.'/cancellations/leaves')->assertOk()->json('data'));
        $this->assertEquals([3, 'CHQ-2026-000001'], [$cand->count(), $cand->firstWhere('value', $this->leaves[0])['issue_ref']]);
        $this->as()->postJson(self::C.'/cancellations', ['remarks' => 'x'])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_leaf_required')]);
        $this->as()->postJson(self::C.'/cancellations', ['cheque_leaf_id' => $this->leaves[1]])->assertUnprocessable()->assertJsonValidationErrors('remarks');
        $this->as()->postJson(self::C.'/cancellations', ['cheque_leaf_id' => $this->leaves[1], 'remarks' => 'Torn'])->assertCreated();
        $this->as()->postJson(self::C.'/cancellations', ['cheque_leaf_id' => $this->leaves[0], 'remarks' => 'Payee refused'])->assertCreated();

        // Deactivated and kept: never issued again; the printed cheque shows cancelled.
        $this->assertEquals(['CANCELLED', false], [DB::table('acc_cheque_leaves')->where('id', $this->leaves[1])->value('status'), (bool) DB::table('acc_cheque_leaves')->where('id', $this->leaves[1])->value('active')]);
        $this->assertSame(['CHQ1003'], array_map(fn ($l) => explode(' · ', $l['label'])[0], $this->as()->getJson(self::C."/leaves?account={$this->account}")->json('data')));
        $this->as()->postJson(self::C, ['cheque_leaf_id' => $this->leaves[1]] + $q)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_leaf')]);
        $this->assertTrue($this->as()->getJson(self::C."/{$issue['id']}")->json('data.cancelled'));
        $this->as()->postJson(self::C.'/cancellations', ['cheque_leaf_id' => $this->leaves[1], 'remarks' => 'Again'])->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.cq_not_cancellable')]);
        $rows = collect($this->as()->getJson(self::C.'/cancellations')->assertOk()->json('data'));
        $this->assertEquals([2, 'CB-01', 'CHQ-2026-000001', false], [$rows->count(), $rows->first()['book_no'], $rows->firstWhere('cheque_no', 'CHQ1001')['issue_ref'], $rows->first()['active']]);

        // Removing a cancellation puts the leaf back as it was.
        $this->as()->deleteJson(self::C.'/cancellations/'.$rows->firstWhere('cheque_no', 'CHQ1001')['id'])->assertOk();
        $this->assertEquals(['ISSUED', false], [DB::table('acc_cheque_leaves')->where('id', $this->leaves[0])->value('status'), (bool) DB::table('acc_cheque_issues')->where('id', $issue['id'])->value('cancelled')]);
        $this->as()->deleteJson(self::C.'/cancellations/'.$rows->firstWhere('cheque_no', 'CHQ1002')['id'])->assertOk();
        $this->assertSame('UNUSED', DB::table('acc_cheque_leaves')->where('id', $this->leaves[1])->value('status'));

        $auditor = $this->token(TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test'));
        $this->withToken($auditor)->getJson(self::C)->assertOk();
        $this->withToken($auditor)->postJson(self::C.'/cancellations', ['cheque_leaf_id' => $this->leaves[2], 'remarks' => 'x'])->assertForbidden();
        $this->withToken($this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test')))->getJson(self::C)->assertForbidden();
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
