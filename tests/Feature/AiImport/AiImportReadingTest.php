<?php

declare(strict_types=1);

namespace Tests\Feature\AiImport;

use App\Modules\AiCore\Models\AiFeature;
use App\Modules\AiCore\Models\AiService;
use App\Modules\AiCore\Services\AiException;
use App\Modules\AiCore\Services\DocumentParts;
use App\Modules\AiCore\Services\OpenAiClient;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Support\MerchAi\ClaudeWriter;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\TenantFixture;
use Tests\TestCase;
use ZipArchive;

/**
 * AI File & PO Import → Intake, read by the AI service Settings names (the
 * user's decisions of 2026-11-12): an uploaded file is read as soon as it is
 * saved (or when Read with AI is pressed); the answer sets the family, buyer,
 * confidence and summary and stores the values the family's rules ask for;
 * then the file fails (not recognised, the service failed or declined),
 * waits for a person (no buyer, a mandatory value missing) or goes to the
 * Approval Center — approved there it is posted, rejected it fails; with no
 * approval workflow it is posted at once. E-mails, Word, CSV and workbooks
 * are read into text. The Merchandise order analysis uses the same settings.
 */
class AiImportReadingTest extends TestCase
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
        $this->hm = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'HM', 'name' => 'H&M Hennes & Mauritz', 'short_name' => 'H&M', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        // What a PO is read for.
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'Order number', 'family' => 'PURCHASE_ORDER', 'erp_column' => 'order_header.order_no', 'data_type' => 'CODE', 'mandatory' => true])->assertCreated();
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'Order quantity', 'family' => 'PURCHASE_ORDER', 'erp_column' => 'order_line.quantity', 'data_type' => 'NUMBER', 'mandatory' => true])->assertCreated();
        $this->as()->postJson(self::E.'/ai-extraction-rules', ['field' => 'Ship date', 'family' => 'PURCHASE_ORDER', 'erp_column' => 'order_line.ship_date', 'data_type' => 'DATE'])->assertCreated();
        $this->as()->postJson(self::E.'/ai-signatures', ['name' => 'H&M PO header block', 'family' => 'PURCHASE_ORDER', 'buyer_id' => $this->hm, 'times_matched' => 10])->assertCreated();
    }

    public function test_an_upload_is_read_checked_and_posted(): void
    {
        $this->openai();
        Http::fake([OpenAiClient::URL => Http::response($this->answer('PURCHASE_ORDER', 'h&m', 94.6, [['order_header.order_no', 'PO-88412', 99], ['order_line.quantity', '12000', 97.5], ['order_line.ship_date', '2027-01-15', 90], ['style.colour', 'Navy', 80]]))]);

        $file = $this->drop('hm-po-88412.pdf');
        $this->assertEquals(['POSTED', 'PURCHASE_ORDER', 'H&M', 94.6, 'OpenAI · gpt-test', 'NOT_REQUIRED', null], [$file['stage'], $file['family'], $file['buyer'], $file['confidence'], $file['read_by'], $file['approval_status'], $file['read_note']]);
        $this->assertSame('An H&M purchase order.', $file['summary']);
        // Only the family's own rules are kept; a column no rule asks for is dropped.
        $this->assertSame("Order number: PO-88412 (99.0%)\nOrder quantity: 12000 (97.5%)\nShip date: 2027-01-15 (90.0%)", $file['values_text']);
        $this->assertSame(11, DB::table('ai_signatures')->value('times_matched'));
        $this->assertSame('2026-11-11', (string) DB::table('ai_signatures')->value('last_matched'));
        // The PDF went to OpenAI as a file, with the buyers and the PO's fields in the instructions.
        Http::assertSent(fn (Request $r) => $r['input'][0]['content'][0]['type'] === 'input_file' && str_starts_with($r['input'][0]['content'][0]['file_data'], 'data:application/pdf;base64,')
            && str_contains($r['instructions'], 'H&M') && str_contains($r['instructions'], 'order_header.order_no: Order number (CODE, mandatory)'));
        // Read again is refused once posted.
        $this->as()->postJson("/api/v1/ai-import/intake/{$file['id']}/read")->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.read.settled'));
    }

    public function test_the_approval_center_decides(): void
    {
        $this->openai();
        $workflow = ApprovalWorkflow::create(['company_id' => $this->delta->id, 'name' => 'Imported files', 'entity_type' => 'IMPORT_FILE', 'active' => true]);
        ApprovalRule::create(['company_id' => $this->delta->id, 'workflow_id' => $workflow->id, 'name' => 'Factory manager', 'sequence' => 1, 'role_id' => Role::whereNull('company_id')->where('name', 'FACTORY_MANAGER')->firstOrFail()->id, 'condition_json' => ['min_cost' => '0']]);
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        Http::fake([OpenAiClient::URL => Http::response($this->answer('PURCHASE_ORDER', 'H&M', 92, [['order_header.order_no', 'PO-1', 99], ['order_line.quantity', '500', 95]]))]);

        $a = $this->drop('a.pdf');
        $b = $this->drop('b.pdf');
        $this->assertEquals(['AWAITING_APPROVAL', 'PENDING'], [$a['stage'], $a['approval_status']]);
        $requests = ApprovalRequest::query()->where('entity_type', 'IMPORT_FILE')->orderBy('created_at')->orderBy('id')->get();
        $this->assertCount(2, $requests);
        $this->assertSame('PURCHASE_ORDER', $requests[0]->context_json['family']);

        $this->as($manager)->postJson("/api/v1/approval-requests/{$requests->firstWhere('entity_id', $a['id'])->id}/approve", ['comment' => 'Fine'])->assertOk();
        $this->as($manager)->postJson("/api/v1/approval-requests/{$requests->firstWhere('entity_id', $b['id'])->id}/reject", ['comment' => 'Wrong buyer'])->assertOk();
        $this->assertSame(['POSTED', 'FAILED'], [DB::table('ai_intake_files')->where('id', $a['id'])->value('stage'), DB::table('ai_intake_files')->where('id', $b['id'])->value('stage')]);
    }

    public function test_where_a_read_stops(): void
    {
        $this->openai();
        Http::fake([OpenAiClient::URL => Http::sequence()
            ->push($this->answer('PURCHASE_ORDER', 'H&M', 88, [['order_header.order_no', 'PO-2', 99]]))
            ->push($this->answer('PURCHASE_ORDER', 'Zara', 70, [['order_header.order_no', 'PO-3', 99], ['order_line.quantity', '10', 90]]))
            ->push($this->answer('UNRECOGNISED', '', 12, []))
            ->push(['error' => ['message' => 'Rate limit reached']], 429)
            ->push(['output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]]),
        ]);
        $missing = $this->drop('missing.pdf');
        $this->assertEquals(['VALIDATING', __('aiimport.read.missing', ['fields' => 'Order quantity'])], [$missing['stage'], $missing['read_note']]);
        $stranger = $this->drop('zara.pdf');
        $this->assertEquals(['VALIDATING', null, __('aiimport.read.no_buyer')], [$stranger['stage'], $stranger['buyer'], $stranger['read_note']]);
        $unknown = $this->drop('scan.pdf');
        $this->assertEquals(['FAILED', 'UNRECOGNISED', __('aiimport.read.unrecognised')], [$unknown['stage'], $unknown['family'], $unknown['read_note']]);
        $limited = $this->drop('busy.pdf');
        $this->assertEquals(['FAILED', __('aicore.e.http_429', ['service' => 'OpenAI'])], [$limited['stage'], $limited['read_note']]);
        $declined = $this->drop('declined.pdf');
        $this->assertEquals(['FAILED', __('aicore.e.declined', ['service' => 'OpenAI'])], [$declined['stage'], $declined['read_note']]);
    }

    public function test_read_on_demand_and_without_a_service(): void
    {
        // No service yet: the upload simply waits.
        $waiting = $this->drop('waiting.pdf');
        $this->assertEquals(['QUEUED', null], [$waiting['stage'], $waiting['read_by']]);
        $this->as()->postJson("/api/v1/ai-import/intake/{$waiting['id']}/read")->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.read.no_service'));
        $this->assertNull($this->as()->getJson('/api/v1/ai-import/options')->json('data.reader'));

        // A service, but switched to on demand: nothing is read until asked.
        $this->openai(auto: false);
        $this->assertEquals(['provider' => 'OPENAI', 'model' => 'gpt-test', 'auto' => false], $this->as()->getJson('/api/v1/ai-import/options')->json('data.reader'));
        Http::fake([OpenAiClient::URL => Http::response($this->answer('PURCHASE_ORDER', 'H&M', 90, [['order_header.order_no', 'PO-9', 99], ['order_line.quantity', '9', 99]]))]);
        $later = $this->drop('later.pdf');
        $this->assertSame('QUEUED', $later['stage']);
        Http::assertNothingSent();
        $this->as()->postJson("/api/v1/ai-import/intake/{$later['id']}/read")->assertOk()->assertJsonPath('data.message', __('aiimport.read.started'));
        $this->assertSame('POSTED', DB::table('ai_intake_files')->where('id', $later['id'])->value('stage'));
        // A name typed in has nothing to read.
        $typed = $this->as()->postJson(self::E.'/ai-intake-files', ['file' => 'typed.pdf'])->assertCreated()->json('data.id');
        $this->as()->postJson("/api/v1/ai-import/intake/{$typed}/read")->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.read.upload_only'));
        // Only those who manage the intake may send a file to be read.
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $this->as($auditor)->postJson("/api/v1/ai-import/intake/{$later['id']}/read")->assertForbidden();
    }

    public function test_the_intake_takes_mail_word_and_csv_and_reads_them_as_text(): void
    {
        $eml = "Received: from mail.hm.example\r\nFrom: Anna <anna@hm.example>\r\nTo: orders@delta.test\r\nSubject: =?UTF-8?Q?PO_88412_=E2=80=93_urgent?=\r\nDate: Tue, 10 Nov 2026 09:00:00 +0000\r\nMIME-Version: 1.0\r\n"
            ."Content-Type: multipart/mixed; boundary=\"XYZ\"\r\n\r\n--XYZ\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nPlease confirm PO 88412.\r\n"
            ."--XYZ\r\nContent-Type: text/csv; name=\"lines.csv\"\r\nContent-Disposition: attachment; filename=\"lines.csv\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode("style,qty\nST-4471,12000\n"))
            ."--XYZ--\r\n";
        foreach (['po.eml' => $eml, 'lines.csv' => "style,qty\nST-4471,12000\n", 'note.docx' => $this->docx('Ship by 15 January.'), 'book.xlsx' => $this->xlsx()] as $name => $bytes) {
            $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->createWithContent($name, $bytes)], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->createWithContent('run.sh', "#!/bin/sh\nrm -rf /\n")], ['Accept' => 'application/json'])->assertStatus(415);
        $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->create('big.pdf', 21 * 1024)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.file.0', __('aiimport.v.file_size'));

        $parts = app(DocumentParts::class);
        $mail = $parts->fromBytes($eml, 'message/rfc822', 'po.eml');
        $this->assertStringContainsString('Subject: PO 88412 – urgent', $mail[0]['text']);
        $this->assertStringContainsString('Please confirm PO 88412.', $mail[0]['text']);
        $this->assertStringContainsString('ST-4471 | 12000', $mail[1]['text']);
        $this->assertStringContainsString('Ship by 15 January.', $parts->fromBytes($this->docx('Ship by 15 January.'), DocumentParts::DOCX, 'note.docx')[0]['text']);
        $sheet = $parts->fromBytes($this->xlsx(), DocumentParts::XLSX, 'book.xlsx')[0]['text'];
        $this->assertStringContainsString('## Sheet: PO lines', $sheet);
        $this->assertStringContainsString('ST-4471 | 12000', $sheet);
        $this->assertSame([['kind' => 'image', 'mime' => 'image/png', 'data' => 'PNG']], $parts->fromBytes('PNG', 'image/png', 'a.png'));
        $this->expectException(AiException::class);
        $parts->fromBytes(str_repeat('x ', DocumentParts::MAX_TEXT), 'text/plain', 'huge.txt');
    }

    public function test_order_analysis_uses_the_settings(): void
    {
        $writer = app(ClaudeWriter::class);
        $this->assertFalse($writer->enabled());
        $service = AiService::query()->create(['provider' => 'ANTHROPIC', 'model' => 'claude-opus-5-5', 'api_key' => 'sk-ant-test-000000000000000']);
        $this->as()->getJson('/api/v1/ai/features')->assertOk();
        AiFeature::query()->where('feature', 'ORDER_ANALYSIS')->update(['service_id' => $service->id]);
        $this->assertTrue(app(ClaudeWriter::class)->enabled());
        AiService::query()->whereKey($service->id)->update(['enabled' => false]);
        $this->assertFalse(app(ClaudeWriter::class)->enabled());
    }

    /** An OpenAI service for file reading, auto-reading unless told otherwise. */
    private function openai(bool $auto = true): void
    {
        $service = AiService::query()->create(['provider' => 'OPENAI', 'model' => 'gpt-test', 'api_key' => 'sk-test-0000000000000000000']);
        $this->as()->getJson('/api/v1/ai/features')->assertOk();
        AiFeature::query()->where('feature', 'FILE_READING')->update(['service_id' => $service->id, 'auto_run' => $auto]);
    }

    /** @param list<array{0: string, 1: string, 2: float|int}> $values */
    private function answer(string $family, string $buyer, float $confidence, array $values): array
    {
        $text = json_encode(['family' => $family, 'buyer' => $buyer, 'confidence' => $confidence, 'summary' => $family === 'UNRECOGNISED' ? '' : 'An H&M purchase order.', 'values' => array_map(fn ($v) => ['erp_column' => $v[0], 'value' => $v[1], 'confidence' => $v[2]], $values)]);

        return ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]]];
    }

    /** Uploads a small PDF and adds it to the intake; returns the row as listed afterwards. */
    private function drop(string $name): array
    {
        $upload = $this->as()->post('/api/v1/ai-import/intake-files', ['file' => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n")], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $id = $this->as()->postJson(self::E.'/ai-intake-files', ['file' => $upload['path'], 'arrived_by' => 'EMAIL'])->assertCreated()->json('data.id');

        return collect($this->as()->getJson(self::E.'/ai-intake-files')->json('data'))->firstWhere('id', $id);
    }

    private function docx(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }

    private function xlsx(): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('PO lines')->fromArray([['style', 'qty'], ['ST-4471', 12000]]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        (new Xlsx($book))->save($path);
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }
}
