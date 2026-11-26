<?php

declare(strict_types=1);

namespace Tests\Feature\AiCore;

use App\Modules\AiCore\Models\AiService;
use App\Modules\AiCore\Services\AiException;
use App\Modules\AiCore\Services\GeminiClient;
use App\Modules\AiCore\Services\OpenAiClient;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Settings → AI Services (the user's decision of 2026-11-12): one service per
 * provider, its key stored encrypted and never returned, kept when an edit
 * leaves it blank; the ERP's AI features listed (created on first sight) and
 * pointed at a service; the connection test against OpenAI and Gemini, with
 * the requests they are sent; and who may.
 */
class AiServicesTest extends TestCase
{
    use RefreshDatabase;

    private const E = '/api/v1/erp';

    private const KEY = 'sk-test-0123456789abcdefWXYZ';

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_services_keep_their_key_to_themselves(): void
    {
        $this->as()->postJson(self::E.'/ai-services', ['provider' => 'ANTHROPIC', 'model' => 'claude-opus-5-5'])->assertUnprocessable()->assertJsonPath('errors.api_key.0', __('aicore.v.api_key'));
        $this->as()->postJson(self::E.'/ai-services', ['provider' => 'ANTHROPIC', 'model' => 'claude-opus-5-5', 'api_key' => 'short'])->assertUnprocessable()->assertJsonPath('errors.api_key.0', __('aicore.v.api_key'));
        $this->as()->postJson(self::E.'/ai-services', ['provider' => 'MISTRAL', 'model' => 'x', 'api_key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors(['provider']);
        $this->as()->postJson(self::E.'/ai-services', ['provider' => 'OPENAI', 'model' => 'gpt 5; drop', 'api_key' => self::KEY])->assertUnprocessable()->assertJsonPath('errors.model.0', __('aicore.v.model'));

        $created = $this->as()->postJson(self::E.'/ai-services', ['provider' => 'ANTHROPIC', 'model' => ' claude-opus-5-5 ', 'api_key' => ' '.self::KEY.' '])->assertCreated();
        $s = $created->json('data');
        $this->assertEquals(['ANTHROPIC', 'claude-opus-5-5', true, '••••WXYZ', null], [$s['provider'], $s['model'], $s['enabled'], $s['key_hint'], $s['last_test_ok']]);
        $this->assertStringNotContainsString(self::KEY, $created->getContent());
        // Encrypted at rest; the model hands back the key itself.
        $raw = DB::table('ai_services')->where('id', $s['id'])->value('api_key');
        $this->assertNotSame(self::KEY, $raw);
        $this->assertStringNotContainsString('WXYZ', (string) $raw);
        $this->assertSame(self::KEY, AiService::query()->find($s['id'])->api_key);
        $this->assertStringNotContainsString(self::KEY, $this->as()->getJson(self::E.'/ai-services')->assertOk()->getContent());

        // One service per provider.
        $this->as()->postJson(self::E.'/ai-services', ['provider' => 'ANTHROPIC', 'model' => 'claude-sonnet-5-5', 'api_key' => self::KEY])->assertUnprocessable()->assertJsonPath('errors.provider.0', __('aicore.v.provider_taken'));

        // A blank key on an edit keeps the key; a new key or model clears the last test.
        AiService::query()->whereKey($s['id'])->update(['last_test_ok' => true, 'last_test_message' => 'ok', 'last_tested_at' => now()]);
        $this->as()->patchJson(self::E."/ai-services/{$s['id']}", ['api_key' => '', 'enabled' => false])->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.last_test_ok', true);
        $this->assertSame(self::KEY, AiService::query()->find($s['id'])->api_key);
        $this->as()->patchJson(self::E."/ai-services/{$s['id']}", ['model' => 'claude-sonnet-5-5'])->assertOk()->assertJsonPath('data.last_test_ok', null)->assertJsonPath('data.key_hint', '••••WXYZ');
        $this->as()->patchJson(self::E."/ai-services/{$s['id']}", ['api_key' => 'sk-another-key-000000001234'])->assertOk()->assertJsonPath('data.key_hint', '••••1234');
    }

    public function test_features_point_at_a_service(): void
    {
        $features = $this->as()->getJson('/api/v1/ai/features')->assertOk()->json('data');
        $this->assertEquals([['FILE_READING', true, true, null], ['ORDER_ANALYSIS', false, true, null]], array_map(fn ($f) => [$f['feature'], $f['auto_run'], $f['enabled'], $f['service']], $features));
        $this->assertCount(2, $this->as()->getJson('/api/v1/ai/features')->json('data'));

        $service = $this->as()->postJson(self::E.'/ai-services', ['provider' => 'GEMINI', 'model' => 'gemini-test', 'api_key' => self::KEY])->assertCreated()->json('data.id');
        $this->as()->patchJson(self::E."/ai-features/{$features[0]['id']}", ['service_id' => $service, 'auto_run' => false])->assertOk();
        $listed = $this->as()->getJson('/api/v1/ai/features')->json('data.0');
        $this->assertEquals(['GEMINI', 'gemini-test', false], [$listed['service'], $listed['service_model'], $listed['auto_run']]);
        $this->as()->patchJson(self::E."/ai-features/{$features[0]['id']}", ['feature' => 'ORDER_ANALYSIS'])->assertUnprocessable()->assertJsonValidationErrors(['feature']);
        $this->as()->deleteJson(self::E."/ai-features/{$features[0]['id']}")->assertStatus(405);
        // Deleting the service leaves the feature without AI.
        $this->as()->deleteJson(self::E."/ai-services/{$service}")->assertNoContent();
        $this->assertNull($this->as()->getJson('/api/v1/ai/features')->json('data.0.service'));
    }

    public function test_connection_tests_against_openai_and_gemini(): void
    {
        Http::fake([
            OpenAiClient::URL => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"ok":true}']]]]]),
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid. Please pass a valid API key.']], 400),
        ]);
        $openai = $this->as()->postJson(self::E.'/ai-services', ['provider' => 'OPENAI', 'model' => 'gpt-test', 'api_key' => self::KEY])->assertCreated()->json('data.id');
        $gemini = $this->as()->postJson(self::E.'/ai-services', ['provider' => 'GEMINI', 'model' => 'gemini-test', 'api_key' => self::KEY])->assertCreated()->json('data.id');

        $ok = $this->as()->postJson("/api/v1/ai/services/{$openai}/test")->assertOk()->json('data');
        $this->assertEquals([true, __('aicore.test.ok'), true], [$ok['ok'], $ok['message'], $ok['last_test_ok']]);
        Http::assertSent(fn (Request $r) => $r->url() === OpenAiClient::URL && $r->hasHeader('Authorization', 'Bearer '.self::KEY) && $r['model'] === 'gpt-test'
            && $r['text']['format']['type'] === 'json_schema' && $r['text']['format']['strict'] === true && $r['input'][0]['content'][0]['type'] === 'input_text');

        $bad = $this->as()->postJson("/api/v1/ai/services/{$gemini}/test")->assertOk()->json('data');
        $this->assertFalse($bad['ok']);
        $this->assertStringContainsString('API key not valid', $bad['message']);
        $this->assertStringNotContainsString(self::KEY, $bad['message']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'models/gemini-test:generateContent') && $r->hasHeader('x-goog-api-key', self::KEY)
            && $r['generationConfig']['responseMimeType'] === 'application/json' && isset($r['generationConfig']['responseJsonSchema']));
        $this->assertFalse(AiService::query()->find($gemini)->last_test_ok);
    }

    public function test_clients_report_refusals_and_bad_answers(): void
    {
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];
        Http::fake([
            OpenAiClient::URL => Http::sequence()
                ->push(['output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]])
                ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'not json']]]]]),
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['finishReason' => 'SAFETY', 'content' => ['parts' => []]]]])
                ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"ok":'], ['text' => 'true}']]]]]]),
        ]);
        $openai = new OpenAiClient(self::KEY, 'gpt-test');
        $gemini = new GeminiClient(self::KEY, 'gemini-test');
        foreach ([[$openai, __('aicore.e.declined', ['service' => 'OpenAI'])], [$openai, __('aicore.e.not_json', ['service' => 'OpenAI'])], [$gemini, __('aicore.e.declined', ['service' => 'Gemini'])]] as [$client, $message]) {
            try {
                $client->structured('s', [['kind' => 'text', 'text' => 'x']], $schema);
                $this->fail('An AiException was expected.');
            } catch (AiException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
        // Gemini's answer may come in pieces.
        $this->assertSame(['ok' => true], $gemini->structured('s', [['kind' => 'pdf', 'data' => '%PDF-1.4']], $schema));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'gemini-test') && ($r['contents'][0]['parts'][0]['inlineData']['mimeType'] ?? null) === 'application/pdf');
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/ai-services')->assertForbidden();
        $this->as($keeper)->getJson('/api/v1/ai/features')->assertForbidden();
        $id = $this->as()->postJson(self::E.'/ai-services', ['provider' => 'OPENAI', 'model' => 'gpt-test', 'api_key' => self::KEY])->assertCreated()->json('data.id');
        $this->as($auditor)->postJson("/api/v1/ai/services/{$id}/test")->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/ai-services')->assertOk()->json('data'));
        $feature = $this->as($stranger, $other)->getJson('/api/v1/ai/features')->json('data.0.id');
        $this->as($stranger, $other)->patchJson(self::E."/ai-features/{$feature}", ['service_id' => $id])->assertUnprocessable()->assertJsonValidationErrors(['service_id']);
        $this->as($stranger, $other)->postJson("/api/v1/ai/services/{$id}/test")->assertNotFound();
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
