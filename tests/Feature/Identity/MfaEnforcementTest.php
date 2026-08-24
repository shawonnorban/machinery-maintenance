<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\ManageMfa;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\Totp;
use App\Modules\Settings\Models\Setting;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Where a second factor is compulsory (SRS 50.3).
 *
 * "MFA is enforceable per company policy, and is mandatory for Platform Super
 * Admin and Company Owner roles."
 *
 * The property that matters most is not that the rule is enforced but that
 * enforcing it never locks anybody out. Refusing the password would mean
 * switching the policy on at nine in the morning leaves the whole company
 * standing outside; these tests hold down that somebody who has not enrolled
 * gets in, gets sent to the one screen where they can, and can leave again.
 */
class MfaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
    }

    // -- Compulsory by role -------------------------------------------------

    public function test_a_company_owner_must_hold_a_second_factor(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test', withMfa: false);
        TenantFixture::actingAsTenant($this->delta);

        // Signed in, not shut out. Anything else would lock out an account
        // that has never had the chance to enrol.
        $this->actingAs($owner)
            ->get('/app/dashboard')
            ->assertRedirect(route('app.account'));

        $this->assertAuthenticatedAs($owner);
    }

    public function test_a_technician_is_left_alone_unless_the_company_says_otherwise(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'karim@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/dashboard')->assertOk();
    }

    public function test_a_platform_administrator_must_hold_one_too(): void
    {
        $staff = User::create([
            'name' => 'Platform Support',
            'email' => 'support@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        // The account that can open a support grant and step inside any
        // customer is the last one that should stand behind a single password.
        $this->actingAs($staff)
            ->get('/platform')
            ->assertRedirect(route('app.account'));
    }

    // -- Compulsory by company policy ---------------------------------------

    public function test_a_company_can_require_it_of_everybody(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'karim@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        Setting::create([
            'company_id' => $this->delta->id,
            'key' => 'security.require_mfa',
            'value' => true,
            'value_type' => 'BOOL',
        ]);

        $this->actingAs($technician)
            ->get('/app/dashboard')
            ->assertRedirect(route('app.account'));
    }

    // -- Never a dead end ---------------------------------------------------

    public function test_the_road_to_enrolling_stays_open(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test', withMfa: false);
        TenantFixture::actingAsTenant($this->delta);

        // If the rule blocked these, it would close the only path to obeying
        // it — and the only way out.
        $this->actingAs($owner)->get('/app/account')->assertOk();
        $this->actingAs($owner)->post('/app/account/mfa')->assertRedirect();
        $this->actingAs($owner)->post('/logout')->assertRedirect(route('login'));
    }

    public function test_enrolling_ends_the_redirect(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test', withMfa: false);
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($owner)->get('/app/dashboard')->assertRedirect(route('app.account'));

        $mfa = app(ManageMfa::class);
        $mfa->begin($owner, 'Test');
        $owner->refresh();
        $mfa->confirm($owner, app(Totp::class)->codeAt($owner->mfa_secret));

        $this->actingAs($owner->fresh())->get('/app/dashboard')->assertOk();
    }

    public function test_the_account_screen_says_why_somebody_was_sent_there(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test', withMfa: false);
        TenantFixture::actingAsTenant($this->delta);

        // A redirect with no explanation reads as a bug. Somebody who asked for
        // the dashboard and got their own account screen needs to be told what
        // happened and what to do about it.
        $this->actingAs($owner)
            ->get('/app/dashboard')
            ->assertSessionHas('mfa_required');

        $this->actingAs($owner)
            ->withSession(['mfa_required' => true])
            ->get('/app/account')
            ->assertOk()
            ->assertSee(__('account.mfa_required_now'));
    }

    // -- Support sessions are exempt ----------------------------------------

    public function test_somebody_in_a_support_session_is_not_asked_to_enrol(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test', withMfa: false);
        TenantFixture::actingAsTenant($this->delta);

        // Whether the customer's own account holds a second factor is decided
        // by the customer's policy, and asking support staff to enrol on their
        // behalf would leave a secret behind in somebody else's account.
        $this->actingAs($owner)
            ->withSession(['impersonated_by' => 'some-platform-user-id'])
            ->get('/app/dashboard')
            ->assertOk();
    }

    // -- The API ------------------------------------------------------------

    public function test_a_json_caller_is_told_in_its_own_language(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test', withMfa: false);
        TenantFixture::actingAsTenant($this->delta);

        // A redirect to an HTML screen means nothing to a client expecting
        // JSON, so it gets the code it can branch on instead.
        $this->actingAs($owner)
            ->getJson('/app/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'MFA_REQUIRED');
    }
}
