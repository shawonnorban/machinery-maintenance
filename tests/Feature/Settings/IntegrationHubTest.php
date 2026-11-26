<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Models\IntegrationLog;
use App\Modules\Settings\Models\SmsGateway;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Settings → Integration Hub (AnnotechERP 03/02): SMS gateways and bank
 * integrations with their tests, the activity log, and the endpoint list.
 */
class IntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    protected function tearDown(): void
    {
        putenv('TEST_SMS_KEY');
        parent::tearDown();
    }

    public function test_a_test_sms_reads_its_key_from_the_environment_and_is_logged(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202], 200)]);

        $this->as()->postJson('/api/v1/erp/sms-gateways', ['name' => 'Main', 'provider' => 'BULKSMSBD', 'api_key_env' => 'secret-key-123'])->assertUnprocessable();
        $id = $this->as()->postJson('/api/v1/erp/sms-gateways', ['name' => 'Main', 'provider' => 'BULKSMSBD', 'sender_id' => 'DELTA', 'api_key_env' => 'TEST_SMS_KEY', 'is_default' => true])
            ->assertCreated()->json('data.id');

        // The variable is not set yet: nothing is sent, and the log says why.
        $this->as()->postJson("/api/v1/settings/sms-gateways/{$id}/test", ['to' => '8801711000000'])
            ->assertOk()->assertJsonPath('data.ok', false);
        Http::assertNothingSent();

        putenv('TEST_SMS_KEY=live-key');
        $this->as()->postJson("/api/v1/settings/sms-gateways/{$id}/test", ['to' => '8801711000000', 'message' => 'Hello'])
            ->assertOk()->assertJsonPath('data.ok', true);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'api_key=live-key') && str_contains($r->url(), 'number=8801711000000'));

        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame(['FAILED', 'SUCCEEDED'], IntegrationLog::query()->orderBy('occurred_at')->orderBy('id')->pluck('status')->all());
        // The key itself is never written anywhere.
        $this->assertFalse(IntegrationLog::query()->where('details', 'like', '%live-key%')->exists());
        $this->assertNotNull(SmsGateway::findOrFail($id)->last_used_at);

        $this->as()->getJson('/api/v1/settings/integration/activity?channel=SMS&status=SUCCEEDED')->assertOk()->assertJsonCount(1, 'data');
        $this->as()->postJson("/api/v1/settings/sms-gateways/{$id}/test", ['to' => 'not-a-number'])->assertUnprocessable();
    }

    public function test_a_private_address_is_never_called(): void
    {
        Http::fake();
        putenv('TEST_SMS_KEY=live-key');

        $id = $this->as()->postJson('/api/v1/erp/sms-gateways', ['name' => 'Inside', 'provider' => 'GENERIC_HTTP', 'base_url' => 'https://169.254.169.254/latest', 'api_key_env' => 'TEST_SMS_KEY'])
            ->assertCreated()->json('data.id');
        $this->as()->postJson("/api/v1/settings/sms-gateways/{$id}/test", ['to' => '8801711000000'])->assertOk()->assertJsonPath('data.ok', false);

        $bank = $this->as()->postJson('/api/v1/erp/bank-integrations', ['bank_name' => 'Local', 'account_no' => '1', 'channel' => 'API', 'endpoint' => 'https://127.0.0.1/ping'])
            ->assertCreated()->json('data.id');
        $this->as()->postJson("/api/v1/settings/bank-integrations/{$bank}/test")->assertOk()->assertJsonPath('data.ok', false);

        Http::assertNothingSent();
    }

    public function test_a_bank_connection_test_is_stamped_and_logged(): void
    {
        Http::fake(['api.brac.example/*' => Http::response('ok', 200)]);

        $this->as()->postJson('/api/v1/erp/bank-integrations', ['bank_name' => 'BRAC', 'account_no' => '1501', 'channel' => 'API'])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/bank-integrations', ['bank_name' => 'BRAC', 'account_no' => '1501', 'channel' => 'FILE', 'swift_code' => 'bad'])->assertUnprocessable();

        $api = $this->as()->postJson('/api/v1/erp/bank-integrations', ['bank_name' => 'BRAC', 'account_no' => '1501', 'channel' => 'API', 'endpoint' => 'https://api.brac.example/ping', 'swift_code' => 'brakbddh'])
            ->assertCreated()->assertJsonPath('data.swift_code', 'BRAKBDDH')->json('data.id');
        $this->as()->postJson("/api/v1/settings/bank-integrations/{$api}/test")->assertOk()->assertJsonPath('data.ok', true);

        $file = $this->as()->postJson('/api/v1/erp/bank-integrations', ['bank_name' => 'City', 'account_no' => '22', 'channel' => 'FILE'])->assertCreated()->json('data.id');
        $this->as()->postJson("/api/v1/settings/bank-integrations/{$file}/test")->assertOk()->assertJsonPath('data.ok', false);

        $this->as()->getJson('/api/v1/erp/bank-integrations')->assertJsonFragment(['bank_name' => 'BRAC', 'last_test_status' => 'SUCCEEDED']);
        $this->as()->getJson('/api/v1/settings/integration/activity?channel=BANK')->assertJsonCount(2, 'data');
    }

    public function test_the_endpoint_list_names_every_outside_address(): void
    {
        putenv('TEST_SMS_KEY=x');
        $this->as()->postJson('/api/v1/erp/sms-gateways', ['name' => 'Main', 'provider' => 'TWILIO', 'username' => 'AC123', 'api_key_env' => 'TEST_SMS_KEY'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/bank-integrations', ['bank_name' => 'BRAC', 'account_no' => '1501', 'channel' => 'SFTP', 'endpoint' => 'sftp.brac.example:22'])->assertCreated();

        $rows = collect($this->as()->getJson('/api/v1/settings/integration/endpoints')->assertOk()->json('data'));
        $this->assertSame('INBOUND', $rows->firstWhere('id', 'api')['direction']);
        $this->assertTrue($rows->contains(fn (array $r): bool => str_starts_with($r['endpoint'], 'SMS: Main')));
        $this->assertTrue($rows->contains(fn (array $r): bool => $r['set_in'] === 'Bank & Payment Integration'));
    }

    private function as(): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
