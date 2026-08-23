<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The front door (API 3).
 *
 * Two kinds of caller reach the same endpoints — a person who typed a password
 * and a machine holding client credentials — and both come out with the same
 * kind of bearer token. What these tests hold down is the part that is easy to
 * get almost right: a token is a key to exactly one company, and no header,
 * default membership or later role change can point it at another.
 */
class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $rival;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');

        $this->rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::factory($this->rival, 'Savar Unit', 'SAV');
        TenantFixture::actingAsTenant($this->rival);
        TenantFixture::user($this->rival, 'COMPANY_OWNER', 'owner@rival.test');

        TenantFixture::actingAsTenant($this->delta);
    }

    // -- A person's token ---------------------------------------------------

    public function test_a_password_is_exchanged_for_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
            'device_name' => 'Dye house tablet',
        ])->assertCreated();

        $token = $response->json('data.access_token');

        $this->assertIsString($token);
        $this->assertStringStartsWith('mmt_', $token);
        $this->assertSame($this->delta->id, $response->json('data.company_id'));

        // The plain token is never stored. What is kept is a hash, so the
        // table is worth nothing to whoever steals it.
        $row = ApiToken::withoutGlobalScope(TenantScope::class)->firstOrFail();

        $this->assertSame(ApiToken::hash($token), $row->token_hash);
        $this->assertNotSame($token, $row->token_hash);
        $this->assertSame('Dye house tablet', $row->name);

        // No session came back with it. A bearer token is not a login.
        $this->assertGuest();
    }

    public function test_a_wrong_password_says_nothing_about_the_account(): void
    {
        $wrong = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@delta.test',
            'password' => 'not-the-password',
        ]);

        $unknown = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@delta.test',
            'password' => 'not-the-password',
        ]);

        // The same answer to both. Telling them apart is how somebody learns
        // which addresses are registered.
        $this->assertSame($wrong->status(), $unknown->status());
        $this->assertSame($wrong->json('message'), $unknown->json('message'));

        $this->assertSame(0, ApiToken::withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_the_token_identifies_its_holder(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.kind', 'USER')
            ->assertJsonPath('data.user.email', 'manager@delta.test')
            ->assertJsonPath('data.company_id', $this->delta->id);
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $this->withToken('mmt_'.str_repeat('x', 40))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_no_token_at_all_is_refused(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_a_revoked_token_stops_working(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

        // Revoked, not deleted: a token that stops working leaves a question
        // behind, and the row is the answer.
        $row = ApiToken::withoutGlobalScope(TenantScope::class)->firstOrFail();

        $this->assertNotNull($row->revoked_at);
    }

    public function test_an_expired_token_stops_working(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        ApiToken::withoutGlobalScope(TenantScope::class)
            ->firstOrFail()
            ->forceFill(['expires_at' => now()->subMinute()])
            ->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    // -- One token, one company ---------------------------------------------

    public function test_a_header_cannot_point_a_token_at_another_company(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        // Refused rather than ignored. Silently overriding a client's explicit
        // instruction is how readings end up in the wrong factory with nobody
        // any the wiser.
        $this->withToken($token)
            ->withHeader('X-Company-Id', $this->rival->id)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_ACCESS_DENIED');
    }

    public function test_naming_the_token_s_own_company_is_accepted(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $this->withToken($token)
            ->withHeader('X-Company-Id', $this->delta->id)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_membership_is_rechecked_on_every_request(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $this->manager->id)
            ->delete();

        // Somebody removed from a company this morning stops being able to
        // read it this morning, whatever they are still holding.
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_ACCESS_DENIED');
    }

    public function test_a_deactivated_account_stops_working(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $this->manager->forceFill(['status' => 'SUSPENDED'])->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    // -- A machine's token --------------------------------------------------

    public function test_client_credentials_are_exchanged_for_a_scoped_token(): void
    {
        [$client, $secret] = $this->machineClient(['meter.reading.create', 'asset.asset.view']);

        $response = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->assertCreated();

        $token = $response->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.kind', 'CLIENT')
            ->assertJsonPath('data.client.client_id', $client->client_id);

        // The scope list is the whole control. An ERP credentialed to post
        // meter readings must not be able to close a work order.
        $permissions = $this->withToken($token)->getJson('/api/v1/auth/permissions')->json('data.permissions');

        $this->assertEqualsCanonicalizing(['asset.asset.view', 'meter.reading.create'], $permissions);
    }

    public function test_a_wrong_secret_is_refused(): void
    {
        [$client] = $this->machineClient(['asset.asset.view']);

        $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => 'sk_wrong',
        ])->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_a_revoked_client_cannot_get_a_token_and_its_tokens_stop_working(): void
    {
        [$client, $secret] = $this->machineClient(['asset.asset.view']);

        $token = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');

        $client->forceFill(['status' => 'REVOKED', 'revoked_at' => now()])->save();

        // Both halves matter: no new tokens, and the ones already out there
        // stop the moment somebody revokes the credential.
        $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->assertUnauthorized();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_machine_cannot_switch_company(): void
    {
        [$client, $secret] = $this->machineClient(['asset.asset.view']);

        $token = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/switch-company', ['company_id' => $this->rival->id])
            ->assertForbidden();
    }

    // -- Permissions reported are the token's, not the account's -------------

    public function test_a_person_is_told_what_their_token_can_do(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $permissions = $this->withToken($token)
            ->getJson('/api/v1/auth/permissions')
            ->assertOk()
            ->json('data.permissions');

        $this->assertContains('work_order.work_order.view', $permissions);
        $this->assertNotContains('admin.company.manage', $permissions);
    }

    // -- Health -------------------------------------------------------------

    public function test_health_answers_without_a_token(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('data.status', 'ok');

        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.database', true);
    }

    public function test_every_response_carries_a_request_id(): void
    {
        // A support ticket quoting a request id has to resolve to the exact
        // changes it caused, which only works if the client was given one.
        $this->getJson('/api/v1/health')->assertJsonStructure(['meta' => ['request_id']]);

        $this->getJson('/api/v1/auth/me')->assertJsonStructure(['meta' => ['request_id']]);
    }

    // -- Helpers ------------------------------------------------------------

    private function tokenFor(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ])->json('data.access_token');
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: ApiClient, 1: string}
     */
    private function machineClient(array $scopes): array
    {
        $secret = 'sk_'.str_repeat('a', 48);

        $client = ApiClient::create([
            'company_id' => $this->delta->id,
            'name' => 'Dye house controller',
            'client_id' => ApiClient::mintClientId(),
            'secret_hash' => Hash::make($secret),
            'scopes_json' => $scopes,
            'status' => 'ACTIVE',
        ]);

        return [$client, $secret];
    }
}
