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
 * Finance & Ledger → Voucher Entry (AnnotechERP 23/02): draft vouchers and
 * their balance, approval and Maker-Checker, posting and unposting, the
 * statutory register, combined MRR bills — and who may do what.
 */
class VoucherEntryTest extends TestCase
{
    use RefreshDatabase;

    private const V = '/api/v1/accounting/vouchers';

    private const B = '/api/v1/accounting/mrr-bills';

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

    public function test_a_draft_balances_is_approved_and_posted(): void
    {
        $opt = $this->as()->getJson(self::V.'/options')->assertOk()->json('data');
        $this->assertGreaterThan(10, count($opt['accounts']));
        $this->assertEquals(1, collect($opt['currencies'])->firstWhere('value', 'BDT')['rate']);
        [$cash, $rent] = [$opt['accounts'][0]['value'], $opt['accounts'][1]['value']];
        $v = ['voucher_date' => '2026-11-10', 'voucher_type' => 'JV', 'trx_source' => 'FINANCE', 'narration' => 'Office rent', 'action_status' => 'CREATE_VOUCHER',
            'lines' => [['account_id' => $rent, 'debit' => 500], ['account_id' => $cash, 'credit' => 500]]];

        // Every line one side, the two sides in agreement, the date in an open fiscal year.
        $this->as()->postJson(self::V, ['lines' => []] + $v)->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->as()->postJson(self::V, ['lines' => [['account_id' => $rent, 'debit' => 500, 'credit' => 500], ['account_id' => $cash, 'credit' => 500]]] + $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.line_side', ['n' => 1])]);
        $this->as()->postJson(self::V, ['lines' => [['account_id' => $rent, 'debit' => 500], ['account_id' => $cash, 'credit' => 400]]] + $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.unbalanced', ['dr' => '500.00', 'cr' => '400.00'])]);
        $this->as()->postJson(self::V, ['voucher_date' => '2020-01-01'] + $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.fy_none', ['date' => '2020-01-01'])]);

        // A draft: its reference on save, no number, editable.
        $d = $this->as()->postJson(self::V, $v)->assertCreated()->json('data');
        $this->assertEquals(['VR-2026-000001', null, 'DRAFT', 500, 2], [$d['voucher_ref_no'], $d['voucher_no'], $d['posting_status'], $d['amount'], count($d['lines'])]);
        $this->assertCount(1, $this->as()->getJson(self::V.'?scope=draft')->json('data'));
        $this->assertCount(0, $this->as()->getJson(self::V)->json('data'));

        // Approval commits it: numbered by type, out of the drafts, waiting to be posted.
        $a = $this->as()->putJson(self::V."/{$d['id']}", ['action_status' => 'APPROVED'] + $v)->assertOk()->json('data');
        $this->assertEquals(['JV-2026-000001', 'UNPOSTED', 'VR-2026-000001'], [$a['voucher_no'], $a['posting_status'], $a['voucher_ref_no']]);
        $this->assertCount(0, $this->as()->getJson(self::V.'?scope=draft')->json('data'));
        $this->assertSame('JV-2026-000001', $this->as()->getJson(self::V.'/last-no?type=JV')->json('data.voucher_no'));
        // Committed, it is amended on Voucher Entry (23/03) until posted, keeping its number and approval.
        $this->as()->putJson(self::V."/{$d['id']}", ['action_status' => 'APPROVED', 'narration' => 'Office rent, Nov'] + $v)->assertOk()->assertJsonPath('data.voucher_no', 'JV-2026-000001')->assertJsonPath('data.action_status', 'APPROVED');

        // Posting control: post, not twice; unpost; post again.
        $board = $this->as()->getJson(self::V.'/posting')->assertOk()->json('data');
        $this->assertEquals([1, 'JV-2026-000001', 500, 500], [$board['companies'][0]['count'], $board['vouchers'][0]['voucher_no'], $board['vouchers'][0]['debit'], $board['vouchers'][0]['credit']]);
        $this->as()->postJson(self::V."/{$d['id']}/post")->assertOk()->assertJsonPath('data.posting_status', 'POSTED');
        $this->as()->postJson(self::V."/{$d['id']}/post")->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.not_unposted', ['no' => 'JV-2026-000001'])]);
        $this->as()->postJson(self::V."/{$d['id']}/unpost")->assertOk()->assertJsonPath('data.posting_status', 'UNPOSTED');
        $this->as()->postJson(self::V."/{$d['id']}/post")->assertOk();
        $row = $this->as()->getJson(self::V)->json('data.0');
        $this->assertEquals(['POSTED', true, 'JV'], [$row['posting_status'], $row['balanced'], $row['voucher_type']]);

        // The statutory register: posted lines on a compliance ledger head, and their share.
        DB::table('acc_compliance_rules')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'account_id' => $rent, 'pct' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $s = $this->as()->getJson(self::V.'/statutory')->assertOk()->json('data');
        $this->assertEquals([1, 'JV-2026-000001', 500, 10, 50], [count($s), $s[0]['voucher'], $s[0]['amount'], $s[0]['pct'], $s[0]['qualifying']]);

        // A draft can be removed; a second draft takes the next reference.
        $e = $this->as()->postJson(self::V, $v)->assertCreated()->assertJsonPath('data.voucher_ref_no', 'VR-2026-000002')->json('data.id');
        $this->as()->deleteJson(self::V."/{$e}")->assertOk();
    }

    public function test_voucher_entry_commits_on_save_and_posts_once_approved(): void
    {
        $opt = $this->as()->getJson(self::V.'/options')->json('data');
        [$cash, $rent] = [$opt['accounts'][0]['value'], $opt['accounts'][1]['value']];
        $v = ['commit' => true, 'voucher_date' => '2026-11-10', 'voucher_type' => 'CPV', 'trx_source' => 'FINANCE', 'bill_no' => 'RB-11', 'voucher_for_type' => 'SUPPLIER', 'voucher_for' => 'Landlord', 'narration' => 'Rent', 'action_status' => 'CREATE_VOUCHER',
            'lines' => [['account_id' => $rent, 'debit' => 300], ['account_id' => $cash, 'credit' => 300]]];

        // Numbered on save, unposted, never a draft.
        $a = $this->as()->postJson(self::V, $v)->assertCreated()->json('data');
        $this->assertEquals(['CPV-2026-000001', 'VR-2026-000001', 'UNPOSTED', 'RB-11', 'SUPPLIER', 'Landlord'], [$a['voucher_no'], $a['voucher_ref_no'], $a['posting_status'], $a['bill_no'], $a['voucher_for_type'], $a['voucher_for']]);
        $this->assertCount(0, $this->as()->getJson(self::V.'?scope=draft')->json('data'));
        $this->assertSame('CPV-2026-000001', $this->as()->getJson(self::V)->json('data.0.voucher_no'));

        // Posted only once approved; its type stays; amended while unposted, keeping its number.
        $this->as()->postJson(self::V."/{$a['id']}/post")->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.not_approved', ['no' => 'CPV-2026-000001'])]);
        $this->as()->putJson(self::V."/{$a['id']}", ['voucher_type' => 'JV'] + $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.type_fixed', ['no' => 'CPV-2026-000001'])]);
        $b = $this->as()->putJson(self::V."/{$a['id']}", ['action_status' => 'APPROVED', 'narration' => 'Rent, November'] + $v)->assertOk()->json('data');
        $this->assertEquals(['CPV-2026-000001', 'APPROVED', 'Rent, November'], [$b['voucher_no'], $b['action_status'], $b['narration']]);
        $this->as()->postJson(self::V."/{$a['id']}/post")->assertOk();
        $this->as()->putJson(self::V."/{$a['id']}", $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.locked', ['no' => 'CPV-2026-000001'])]);
        $this->as()->deleteJson(self::V."/{$a['id']}")->assertUnprocessable();

        // A second, unposted voucher can be removed; every step is logged with the voucher as it stood.
        $c = $this->as()->postJson(self::V, $v)->assertCreated()->assertJsonPath('data.voucher_no', 'CPV-2026-000002')->json('data.id');
        $this->as()->deleteJson(self::V."/{$c}")->assertOk();
        $logs = DB::table('acc_voucher_logs')->orderBy('created_at')->orderBy('id')->get();
        $this->assertEquals(['CREATE', 'UPDATE', 'APPROVE', 'POST'], $logs->where('voucher_id', $a['id'])->pluck('action')->values()->all());
        $gone = $logs->firstWhere('action', 'DELETE');
        $this->assertEquals(['CPV-2026-000002', 'Landlord', 2], [$gone->voucher_no, json_decode($gone->snapshot, true)['voucher_for'], count(json_decode($gone->snapshot, true)['lines'])]);
    }

    public function test_maker_checker_and_who_may(): void
    {
        $opt = $this->as()->getJson(self::V.'/options')->json('data');
        [$cash, $bank] = [$opt['accounts'][0]['value'], $opt['accounts'][1]['value']];
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $both = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'fa@delta.test');
        foreach ([[$this->owner, true, false], [$manager, false, true], [$both, true, true]] as [$u, $make, $check]) {
            DB::table('acc_voucher_grants')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'voucher_type' => 'BPV', 'user_id' => $u->id, 'can_make' => $make, 'can_check' => $check, 'created_at' => now(), 'updated_at' => now()]);
        }
        $v = ['voucher_date' => '2026-11-10', 'voucher_type' => 'BPV', 'action_status' => 'CREATE_VOUCHER', 'lines' => [['account_id' => $cash, 'debit' => 100], ['account_id' => $bank, 'credit' => 100]]];

        // Only a maker raises it; only a checker approves it, never the one who raised it.
        $this->withToken($this->token($manager))->postJson(self::V, $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.not_maker', ['type' => __('accounting.vo.vtype.BPV')])]);
        $this->as()->postJson(self::V, ['action_status' => 'APPROVED'] + $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.not_checker', ['type' => __('accounting.vo.vtype.BPV')])]);
        $mine = $this->withToken($this->token($both))->postJson(self::V, $v)->assertCreated()->json('data.id');
        $this->withToken($this->token($both))->putJson(self::V."/{$mine}", ['action_status' => 'APPROVED'] + $v)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.self_check')]);
        $id = $this->as()->postJson(self::V, $v)->assertCreated()->json('data.id');
        $this->withToken($this->token($manager))->putJson(self::V."/{$id}", ['action_status' => 'APPROVED'] + $v)->assertOk()->assertJsonPath('data.voucher_no', 'BPV-2026-000001');
        // A type with no grants is raised and approved by anyone who may manage vouchers.
        $this->as()->postJson(self::V, ['voucher_type' => 'CPV', 'action_status' => 'APPROVED'] + $v)->assertCreated()->assertJsonPath('data.voucher_no', 'CPV-2026-000001');

        $auditor = $this->token(TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test'));
        $this->withToken($auditor)->getJson(self::V)->assertOk();
        $this->withToken($auditor)->getJson(self::B)->assertOk();
        $this->withToken($auditor)->postJson(self::V, $v)->assertForbidden();
        $this->withToken($auditor)->postJson(self::V."/{$id}/post")->assertForbidden();
        $this->withToken($this->token(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test')))->getJson(self::V.'/options')->assertForbidden();
    }

    public function test_a_combined_mrr_bill_from_a_suppliers_receipts(): void
    {
        $c = $this->delta->id;
        $id = fn () => strtolower((string) Str::ulid());
        $stamp = ['created_at' => now(), 'updated_at' => now()];
        $supplier = $id();
        $other = $id();
        DB::table('mp_suppliers')->insert([['id' => $supplier, 'company_id' => $c, 'code' => 'SUP-1', 'name' => 'Oil House', 'supplier_type' => 'LOCAL_NON_EPZ'] + $stamp, ['id' => $other, 'company_id' => $c, 'code' => 'SUP-2', 'name' => 'Other', 'supplier_type' => 'LOCAL_NON_EPZ'] + $stamp]);
        $store = $id();
        $warehouse = $id();
        DB::table('warehouses')->insert(['id' => $warehouse, 'company_id' => $c, 'factory_id' => DB::table('factories')->where('company_id', $c)->value('id'), 'code' => 'WH-1', 'name' => 'Main Warehouse'] + $stamp);
        DB::table('stores')->insert(['id' => $store, 'company_id' => $c, 'warehouse_id' => $warehouse, 'code' => 'ST-1', 'name' => 'Main'] + $stamp);
        $cat = $id();
        $unit = $id();
        $item = $id();
        DB::table('item_categories')->insert(['id' => $cat, 'company_id' => $c, 'code' => 'OIL', 'name' => 'Oil'] + $stamp);
        DB::table('units_of_measure')->insert(['id' => $unit, 'company_id' => $c, 'code' => 'LTR-K', 'name' => 'Litre'] + $stamp);
        DB::table('gs_items')->insert(['id' => $item, 'company_id' => $c, 'gs_category_id' => $cat, 'gs_unit_id' => $unit, 'item_code' => 'OIL-1', 'name' => 'Knit oil'] + $stamp);
        $receipt = function (string $no, string $status, string $sup, float $qty, float $amount) use ($id, $c, $store, $item, $stamp) {
            $r = $id();
            DB::table('knit_oil_receipts')->insert(['id' => $r, 'company_id' => $c, 'mrr_no' => $no, 'mrr_date' => '2026-11-05', 'store_id' => $store, 'supplier_id' => $sup, 'challan_no' => "CH-{$no}", 'pay_method' => 'CASH', 'source' => 'LOCAL', 'status' => $status] + $stamp);
            DB::table('knit_oil_receipt_lines')->insert(['id' => $id(), 'company_id' => $c, 'receipt_id' => $r, 'sort_order' => 1, 'item_id' => $item, 'uom' => 'LTR', 'challan_qty' => $qty, 'received_qty' => $qty, 'cond_qty' => 0, 'rate' => $amount / $qty, 'amount' => $amount] + $stamp);

            return $r;
        };
        $m1 = $receipt('OMRR-1', 'FINALIZED', $supplier, 100, 5000);
        $m2 = $receipt('OMRR-2', 'FINALIZED', $supplier, 10, 800);
        $receipt('OMRR-3', 'DRAFT', $supplier, 5, 100);
        $foreign = $receipt('OMRR-4', 'FINALIZED', $other, 5, 100);

        $opt = $this->as()->getJson(self::B.'/options')->assertOk()->json('data');
        [$payable, $expense] = [$opt['accounts'][0]['value'], $opt['accounts'][1]['value']];
        $list = $this->as()->getJson(self::B."/mrrs?type=OIL&supplier={$supplier}")->assertOk()->json('data');
        $this->assertEquals([['OMRR-1', 'OMRR-2'], 5000, 50, 'LTR'], [array_column($list, 'mrr_no'), $list[0]['total_amount'], $list[0]['rate'], $list[0]['uom']]);

        $bill = ['supplier_id' => $supplier, 'bill_date' => '2026-11-10', 'mrr_type' => 'OIL', 'credit_account_id' => $payable, 'remarks' => 'November oil',
            'lines' => [['mrr_id' => $m1, 'debit_account_id' => $expense, 'other_cost' => 200, 'vds' => 100, 'tds' => 50]]];
        $this->as()->postJson(self::B, ['lines' => []] + $bill)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.mb_lines')]);
        $this->as()->postJson(self::B, ['lines' => [['mrr_id' => $foreign, 'debit_account_id' => $expense]]] + $bill)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.mb_supplier', ['mrr' => 'OMRR-4'])]);
        $this->as()->postJson(self::B, ['mrr_type' => 'NEEDLE'] + $bill)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.mb_mrr', ['n' => 1])]);
        $this->as()->postJson(self::B, ['lines' => [['mrr_id' => $m1, 'debit_account_id' => $expense, 'vds' => 6000]]] + $bill)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.mb_net', ['mrr' => 'OMRR-1'])]);
        $b = $this->as()->postJson(self::B, $bill)->assertCreated()->json('data');
        $this->assertEquals(['MCB-2026-000001', 5050, 5000, 'OMRR-1', 'CH-OMRR-1', 100], [$b['bill_no'], $b['lines'][0]['net_amount'], $b['total'], $b['mrr_nos'], $b['challans'], $b['lines'][0]['total_qty']]);

        // Each MRR on one bill: the one taken is gone from the pick list, and refused on another bill.
        $this->assertSame(['OMRR-2'], array_column($this->as()->getJson(self::B."/mrrs?type=OIL&supplier={$supplier}")->json('data'), 'mrr_no'));
        $this->assertSame(['OMRR-1', 'OMRR-2'], array_column($this->as()->getJson(self::B."/mrrs?type=OIL&supplier={$supplier}&except={$b['id']}")->json('data'), 'mrr_no'));
        $this->as()->postJson(self::B, $bill)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.mb_taken', ['mrr' => 'OMRR-1', 'bill' => 'MCB-2026-000001'])]);

        // Adding a line; fixed asset; finalize locks it.
        $b = $this->as()->putJson(self::B."/{$b['id']}", ['lines' => [...$bill['lines'], ['mrr_id' => $m2, 'debit_account_id' => $expense]]] + $bill)->assertOk()->json('data');
        $this->assertEquals([5800, 5850, 'OMRR-1, OMRR-2'], [$b['total'], $b['net'], $b['mrr_nos']]);
        $this->as()->postJson(self::B."/{$b['id']}/fixed-asset")->assertOk()->assertJsonPath('data.fixed_asset', true);
        $this->as()->postJson(self::B."/{$b['id']}/final")->assertOk()->assertJsonPath('data.final', true);
        $this->as()->putJson(self::B."/{$b['id']}", $bill)->assertUnprocessable()->assertJsonFragment([__('accounting.vo.v.locked', ['no' => 'MCB-2026-000001'])]);
        $this->as()->deleteJson(self::B."/{$b['id']}")->assertUnprocessable();
        $row = $this->as()->getJson(self::B)->json('data.0');
        $this->assertEquals(['Oil House', 'OIL', true, 5850], [$row['supplier'], $row['mrr_type'], $row['final'], $row['net']]);
        $this->withToken($this->token(TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test')))->postJson(self::B, $bill)->assertForbidden();
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
