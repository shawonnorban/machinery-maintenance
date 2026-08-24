<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Identity\Actions\AttemptLogin;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The side of the product that runs the business (SRS 3.1, 5, 5.4, 40).
 *
 * Two properties matter more than the screens themselves. A platform
 * administrator can see that a customer exists and how large they are, and
 * nothing whatever about their machines. And every route into a customer's
 * data leaves a trail the customer can read.
 */
class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

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

        // Platform staff belong to no company at all. That is the point: every
        // role in this system hangs off a company or a factory, and giving
        // platform staff one would put them inside a tenant.
        $this->staff = User::create([
            'name' => 'Platform Support',
            'email' => 'support@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        // A second factor is compulsory for platform staff (SRS 50.3), and the
        // enforcement is live, so an account without one would be sent to enrol
        // instead of reaching any of these screens. Enrolled here rather than
        // exempted, because in a real deployment it would be.
        TenantFixture::enrolMfa($this->staff);
    }

    // -- The gate -----------------------------------------------------------

    public function test_a_customer_cannot_find_the_platform_area(): void
    {
        // 404, not 403. A company owner has no business learning that a
        // platform area exists, let alone that they were refused entry to it.
        $this->actingAs($this->owner)->get('/platform')->assertNotFound();

        $this->assertSame(1, AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('entity_label', 'PLATFORM_ACCESS_DENIED')
            ->count());
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/platform')->assertRedirect(route('login'));
    }

    public function test_platform_staff_see_the_customer_list(): void
    {
        $this->actingAs($this->staff)
            ->get('/platform')
            ->assertOk()
            ->assertSee('Delta Apparels Ltd')
            ->assertSee('DAL');
    }

    // -- Onboarding ---------------------------------------------------------

    public function test_a_customer_can_be_taken_on(): void
    {
        $response = $this->actingAs($this->staff)
            ->post('/platform/tenants', [
                'name' => 'Rival Textiles Ltd',
                'code' => 'RTL',
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => 'bn',
                'factory_name' => 'Savar Unit',
                'factory_code' => 'SAV',
                'owner_name' => 'Nusrat Jahan',
                'owner_email' => 'nusrat@rival.test',
            ])
            ->assertRedirect();

        $company = Company::withoutGlobalScope(TenantScope::class)
            ->where('code', 'RTL')
            ->firstOrFail();

        // All three, or the customer cannot use what they bought: a company
        // with no owner is a tenant nobody can sign in to, and one with no
        // factory cannot hold a machine.
        $this->assertSame(1, Factory::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)->count());

        $owner = User::where('email', 'nusrat@rival.test')->firstOrFail();

        $this->assertTrue($owner->belongsToCompany($company->id));

        // The password is readable exactly once, on the screen that follows.
        $password = $response->getSession()->get('owner_password');

        $this->assertIsString($password);

        $this->assertTrue(app(AttemptLogin::class)
            ->verify('nusrat@rival.test', $password, '127.0.0.1')
            ->is($owner));
    }

    public function test_the_new_owner_can_actually_do_something(): void
    {
        $this->actingAs($this->staff)->post('/platform/tenants', [
            'name' => 'Rival Textiles Ltd',
            'code' => 'RTL',
            'base_currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'default_locale' => 'en',
            'factory_name' => 'Savar Unit',
            'factory_code' => 'SAV',
            'owner_name' => 'Nusrat Jahan',
            'owner_email' => 'nusrat@rival.test',
        ]);

        $owner = User::where('email', 'nusrat@rival.test')->firstOrFail();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // The whole point of onboarding: somebody can sign in and reach the
        // product. Until this existed, a company could only be created by hand
        // in the database.
        //
        // Where they land is their own account screen rather than the
        // dashboard, and that is correct: a Company Owner must hold a second
        // factor (SRS 50.3), and a brand new one does not yet. They are signed
        // in and sent to enrol — not shut out, which is the distinction the
        // whole enforcement design turns on.
        $this->actingAs($owner)->get('/app/dashboard')->assertRedirect(route('app.account'));
        $this->assertAuthenticatedAs($owner);

        $this->actingAs($owner)->get('/app/account')->assertOk();

        // Once enrolled, the product opens up.
        TenantFixture::enrolMfa($owner);

        $this->actingAs($owner->fresh())->get('/app/dashboard')->assertOk();
        $this->actingAs($owner->fresh())->get('/app/assets')->assertOk();
    }

    public function test_a_duplicate_code_is_refused(): void
    {
        $this->actingAs($this->staff)
            ->from('/platform/tenants/new')
            ->post('/platform/tenants', [
                'name' => 'Another Delta',
                'code' => 'DAL',
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => 'en',
                'factory_name' => 'Unit',
                'factory_code' => 'U1',
                'owner_name' => 'Somebody',
                'owner_email' => 'somebody@delta2.test',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_nothing_is_half_created_when_onboarding_fails(): void
    {
        $before = Company::withoutGlobalScope(TenantScope::class)->count();

        $this->actingAs($this->staff)
            ->from('/platform/tenants/new')
            ->post('/platform/tenants', [
                'name' => 'Rival Textiles Ltd',
                'code' => 'RTL',
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => 'en',
                'factory_name' => 'Savar Unit',
                'factory_code' => 'SAV',
                'owner_name' => 'Nusrat Jahan',
                // Already taken by the fixture owner.
                'owner_email' => 'owner@delta.test',
            ])
            ->assertSessionHasErrors('owner_email');

        // A company with no owner would need somebody with database access to
        // finish the job, which is the position this action exists to end.
        $this->assertSame($before, Company::withoutGlobalScope(TenantScope::class)->count());
    }

    // -- Contract (SRS 40) --------------------------------------------------

    public function test_a_contract_supersedes_rather_than_edits(): void
    {
        $this->actingAs($this->staff)->post('/platform/tenants/'.$this->delta->id.'/contract', [
            'contract_number' => 'SUB-0001',
            'start_date' => '2026-01-01',
            'billing_cycle' => 'MONTHLY',
            'amount' => '25000',
            'currency' => 'BDT',
            'grace_period_days' => 14,
            'overage_policy' => 'WARN_ONLY',
        ])->assertRedirect();

        $this->actingAs($this->staff)->post('/platform/tenants/'.$this->delta->id.'/contract', [
            'contract_number' => 'SUB-0002',
            'start_date' => '2026-07-01',
            'billing_cycle' => 'YEARLY',
            'amount' => '270000',
            'currency' => 'BDT',
            'grace_period_days' => 30,
            'overage_policy' => 'ALLOW_AND_BILL',
        ])->assertRedirect();

        $contracts = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $this->delta->id)
            ->orderBy('contract_number')
            ->get();

        // An invoice already raised under the old terms has been sent to
        // somebody. Editing what it was calculated from makes it
        // unexplainable, so the old one is archived instead.
        $this->assertSame(2, $contracts->count());
        $this->assertSame('ARCHIVED', $contracts[0]->status);
        $this->assertSame('ACTIVE', $contracts[1]->status);
    }

    // -- Suspension ---------------------------------------------------------

    public function test_suspending_signs_everybody_out_and_deletes_nothing(): void
    {
        DB::table('sessions')->insert([
            'id' => 'owner-session',
            'user_id' => $this->owner->id,
            'ip_address' => '10.0.0.4',
            'user_agent' => 'Firefox',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/suspend')
            ->assertRedirect();

        $this->assertSame('SUSPENDED', $this->delta->fresh()->status);
        $this->assertNull(DB::table('sessions')->where('id', 'owner-session')->value('id'));

        // SRS 40: cancellation does not delete data. A customer who settles an
        // invoice on Friday finds everything where they left it on Monday.
        $this->assertNotNull(User::find($this->owner->id));

        $this->actingAs($this->staff)->post('/platform/tenants/'.$this->delta->id.'/suspend');

        $this->assertSame('ACTIVE', $this->delta->fresh()->status);
    }

    // -- Support access (SRS 5.4) -------------------------------------------

    public function test_opening_access_needs_a_real_reason(): void
    {
        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/tenants/'.$this->delta->id.'/support', [
                'reason' => 'looking',
                'hours' => 2,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, SupportGrant::count());
    }

    public function test_opening_access_tells_the_customer_and_the_audit_log(): void
    {
        $this->openGrant();

        $grant = SupportGrant::firstOrFail();

        $this->assertTrue($grant->isActive());
        // Time-boxed by construction: there is no "until revoked".
        $this->assertTrue($grant->expires_at->lessThanOrEqualTo(now()->addHours(2)->addMinute()));

        $this->assertSame(1, AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('entity_label', 'SUPPORT_GRANT_OPENED')->count());

        // The customer is told, in the product, by name and with the reason.
        $notification = Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'SUPPORT_ACCESS')
            ->where('user_id', $this->owner->id)
            ->firstOrFail();

        $this->assertStringContainsString('Platform Support', $notification->body);
        $this->assertStringContainsString('Ticket 4471', $notification->body);
    }

    public function test_a_grant_alone_shows_nobody_anything(): void
    {
        $this->openGrant();

        // Permission to enter is not entry. The platform administrator still
        // cannot open a single screen of the customer's application.
        $this->actingAs($this->staff)->get('/app/assets')->assertForbidden();
    }

    public function test_entering_acts_as_a_named_user_and_is_audited(): void
    {
        $grant = $this->openGrant();

        $this->actingAs($this->staff)
            ->post('/platform/support/'.$grant->id.'/enter', ['user_id' => $this->owner->id])
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($this->owner);

        $this->assertSame(1, AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('entity_label', 'SUPPORT_SESSION_STARTED')->count());

        // Every page says so, unmissably. The failure this prevents is
        // somebody forgetting whose account they are in.
        $this->get('/app/dashboard')
            ->assertOk()
            ->assertSee(__('platform.support_session_banner'));
    }

    public function test_work_done_during_support_records_who_was_really_behind_it(): void
    {
        $grant = $this->openGrant();

        $this->actingAs($this->staff)
            ->post('/platform/support/'.$grant->id.'/enter', ['user_id' => $this->owner->id]);

        // A spare part, because it is one of the models the audit observer
        // watches. The point is not the part; it is that an ordinary write
        // made during a support session carries the trail on its own, with
        // nothing in the inventory module knowing support exists.
        $this->post('/app/inventory/parts', [
            'part_number' => 'JK-DDL9000-HOOK',
            'name' => 'Rotary hook',
            'unit' => 'PCS',
        ])->assertRedirect();

        // The column the audit screen has always shown in red, finally
        // populated by something. Until now it described a feature that did
        // not exist.
        $this->assertSame($this->staff->id, AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $this->delta->id)
            ->whereNotNull('impersonated_by')
            ->value('impersonated_by'));
    }

    public function test_leaving_puts_the_platform_account_back_and_is_audited(): void
    {
        $grant = $this->openGrant();

        $this->actingAs($this->staff)
            ->post('/platform/support/'.$grant->id.'/enter', ['user_id' => $this->owner->id]);

        $this->post('/app/support/leave')->assertRedirect(route('platform.tenants'));

        $this->assertAuthenticatedAs($this->staff);

        // "When did they leave" is half the answer to "what could they have
        // seen"; a grant that records only its beginning tells a customer
        // nothing about how long somebody was inside.
        $this->assertSame(1, AuditLog::withoutGlobalScope(TenantScope::class)
            ->where('entity_label', 'SUPPORT_SESSION_ENDED')->count());
    }

    public function test_another_administrators_grant_cannot_be_used(): void
    {
        $grant = $this->openGrant();

        $other = User::create([
            'name' => 'Second Support',
            'email' => 'second@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        TenantFixture::enrolMfa($other);

        // A grant names one person. Sharing one would make the audit trail say
        // somebody was inside who was not.
        $this->actingAs($other)
            ->post('/platform/support/'.$grant->id.'/enter', ['user_id' => $this->owner->id])
            ->assertNotFound();
    }

    public function test_an_expired_grant_cannot_be_entered(): void
    {
        $grant = $this->openGrant();

        $grant->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/support/'.$grant->id.'/enter', ['user_id' => $this->owner->id])
            ->assertSessionHasErrors('grant');

        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_a_person_outside_the_company_cannot_be_acted_as(): void
    {
        $grant = $this->openGrant();

        $outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@elsewhere.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
        ]);

        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/support/'.$grant->id.'/enter', ['user_id' => $outsider->id])
            ->assertSessionHasErrors('user_id');
    }

    private function openGrant(): SupportGrant
    {
        $this->actingAs($this->staff)->post('/platform/tenants/'.$this->delta->id.'/support', [
            'reason' => 'Ticket 4471: work orders missing after a factory transfer.',
            'hours' => 2,
        ]);

        return SupportGrant::firstOrFail();
    }
}
