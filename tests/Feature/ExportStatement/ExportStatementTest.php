<?php

declare(strict_types=1);

namespace Tests\Feature\ExportStatement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\ExportStatement\Models\EsEditRequest;
use App\Modules\ExportStatement\Models\ExportStatement;
use App\Modules\ExportStatement\Support\EsApproval;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Export Statement (AnnotechERP 35, Norban's MD Sir Export Status): new
 * entries saved at once with Norban's ⚡ columns worked out, one Company +
 * Invoice No. per entry, an edit saved at once for an approver and else held
 * for the Approval Center (approved → written, rejected / withdrawn → as it
 * was, someone else's edit of a waiting entry refused), Excel both ways, and
 * the Dashboard's KPI cards, and its notifications.
 */
class ExportStatementTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $merchant;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        // Commercial staff: enter and edit statements; their edits wait for approval.
        Role::query()->whereNull('company_id')->where('name', 'VIEWER')->firstOrFail()->givePermissionTo(['exportstatement.view', 'exportstatement.create', 'exportstatement.update', 'exportstatement.import']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->merchant = TenantFixture::user($this->delta, 'VIEWER', 'commercial@delta.test');
        $this->other = TenantFixture::user($this->delta, 'VIEWER', 'commercial2@delta.test');
        TenantFixture::factory($this->delta, 'Norban Comtex Ltd', 'NCL');
        TenantFixture::factory($this->delta, 'Hornbill Apparel Ltd', 'HAL');
        Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'NAK', 'name' => 'Nakata', 'short_name' => 'Nakata', 'pi_address' => 'JP', 'registered_from' => '2026-01-01', 'active' => true]);
        DB::table('tx_banks')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'code' => 'MTBL', 'name' => 'MUTUAL TRUST BANK LIMITED', 'is_local' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_new_entry_is_saved_at_once_with_norbans_worked_out_columns(): void
    {
        $meta = $this->api($this->merchant)->getJson('/api/v1/export-statement/meta')->assertOk()->json('data');
        $this->assertSame(['HAL', 'NCL'], array_column($meta['sources']['factories'], 'value'));
        $this->assertFalse($meta['can']['approve']);

        $row = $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry()]]])->assertCreated()->json('data.rows.0');
        $this->assertSame(1, $row['serial_no']);
        $this->assertSame('CREATED', $row['status']);
        $v = $row['values'];
        $this->assertEqualsWithDelta(48532.98, $v['invoice_value_net'], 0.001); // 50,034.00 − 1,501.02
        $this->assertSame('2026-10-03', $v['max_docs_submission_date']); // BL 23-09 + 10 days
        $this->assertEquals(-4, $v['docs_submission_delay_days']); // submitted 29-09, 4 days early
        $this->assertEqualsWithDelta(-48532.98, $v['shortage_over_payment'], 0.001);
        $this->assertSame('Not realized', $v['claim_days_remaining']);
        $this->assertSame('Created', $v['status']);

        // One Company + Invoice No. is one entry; Company and Invoice No. are needed.
        $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry(['invoice_no' => 'ncl-ex-26129'])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.invoice_no');
        $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry(['invoice_no' => 'X-1'])], ['values' => $this->entry(['invoice_no' => 'X-1'])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.1.values.invoice_no');
        $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry(['company_name' => null, 'invoice_no' => 'X-2'])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values');
        // The same invoice number of another company is another entry; a value not in its list is refused.
        $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry(['company_name' => 'HAL'])]]])->assertCreated();
        $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry(['invoice_no' => 'X-3', 'ship_mode' => 'Rail'])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.ship_mode');
    }

    public function test_an_edit_waits_for_approval_unless_the_editor_may_approve(): void
    {
        $row = $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [['values' => $this->entry()]]])->json('data.rows.0');
        $edit = fn (User $u, array $row, array $values) => $this->api($u)->patchJson('/api/v1/export-statement/statements', ['rows' => [['id' => $row['id'], 'version' => $row['version'], 'values' => $values]]]);

        // Nobody signs the chain's step yet: the edit cannot be sent.
        $edit($this->merchant, $row, ['eta' => '2026-11-12'])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values');
        $rule = ApprovalRule::query()->whereHas('workflow', fn ($q) => $q->where('entity_type', EsApproval::ENTITY))->firstOrFail();
        $rule->forceFill(['user_id' => $this->owner->id])->save();

        // Sent for approval: the entry keeps its values, Pending Approval.
        $res = $edit($this->merchant, $row, ['eta' => '2026-11-12', 'realized_value' => 48000])->assertOk()->json('data');
        $this->assertSame(1, $res['pending']);
        $this->assertNotNull($res['notice']);
        $row = $res['rows'][0];
        $this->assertSame('PENDING_APPROVAL', $row['status']);
        $this->assertSame('2026-10-25', $row['values']['eta']);
        // Someone else may not edit it meanwhile.
        $edit($this->other, $row, ['remarks' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values');

        $list = $this->api($this->owner)->getJson('/api/v1/export-statement/approvals')->assertOk()->json('data.pending');
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['can_act']);
        $this->assertSame(['25-10-2026', '12-11-2026'], [collect($list[0]['changes'])->firstWhere('key', 'eta')['old'], collect($list[0]['changes'])->firstWhere('key', 'eta')['new']]);
        $this->assertFalse($this->api($this->merchant)->getJson('/api/v1/export-statement/approvals')->json('data.pending.0.can_act'));
        $this->api($this->merchant)->postJson("/api/v1/export-statement/edits/{$list[0]['id']}/decision", ['action' => 'approve'])->assertUnprocessable();

        // Approved: the new values, the formulas worked out again.
        $this->api($this->owner)->postJson("/api/v1/export-statement/edits/{$list[0]['id']}/decision", ['action' => 'approve', 'comment' => 'OK'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $s = ExportStatement::query()->firstOrFail();
        $this->assertSame('APPROVED', $s->status);
        $this->assertSame('2026-11-12', $s->values['eta']);
        $this->assertEqualsWithDelta(-532.98, $s->values['shortage_over_payment'], 0.001);
        $this->assertSame($this->owner->id, $s->approved_by);

        // Rejected: as it was, status included.
        $row = $this->api($this->owner)->getJson('/api/v1/export-statement/statements')->json('data.rows.0');
        $edit($this->merchant, $row, ['remarks' => 'Docs at bank'])->assertOk()->assertJsonPath('data.pending', 1);
        $id = EsEditRequest::query()->where('status', 'PENDING')->value('id');
        $this->api($this->owner)->postJson("/api/v1/export-statement/edits/{$id}/decision", ['action' => 'reject', 'comment' => 'Wrong'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $s->refresh();
        $this->assertArrayNotHasKey('remarks', $s->values);
        $this->assertSame('APPROVED', $s->status);

        // Withdrawn by the editor (only them), or by putting the values back.
        $row = $this->api($this->owner)->getJson('/api/v1/export-statement/statements')->json('data.rows.0');
        $row = $edit($this->merchant, $row, ['remarks' => 'Again'])->json('data.rows.0');
        $id = EsEditRequest::query()->where('status', 'PENDING')->value('id');
        $this->api($this->other)->postJson("/api/v1/export-statement/edits/{$id}/withdraw")->assertUnprocessable();
        $this->api($this->merchant)->postJson("/api/v1/export-statement/edits/{$id}/withdraw")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame('APPROVED', $s->fresh()->status);

        // The approver's own edit is saved at once.
        $row = $this->api($this->owner)->getJson('/api/v1/export-statement/statements')->json('data.rows.0');
        $res = $edit($this->owner, $row, ['prc_status' => 'Received'])->assertOk()->json('data');
        $this->assertSame(0, $res['pending']);
        $this->assertSame('Received', $res['rows'][0]['values']['prc_status']);
    }

    public function test_excel_both_ways_and_the_dashboard(): void
    {
        $this->api($this->owner)->postJson('/api/v1/export-statement/statements', ['rows' => [
            ['values' => $this->entry()],
            ['values' => $this->entry(['invoice_no' => 'NCL-EX-26130', 'realized_value' => 50000, 'realized_date' => now('Asia/Dhaka')->subDays(10)->toDateString(), 'due_payment' => 'PAID', 'ship_mode' => 'Air'])],
        ]])->assertCreated();

        $res = $this->api($this->owner)->get('/api/v1/export-statement/statements/export');
        $res->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'es').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $ws = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('Company Name', $ws->getCell('B1')->getValue());
        $this->assertSame('00FF00', $ws->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('NCL-EX-26129', $ws->getCell('F2')->getValue());

        // The book back: nothing changed. One cell changed and a new row: an update (saved at once for the owner) and a new entry.
        $import = fn (User $u) => $this->api($u)->post('/api/v1/export-statement/statements/import', ['file' => new UploadedFile($path, 'es.xlsx', null, null, true)]);
        $import($this->owner)->assertOk()->assertJsonPath('data.unchanged', 2)->assertJsonPath('data.created', 0);
        $ws->setCellValue('A4', 3)->setCellValue('B4', 'HAL')->setCellValue('F4', 'HAL-EX-1');
        $headings = array_map(fn ($c) => $ws->getCell([$c, 1])->getValue(), range(1, 25));
        $remarks = array_search('PO No.', $headings, true) + 1;
        $ws->setCellValue([$remarks, 2], 'PO-NEW');
        IOFactory::createWriter($ws->getParent(), 'Xlsx')->save($path);
        $out = $import($this->owner)->assertOk()->json('data');
        $this->assertSame([1, 1, 0], [$out['created'], $out['updated'], $out['sent_for_approval']]);
        $this->assertSame('PO-NEW', ExportStatement::query()->where('serial_no', 1)->first()->values['po_no']);

        // A merchant's import of a changed cell: sent for approval.
        EsApproval::workflow($this->delta->id);
        ApprovalRule::query()->whereHas('workflow', fn ($q) => $q->where('entity_type', EsApproval::ENTITY))->firstOrFail()->forceFill(['user_id' => $this->owner->id])->save();
        $ws->setCellValue([$remarks, 2], 'PO-AGAIN');
        IOFactory::createWriter($ws->getParent(), 'Xlsx')->save($path);
        $this->assertSame(1, $import($this->merchant)->assertOk()->json('data.sent_for_approval'));

        $d = $this->api($this->owner)->getJson('/api/v1/export-statement/dashboard')->assertOk()->json('data');
        $this->assertSame(3, $d['total']);
        $this->assertSame(1, $d['pending_approval']);
        $this->assertEqualsWithDelta(48532.98 * 2, $d['payment']['total_net'], 0.01); // the imported HAL row has no amounts
        $this->assertSame(1, $d['payment']['realized_count']);
        $this->assertSame(1, $d['payment']['due_count']);
        $this->assertSame(1, $d['incentive']['open']);
        $this->assertGreaterThan(0, $d['quantity']['air_pct']);
    }

    public function test_saves_are_told_to_the_heads_and_author_and_due_documents_and_claims_each_morning(): void
    {
        $notes = fn (User $u, string $event) => Notification::withoutGlobalScope(TenantScope::class)->where('user_id', $u->id)->where('event_type', $event)->orderBy('created_at')->orderBy('id')->get();
        $day = fn (int $n) => now('Asia/Dhaka')->addDays($n)->toDateString();

        $rows = $this->api($this->merchant)->postJson('/api/v1/export-statement/statements', ['rows' => [
            ['values' => $this->entry(['invoice_no' => 'DOCS-SOON', 'bl_date' => $day(-9), 'docs_submission_date' => null])],
            ['values' => $this->entry(['invoice_no' => 'DOCS-LATE', 'bl_date' => $day(-15), 'docs_submission_date' => null])],
            ['values' => $this->entry(['invoice_no' => 'CLAIM-SOON', 'realized_value' => 48532.98, 'realized_date' => $day(-160)])],
            ['values' => $this->entry(['invoice_no' => 'ALL-DONE', 'realized_value' => 48532.98, 'realized_date' => $day(-160), 'cash_incentive_claim_date' => $day(-5)])],
        ]])->assertCreated()->json('data.rows');
        // The head (an approver) hears of the new invoices, once; the author does not hear of their own save.
        $heard = $notes($this->owner, 'ES_STATEMENTS_CHANGED');
        $this->assertCount(1, $heard);
        $this->assertSame(4, $heard[0]->data_json['n']);
        $this->assertSame('DOCS-SOON, DOCS-LATE, CLAIM-SOON +1', $heard[0]->data_json['invoices']);
        $this->assertStringContainsString('added 4 invoice(s)', $heard[0]->title);
        $this->assertCount(0, $notes($this->merchant, 'ES_STATEMENTS_CHANGED'));
        $this->assertCount(0, $notes($this->other, 'ES_STATEMENTS_CHANGED'));

        // The head changes the author's entry at once: the author hears it.
        $this->api($this->owner)->patchJson('/api/v1/export-statement/statements', ['rows' => [['id' => $rows[0]['id'], 'version' => $rows[0]['version'], 'values' => ['remarks' => 'Docs with courier']]]])->assertOk();
        $mine = $notes($this->merchant, 'ES_STATEMENTS_CHANGED');
        $this->assertCount(1, $mine);
        $this->assertSame(['DOCS-SOON', 1], [$mine[0]->data_json['invoices'], $mine[0]->data_json['cells']]);

        // An edit waiting for approval is the Approval Center's to tell — the signer, then the editor; nothing more here.
        EsApproval::workflow($this->delta->id);
        ApprovalRule::query()->whereHas('workflow', fn ($q) => $q->where('entity_type', EsApproval::ENTITY))->firstOrFail()->forceFill(['user_id' => $this->owner->id])->save();
        $this->api($this->other)->patchJson('/api/v1/export-statement/statements', ['rows' => [['id' => $rows[1]['id'], 'version' => $rows[1]['version'], 'values' => ['eta' => $day(5)]]]])->assertOk()->assertJsonPath('data.pending', 1);
        $this->assertCount(1, $notes($this->owner, 'APPROVAL_REQUESTED'));
        $id = EsEditRequest::query()->where('status', 'PENDING')->value('id');
        $this->api($this->owner)->postJson("/api/v1/export-statement/edits/{$id}/decision", ['action' => 'approve'])->assertOk();
        $this->assertCount(1, $notes($this->other, 'APPROVAL_DECIDED'));
        $this->assertCount(1, $notes($this->owner, 'ES_STATEMENTS_CHANGED'));
        $this->assertCount(1, $notes($this->merchant, 'ES_STATEMENTS_CHANGED'));

        // The morning: documents late and due tomorrow, a claim window closing in 20 days — to those who keep the statements.
        $this->artisan('erp:notify-pending-work', ['--company' => 'DAL'])->assertSuccessful();
        $due = $notes($this->merchant, 'ES_PENDING_WORK');
        $this->assertCount(1, $due);
        $this->assertSame([1, 1, 1, 'DOCS-LATE, DOCS-SOON, CLAIM-SOON'], [$due[0]->data_json['docs_overdue'], $due[0]->data_json['docs_soon'], $due[0]->data_json['claim_soon'], $due[0]->data_json['invoices']]);
        $this->assertSame('CRITICAL', $due[0]->severity);
        $this->assertCount(1, $notes($this->owner, 'ES_PENDING_WORK'));
    }

    /** @return array<string, mixed> Norban's entry 29 */
    private function entry(array $over = []): array
    {
        return array_merge([
            'company_name' => 'NCL', 'job_no' => '19/2026', 'buyer_name' => 'Nakata', 'ex_factory_date' => '2026-09-22', 'invoice_no' => 'NCL-EX-26129', 'invoice_date' => '2026-09-22',
            'exp_no' => '1918-122395-2026', 'po_no' => 'PO-579457', 'export_qty' => 9300, 'invoice_value_gross' => 50034, 'commission' => 1501.02,
            'bl_no' => 'BL-51056493', 'bl_date' => '2026-09-23', 'eta' => '2026-10-25', 'docs_submission_date' => '2026-09-29', 'due_payment' => 'DUE',
            'shippers_bank_name' => 'MUTUAL TRUST BANK LIMITED', 'sc_lc_no' => 'NCL/NAK/019/2026', 'incoterms' => 'CNF (CFR)', 'ship_mode' => 'Sea',
        ], $over);
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
