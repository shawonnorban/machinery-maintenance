<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitScProgram;
use App\Modules\Knitting\Models\KnitScProgramLine;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptFabric;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract money
 * papers: each bill kind charged only to that party's own programmes of its
 * kind, amounts and net recomputed on the server, Submit → Approve, a
 * revision marking the bill it replaces Revised (and Cancel restoring it);
 * and the debit memo's Submit → Approve → Settle.
 */
class KnittingScBillTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
    }

    public function test_bills_revision_and_debit_memo(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $akij = MaterialSupplier::query()->create(['code' => 'SUP-50001', 'name' => 'Akij Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $square = MaterialSupplier::query()->create(['code' => 'SUP-50002', 'name' => 'Square Textiles', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $prog = KnitScProgram::query()->create(['program_no' => 'SCP-1', 'kind' => 'BULK_SOLID', 'program_type' => 'BULK', 'po_date' => $today, 'party_id' => $akij, 'status' => 'APPROVED']);
        KnitScProgramLine::query()->create(['program_id' => $prog->id, 'sort_order' => 1, 'fabric' => 'S/J 30/1 160 GSM', 'qty' => 500, 'rate' => 18.5, 'uom' => 'kg', 'received_qty' => 300]);
        KnitScProgram::query()->create(['program_no' => 'SCP-2', 'kind' => 'BULK_SOLID', 'program_type' => 'BULK', 'po_date' => $today, 'party_id' => $square, 'status' => 'APPROVED']);
        $scr = KnitScReceipt::query()->create(['program_no' => 'SQT-KP-1', 'auto_number' => false, 'prog_date' => $today, 'party_id' => $square, 'yarn_store_id' => $this->store, 'status' => 'CONFIRMED']);
        KnitScReceiptFabric::query()->create(['receipt_id' => $scr->id, 'sl' => 1, 'party_ref' => 'Rib 1x1', 'qty' => 200, 'uom' => 'kg', 'rate' => 12]);
        $yd = KnitYdProgram::query()->create(['program_no' => 'YDP-1', 'program_date' => $today, 'yd_type' => 'SUB_CONTRACT', 'supplier_id' => $square, 'status' => 'RUNNING']);
        KnitYdProgramLine::query()->create(['program_id' => $yd->id, 'sort_order' => 1, 'colour_name' => 'Navy', 'qty' => 100, 'rate' => 95, 'uom' => 'kg']);
        $usd = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $usd, 'company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $opt = $this->as()->getJson('/api/v1/knitting/fstore/bills/options')->json('data.programs');
        $this->assertEquals(['SQT-KP-1'], array_column($opt['IN_HOUSE'], 'value'));
        $scp = collect($opt['OUT_HOUSE'])->firstWhere('value', 'SCP-1');
        $this->assertEquals([18.5, 'S/J 30/1 160 GSM', 300.0, 500.0, $akij], [$scp['rate'], $scp['description'], $scp['received'], $scp['planned'], $scp['party_id']]);

        // Out-house bill: only the party's own S/C programmes; amounts and net from the server.
        $line = ['program_no' => 'SCP-1', 'description' => 'S/J 30/1 160 GSM', 'challan_no' => 'AK/CH-501', 'qty' => 120, 'uom' => 'kg', 'rate' => 18.5];
        $b = ['bill_date' => $today, 'party_id' => $akij, 'party_bill_no' => 'AK-B-77', 'currency_id' => $usd, 'deduction' => 40, 'vat' => 111, 'lines' => [$line, ['qty' => 30, 'rate' => 20] + $line]];
        $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', ['lines' => []] + $b)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A bill with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', ['lines' => [['program_no' => 'SCP-2'] + $line]] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.program_no' => "Line 1: SCP-2 is not this party's programme."]);
        $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', ['lines' => [['program_no' => 'YDP-1'] + $line]] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.program_no' => 'Line 1: YDP-1 is not a programme this bill can be charged to.']);
        $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', ['deduction' => 5000] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['deduction' => 'Deduction cannot be more than the bill (2,931.00).']);
        $bill = $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', $b)->assertCreated()->json('data');
        $no = $bill['bill_no'];
        $this->assertEquals(["SOB-{$ym}0001", 2820.0, 2891.0, 'USD', 'KG', 'SCP-1', 600.0], [$no, $bill['gross'], $bill['net'], $bill['currency'], $bill['lines'][0]['uom'], $bill['lines'][1]['program_no'], $bill['lines'][1]['amount']]);
        $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', $b)->assertUnprocessable()->assertJsonPath('errors.party_bill_no.0', "The party's bill AK-B-77 is already on file.");
        $this->as()->getJson("/api/v1/knitting/fstore/bills/in-house/{$no}")->assertNotFound();
        $this->assertEquals(150.0, collect($this->as()->getJson('/api/v1/knitting/fstore/bills/options')->json('data.programs.OUT_HOUSE'))->firstWhere('value', 'SCP-1')['billed']);

        // Submit claims it; a revision replaces it on submit, and cancelling the revision restores it.
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$no}/approve")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Draft, so it cannot be approved.");
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/knitting/fstore/bills/out-house/{$no}", $b)->assertUnprocessable()->assertJsonValidationErrors('status');
        $r = ['ref_bill_no' => $no, 'lines' => [$line]] + $b;
        $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', $r)->assertUnprocessable()->assertJsonPath('errors.rev_descr.0', 'Say why the bill is being re-raised.');
        $rev = $this->as()->postJson('/api/v1/knitting/fstore/bills/out-house', ['rev_descr' => 'Rate for 30 kg was wrong'] + $r)->assertCreated()->json('data');
        $this->assertEquals([$no, 2220.0 - 40 + 111], [$rev['ref_bill_no'], $rev['net']]);
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$rev['bill_no']}/submit")->assertOk();
        $old = $this->as()->getJson("/api/v1/knitting/fstore/bills/out-house/{$no}")->json('data');
        $this->assertEquals(['REVISED', $rev['bill_no']], [$old['status'], $old['revised_by']]);
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$no}/approve")->assertUnprocessable();
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$rev['bill_no']}/cancel")->assertOk();
        $this->assertSame('SUBMITTED', $this->as()->getJson("/api/v1/knitting/fstore/bills/out-house/{$no}")->json('data.status'));
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$no}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->as()->postJson("/api/v1/knitting/fstore/bills/out-house/{$no}/cancel")->assertUnprocessable();
        $row = $this->as()->getJson('/api/v1/knitting/fstore/bills/out-house')->json('data');
        $this->assertEquals([2, 'Akij Knit', ['KG' => 150.0]], [count($row), collect($row)->firstWhere('bill_no', $no)['party'], collect($row)->firstWhere('bill_no', $no)['qty']]);

        // In-house and Y/D bills: their own programme kinds, own numbers.
        $ih = $this->as()->postJson('/api/v1/knitting/fstore/bills/in-house', ['bill_date' => $today, 'party_id' => $square,
            'lines' => [['program_no' => 'SQT-KP-1', 'description' => 'Rib 1x1', 'qty' => 50, 'uom' => 'kg', 'rate' => 12]]])->assertCreated()->json('data');
        $this->assertEquals(["SIB-{$ym}0001", 600.0, 'SQT-KP-1'], [$ih['bill_no'], $ih['net'], $ih['lines'][0]['program_no']]);
        $ydb = $this->as()->postJson('/api/v1/knitting/fstore/bills/yd', ['bill_date' => $today, 'party_id' => $square,
            'lines' => [['program_no' => 'YDP-1', 'colour_name' => 'Navy', 'qty' => 40, 'uom' => 'kg', 'rate' => 95]]])->assertCreated()->json('data');
        $this->assertEquals(["YDB-{$ym}0001", 3800.0, 'YDP-1'], [$ydb['bill_no'], $ydb['gross'], $ydb['lines'][0]['program_no']]);
        $this->as()->postJson('/api/v1/knitting/fstore/bills/yd', ['bill_date' => $today, 'party_id' => $square,
            'lines' => [['program_no' => 'SQT-KP-1', 'qty' => 1, 'uom' => 'kg', 'rate' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.program_no');

        // Debit memo: lines need a description; Submit → Approve → Settle; settled cannot be cancelled.
        $m = ['memo_date' => $today, 'party_id' => $akij, 'reason' => 'Holes along the selvedge', 'lines' => [
            ['part' => 'FABRIC', 'description' => 'S/J 30/1 rejected roll', 'challan_no' => 'AK/CH-501', 'qty' => 12.5, 'uom' => 'kg', 'rate' => 4.4],
            ['part' => 'COLLAR', 'description' => 'Collar shade off', 'qty' => 40, 'uom' => 'pcs', 'rate' => 0.25],
        ]];
        $this->as()->postJson('/api/v1/knitting/fstore/debit-memos', ['lines' => []] + $m)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A memo with no charged lines on it claims nothing. Add at least one.');
        $this->as()->postJson('/api/v1/knitting/fstore/debit-memos', ['lines' => [['description' => ''] + $m['lines'][0]]] + $m)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description' => 'Every line needs a description.']);
        $memo = $this->as()->postJson('/api/v1/knitting/fstore/debit-memos', $m)->assertCreated()->json('data');
        $mn = $memo['memo_no'];
        $this->assertEquals(["DMO-{$ym}0001", 65.0, 'PCS'], [$mn, $memo['total'], $memo['lines'][1]['uom']]);
        $this->as()->postJson("/api/v1/knitting/fstore/debit-memos/{$mn}/settle")->assertUnprocessable();
        foreach (['submit' => 'SUBMITTED', 'approve' => 'APPROVED', 'settle' => 'SETTLED'] as $a => $s) {
            $this->as()->postJson("/api/v1/knitting/fstore/debit-memos/{$mn}/{$a}")->assertOk()->assertJsonPath('data.status', $s);
        }
        $this->as()->postJson("/api/v1/knitting/fstore/debit-memos/{$mn}/cancel")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$mn} is Settled, so it cannot be cancelled.");
        $this->assertEquals([65.0, 'Akij Knit'], [$this->as()->getJson('/api/v1/knitting/fstore/debit-memos')->json('data.0.total'), $this->as()->getJson('/api/v1/knitting/fstore/debit-memos')->json('data.0.party')]);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
