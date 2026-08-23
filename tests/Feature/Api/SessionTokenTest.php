<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The token the page itself uses (SRS 38).
 *
 * The offline queue posts to the API, and the API takes bearer tokens rather
 * than session cookies. This is the bridge, and it is the one place in the
 * product where a bearer token is handed to a browser — so what it may do is
 * narrower than the person holding it.
 */
class SessionTokenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'karim@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_a_signed_in_person_is_given_a_token_for_their_own_company(): void
    {
        $response = $this->actingAs($this->technician)
            ->postJson('/app/session-token')
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at']);

        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'karim@delta.test')
            ->assertJsonPath('data.company_id', $this->delta->id);
    }

    public function test_the_token_can_do_only_what_the_queue_posts(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $token = $this->actingAs($this->technician)->postJson('/app/session-token')->json('token');

        $this->flushSession();

        // The one thing it is for.
        $this->withToken($token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $asset->id,
                'problem_description' => 'Needle bar seized',
            ])
            ->assertCreated();

        // And nothing else, even though a technician's account can read
        // machines and work orders perfectly well on a screen. A token lying
        // around in a phone on a factory floor should carry less than the
        // person does.
        $this->withToken($token)->getJson('/api/v1/assets')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/work-orders')->assertForbidden();
    }

    public function test_minting_a_token_retires_the_previous_one(): void
    {
        $first = $this->actingAs($this->technician)->postJson('/app/session-token')->json('token');
        $second = $this->actingAs($this->technician)->postJson('/app/session-token')->json('token');

        $this->assertNotSame($first, $second);

        $this->flushSession();

        // A shared tablet used by four people across a shift should leave one
        // live token behind, not four.
        $this->withToken($first)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/v1/auth/me')->assertOk();

        $live = ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $this->technician->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertSame(1, $live);
    }

    public function test_the_token_is_short_lived(): void
    {
        $this->actingAs($this->technician)->postJson('/app/session-token')->assertOk();

        $token = ApiToken::withoutGlobalScope(TenantScope::class)
            ->whereNull('revoked_at')
            ->firstOrFail();

        // The tab it lives in is open for a shift, not a month.
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addDay()->addMinute()));
        $this->assertTrue($token->expires_at->greaterThan(now()->addHours(23)));
    }

    public function test_a_guest_gets_nothing(): void
    {
        $this->postJson('/app/session-token')->assertUnauthorized();

        $this->assertSame(0, ApiToken::withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_a_person_with_no_membership_in_the_named_company_cannot_mint_for_it(): void
    {
        $rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::factory($rival, 'Savar Unit', 'SAV');
        TenantFixture::actingAsTenant($rival);
        TenantFixture::user($rival, 'COMPANY_OWNER', 'owner@rival.test');
        TenantFixture::actingAsTenant($this->delta);

        $token = $this->actingAs($this->technician)->postJson('/app/session-token')->json('token');

        $this->flushSession();

        // Same rule as every other token: the company comes from the token,
        // and a header naming another one is refused rather than honoured.
        $this->withToken($token)
            ->withHeader('X-Company-Id', $rival->id)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_ACCESS_DENIED');
    }
}
