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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
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
        // A person's token is a Sanctum personal access token now: "{id}|{plaintext}".
        $this->assertMatchesRegularExpression('/^\d+\|.+$/', $token);
        $this->assertSame($this->delta->id, $response->json('data.company_id'));

        // The plain token is never stored. What is kept is a hash, so the
        // table is worth nothing to whoever steals it.
        $row = PersonalAccessToken::firstOrFail();

        $this->assertNotSame($token, $row->token);
        $this->assertSame('Dye house tablet', $row->name);
        $this->assertSame($this->delta->id, $row->company_id);

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

    /** Mirrors the web's `PreferenceController::locale` — one of "the two global scope controls in the header" that had no API of its own before this. */
    public function test_a_person_can_change_their_own_locale(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        $this->withToken($token)
            ->patchJson('/api/v1/auth/locale', ['locale' => 'bn'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'bn');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.user.locale', 'bn');
    }

    public function test_an_unsupported_locale_is_refused(): void
    {
        $this->withToken($this->tokenFor('manager@delta.test'))
            ->patchJson('/api/v1/auth/locale', ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');
    }

    public function test_a_machine_caller_cannot_set_a_locale(): void
    {
        [$client, $secret] = $this->machineClient(['asset.asset.view']);

        $token = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');

        $this->withToken($token)
            ->patchJson('/api/v1/auth/locale', ['locale' => 'bn'])
            ->assertForbidden();
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

        // Deleted, not merely marked: Sanctum has no revoked_at, so a
        // revoked personal access token leaves no row at all.
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_an_expired_token_stops_working(): void
    {
        $token = $this->tokenFor('manager@delta.test');

        PersonalAccessToken::firstOrFail()
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

    // -- Sessions -------------------------------------------------------

    public function test_sessions_lists_and_revokes_browser_sessions(): void
    {
        config(['session.driver' => 'database']);

        DB::table('sessions')->insert([
            [
                'id' => 'session-a',
                'user_id' => $this->manager->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
                'payload' => '',
                'last_activity' => now()->getTimestamp(),
            ],
            [
                'id' => 'session-b',
                'user_id' => $this->manager->id,
                'ip_address' => '10.0.0.2',
                'user_agent' => 'Mozilla/5.0 (iPhone) Safari/604.1',
                'payload' => '',
                'last_activity' => now()->getTimestamp(),
            ],
        ]);

        $token = $this->tokenFor('manager@delta.test');

        $sessions = $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $sessions);

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/sessions/session-a')
            ->assertNoContent();

        $this->assertSame(0, DB::table('sessions')->where('id', 'session-a')->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'session-b')->count());
    }

    public function test_revoke_all_sessions_clears_every_one(): void
    {
        config(['session.driver' => 'database']);

        DB::table('sessions')->insert([
            ['id' => 'session-x', 'user_id' => $this->manager->id, 'ip_address' => '10.0.0.1', 'user_agent' => '', 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'session-y', 'user_id' => $this->manager->id, 'ip_address' => '10.0.0.2', 'user_agent' => '', 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->withToken($this->tokenFor('manager@delta.test'))
            ->postJson('/api/v1/auth/sessions/revoke-all')
            ->assertNoContent();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->manager->id)->count());
    }

    public function test_a_session_cannot_be_revoked_for_somebody_elses_account(): void
    {
        config(['session.driver' => 'database']);

        $stranger = User::where('email', 'owner@rival.test')->firstOrFail();

        DB::table('sessions')->insert([
            'id' => 'someone-elses-session',
            'user_id' => $stranger->id,
            'ip_address' => '10.0.0.9',
            'user_agent' => '',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->withToken($this->tokenFor('manager@delta.test'))
            ->deleteJson('/api/v1/auth/sessions/someone-elses-session')
            ->assertNoContent();

        // A no-op, not an error: the row simply was not this caller's to
        // delete, the same non-disclosure every other cross-tenant path uses.
        $this->assertSame(1, DB::table('sessions')->where('id', 'someone-elses-session')->count());
    }

    public function test_a_machine_caller_sees_no_sessions_and_cannot_revoke(): void
    {
        [$client, $secret] = $this->machineClient(['asset.asset.view']);

        $token = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->withToken($token)
            ->postJson('/api/v1/auth/sessions/revoke-all')
            ->assertForbidden();
    }

    // -- API tokens -----------------------------------------------------------

    public function test_tokens_lists_every_live_one_and_revokes_by_id(): void
    {
        $current = $this->tokenFor('manager@delta.test');

        $other = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
            'device_name' => 'Second device',
        ])->json('data.access_token');

        $list = $this->withToken($current)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $list);
        $otherId = (string) explode('|', $other)[0];
        $row = collect($list)->firstWhere('id', $otherId);
        $this->assertNotNull($row, 'the second device\'s token id should be listed');
        $this->assertFalse($row['is_current']);

        $currentId = (string) explode('|', $current)[0];
        $currentRow = collect($list)->firstWhere('id', $currentId);
        // Revoking the token that authenticated this very request would
        // end the caller's own session mid-click — the frontend relies on
        // this flag to hide that row's own revoke control.
        $this->assertTrue($currentRow['is_current']);

        $this->withToken($current)
            ->deleteJson("/api/v1/auth/tokens/{$row['id']}")
            ->assertNoContent();

        $this->withToken($other)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // The token used to revoke the other one is untouched.
        $this->withToken($current)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_a_token_cannot_be_revoked_for_somebody_elses_account(): void
    {
        $strangerToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@rival.test',
            'password' => 'correct-horse-battery',
        ])->json('data.access_token');
        $strangerTokenId = explode('|', $strangerToken)[0];

        $this->withToken($this->tokenFor('manager@delta.test'))
            ->deleteJson("/api/v1/auth/tokens/{$strangerTokenId}")
            ->assertStatus(404);

        // Untouched: the stranger's token still works.
        $this->withToken($strangerToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    // -- Password -------------------------------------------------------------

    public function test_the_current_password_must_be_correct(): void
    {
        $this->withToken($this->tokenFor('manager@delta.test'))
            ->postJson('/api/v1/auth/password', [
                'current_password' => 'not-the-password',
                'password' => 'a-genuinely-long-new-passphrase',
                'password_confirmation' => 'a-genuinely-long-new-passphrase',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_changing_the_password_revokes_every_other_token_but_keeps_this_one(): void
    {
        $current = $this->tokenFor('manager@delta.test');
        $other = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
            'device_name' => 'Second device',
        ])->json('data.access_token');

        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'a-web-session', 'user_id' => $this->manager->id, 'ip_address' => '10.0.0.1',
            'user_agent' => '', 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);

        $this->withToken($current)
            ->postJson('/api/v1/auth/password', [
                'current_password' => 'correct-horse-battery',
                'password' => 'a-genuinely-long-new-passphrase',
                'password_confirmation' => 'a-genuinely-long-new-passphrase',
            ])
            ->assertOk();

        // The token this very request used still works afterward.
        $this->withToken($current)->getJson('/api/v1/auth/me')->assertOk();

        // Every other token and web session are gone.
        $this->withToken($other)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->manager->id)->count());

        // The new password actually works; the old one no longer does.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@delta.test',
            'password' => 'a-genuinely-long-new-passphrase',
        ])->assertCreated();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertStatus(422);
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
