<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Actions\ManageMfa;
use App\Modules\Identity\Models\MfaRecoveryCode;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\Totp;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The account a person owns rather than administers (SRS 50.1–50.4).
 *
 * The rules worth holding down are the ones that decide whether a stolen
 * password is enough: that nothing is signed in until a second factor is
 * answered, that turning the factor off needs a code and not merely the
 * session, and that changing a password takes every other way in with it.
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

        RateLimiter::clear('mfa:'.$this->manager->id);
    }

    // -- The one-time password itself ---------------------------------------

    public function test_the_code_matches_the_published_test_vectors(): void
    {
        $totp = new Totp;

        // RFC 6238 appendix B, SHA-1: the secret is the ASCII digits
        // "12345678901234567890", base32-encoded. If this drifts, every
        // authenticator app on every phone disagrees with us and nobody can
        // sign in — so it is pinned to the standard rather than to ourselves.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $totp->codeAt($secret, 59));
        $this->assertSame('081804', $totp->codeAt($secret, 1111111109));
        $this->assertSame('005924', $totp->codeAt($secret, 1234567890));
        $this->assertSame('279037', $totp->codeAt($secret, 2000000000));
    }

    public function test_a_code_is_accepted_a_little_either_side_of_now(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;

        // Phones drift and people type slowly. One step each way; two is a
        // wider net for a brute force and buys nothing.
        $this->assertTrue($totp->verify($secret, $totp->codeAt($secret, $now - 30), $now));
        $this->assertTrue($totp->verify($secret, $totp->codeAt($secret, $now + 30), $now));
        $this->assertFalse($totp->verify($secret, $totp->codeAt($secret, $now - 90), $now));
    }

    // -- Enrolment ----------------------------------------------------------

    public function test_enrolment_takes_two_steps(): void
    {
        $this->actingAs($this->manager)->post('/app/account/mfa')->assertRedirect();

        $this->manager->refresh();

        // A secret exists, and the factor is not yet in force. Somebody who
        // scans the QR and drops the phone in a dye vat is where they started,
        // not locked out.
        $this->assertNotNull($this->manager->mfa_secret);
        $this->assertFalse($this->manager->hasMfa());

        $code = app(Totp::class)->codeAt($this->manager->mfa_secret);

        $this->actingAs($this->manager)
            ->post('/app/account/mfa/confirm', ['code' => $code])
            ->assertRedirect()
            ->assertSessionHas('recovery_codes');

        $this->assertTrue($this->manager->fresh()->hasMfa());
    }

    public function test_a_wrong_code_does_not_switch_the_factor_on(): void
    {
        $this->actingAs($this->manager)->post('/app/account/mfa');

        $this->actingAs($this->manager)
            ->from('/app/account')
            ->post('/app/account/mfa/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($this->manager->fresh()->hasMfa());
    }

    public function test_the_secret_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->manager)->post('/app/account/mfa');

        $stored = (string) DB::table('users')
            ->where('id', $this->manager->id)
            ->value('mfa_secret');

        // The one credential in this schema a database dump could be used
        // with, so the key lives in the environment rather than beside it.
        $this->assertNotSame($this->manager->fresh()->mfa_secret, $stored);
        $this->assertStringNotContainsString($this->manager->fresh()->mfa_secret, $stored);
    }

    // -- Signing in ---------------------------------------------------------

    public function test_a_password_alone_does_not_sign_in_an_account_with_a_second_factor(): void
    {
        $this->enableMfa($this->manager);

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/mfa/challenge');

        // Nothing is authenticated while the challenge is on screen. Somebody
        // who closes the tab here is signed out, not signed in.
        $this->assertGuest();
    }

    public function test_the_code_finishes_the_sign_in(): void
    {
        $secret = $this->enableMfa($this->manager);

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ]);

        $this->post('/mfa/challenge', ['code' => app(Totp::class)->codeAt($secret)])
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($this->manager);
    }

    public function test_a_wrong_code_leaves_the_person_signed_out(): void
    {
        $this->enableMfa($this->manager);

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ]);

        $this->from('/mfa/challenge')
            ->post('/mfa/challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_the_challenge_cannot_be_reached_without_the_password_first(): void
    {
        $this->enableMfa($this->manager);

        // No half-finished login in the session, so there is nobody to
        // challenge and nothing to complete.
        $this->get('/mfa/challenge')->assertRedirect(route('login'));

        $this->post('/mfa/challenge', ['code' => '000000'])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $codes = $this->enableMfaWithCodes($this->manager);

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ]);

        $this->post('/mfa/challenge', ['code' => $codes[0]])->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($this->manager);

        // A recovery code that still works after being used is a password
        // somebody has left in a drawer.
        $this->post('/logout');
        $this->flushSession();

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ]);

        $this->from('/mfa/challenge')
            ->post('/mfa/challenge', ['code' => $codes[0]])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_repeated_wrong_codes_are_throttled(): void
    {
        $this->enableMfa($this->manager);

        $mfa = app(ManageMfa::class);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($mfa->verifyChallenge($this->manager, '000000'));
        }

        // Six digits is a million possibilities, which is a great many for a
        // person and very few for a script.
        $this->expectException(ValidationException::class);

        $mfa->verifyChallenge($this->manager, '000000');
    }

    // -- Turning it off -----------------------------------------------------

    public function test_turning_the_factor_off_needs_a_code_not_just_the_session(): void
    {
        $secret = $this->enableMfa($this->manager);

        $this->actingAs($this->manager)
            ->from('/app/account')
            ->delete('/app/account/mfa', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        // Somebody who has taken over a session must not be able to remove the
        // factor that would have stopped them.
        $this->assertTrue($this->manager->fresh()->hasMfa());

        $this->actingAs($this->manager)
            ->delete('/app/account/mfa', ['code' => app(Totp::class)->codeAt($secret)])
            ->assertRedirect();

        $this->assertFalse($this->manager->fresh()->hasMfa());
        $this->assertSame(0, MfaRecoveryCode::where('user_id', $this->manager->id)->count());
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

    public function test_the_account_screen_lists_this_device(): void
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

        // And their own row goes when they ask for it.
        DB::table('sessions')->insert([
            'id' => 'my-session-id',
            'user_id' => $this->manager->id,
            'ip_address' => '10.0.0.4',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Firefox/128',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->manager)
            ->delete('/app/account/sessions/my-session-id')
            ->assertRedirect();

        $this->assertNull(DB::table('sessions')->where('id', 'my-session-id')->value('id'));
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

    // -- Helpers ------------------------------------------------------------

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

    private function enableMfa(User $user): string
    {
        $this->enableMfaWithCodes($user);

        return $user->fresh()->mfa_secret;
    }

    /**
     * @return list<string>
     */
    private function enableMfaWithCodes(User $user): array
    {
        $totp = app(Totp::class);
        $mfa = app(ManageMfa::class);

        $mfa->begin($user, 'Test');
        $user->refresh();

        $codes = $mfa->confirm($user, $totp->codeAt($user->mfa_secret));

        $user->refresh();
        $this->flushSession();

        return $codes;
    }
}
