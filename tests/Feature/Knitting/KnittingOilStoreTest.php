<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\Identity\Models\User;
use App\Modules\Knitting\Models\KnitNeedleMove;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Oil Store (AnnotechERP 15/10): the opening line posts on
 * Finalize at Unit Price × Conv. Rate; the receipt walks its desks and posts
 * only on Finalize, leaves Accounts only finalized and Approved only
 * accounted; a return issues against a finalized receipt and re-opens one
 * step at a time; the indent's Issue Qty moves the oil from the store asked
 * into the store asking, never more than asked or held, and Re-open takes it
 * back; a consumption sheet is finalised only with Issue By named; and the
 * oil loan position lives on its own book.
 */
class KnittingOilStoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Factory $unit;

    private string $main;

    private string $floor;

    private string $oil;

    private string $cat;

    private string $party;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->unit = Factory::query()->first();
        $c = $this->delta->id;
        $this->cat = GsCategory::query()->create(['company_id' => $c, 'code' => 'LUB', 'name' => 'Lubricants', 'active' => true])->id;
        $ltr = GsUnit::query()->create(['company_id' => $c, 'code' => 'LTR', 'name' => 'Litre', 'active' => true])->id;
        $this->oil = GsItem::query()->create(['company_id' => $c, 'gs_category_id' => $this->cat, 'gs_unit_id' => $ltr, 'item_code' => 'OIL-KN-22', 'name' => 'Knitting oil ISO 22', 'default_price' => 300, 'active' => true])->id;
        $this->main = GsStore::query()->create(['company_id' => $c, 'code' => 'MAINT', 'name' => 'Maintenance Store', 'active' => true])->id;
        $this->floor = GsStore::query()->create(['company_id' => $c, 'code' => 'KNT', 'name' => 'Knitting Floor', 'active' => true])->id;
        $this->party = MaterialSupplier::query()->create(['code' => 'SUP-80001', 'name' => 'Square Textiles', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
    }

    public function test_opening_receipt_and_return(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $usd = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $usd, 'company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 120, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        // Opening: Cost Price is struck as Unit Price × Conv. Rate; one line per store, item and date; Finalize posts and locks it.
        $ob = ['store_id' => $this->main, 'ob_date' => $today, 'item_id' => $this->oil, 'qty' => 40, 'unit_price' => 3, 'currency_id' => $usd, 'conv_rate' => 120];
        $this->as()->postJson('/api/v1/knitting/needle/oil/openings', ['qty' => 0] + $ob)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Give the opening quantity.');
        $line = $this->as()->postJson('/api/v1/knitting/needle/oil/openings', $ob)->assertCreated()->assertJsonPath('data.currency', 'USD')->json('data');
        $this->assertEquals([360.0, 14400.0], [$line['cost_price'], $line['value']]);
        $this->as()->postJson('/api/v1/knitting/needle/oil/openings', $ob)->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->assertEquals(0.0, NeedleStore::balance($this->main, $this->oil));
        $this->as()->postJson('/api/v1/knitting/needle/oil/openings/finalize', ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->as()->postJson('/api/v1/knitting/needle/oil/openings/finalize', ['ids' => [$line['id']]])->assertOk()->assertJsonPath('data.0.finalized', true);
        $this->assertEquals(40.0, NeedleStore::balance($this->main, $this->oil));
        $this->assertSame('OIL', KnitNeedleMove::query()->where('doc_no', 'OOB-'.$line['id'])->value('book'));
        $this->as()->putJson("/api/v1/knitting/needle/oil/openings/{$line['id']}", ['qty' => 50] + $ob)->assertUnprocessable()->assertJsonValidationErrors('ob_date');
        $this->as()->postJson("/api/v1/knitting/needle/oil/openings/{$line['id']}/finalize")->assertUnprocessable();

        // Receipt: payment method and source required, Cond. Qty within RCV Qty, L/C only on an import.
        $r = ['mrr_date' => $today, 'store_id' => $this->main, 'supplier_id' => $this->party, 'pay_method' => 'CREDIT', 'source' => 'LOCAL', 'challan_no' => 'SQ-OIL-9',
            'lines' => [['item_id' => $this->oil, 'challan_qty' => 100, 'received_qty' => 98, 'cond_qty' => 10, 'rate' => 300]]];
        $this->as()->postJson('/api/v1/knitting/needle/oil/receipts', ['lines' => []] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A receipt with no oil on it receives nothing. Add a line before saving.');
        $this->as()->postJson('/api/v1/knitting/needle/oil/receipts', ['pay_method' => null, 'source' => null] + $r)->assertUnprocessable()->assertJsonValidationErrors(['pay_method', 'source']);
        $this->as()->postJson('/api/v1/knitting/needle/oil/receipts', ['lines' => [['cond_qty' => 99] + $r['lines'][0]]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.cond_qty' => 'Line 1: Cond. Qty cannot be more than RCV Qty.']);
        $this->as()->postJson('/api/v1/knitting/needle/oil/receipts', ['lc_no' => 'LC-9'] + $r)->assertUnprocessable()->assertJsonValidationErrors('lc_no');
        $no = $this->as()->postJson('/api/v1/knitting/needle/oil/receipts', $r)->assertCreated()->json('data.mrr_no');
        $this->assertSame("OMR-{$ym}0001", $no);
        $go = fn (string $action) => $this->as()->postJson("/api/v1/knitting/needle/oil/receipts/{$no}/{$action}");

        // Each desk in turn; Finalize only at Verification or Accounts, and it is what posts.
        $go('finalize')->assertUnprocessable();
        $go('next')->assertOk()->assertJsonPath('data.status', 'WAIT_QC');
        $go('next')->assertOk()->assertJsonPath('data.status', 'WAITING');
        $go('next')->assertOk()->assertJsonPath('data.status', 'WAIT_ACCOUNTS');
        $go('next')->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} has not been finalized, so it cannot leave the accounts desk.");
        $go('accounting')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertEquals(40.0, NeedleStore::balance($this->main, $this->oil));
        $go('finalize')->assertOk()->assertJsonPath('data.finalized', true);
        $this->assertEquals(138.0, NeedleStore::balance($this->main, $this->oil));
        $go('cancel')->assertUnprocessable()->assertJsonValidationErrors('status');
        $go('next')->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $go('next')->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} is not yet marked Accounting Done, so it cannot be completed.");
        $go('accounting')->assertOk()->assertJsonPath('data.accounted', true);
        $go('accounting')->assertUnprocessable();
        $go('next')->assertOk()->assertJsonPath('data.status', 'COMPLETE');
        $go('next')->assertUnprocessable();

        // Return: only against a finalized receipt, never more than is returnable; Issue posts, Re-open steps back.
        $draft = $this->as()->postJson('/api/v1/knitting/needle/oil/receipts', ['challan_no' => 'SQ-OIL-10'] + $r)->assertCreated()->json('data.mrr_no');
        $srcLine = $this->as()->getJson("/api/v1/knitting/needle/oil/receipts/{$no}/source")->assertOk()->assertJsonPath('data.lines.0.left', 98)->json('data.lines.0.receipt_line_id');
        $this->as()->getJson("/api/v1/knitting/needle/oil/receipts/{$draft}/source")->assertNotFound();
        $t = ['mrr_no' => $no, 'challan_no' => 'RTN-OIL-1', 'challan_date' => $today, 'challan_type' => 'NON_RETURNABLE', 'lines' => [['receipt_line_id' => $srcLine, 'qty' => 3]]];
        $this->as()->postJson('/api/v1/knitting/needle/oil/returns', ['mrr_no' => $draft] + $t)->assertUnprocessable()->assertJsonValidationErrors('mrr_no');
        $this->as()->postJson('/api/v1/knitting/needle/oil/returns', ['challan_no' => null] + $t)->assertUnprocessable()
            ->assertJsonPath('errors.challan_no.0', "Give the store's own return challan number.");
        $this->as()->postJson('/api/v1/knitting/needle/oil/returns', ['lines' => [['receipt_line_id' => $srcLine, 'qty' => 99]]] + $t)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 sends back more than is still returnable on the receipt (98).']);
        $this->as()->postJson('/api/v1/knitting/needle/oil/returns', $t)->assertCreated();
        $ret = fn (string $action) => $this->as()->postJson("/api/v1/knitting/needle/oil/returns/RTN-OIL-1/{$action}");
        $ret('finalize')->assertUnprocessable();
        $ret('issue')->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals(135.0, NeedleStore::balance($this->main, $this->oil));
        $ret('reopen')->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->assertEquals(138.0, NeedleStore::balance($this->main, $this->oil));
        $ret('issue')->assertOk();
        $ret('finalize')->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $ret('reopen')->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals(135.0, NeedleStore::balance($this->main, $this->oil));
        $row = collect($this->as()->getJson('/api/v1/knitting/needle/oil/receipts')->json('data'))->firstWhere('mrr_no', $no);
        $this->assertEquals([98.0, 10.0, 95.0], [$row['received_qty'], $row['cond_qty'], $row['returnable']]);
    }

    public function test_indent_consumption_and_loan_opening(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        KnitNeedleMove::query()->create(['book' => 'OIL', 'store_id' => $this->main, 'item_id' => $this->oil, 'move_date' => $today, 'kind' => 'OPENING', 'doc_no' => 'OB-O', 'qty' => 30]);

        // Indent: two different stores; once verified only Issue Qty changes, never past what was asked or the store asked holds.
        $q = ['req_date' => $today, 'from_store_id' => $this->floor, 'to_store_id' => $this->main, 'req_type' => 'KNIT_MC_OIL', 'reason' => 'Shift top-up',
            'lines' => [['item_id' => $this->oil, 'required_qty' => 20, 'rate' => 300]]];
        $this->as()->postJson('/api/v1/knitting/needle/oil/reqs', ['to_store_id' => $this->floor] + $q)->assertUnprocessable()
            ->assertJsonPath('errors.to_store_id.0', 'The store asking and the store asked have to be different stores.');
        $this->as()->postJson('/api/v1/knitting/needle/oil/reqs', ['lines' => []] + $q)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A requisition with no oil on it asks for nothing. Add a line before saving.');
        $req = $this->as()->postJson('/api/v1/knitting/needle/oil/reqs', $q)->assertCreated()->json('data');
        $this->assertSame("ORQ-{$ym}0001", $req['req_no']);
        $at = "/api/v1/knitting/needle/oil/reqs/{$req['req_no']}";
        $w = fn (float $n) => ['lines' => [['id' => $req['lines'][0]['id'], 'issue_qty' => $n]]];
        $this->as()->postJson("{$at}/issue-full")->assertUnprocessable();
        $this->as()->postJson("{$at}/submit-verify")->assertOk()->assertJsonPath('data.status', 'VERIFIED');
        $this->as()->putJson($at, $w(25))->assertUnprocessable()->assertJsonValidationErrors(['lines.0.issue_qty' => 'Line 1 issues more than it asks for (20).']);
        $this->as()->putJson($at, $w(5))->assertOk()->assertJsonPath('data.status', 'PARTLY_ISSUED');
        $this->assertEquals([25.0, 5.0], [NeedleStore::balance($this->main, $this->oil), NeedleStore::balance($this->floor, $this->oil)]);
        $this->as()->putJson($at, $w(8))->assertOk();
        $this->assertEquals([22.0, 8.0], [NeedleStore::balance($this->main, $this->oil), NeedleStore::balance($this->floor, $this->oil)]);
        $this->as()->postJson("{$at}/issue-full")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertEquals([10.0, 20.0], [NeedleStore::balance($this->main, $this->oil), NeedleStore::balance($this->floor, $this->oil)]);
        $row = collect($this->as()->getJson('/api/v1/knitting/needle/oil/reqs')->json('data'))->firstWhere('req_no', $req['req_no']);
        $this->assertEquals([20.0, 20.0, 0.0, 20.0], [$row['required'], $row['issued'], $row['balance'], $row['outstanding']]);
        $this->as()->postJson("{$at}/reopen")->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->assertEquals([30.0, 0.0], [NeedleStore::balance($this->main, $this->oil), NeedleStore::balance($this->floor, $this->oil)]);
        $big = $this->as()->postJson('/api/v1/knitting/needle/oil/reqs', ['lines' => [['required_qty' => 50] + $q['lines'][0]]] + $q)->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/knitting/needle/oil/reqs/{$big['req_no']}/submit-verify")->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/oil/reqs/{$big['req_no']}/issue-full")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'The store holds only 30 of OIL-KN-22 · Knitting oil ISO 22.');

        // Consumption: one sheet per store, date and shift; Finalize needs Issue By and takes the oil out.
        $machine = $this->machine();
        $shift = FactoryCalendarSetup::shift($this->delta->id, 'A', 'A shift', '06:00', '14:00');
        $s = ['cons_date' => $today, 'shift_id' => $shift, 'office_id' => $this->unit->id, 'store_id' => $this->main,
            'lines' => [['machine_id' => $machine, 'item_id' => $this->oil, 'qty' => 2.5, 'running_hrs' => 8, 'cost_price' => 300]]];
        $this->as()->postJson('/api/v1/knitting/needle/oil/consumptions', ['lines' => []] + $s)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A consumption sheet with no machines on it records nothing. Add a line before saving.');
        $sheet = $this->as()->postJson('/api/v1/knitting/needle/oil/consumptions', $s)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->json('data.cons_no');
        $this->assertSame("OCS-{$ym}0001", $sheet);
        $this->as()->postJson('/api/v1/knitting/needle/oil/consumptions', $s)->assertUnprocessable()->assertJsonValidationErrors('shift_id');
        $this->as()->postJson("/api/v1/knitting/needle/oil/consumptions/{$sheet}/finalize")->assertUnprocessable()->assertJsonValidationErrors('issued_by');
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => 'C-911', 'name' => 'Oil Keeper', 'nid' => '1234567891', 'mobile' => '01712345679',
            'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();
        $keeper = DB::table('hr_employees')->where('company_id', $this->delta->id)->where('card_no', 'C-911')->value('id');
        $this->as()->putJson("/api/v1/knitting/needle/oil/consumptions/{$sheet}", ['issued_by' => $keeper] + $s)->assertOk();
        $this->as()->postJson("/api/v1/knitting/needle/oil/consumptions/{$sheet}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->assertEquals(27.5, NeedleStore::balance($this->main, $this->oil));
        $this->assertSame('CONSUME', KnitNeedleMove::query()->where('doc_no', $sheet)->value('kind'));
        $this->as()->putJson("/api/v1/knitting/needle/oil/consumptions/{$sheet}", $s)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->getJson('/api/v1/knitting/needle/oil/consumptions')->assertJsonPath('data.0.running_hrs', 8);

        // Oil Loan Opening: its own book — the same party and item may hold a needle position too; it counts as borrowed oil.
        $lo = ['ob_date' => $today, 'party_id' => $this->party, 'item_id' => $this->oil, 'loan_received' => 12];
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', ['book' => 'OIL'] + $lo)->assertCreated();
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', ['book' => 'OIL'] + $lo)->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->as()->postJson('/api/v1/knitting/needle/loan-openings', $lo)->assertCreated();
        $this->as()->getJson('/api/v1/knitting/needle/loan-openings?book=OIL')->assertJsonCount(1, 'data')->assertJsonPath('data.0.book', 'OIL');
        $this->assertEquals(12.0, NeedleStore::borrowed('OIL', $this->party)[$this->oil]);
    }

    private function machine(): string
    {
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knit', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Knit Floor', 'code' => 'KF-1']);
        $ref = fn (string $list, string $code) => collect($this->as()->getJson("/api/v1/knitting/ref-lists/{$list}")->json('data'))->firstWhere('code', $code)['id'];
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id')
            ?? collect($this->as()->getJson('/api/v1/erp/origin-countries')->json('data'))->firstWhere('name', 'Germany')['id'];

        return $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => 'MC-01', 'machine_type_id' => $ref('MC_TYPE', 'SJ'), 'brand_id' => $ref('BRAND', 'MAYER'),
            'origin_country_id' => $country, 'dia_id' => $ref('DIA', 'D30'), 'gauge_id' => $ref('GG', 'GG24'), 'supported_gauge_ids' => $ref('GG', 'GG24'), 'capacity_kg' => 180,
            'feeders' => 96, 'rpm' => 24, 'efficiency' => 85, 'floor_id' => $floor->id, 'mfg_year' => 2019])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
