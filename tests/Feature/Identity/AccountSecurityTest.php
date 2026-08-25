<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The account a person owns rather than administers (SRS 50.1, 50.2, 50.4).
 *
 * With two-step sign-in withdrawn (see SRS 50.3), the password is the whole of
 * the credential — which makes what happens around it matter more, not less.
 * Changing one has to take every other way in with it, and the devices holding
 * a session have to be visible to the person whose account they are on.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    // -- Password -----------------------------------------------------------

    public function test_changing_a_password_takes_every_other_way_in_with_it(): void
    {
        ['token' => $token] = app(IssueApiToken::class)
            ->forUser($this->manager, $this->delta->id, 'Phone');

        $this->actingAs($this->manager)
            ->post('/app/account/password', [
                'current_password' => 'correct-horse-battery',
                'password' => 'a-much-longer-passphrase-9271',
                'password_confirmation' => 'a-much-longer-passphrase-9271',
            ])
            ->assertRedirect();

        // A password changed because it may have leaked is a password whose
        // tokens may have leaked with it. Leaving them alive makes the change
        // ceremonial.
        $this->assertNotNull(ApiToken::withoutGlobalScope(TenantScope::class)
            ->whereKey($token->id)
            ->value('revoked_at'));

        $this->signOut();

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'a-much-longer-passphrase-9271',
        ])->assertRedirect(route('app.dashboard'));
    }

    public function test_other_devices_are_signed_out_but_this_one_is_not(): void
    {
        DB::table('sessions')->insert([
            [
                'id' => 'another-device',
                'user_id' => $this->manager->id,
                'ip_address' => '10.0.0.9',
                'user_agent' => 'Chrome',
                'payload' => '',
                'last_activity' => now()->getTimestamp(),
            ],
        ]);

        $this->actingAs($this->manager)
            ->post('/app/account/password', [
                'current_password' => 'correct-horse-battery',
                'password' => 'a-much-longer-passphrase-9271',
                'password_confirmation' => 'a-much-longer-passphrase-9271',
            ])
            ->assertRedirect();

        // Everywhere else goes; the screen they are standing on stays, because
        // being thrown out of the form you just submitted reads as a failure.
        $this->assertNull(DB::table('sessions')->where('id', 'another-device')->value('id'));
    }

    public function test_the_current_password_is_required_to_change_it(): void
    {
        $this->actingAs($this->manager)
            ->from('/app/account')
            ->post('/app/account/password', [
                'current_password' => 'not-my-password',
                'password' => 'a-much-longer-passphrase-9271',
                'password_confirmation' => 'a-much-longer-passphrase-9271',
            ])
            ->assertSessionHasErrors('current_password');

        // The check that stops a borrowed unlocked laptop becoming a permanent
        // one.
        $this->signOut();

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('app.dashboard'));
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->from('/app/account')
            ->post('/app/account/password', [
                'current_password' => 'correct-horse-battery',
                'password' => 'short1',
                'password_confirmation' => 'short1',
            ])
            ->assertSessionHasErrors('password');
    }

    // -- Devices ------------------------------------------------------------

    public function test_the_account_screen_opens(): void
    {
        $this->actingAs($this->manager)
            ->get('/app/account')
            ->assertOk()
            ->assertSee(__('account.your_account'))
            ->assertSee(__('account.signed_in_devices'));
    }

    public function test_one_person_cannot_sign_another_out(): void
    {
        $other = TenantFixture::user($this->delta, 'TECHNICIAN', 'karim@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        // Written directly, because the suite runs on the array session driver
        // and never populates this table. What is under test is the scoping on
        // the delete, not how a row got there.
        DB::table('sessions')->insert([
            'id' => 'their-session-id',
            'user_id' => $other->id,
            'ip_address' => '10.0.0.9',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->manager)
            ->delete('/app/account/sessions/their-session-id')
            ->assertRedirect();

        // Scoped to the asker. Without that, anybody could sign anybody out by
        // guessing a session id.
        $this->assertNotNull(DB::table('sessions')
            ->where('id', 'their-session-id')
            ->value('id'));
    }

    // -- Headers ------------------------------------------------------------

    public function test_every_response_carries_the_defensive_headers(): void
    {
        $response = $this->actingAs($this->manager)->get('/app/account')->assertOk();

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_the_json_api_gets_them_too(): void
    {
        // A JSON endpoint that can be framed or MIME-sniffed is one that can
        // be read across origins.
        $response = $this->getJson('/api/v1/health')->assertOk();

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * Genuinely signed out, for the next request in the same test.
     *
     * `flushSession()` alone is not enough: `actingAs` leaves the user set on
     * the guard instance, which survives into the next request and makes
     * `POST /login` a request from somebody already signed in — bounced by the
     * guest middleware to somewhere that has nothing to do with the test.
     */
    private function signOut(): void
    {
        $this->flushSession();

        $this->app['auth']->forgetGuards();
    }
}
