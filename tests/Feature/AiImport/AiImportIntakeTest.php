<?php

declare(strict_types=1);

namespace Tests\Feature\AiImport;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * AI File & PO Import → Intake (AnnotechERP 26/01): the intake (a file by
 * name or uploaded — its size from the upload — received today, read only
 * once recognised), recognition signatures (named once, any buyer, matched
 * never in the future), extraction rules (table.column, one rule per column
 * within a family), validations, and approval steps (a new step goes last in
 * its family, each place taken once); and who may.
 */
class AiImportIntakeTest extends TestCase
{
    use RefreshDatabase;

    private const E = '/api/v1/erp';

    private Company $delta;

    private User $owner;

    private string $hm;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $this->hm = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'HM', 'name' => 'H&M Ltd', 'short_name' => 'H&M', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
    }

    public function test_intake(): void
    {
        $this->assertSame([['value' => $this->hm, 'label' => 'H&M']], $this->as()->getJson('/api/v1/ai-import/options')->assertOk()->json('data.buyers'));
        $this->as()->postJson(self::E.'/ai-intake-files', [])->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.v.file'));
        $typed = $this->as()->postJson(self::E.'/ai-intake-files', ['file' => ' primark-po-88412.pdf ', 'arrived_by' => 'EMAIL', 'family' => 'PURCHASE_ORDER', 'size_mb' => 1.4, 'confidence' => 96.2])->assertCreated()->json('data');
        $this->assertEquals(['primark-po-88412.pdf', 'primark-po-88412.pdf', null, 'QUEUED', '2026-11-11', 1.4, 96.2, null], [$typed['file'], $typed['file_name'], $typed['file_url'], $typed['stage'], $typed['received_on'], $typed['size_mb'], $typed['confidence'], $typed['buyer']]);
        $this->as()->postJson(self::E.'/ai-intake-files', ['file' => 'x.pdf', 'received_on' => '2026-11-12'])->assertUnprocessable()->assertJsonPath('errors.received_on.0', __('aiimport.v.future'));
        $this->as()->postJson(self::E.'/ai-intake-files', ['file' => 'x.pdf', 'confidence' => 101])->assertUnprocessable()->assertJsonValidationErrors(['confidence']);
        $this->as()->postJson(self::E.'/ai-intake-files', ['file' => 'x.pdf', 'arrived_by' => 'FAX'])->assertUnprocessable()->assertJsonValidationErrors(['arrived_by']);

        // A file is read only once it is recognised — from either side.
        $this->as()->postJson(self::E.'/ai-intake-files', ['file' => 'scan_0004.jpg', 'stage' => 'EXTRACTING'])->assertUnprocessable()->assertJsonPath('errors.stage.0', __('aiimport.v.unrecognised'));
        $scan = $this->as()->postJson(self::E.'/ai-intake-files', ['file' => 'scan_0004.jpg', 'family' => 'UNRECOGNISED', 'stage' => 'FAILED', 'confidence' => 24.6])->assertCreated()->json('data');
        $this->as()->patchJson(self::E."/ai-intake-files/{$scan['id']}", ['stage' => 'POSTED'])->assertUnprocessable()->assertJsonPath('errors.stage.0', __('aiimport.v.unrecognised'));
        $this->as()->patchJson(self::E."/ai-intake-files/{$typed['id']}", ['stage' => 'POSTED', 'buyer_id' => $this->hm])->assertOk()->assertJsonPath('data.stage', 'POSTED')->assertJsonPath('data.buyer', 'H&M');
        $this->as()->patchJson(self::E."/ai-intake-files/{$typed['id']}", ['family' => 'UNRECOGNISED'])->assertUnprocessable()->assertJsonPath('errors.family.0', __('aiimport.v.unrecognised'));

        // An uploaded file: its own name, its own size; any other path is refused.
        $upload = $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->image('hm-winter26-techpack-r3.png', 800, 600)->size(2300)], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame('hm-winter26-techpack-r3.png', $upload['name']);
        $file = $this->as()->postJson(self::E.'/ai-intake-files', ['file' => $upload['path'], 'family' => 'TECH_PACK', 'size_mb' => 99, 'buyer_id' => $this->hm, 'arrived_by' => 'PORTAL'])->assertCreated()->json('data');
        $this->assertEquals(['hm-winter26-techpack-r3.png', $upload['path'], $upload['size_mb']], [$file['file_name'], $file['file_url'], $file['size_mb']]);
        $this->assertNotEquals(99, $file['size_mb']);
        $this->as()->get(str_replace('/api/', '/api/v1/', $upload['path']))->assertRedirect();
        $tiny = $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->image('tiny.png', 4, 4)], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertEquals(0.1, $this->as()->postJson(self::E.'/ai-intake-files', ['file' => $tiny['path']])->assertCreated()->json('data.size_mb'));
        $this->as()->postJson(self::E.'/ai-intake-files', ['file' => '/api/files/01ARZ3NDEKTSV4RRFFQ69G5FAV/download'])->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.v.file_upload'));
        $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->create('huge.pdf', 52000)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.v.file_size'));
    }

    public function test_signatures_rules_and_validations(): void
    {
        $this->as()->postJson(self::E.'/ai-signatures', [])->assertUnprocessable()->assertJsonPath('errors.name.0', __('aiimport.v.signature'));
        $s = $this->as()->postJson(self::E.'/ai-signatures', ['name' => ' H&M PO header block ', 'family' => 'PURCHASE_ORDER', 'buyer_id' => $this->hm, 'marker' => 'HEADER_TEXT', 'hit_rate' => 98.4, 'times_matched' => 1240, 'last_matched' => '2026-11-10'])->assertCreated()->json('data');
        $this->assertEquals(['H&M PO header block', 'H&M', 98.4, 1240, true], [$s['name'], $s['buyer'], $s['hit_rate'], $s['times_matched'], $s['enabled']]);
        $this->as()->postJson(self::E.'/ai-signatures', ['name' => 'h&m po header BLOCK'])->assertUnprocessable()->assertJsonPath('errors.name.0', __('aiimport.v.signature_taken'));
        $any = $this->as()->postJson(self::E.'/ai-signatures', ['name' => 'Generic BOM sheet', 'family' => 'BOM', 'marker' => 'SHEET_NAME'])->assertCreated()->json('data');
        $this->assertNull($any['buyer']);
        $this->as()->postJson(self::E.'/ai-signatures', ['name' => 'X', 'last_matched' => '2026-11-12'])->assertUnprocessable()->assertJsonPath('errors.last_matched.0', __('aiimport.v.future'));
        $this->as()->postJson(self::E.'/ai-signatures', ['name' => 'X', 'family' => 'UNRECOGNISED'])->assertUnprocessable()->assertJsonValidationErrors(['family']);
        $this->as()->patchJson(self::E."/ai-signatures/{$s['id']}", ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
        $this->as()->patchJson(self::E."/ai-signatures/{$s['id']}", ['name' => 'H&M PO header block', 'hit_rate' => 97])->assertOk()->assertJsonPath('data.hit_rate', 97);

        // Extraction rules: table.column, lower case, one rule per column within a family.
        $this->as()->postJson(self::E.'/ai-extraction-rules', [])->assertUnprocessable()->assertJsonPath('errors.field.0', __('aiimport.v.field'));
        $r = $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'Order quantity', 'family' => 'PURCHASE_ORDER', 'found_by' => 'TABLE_COLUMN', 'erp_column' => ' Order_Line.Quantity ', 'data_type' => 'NUMBER', 'accuracy' => 97.8])->assertCreated()->json('data');
        $this->assertEquals(['order_line.quantity', 'NUMBER', false], [$r['erp_column'], $r['data_type'], $r['mandatory']]);
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'Qty', 'family' => 'PURCHASE_ORDER', 'erp_column' => 'order_line.quantity'])->assertUnprocessable()->assertJsonPath('errors.erp_column.0', __('aiimport.v.column_taken'));
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'Consumption', 'family' => 'BOM', 'erp_column' => 'order_line.quantity'])->assertCreated();
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'X', 'erp_column' => 'quantity'])->assertUnprocessable()->assertJsonPath('errors.erp_column.0', __('aiimport.v.erp_column'));
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'X', 'family' => 'ARTWORK'])->assertUnprocessable()->assertJsonValidationErrors(['family']);
        $this->as()->patchJson(self::E."/ai-extraction-rules/{$r['id']}", ['mandatory' => true])->assertOk()->assertJsonPath('data.mandatory', true);

        // Validations.
        $this->as()->postJson(self::E.'/ai-validations', [])->assertUnprocessable()->assertJsonPath('errors.name.0', __('aiimport.v.validation'));
        $v = $this->as()->postJson(self::E.'/ai-validations', ['name' => 'Buyer must exist in the buyer master', 'kind' => 'MASTER_LOOKUP', 'on_failure' => 'BLOCK', 'triggered' => 1840, 'pass_rate' => 98.9])->assertCreated()->json('data');
        $this->assertEquals(['EVERY', 'BLOCK', true], [$v['applies_to'], $v['on_failure'], $v['enabled']]);
        $this->as()->postJson(self::E.'/ai-validations', ['name' => 'X', 'on_failure' => 'IGNORE'])->assertUnprocessable()->assertJsonValidationErrors(['on_failure']);
        $this->as()->postJson(self::E.'/ai-validations', ['name' => 'X', 'applies_to' => 'ARTWORK'])->assertUnprocessable()->assertJsonValidationErrors(['applies_to']);
        $this->as()->patchJson(self::E."/ai-validations/{$v['id']}", ['pass_rate' => 120])->assertUnprocessable()->assertJsonValidationErrors(['pass_rate']);
    }

    public function test_approval_steps(): void
    {
        $this->as()->postJson(self::E.'/ai-approval-steps', [])->assertUnprocessable()->assertJsonPath('errors.name.0', __('aiimport.v.step'));
        $one = $this->as()->postJson(self::E.'/ai-approval-steps', ['name' => 'Merchandiser confirms quantities', 'applies_to' => 'PURCHASE_ORDER', 'approver' => 'MERCHANDISER', 'auto_skip' => 97, 'sla_hours' => 8, 'pending' => 6])->assertCreated()->json('data');
        $two = $this->as()->postJson(self::E.'/ai-approval-steps', ['name' => 'Planning confirms the ship date', 'applies_to' => 'PURCHASE_ORDER', 'approver' => 'PLANNING'])->assertCreated()->json('data');
        $cost = $this->as()->postJson(self::E.'/ai-approval-steps', ['name' => 'Costing signs off the rates', 'applies_to' => 'COST_SHEET'])->assertCreated()->json('data');
        $this->assertSame([1, 2, 1], [$one['sequence'], $two['sequence'], $cost['sequence']]);
        $this->assertEquals([97, 8, 6, true], [$one['auto_skip'], $one['sla_hours'], $one['pending'], $one['enabled']]);
        // Each place in a family's order is taken once — checked when the family moves too.
        $this->as()->postJson(self::E.'/ai-approval-steps', ['name' => 'X', 'applies_to' => 'PURCHASE_ORDER', 'sequence' => 2])->assertUnprocessable()->assertJsonPath('errors.sequence.0', __('aiimport.v.sequence_taken', ['n' => 2]));
        $this->as()->patchJson(self::E."/ai-approval-steps/{$cost['id']}", ['applies_to' => 'PURCHASE_ORDER'])->assertUnprocessable()->assertJsonPath('errors.applies_to.0', __('aiimport.v.sequence_taken', ['n' => 1]));
        $this->as()->patchJson(self::E."/ai-approval-steps/{$cost['id']}", ['applies_to' => 'PURCHASE_ORDER', 'sequence' => 3])->assertOk()->assertJsonPath('data.sequence', 3);
        // Left blank on an edit, a step keeps its place.
        $this->as()->patchJson(self::E."/ai-approval-steps/{$two['id']}", ['sequence' => null, 'pending' => 3])->assertOk()->assertJsonPath('data.sequence', 2);
        $this->as()->postJson(self::E.'/ai-approval-steps', ['name' => 'X', 'sla_hours' => 0])->assertUnprocessable()->assertJsonValidationErrors(['sla_hours']);
        $this->as()->postJson(self::E.'/ai-approval-steps', ['name' => 'X', 'approver' => 'CEO'])->assertUnprocessable()->assertJsonValidationErrors(['approver']);
        $this->as()->patchJson(self::E."/ai-approval-steps/{$one['id']}", ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/ai-intake-files')->assertOk();
        $this->as($auditor)->getJson('/api/v1/ai-import/options')->assertOk();
        $this->as($auditor)->postJson(self::E.'/ai-signatures', ['name' => 'X'])->assertForbidden();
        $this->as($auditor)->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertForbidden();
        $this->as($manager)->postJson(self::E.'/ai-validations', ['name' => 'X'])->assertCreated();
        $upload = $this->as($manager)->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->image('z.png')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->as($auditor)->get(str_replace('/api/', '/api/v1/', $upload['path']))->assertRedirect();
        $this->as($keeper)->getJson(self::E.'/ai-approval-steps')->assertForbidden();
        $this->as($keeper)->getJson('/api/v1/ai-import/options')->assertForbidden();
        $this->as($keeper)->get(str_replace('/api/', '/api/v1/', $upload['path']), ['Accept' => 'application/json'])->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/ai-validations')->assertOk()->json('data'));
        $this->as($stranger, $other)->postJson(self::E.'/ai-signatures', ['name' => 'X', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);
        // Nor is another company's upload theirs to put in an intake.
        $this->as($stranger, $other)->postJson(self::E.'/ai-intake-files', ['file' => $upload['path']])->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.v.file_upload'));
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
