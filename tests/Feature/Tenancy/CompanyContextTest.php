<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The X-Company-Id header and the company switcher select among memberships.
 * Neither may ever grant access to a company the user does not belong to
 * (SRS 4, API 1).
 */
class CompanyContextTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $omega;

    private Company $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $this->rival = TenantFixture::company('Rival Garments Ltd', 'RGL');

        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::factory($this->omega, 'Narayanganj Unit', 'NGJ');
        TenantFixture::factory($this->rival, 'Rival Plant', 'RVP');
    }

    /** The active company, as opposed to one merely listed in the switcher. */
    private function activeCompany(Company $company): string
    {
        return 'data-company-id="'.$company->id.'"';
    }

    public function test_context_defaults_to_the_users_default_membership(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee($this->activeCompany($this->delta), false);
    }

    public function test_a_multi_company_user_can_switch(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::addMembership($user, $this->omega, 'COMPANY_ADMIN');

        $this->actingAs($user)
            ->post('/app/switch-company', ['company_id' => $this->omega->id])
            ->assertRedirect(route('app.dashboard'));

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee($this->activeCompany($this->omega), false)
            ->assertDontSee($this->activeCompany($this->delta), false);
    }

    public function test_switching_to_a_company_the_user_does_not_belong_to_is_refused(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->actingAs($user)
            ->post('/app/switch-company', ['company_id' => $this->rival->id])
            ->assertSessionHasErrors('company_id');

        // Context must be unchanged after a refused switch.
        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee($this->activeCompany($this->delta), false)
            ->assertDontSee($this->activeCompany($this->rival), false);
    }

    public function test_a_company_id_header_naming_a_non_membership_is_forbidden(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        // 403, not 404: the caller named the tenant, so there is nothing left
        // to conceal (API 2).
        $this->actingAs($user)
            ->withHeader('X-Company-Id', $this->rival->id)
            ->get('/app/dashboard')
            ->assertForbidden();
    }

    public function test_a_company_id_header_naming_a_real_membership_is_honoured(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::addMembership($user, $this->omega, 'COMPANY_ADMIN');

        $this->actingAs($user)
            ->withHeader('X-Company-Id', $this->omega->id)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee($this->activeCompany($this->omega), false);
    }

    public function test_a_user_with_no_active_membership_is_refused(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        CompanyUser::where('user_id', $user->id)->update(['status' => 'SUSPENDED']);

        $this->actingAs($user)->get('/app/dashboard')->assertForbidden();
    }

    public function test_a_suspended_membership_cannot_be_selected_by_header(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::addMembership($user, $this->omega, 'COMPANY_ADMIN');

        CompanyUser::where('user_id', $user->id)
            ->where('company_id', $this->omega->id)
            ->update(['status' => 'SUSPENDED']);

        $this->actingAs($user)
            ->withHeader('X-Company-Id', $this->omega->id)
            ->get('/app/dashboard')
            ->assertForbidden();
    }

    public function test_the_factory_scope_is_cleared_when_switching_company(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::addMembership($user, $this->omega, 'COMPANY_ADMIN');

        TenantFixture::actingAsTenant($this->delta);
        $deltaFactoryId = $this->delta->factories()->first()->id;

        $this->actingAs($user)
            ->withSession([ResolveTenantContext::FACTORY_SCOPE_KEY => $deltaFactoryId])
            ->post('/app/switch-company', ['company_id' => $this->omega->id]);

        // Carrying a Delta factory id into Omega would either leak a foreign
        // id or silently filter every list to nothing.
        $this->assertNull(session(ResolveTenantContext::FACTORY_SCOPE_KEY));
    }

    public function test_the_response_carries_a_request_id(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $response = $this->actingAs($user)->get('/app/dashboard');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_a_client_supplied_request_id_is_echoed_back(): void
    {
        $user = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $response = $this->actingAs($user)
            ->withHeader('X-Request-Id', 'support-ticket-4821')
            ->get('/app/dashboard');

        $this->assertSame('support-ticket-4821', $response->headers->get('X-Request-Id'));
    }
}
