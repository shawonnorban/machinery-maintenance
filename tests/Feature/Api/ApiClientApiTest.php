<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Minting a machine's credentials, over the API (API 4.2).
 *
 * `ApiClientScreenTest` proves the same rules over the web screen; this
 * suite proves the JSON wiring — most notably that the one-time secret,
 * shown once via a flashed session value on the web, is instead simply
 * returned in the `store`/`rotate` response body here and nowhere else.
 */
class ApiClientApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->ownerToken = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'x')['plain'];
    }

    private function asOwner(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken);

        return $this;
    }

    public function test_a_credential_is_minted_and_its_secret_returned_once(): void
    {
        $response = $this->asOwner()->postJson('/api/v1/api-clients', [
            'name' => 'Dye house controller',
            'scopes' => ['meter.reading.create', 'asset.asset.view'],
        ])->assertCreated();

        $this->assertSame(['meter.reading.create', 'asset.asset.view'], $response->json('data.scopes'));
        $this->assertStringStartsWith('sk_', (string) $response->json('data.secret'));

        $client = ApiClient::firstOrFail();
        $this->assertTrue($client->verifySecret($response->json('data.secret')));

        // Never again on a plain read.
        $this->asOwner()->getJson('/api/v1/api-clients')
            ->assertOk()
            ->assertJsonMissingPath('data.0.secret');
    }

    public function test_a_credential_with_no_scopes_is_refused(): void
    {
        $this->asOwner()->postJson('/api/v1/api-clients', ['name' => 'Nothing yet'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scopes');

        $this->assertSame(0, ApiClient::count());
    }

    public function test_a_scope_that_is_not_a_real_permission_is_refused(): void
    {
        $this->asOwner()->postJson('/api/v1/api-clients', [
            'name' => 'Typo',
            'scopes' => ['asset.asset.veiw'],
        ])->assertStatus(422)->assertJsonValidationErrors('scopes');

        $this->assertSame(0, ApiClient::count());
    }

    public function test_rotating_the_secret_stops_the_tokens_already_out_there(): void
    {
        $created = $this->asOwner()->postJson('/api/v1/api-clients', [
            'name' => 'Dye house controller',
            'scopes' => ['asset.asset.view'],
        ])->assertCreated();

        $client = ApiClient::firstOrFail();
        $secret = $created->json('data.secret');

        $token = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $rotated = $this->asOwner()->postJson("/api/v1/api-clients/{$client->id}/rotate")->assertOk();
        $this->assertStringStartsWith('sk_', (string) $rotated->json('data.secret'));
        $this->assertNotSame($secret, $rotated->json('data.secret'));

        $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->assertUnauthorized();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_narrowing_the_scopes_stops_the_wider_tokens(): void
    {
        $created = $this->asOwner()->postJson('/api/v1/api-clients', [
            'name' => 'Dye house controller',
            'scopes' => ['asset.asset.view', 'work_order.work_order.close'],
        ])->assertCreated();

        $client = ApiClient::firstOrFail();

        $this->asOwner()->patchJson("/api/v1/api-clients/{$client->id}", [
            'scopes' => ['asset.asset.view'],
        ])->assertOk()->assertJsonPath('data.scopes', ['asset.asset.view']);

        $this->assertSame(0, ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('api_client_id', $client->id)
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_revoking_keeps_the_row(): void
    {
        $this->asOwner()->postJson('/api/v1/api-clients', [
            'name' => 'Dye house controller',
            'scopes' => ['asset.asset.view'],
        ])->assertCreated();

        $client = ApiClient::firstOrFail();

        $this->asOwner()->deleteJson("/api/v1/api-clients/{$client->id}")->assertNoContent();

        $client->refresh();
        $this->assertSame('REVOKED', $client->status);
        $this->assertNotNull($client->revoked_at);
        $this->assertFalse($client->isUsable());
    }

    public function test_form_options_returns_permissions_grouped_by_module(): void
    {
        $response = $this->asOwner()->getJson('/api/v1/api-clients/form-options')->assertOk();

        $modules = $response->json('data.modules');
        $this->assertNotEmpty($modules);
        $this->assertArrayHasKey('module', $modules[0]);
        $this->assertArrayHasKey('permissions', $modules[0]);
    }

    public function test_a_role_without_the_permission_is_refused(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        $managerToken = app(IssueApiToken::class)->forUser($manager, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->getJson('/api/v1/api-clients')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson('/api/v1/api-clients', ['name' => 'Mine', 'scopes' => ['asset.asset.view']])
            ->assertForbidden();
    }
}
