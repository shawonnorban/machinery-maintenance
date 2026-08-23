<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

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
 * Minting a machine's credentials from a screen (API 4.2).
 *
 * The screen exists because the alternative is a database seed and a support
 * ticket. What it must get right is narrower than it looks: the secret is
 * readable exactly once, and a credential can never do more than the person
 * minting it explicitly ticked.
 */
class ApiClientScreenTest extends TestCase
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
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_the_screen_opens(): void
    {
        $this->actingAs($this->owner)
            ->get('/app/settings/api-clients')
            ->assertOk()
            ->assertSee(__('api.api_clients'));
    }

    public function test_a_credential_is_minted_and_its_secret_shown_once(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/api-clients', [
                'name' => 'Dye house controller',
                'scopes' => ['meter.reading.create', 'asset.asset.view'],
            ])
            ->assertRedirect();

        $client = ApiClient::firstOrFail();

        $this->assertSame(['meter.reading.create', 'asset.asset.view'], $client->scopes());
        $this->assertSame('ACTIVE', $client->status);

        // Flash survives exactly one request. The first GET is the only place
        // the secret is ever readable.
        $first = $this->actingAs($this->owner)->get('/app/settings/api-clients')->assertOk();
        $secret = $this->flashedSecret($first->getContent());

        $this->assertIsString($secret);
        $this->assertStringStartsWith('sk_', $secret);
        $this->assertTrue($client->verifySecret($secret));

        $this->actingAs($this->owner)
            ->get('/app/settings/api-clients')
            ->assertOk()
            ->assertDontSee($secret);
    }

    public function test_the_secret_is_never_stored_in_the_clear(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/api-clients', [
            'name' => 'ERP',
            'scopes' => ['cost.entry.create'],
        ]);

        $client = ApiClient::firstOrFail();
        $secret = $this->flashedSecret(
            $this->actingAs($this->owner)->get('/app/settings/api-clients')->getContent(),
        );

        // A leaked database gives an attacker nothing they can present as a
        // credential.
        $this->assertNotSame($secret, $client->secret_hash);
        $this->assertStringNotContainsString($secret, $client->secret_hash);
    }

    public function test_a_credential_with_no_scopes_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/api-clients')
            ->post('/app/settings/api-clients', ['name' => 'Nothing yet'])
            ->assertSessionHasErrors('scopes');

        // "No restrictions listed, so no restrictions" is the reading that
        // turns a narrow credential into a wide one.
        $this->assertSame(0, ApiClient::count());
    }

    public function test_a_scope_that_is_not_a_real_permission_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/api-clients')
            ->post('/app/settings/api-clients', [
                'name' => 'Typo',
                'scopes' => ['asset.asset.veiw'],
            ])
            ->assertSessionHasErrors('scopes');

        // A typo would otherwise become a scope that grants nothing and
        // refuses everything, and the integration would fail with a 403
        // nobody can explain.
        $this->assertSame(0, ApiClient::count());
    }

    public function test_rotating_the_secret_stops_the_tokens_already_out_there(): void
    {
        $client = $this->mintedClient(['asset.asset.view']);
        $secret = $this->flashedSecret(
            $this->actingAs($this->owner)->get('/app/settings/api-clients')->getContent(),
        );

        $token = $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->actingAs($this->owner)
            ->post('/app/settings/api-clients/'.$client->id.'/rotate')
            ->assertRedirect();

        // Rotation exists for the day somebody pasted the old secret into a
        // ticket, so the old secret and everything minted from it must stop.
        $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->assertUnauthorized();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_narrowing_the_scopes_stops_the_wider_tokens(): void
    {
        $client = $this->mintedClient(['asset.asset.view', 'work_order.work_order.close']);

        $this->actingAs($this->owner)
            ->patch('/app/settings/api-clients/'.$client->id, [
                'scopes' => ['asset.asset.view'],
            ])
            ->assertRedirect();

        $this->assertSame(['asset.asset.view'], $client->fresh()->scopes());

        // A token carries the scope list it was minted with, so narrowing the
        // client would otherwise leave the wider token running.
        $this->assertSame(0, ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('api_client_id', $client->id)
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_revoking_keeps_the_row(): void
    {
        $client = $this->mintedClient(['asset.asset.view']);

        $this->actingAs($this->owner)
            ->delete('/app/settings/api-clients/'.$client->id)
            ->assertRedirect();

        $client->refresh();

        // Months later somebody will ask what posted a reading last March, and
        // a deleted client makes that question unanswerable.
        $this->assertSame('REVOKED', $client->status);
        $this->assertNotNull($client->revoked_at);
        $this->assertFalse($client->isUsable());
    }

    public function test_a_role_without_the_permission_cannot_reach_the_screen(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->flushSession();

        $this->actingAs($manager)->get('/app/settings/api-clients')->assertForbidden();

        $this->actingAs($manager)
            ->post('/app/settings/api-clients', ['name' => 'Mine', 'scopes' => ['asset.asset.view']])
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $scopes
     */
    private function mintedClient(array $scopes): ApiClient
    {
        $this->actingAs($this->owner)->post('/app/settings/api-clients', [
            'name' => 'Dye house controller',
            'scopes' => $scopes,
        ]);

        return ApiClient::firstOrFail();
    }

    private function flashedSecret(string $html): ?string
    {
        return preg_match('/(sk_[A-Za-z0-9]{48})/', $html, $matches) === 1 ? $matches[1] : null;
    }
}
