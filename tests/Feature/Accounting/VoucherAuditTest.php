<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finance & Ledger → Voucher Audit (AnnotechERP 23/06): the activity trail,
 * the deleted voucher history and the amendment log, read off the voucher
 * log — and who may read them.
 */
class VoucherAuditTest extends TestCase
{
    use RefreshDatabase;

    private const V = '/api/v1/accounting/vouchers';

    private const A = '/api/v1/accounting/voucher-audit';

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

    public function test_the_three_registers_read_the_voucher_log(): void
    {
        $opt = $this->as()->getJson(self::V.'/options')->json('data');
        [$a, $b] = [$opt['accounts'][0]['value'], $opt['accounts'][1]['value']];
        $v = ['commit' => true, 'voucher_date' => '2026-11-10', 'voucher_type' => 'JV', 'bill_no' => 'B-1', 'narration' => 'Rent', 'action_status' => 'CREATE_VOUCHER', 'lines' => [['account_id' => $a, 'debit' => 100], ['account_id' => $b, 'credit' => 100]]];

        $one = $this->as()->postJson(self::V, $v)->assertCreated()->json('data.id');
        $this->as()->putJson(self::V."/{$one}", ['narration' => 'Rent, Nov', 'bill_no' => 'B-2'] + $v)->assertOk();
        $this->as()->putJson(self::V."/{$one}", ['narration' => 'Rent, Nov', 'bill_no' => 'B-2', 'action_status' => 'APPROVED', 'lines' => [['account_id' => $a, 'debit' => 150], ['account_id' => $b, 'credit' => 150]]] + $v)->assertOk();
        $this->as()->postJson(self::V."/{$one}/post")->assertOk();
        $two = $this->as()->postJson(self::V, ['bill_no' => 'B-9'] + $v)->assertCreated()->json('data.id');
        $this->as()->deleteJson(self::V."/{$two}")->assertOk();

        // The activity trail: every step, newest first.
        $all = collect($this->as()->getJson(self::A.'?kind=activity')->assertOk()->json('data'));
        $this->assertEquals(['DELETE', 'CREATE', 'POST', 'APPROVE', 'UPDATE', 'UPDATE', 'CREATE'], $all->pluck('action')->all());
        $this->assertEquals(['JV-2026-000001', 'POSTED', 'Rent, Nov', 'JV'], [$all[2]['voucher_no'], $all[2]['posting_status'], $all[2]['note'], $all[2]['voucher_type']]);

        // The deleted history: the removed voucher, kept as text with what it held.
        $gone = $this->as()->getJson(self::A.'?kind=deleted')->json('data');
        $this->assertEquals([1, 'JV-2026-000002', 'B-9', __('accounting.vo.au.note_deleted', ['n' => 2, 'amount' => '100.00'])], [count($gone), $gone[0]['voucher_no'], $gone[0]['bill_no'], $gone[0]['note']]);

        // The amendment log: what changed against the voucher as it stood before.
        $amended = $this->as()->getJson(self::A.'?kind=amended')->json('data');
        $this->assertCount(2, $amended);
        $this->assertSame(__('accounting.vo.au.note_changed', ['what' => implode(', ', [__('accounting.vo.f.bill_no'), __('accounting.vo.f.narration')])]), $amended[1]['note']);
        $this->assertSame(__('accounting.vo.au.note_changed', ['what' => implode(', ', [__('accounting.vo.f.action_status'), __('accounting.vo.au.note_lines_amount', ['from' => '100.00', 'to' => '150.00'])])]), $amended[0]['note']);
        $this->assertSame($this->owner->name, $amended[0]['by']);

        $this->as()->getJson(self::A.'?kind=other')->assertUnprocessable();
        $this->withToken($this->token(TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test')))->getJson(self::A.'?kind=activity')->assertOk();
        $this->withToken($this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test')))->getJson(self::A.'?kind=activity')->assertForbidden();
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
