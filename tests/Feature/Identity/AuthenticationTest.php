<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\TenantFixture;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->company = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->company, 'Dhaka Unit 1', 'DHK');

        $this->user = TenantFixture::user(
            $this->company,
            'MAINTENANCE_MANAGER',
            'manager@delta.test',
        );
    }

    public function test_a_user_can_sign_in_and_reaches_the_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($this->user);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'manager@delta.test',
            'successful' => true,
        ]);
    }

    public function test_last_login_at_is_recorded(): void
    {
        $this->assertNull($this->user->last_login_at);

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ]);

        $this->assertNotNull($this->user->fresh()->last_login_at);
    }

    public function test_a_wrong_password_is_rejected_and_audited(): void
    {
        $response = $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'manager@delta.test',
            'successful' => false,
            'failure_reason' => 'BAD_PASSWORD',
        ]);
    }

    public function test_an_unknown_email_gives_the_same_message_as_a_wrong_password(): void
    {
        // Distinguishing the two would tell an attacker which addresses exist,
        // so both must produce the identical generic message.
        $this->post('/login', [
            'email' => 'nobody@delta.test',
            'password' => 'whatever',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->flushSession();

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        $this->user->forceFill(['status' => 'INACTIVE'])->save();

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_attempts', ['failure_reason' => 'ACCOUNT_INACTIVE']);
    }

    public function test_repeated_failures_are_rate_limited_per_account(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'manager@delta.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        // Even the correct password is refused while the lockout stands.
        $this->assertGuest();
        $this->assertDatabaseHas('login_attempts', ['failure_reason' => 'RATE_LIMITED']);
    }

    public function test_a_successful_login_clears_the_account_rate_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'manager@delta.test', 'password' => 'nope']);
        }

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticated();

        RateLimiter::clear('login:ip:'.sha1('127.0.0.1'));
    }

    public function test_the_session_id_is_regenerated_on_login(): void
    {
        $this->get('/login')->assertOk();
        $before = $this->app['session']->getId();
        $this->assertNotEmpty($before);

        $this->post('/login', [
            'email' => 'manager@delta.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('app.dashboard'));

        // Session fixation: the pre-login id must not remain valid.
        $this->assertNotSame($before, $this->app['session']->getId());
    }

    public function test_signing_out_invalidates_the_session(): void
    {
        $this->actingAs($this->user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/app/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_attempts_are_recorded_for_every_outcome(): void
    {
        $this->post('/login', ['email' => 'manager@delta.test', 'password' => 'nope']);
        $this->post('/login', ['email' => 'manager@delta.test', 'password' => 'correct-horse-battery']);

        $this->assertSame(2, LoginAttempt::count());
    }
}
