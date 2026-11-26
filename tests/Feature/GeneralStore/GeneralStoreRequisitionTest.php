<?php

declare(strict_types=1);

namespace Tests\Feature\GeneralStore;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\GeneralStore\Models\GsAuditEntry;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * General Store → Requisition and Administration (AnnotechERP 10/04–10/07):
 * a department drafts, edits and submits a requisition; the Store Manager
 * approves it line by line or rejects it; the store issues against it, never
 * past what was approved, moving it to Partially Issued and Issued (and back
 * when an issue is cancelled); Settings switch approval and direct issue off
 * and name the vouchers; the Audit Log reads it all.
 */
class GeneralStoreRequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $keeper;

    /** @var array<string, string> */
    private array $stores = [];

    /** @var array<string, string> */
    private array $depts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->stores = collect($this->as()->getJson('/api/v1/erp/store-locations')->json('data'))->pluck('id', 'code')->all();
        $this->depts = collect($this->as()->getJson('/api/v1/erp/store-departments')->json('data'))->pluck('id', 'code')->all();
    }

    public function test_a_requisition_is_drafted_approved_and_issued_against(): void
    {
        $paper = $this->item('ITM-001', 'A4 Paper', 'REAM', 420);
        $pen = $this->item('ITM-009', 'Ball Pen', 'PCS', 5);
        $tape = $this->item('ITM-040', 'Tape', 'ROLL', 30);
        $this->stock([[$paper, 20], [$pen, 100]]);
        $year = now()->format('Y');
        $head = ['req_date' => now()->toDateString(), 'gs_department_id' => $this->depts['MAINT'], 'gs_store_id' => $this->stores['MAIN']];
        $reqs = '/api/v1/general-store/requisitions';

        // The form: an item at least, each with a quantity.
        $this->as($this->keeper)->postJson($reqs, $head + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Pick at least one item.');
        $this->assertSame('Line 1: Required Qty must be a number greater than zero.', $this->as($this->keeper)->postJson($reqs, $head + ['lines' => [['gs_item_id' => $paper, 'quantity' => 0]]])->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $this->as($this->keeper)->postJson($reqs, ['gs_department_id' => null] + $head + ['lines' => [['gs_item_id' => $paper, 'quantity' => 1]]])->assertUnprocessable()->assertJsonPath('errors.gs_department_id.0', 'Department is required.');

        // A draft, edited, then submitted.
        $draft = $this->as($this->keeper)->postJson($reqs, $head + ['reason' => 'Monthly', 'lines' => [['gs_item_id' => $paper, 'quantity' => 5]]])->assertCreated()->json('data');
        $no = $draft['req_no'];
        $this->assertEquals(["REQ-{$year}-000001", 'DRAFT', 20], [$no, $draft['status'], $draft['lines'][0]['in_store']]);
        $this->as()->putJson("{$reqs}/{$no}", $head + ['lines' => [['gs_item_id' => $paper, 'quantity' => 5]]])->assertUnprocessable()->assertJsonPath('errors.requisition.0', "{$no} belongs to someone else.");
        $this->as($this->keeper)->putJson("{$reqs}/{$no}", $head + ['reason' => 'Monthly', 'lines' => [['gs_item_id' => $paper, 'quantity' => 6], ['gs_item_id' => $pen, 'quantity' => 50], ['gs_item_id' => $tape, 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonCount(3, 'data.lines');
        $this->as($this->keeper)->postJson("{$reqs}/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->as($this->keeper)->postJson("{$reqs}/{$no}/submit")->assertUnprocessable()->assertJsonPath('errors.requisition.0', "{$no} is no longer a draft.");
        $this->assertSame([$no], array_column($this->as($this->keeper)->getJson("{$reqs}?list=mine")->json('data.rows'), 'req_no'));
        $this->as($this->keeper)->getJson("{$reqs}?list=pending")->assertForbidden();
        $this->as($this->keeper)->postJson("{$reqs}/{$no}/approve", ['lines' => []])->assertForbidden();

        // Approve: no line above what was asked, not all of them nothing.
        $lines = collect($this->as()->getJson("{$reqs}/{$no}")->json('data.lines'))->pluck('id', 'item_id');
        $approve = fn (array $q, ?string $note = null) => $this->as()->postJson("{$reqs}/{$no}/approve", ['note' => $note, 'lines' => [
            ['line_id' => $lines[$paper], 'approved_qty' => $q[0]], ['line_id' => $lines[$pen], 'approved_qty' => $q[1]], ['line_id' => $lines[$tape], 'approved_qty' => $q[2]],
        ]]);
        $this->assertSame('Line 1: approve no more than the 6 REAM requested.', $approve([7, 50, 0])->assertUnprocessable()->json('errors')['lines.0.approved_qty'][0]);
        $approve([0, 0, 0])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Approve at least one item — or reject the requisition.');
        $approved = $approve([4, 50, 0], 'Tape is out of stock')->assertOk()->json('data');
        $this->assertSame(['APPROVED', 'Tape is out of stock'], [$approved['status'], $approved['approval_note']]);
        $this->assertSame([$no], array_column($this->as()->getJson("{$reqs}?list=approved")->json('data.rows'), 'req_no'));

        // Issue against it: from its store to its department, its approved items only, never past what is left.
        $issuable = $this->as()->getJson("{$reqs}/{$no}/issuable")->assertOk()->json('data');
        $this->assertEquals([[$paper, 4], [$pen, 50]], array_map(fn ($l) => [$l['item_id'], $l['remaining']], $issuable['lines']));
        $this->assertSame("{$no} — Maintenance", collect($this->as()->getJson('/api/v1/general-store/issue/options')->json('data.requisitions'))->sole()['label']);
        $issue = '/api/v1/general-store/issue/documents';
        $form = ['doc_date' => now()->toDateString(), 'gs_store_id' => $this->stores['ELEC'], 'gs_department_id' => $this->depts['IT'], 'requisition' => $no];
        $this->assertSame("Line 1: only 4 REAM of A4 Paper can be issued against {$no}.", $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $paper, 'quantity' => 5, 'unit_price' => 420]]])->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $this->assertSame("Line 1: Tape is not on {$no}.", $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $tape, 'quantity' => 1, 'unit_price' => 30]]])->assertUnprocessable()->json('errors')['lines.0.gs_item_id'][0]);
        $first = $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $paper, 'quantity' => 4, 'unit_price' => 420], ['gs_item_id' => $pen, 'quantity' => 20, 'unit_price' => 5]]])->assertCreated()->json('data');
        $this->assertSame([$no, 'Main Store', 'Maintenance'], [$first['requisition'], $first['store'], $first['department']]);
        $this->assertSame('PARTIAL', $this->as()->getJson("{$reqs}/{$no}")->json('data.status'));
        $second = $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $pen, 'quantity' => 30, 'unit_price' => 5]]])->assertCreated()->json('data');
        $this->assertSame('ISSUED', $this->as()->getJson("{$reqs}/{$no}")->json('data.status'));
        $this->assertSame([$no], array_column($this->as()->getJson("{$reqs}?list=issued")->json('data.rows'), 'req_no'));
        $this->assertSame($no, collect($this->as()->getJson('/api/v1/general-store/issue/documents')->json('data.rows'))->firstWhere('doc_no', $second['doc_no'])['requisition']);
        $this->as()->getJson("{$reqs}/{$no}/issuable")->assertUnprocessable()->assertJsonPath('errors.requisition.0', "{$no} is not approved for issue.");

        // A cancelled issue gives the requisition its quantity back.
        $this->as()->postJson("/api/v1/general-store/documents/{$second['doc_no']}/cancel", ['reason' => 'Wrong pens'])->assertOk();
        $this->assertSame('PARTIAL', $this->as()->getJson("{$reqs}/{$no}")->json('data.status'));
        $this->as()->postJson("{$reqs}/{$no}/cancel", ['reason' => 'x'])->assertUnprocessable()->assertJsonPath('errors.requisition.0', "{$no} can no longer be cancelled.");

        // Reject and cancel close the others; both stay on record.
        $make = fn () => $this->as($this->keeper)->postJson($reqs, $head + ['submit' => true, 'lines' => [['gs_item_id' => $tape, 'quantity' => 1]]])->assertCreated()->json('data.req_no');
        $rejected = $make();
        $this->as()->postJson("{$reqs}/{$rejected}/reject", [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Reason is required.');
        $this->as()->postJson("{$reqs}/{$rejected}/reject", ['reason' => 'Use the old stock'])->assertOk()->assertJsonPath('data.status', 'REJECTED')->assertJsonPath('data.closing_reason', 'Use the old stock');
        $cancelled = $make();
        $this->as($this->keeper)->postJson("{$reqs}/{$cancelled}/cancel", ['reason' => 'Not needed'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        // The audit trail followed every step.
        $summaries = GsAuditEntry::query()->orderBy('created_at')->pluck('summary')->all();
        foreach (["Requisition {$no} saved as draft", "Requisition {$no} submitted", "Requisition {$no} approved", "Requisition {$rejected} rejected: Use the old stock", "Requisition {$cancelled} cancelled"] as $line) {
            $this->assertContains($line, $summaries);
        }
        $this->as()->postJson("{$reqs}/{$no}/printed", ['screen' => 'gs-req-approved'])->assertOk();
        $this->assertSame(['REPORT_EXPORT', 'gs-req-approved'], [GsAuditEntry::query()->latest('created_at')->first()->action, GsAuditEntry::query()->latest('created_at')->first()->screen]);
    }

    public function test_settings_switch_approval_and_direct_issue_and_the_audit_log_reads_it_all(): void
    {
        $paper = $this->item('ITM-001', 'A4 Paper', 'REAM', 420);
        $this->stock([[$paper, 10]]);
        $settings = $this->as()->getJson('/api/v1/general-store/settings')->assertOk()->json('data');
        $this->assertSame([true, true, false, 'Delta Apparels Ltd'], [$settings['requisition_approval'], $settings['direct_issue'], $settings['receiver_required'], $settings['company_name']]);
        $this->assertSame(['keeper@delta.test'], array_column(collect($settings['roles'])->firstWhere('role', 'STOREKEEPER')['people'], 'email'));
        $this->as($this->keeper)->getJson('/api/v1/general-store/settings')->assertForbidden();

        $this->as()->putJson('/api/v1/general-store/settings', ['requisition_approval' => false, 'direct_issue' => false, 'receiver_required' => true, 'voucher_name' => 'Delta Store, Unit 1', 'voucher_footer' => 'Received in good order'])
            ->assertOk()->assertJsonPath('data.voucher_name', 'Delta Store, Unit 1');
        $this->assertSame(['name' => 'Delta Store, Unit 1', 'footer' => 'Received in good order'], $this->as()->getJson('/api/v1/general-store/issue/options')->json('data.voucher'));

        // Approval off: a submitted requisition is approved as asked.
        $req = $this->as($this->keeper)->postJson('/api/v1/general-store/requisitions', ['req_date' => now()->toDateString(), 'gs_department_id' => $this->depts['IT'], 'gs_store_id' => $this->stores['MAIN'], 'submit' => true, 'lines' => [['gs_item_id' => $paper, 'quantity' => 3]]])
            ->assertCreated()->json('data');
        $this->assertEquals(['APPROVED', 3], [$req['status'], $req['lines'][0]['approved']]);

        // Direct issue off: only against a requisition; and the receiver must be named.
        $issue = '/api/v1/general-store/issue/documents';
        $form = ['doc_date' => now()->toDateString(), 'gs_store_id' => $this->stores['MAIN'], 'gs_department_id' => $this->depts['IT'], 'lines' => [['gs_item_id' => $paper, 'quantity' => 1, 'unit_price' => 420]]];
        $this->as()->postJson($issue, $form + ['person' => 'Rahim'])->assertUnprocessable()->assertJsonPath('errors.requisition.0', 'Issue against an approved requisition — direct issue is switched off in Settings.');
        $this->as()->postJson($issue, $form + ['requisition' => $req['req_no']])->assertUnprocessable()->assertJsonPath('errors.person.0', 'Employee / Receiver is required.');
        $this->as()->postJson($issue, $form + ['requisition' => $req['req_no'], 'person' => 'Rahim'])->assertCreated();

        // The Audit Log: newest first, searched, as Excel; the store manager's, not the storekeeper's.
        $log = $this->as()->getJson('/api/v1/general-store/audit-log')->assertOk()->json('data.rows');
        $this->assertSame(['CREATE', 'gs-issue'], [$log[0]['action'], $log[0]['screen']]);
        $this->assertContains('Item ITM-001 — A4 Paper', array_column($log, 'summary'));
        $this->assertContains('Settings updated: requisition_approval, direct_issue, receiver_required, voucher_name, voucher_footer', array_column($log, 'summary'));
        $this->assertCount(1, $this->as()->getJson('/api/v1/general-store/audit-log?q=settings')->json('data.rows'));
        $this->as()->get('/api/v1/general-store/audit-log/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->as($this->keeper)->getJson('/api/v1/general-store/audit-log')->assertForbidden();
    }

    /** @param list<array{0: string, 1: float}> $lines */
    private function stock(array $lines): void
    {
        $this->as()->postJson('/api/v1/general-store/opening/documents', ['doc_date' => now()->toDateString(), 'gs_store_id' => $this->stores['MAIN'], 'lines' => array_map(fn ($l) => ['gs_item_id' => $l[0], 'quantity' => $l[1]], $lines)])->assertCreated();
    }

    private function item(string $code, string $name, string $unit, float $price): string
    {
        $cat = collect($this->as()->getJson('/api/v1/erp/store-categories')->json('data'))->firstWhere('code', 'STAT')['id'];
        $uom = collect($this->as()->getJson('/api/v1/erp/store-units')->json('data'))->firstWhere('code', $unit)['id'];

        return $this->as()->postJson('/api/v1/erp/store-items', ['item_code' => $code, 'name' => $name, 'gs_category_id' => $cat, 'gs_unit_id' => $uom, 'default_price' => $price])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
