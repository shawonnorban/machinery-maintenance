<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finance & Ledger → Master Data (AnnotechERP 23/01): payment modes,
 * maker-checker grants, the chart of accounts and its code rules, cost
 * centres, the shared bank register with its branches and the company's
 * accounts, cheque books and their leaves, fiscal years, account type
 * classifications and statutory ledger rules — and who may change them.
 */
class AccountingMasterTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_payment_modes_and_maker_checker_grants(): void
    {
        $modes = collect($this->as()->getJson('/api/v1/erp/acc-payment-modes')->assertOk()->json('data'));
        $this->assertCount(9, $modes);
        $this->assertSame('BPV,BRV,CV', $modes->firstWhere('name', 'Cheque')['voucher_types']);
        $this->as()->postJson('/api/v1/erp/acc-payment-modes', ['short_name' => 'MFS'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Name is required.');
        $this->as()->postJson('/api/v1/erp/acc-payment-modes', ['name' => 'Mobile Banking', 'voucher_types' => 'BPV,XYZ'])->assertUnprocessable()->assertJsonValidationErrors('voucher_types');
        $this->as()->postJson('/api/v1/erp/acc-payment-modes', ['name' => 'Mobile Banking', 'short_name' => 'MFS', 'voucher_types' => 'bpv,brv'])->assertCreated()->assertJsonPath('data.voucher_types', 'BPV,BRV');
        $this->as()->postJson('/api/v1/erp/acc-payment-modes', ['name' => 'cheque'])->assertUnprocessable()->assertJsonValidationErrors('name');

        $checker = TenantFixture::user($this->delta, 'AUDITOR', 'checker@delta.test');
        $this->as()->getJson('/api/v1/accounting/voucher-types')->assertJsonCount(7, 'data');
        $this->as()->putJson('/api/v1/accounting/voucher-types/BPV', ['rows' => [['user_id' => $this->owner->id]]])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->putJson('/api/v1/accounting/voucher-types/BPV', ['rows' => [['user_id' => $this->owner->id, 'can_make' => true], ['user_id' => $this->owner->id, 'can_check' => true]]])->assertUnprocessable();
        $this->as()->putJson('/api/v1/accounting/voucher-types/BPV', ['rows' => [['user_id' => $this->owner->id, 'can_make' => true], ['user_id' => $checker->id, 'can_check' => true]]])->assertOk()->assertJsonCount(2, 'data');
        $this->as()->getJson('/api/v1/accounting/voucher-types')->assertJsonPath('data.0.count', 2);
        $this->as()->putJson('/api/v1/accounting/voucher-types/XX', ['rows' => []])->assertNotFound();
    }

    public function test_the_chart_of_accounts_keeps_its_codes_and_shape(): void
    {
        $accounts = collect($this->as()->getJson('/api/v1/erp/acc-accounts')->assertOk()->json('data'));
        $this->assertCount(128, $accounts);
        $ppe = $accounts->firstWhere('code', '10101');
        $land = $accounts->firstWhere('code', '1010101');
        $this->assertSame('101 — NON CURRENT ASSETS', $ppe['parent']);
        $this->assertSame(5, $ppe['children']);
        $this->assertCount(54, $this->as()->getJson('/api/v1/accounting/tree')->json('data'));
        $this->as()->getJson("/api/v1/accounting/tree/{$ppe['id']}")->assertJsonCount(5, 'data');

        $this->as()->postJson('/api/v1/erp/acc-accounts', ['parent_id' => $ppe['id'], 'type' => 'ASSET'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Name is required.');
        $this->as()->postJson('/api/v1/erp/acc-accounts', ['parent_id' => $ppe['id'], 'name' => 'Vehicles', 'type' => 'ASSET'])->assertCreated()->assertJsonPath('data.code', '1010106');
        $this->as()->postJson('/api/v1/erp/acc-accounts', ['parent_id' => $ppe['id'], 'name' => 'Computers', 'type' => 'LIABILITY'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->as()->postJson('/api/v1/erp/acc-accounts', ['parent_id' => $land['id'], 'name' => 'Sub land', 'type' => 'ASSET'])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->as()->postJson('/api/v1/erp/acc-accounts', ['parent_id' => $ppe['id'], 'code' => '999', 'name' => 'Computers', 'type' => 'ASSET'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->patchJson("/api/v1/erp/acc-accounts/{$ppe['id']}", ['postable' => true])->assertUnprocessable()->assertJsonValidationErrors('postable');
        $this->as()->postJson('/api/v1/erp/acc-accounts', ['name' => 'MEMORANDUM', 'type' => 'ASSET', 'postable' => false])->assertCreated()->assertJsonPath('data.code', '6');
        $this->as()->postJson('/api/v1/erp/acc-accounts', ['name' => 'Ref head', 'type' => 'ASSET', 'voucher_ref_id' => $ppe['id']])->assertUnprocessable()->assertJsonValidationErrors('voucher_ref_type');
        // Brickland removes an account; one that others are filed under is refused (deactivate it instead).
        $this->as()->deleteJson("/api/v1/erp/acc-accounts/{$ppe['id']}")->assertUnprocessable()->assertJsonPath('errors.id.0', 'This record is in use elsewhere and cannot be removed — deactivate it instead.');

        // Classifications are one per account and layer; compliance rules one per head, saved again it replaces.
        $classes = $this->as()->getJson('/api/v1/erp/acc-account-classes')->assertJsonCount(3, 'data')->json('data');
        $this->assertSame('1020401 — Cash In Hand', $classes[0]['account']);
        $cash = $accounts->firstWhere('code', '1020401')['id'];
        $this->as()->postJson('/api/v1/erp/acc-account-classes', ['account_type' => 'GENERAL', 'layer' => 'LEDGER', 'account_id' => $cash])->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->as()->postJson('/api/v1/erp/acc-account-classes', ['layer' => 'LEDGER', 'account_id' => $cash])->assertUnprocessable()->assertJsonPath('errors.account_type.0', 'Account Type is required.');
        $this->as()->postJson('/api/v1/erp/acc-compliance-rules', ['account_id' => $cash])->assertUnprocessable()->assertJsonPath('errors.pct.0', 'Compliance PCT is required.');
        $this->as()->postJson('/api/v1/erp/acc-compliance-rules', ['account_id' => $ppe['id'], 'pct' => 5])->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->as()->postJson('/api/v1/erp/acc-compliance-rules', ['account_id' => $cash, 'pct' => 5])->assertCreated();
        $this->as()->postJson('/api/v1/erp/acc-compliance-rules', ['account_id' => $cash, 'pct' => 7.5])->assertCreated();
        $this->as()->getJson('/api/v1/erp/acc-compliance-rules')->assertJsonCount(1, 'data')->assertJsonPath('data.0.pct', '7.50');
    }

    public function test_cost_centres_banks_accounts_and_cheque_books(): void
    {
        $group = $this->as()->postJson('/api/v1/erp/acc-cost-center-groups', ['name' => 'Sewing Floors', 'display_order' => 1])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/acc-cost-center-groups', ['remarks' => 'x'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Cost Center is required.');
        $this->as()->postJson('/api/v1/erp/acc-cost-centers', ['name' => 'Line 1', 'group_id' => $group])->assertCreated()->assertJsonPath('data.code', '001')->assertJsonPath('data.group', 'Sewing Floors');
        $this->as()->postJson('/api/v1/erp/acc-cost-centers', ['name' => 'Line 2'])->assertCreated()->assertJsonPath('data.code', '002');

        // The bank register is Trade & Export's: 26 banks, codes issued here from the prefix.
        $banks = collect($this->as()->getJson('/api/v1/erp/acc-banks')->assertOk()->json('data'));
        $this->assertCount(26, $banks);
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '050', 'name' => 'Bangladesh'])->json('data.id');
        $this->as()->postJson('/api/v1/erp/acc-banks', ['prefix' => 'MBL', 'country_id' => $country])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Bank Name (EN) is required.');
        $mbl = $this->as()->postJson('/api/v1/erp/acc-banks', ['name' => 'Mercantile Bank', 'prefix' => 'mbl', 'country_id' => $country, 'is_treasury' => true])->assertCreated()->assertJsonPath('data.code', 'MBL')->assertJsonPath('data.is_treasury', true)->json('data.id');
        $this->as()->getJson('/api/v1/erp/tx-banks')->assertJsonCount(27, 'data');

        $this->as()->putJson("/api/v1/accounting/banks/{$mbl}", ['rows' => [['code' => 'GUL', 'name' => 'Gulshan'], ['code' => 'gul', 'name' => 'Gulshan 2']]])->assertUnprocessable();
        $branch = $this->as()->putJson("/api/v1/accounting/banks/{$mbl}", ['rows' => [['code' => 'GUL', 'name' => 'Gulshan', 'routing_no' => '140261726']]])->assertOk()->json('data.0.id');
        $account = ['bank_id' => $mbl, 'branch_id' => $branch, 'account_no' => '0111-1234567', 'account_name' => 'Delta Apparels Ltd', 'account_type' => 'CURRENT', 'currency' => 'bdt'];
        $other = $banks->firstWhere('code', 'ABN')['id'];
        $this->as()->postJson('/api/v1/erp/acc-bank-accounts', ['bank_id' => $other] + $account)->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $acc = $this->as()->postJson('/api/v1/erp/acc-bank-accounts', $account)->assertCreated()->assertJsonPath('data.currency', 'BDT')->assertJsonPath('data.branch', 'Gulshan')->json('data.id');
        $this->as()->putJson("/api/v1/accounting/banks/{$mbl}", ['rows' => []])->assertUnprocessable()->assertJsonValidationErrors('rows');

        $book = ['bank_account_id' => $acc, 'book_no' => 'CB-01', 'required_leaf' => 10, 'prefix' => 'CHQ', 'no_to' => 1000010, 'requisition_date' => '2026-10-01', 'receive_date' => '2026-10-05'];
        $this->as()->postJson('/api/v1/erp/acc-cheque-books', $book)->assertUnprocessable()->assertJsonPath('errors.no_from.0', 'Cheque No From is required.');
        $this->as()->postJson('/api/v1/erp/acc-cheque-books', ['no_from' => 1000001, 'no_to' => 1000020] + $book)->assertUnprocessable()->assertJsonValidationErrors('required_leaf');
        $id = $this->as()->postJson('/api/v1/erp/acc-cheque-books', ['no_from' => 1000001, 'no_to' => 1000010] + $book)->assertCreated()->assertJsonPath('data.leaves', 10)->assertJsonPath('data.bank', 'Mercantile Bank')->json('data.id');
        $this->as()->postJson('/api/v1/erp/acc-cheque-books', ['book_no' => 'CB-02', 'no_from' => 1000005, 'no_to' => 1000014] + $book)->assertUnprocessable()->assertJsonValidationErrors('no_from');
        $leaves = $this->as()->getJson("/api/v1/accounting/cheque-books/{$id}")->assertJsonCount(10, 'data')->assertJsonPath('data.0.cheque_no', 'CHQ1000001')->json('data');
        $this->as()->putJson("/api/v1/accounting/cheque-books/{$id}", ['rows' => array_map(fn ($l) => ['id' => $l['id'], 'active' => $l['cheque_no'] !== 'CHQ1000001'], array_slice($leaves, 0, 9))])->assertUnprocessable();
        $this->as()->putJson("/api/v1/accounting/cheque-books/{$id}", ['rows' => array_map(fn ($l) => ['id' => $l['id'], 'active' => $l['cheque_no'] !== 'CHQ1000001'], $leaves)])->assertOk()->assertJsonPath('data.0.active', false);
        $this->as()->patchJson("/api/v1/erp/acc-cheque-books/{$id}", ['finalized' => true])->assertOk();
        $this->as()->patchJson("/api/v1/erp/acc-cheque-books/{$id}", ['remarks' => 'late'])->assertUnprocessable()->assertJsonValidationErrors('finalized');
    }

    public function test_fiscal_years_and_who_may_change_finance(): void
    {
        $years = $this->as()->getJson('/api/v1/erp/acc-fiscal-years')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame('FY2027-28', $years[0]['name']);
        $year = ['name' => 'FY2028-29', 'fy_end' => '2029-06-30', 'cal_start' => '2028-01-01', 'cal_end' => '2028-12-31'];
        $this->as()->postJson('/api/v1/erp/acc-fiscal-years', $year)->assertUnprocessable()->assertJsonPath('errors.fy_start.0', 'Fiscal Year Start is required.');
        $this->as()->postJson('/api/v1/erp/acc-fiscal-years', ['fy_start' => '2028-03-01'] + $year)->assertUnprocessable()->assertJsonValidationErrors('fy_start');
        $this->as()->postJson('/api/v1/erp/acc-fiscal-years', ['fy_start' => '2029-07-01'] + $year)->assertUnprocessable()->assertJsonValidationErrors('fy_end');
        $this->as()->postJson('/api/v1/erp/acc-fiscal-years', ['fy_start' => '2028-07-01'] + $year)->assertCreated()->assertJsonPath('data.created_by', $this->owner->name);

        $auditor = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($auditor)->getJson('/api/v1/erp/acc-accounts')->assertOk();
        $this->withToken($auditor)->postJson('/api/v1/erp/acc-cost-center-groups', ['name' => 'X'])->assertForbidden();

        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->getJson('/api/v1/erp/acc-accounts')->assertOk();
        $this->withToken($manager)->postJson('/api/v1/erp/acc-cost-center-groups', ['name' => 'X'])->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
