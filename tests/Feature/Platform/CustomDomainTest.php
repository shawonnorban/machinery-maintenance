<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\CompanyDomain;
use App\Modules\Tenancy\Services\DomainVerifier;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * A customer on their own address.
 *
 * The security question here is the whole feature: a row saying
 * maintenance.some-other-company.com is a claim, not a fact, and honouring an
 * unproven claim would let one customer put their name on an address they do
 * not own and collect another company's sign-ins. So an unverified domain
 * resolves to nobody, and the proof is a DNS record only the domain's owner
 * can publish.
 *
 * The verifier is faked because the real one asks the network, and a test that
 * depends on DNS is a test that fails on a train.
 */
class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Company $delta;

    private Company $omega;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($this->omega, 'Savar Unit 1', 'SAV');
        TenantFixture::actingAsTenant($this->omega);

        $this->staff = User::create([
            'name' => 'Platform Support',
            'email' => 'support@platform.test',
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        config(['tenancy.platform_host' => 'app.example.com']);
    }

    public function test_a_subdomain_works_immediately(): void
    {
        $this->addDomain('SUBDOMAIN', 'delta');

        $domain = $this->domain();

        // Nothing to prove: the host is one we already control.
        $this->assertSame('delta.app.example.com', $domain->host);
        $this->assertTrue($domain->isVerified());
        $this->assertTrue($domain->is_primary);
    }

    public function test_a_customers_own_domain_starts_unproven(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        $domain = $this->domain();

        $this->assertFalse($domain->isVerified());
        $this->assertNotEmpty($domain->verification_token);
        $this->assertSame('_mm-verify.maintenance.deltaapparels.com', $domain->verificationRecordName());
    }

    public function test_an_unproven_domain_pins_nobody(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        TenantFixture::actingAsTenant($this->omega);
        $stranger = TenantFixture::user($this->omega, 'COMPANY_OWNER', 'owner@omega.test');

        // An unrecognised host is just a name pointing at the app: it grants
        // nothing and decides nothing, and the visitor resolves from their own
        // membership as they always did. The same request against the *proven*
        // address is refused — see the test below. That difference is the
        // whole of what verification buys.
        $this->actingAs($stranger)
            ->get('http://maintenance.deltaapparels.com/app/dashboard')
            ->assertOk();

        $this->assertFalse($this->domain()->isVerified());
    }

    public function test_a_proven_domain_puts_the_customer_in_their_own_system(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');
        $this->fakeDnsFor($this->domain());

        $this->actingAs($this->staff)
            ->post('/platform/domains/'.$this->domain()->id.'/verify')
            ->assertRedirect();

        $this->assertTrue($this->domain()->isVerified());

        $this->actingAs($this->owner)
            ->get('http://maintenance.deltaapparels.com/app/dashboard')
            ->assertOk();
    }

    public function test_the_check_says_not_yet_rather_than_wrong(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        // No record published. DNS takes time, and the honest answer is "not
        // yet" — a customer who has done everything right should not be told
        // they have got it wrong.
        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/domains/'.$this->domain()->id.'/verify')
            ->assertSessionHasErrors('host');

        $this->assertFalse($this->domain()->isVerified());
    }

    public function test_one_customer_cannot_take_anothers_address(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->omega->id)
            ->post('/platform/tenants/'.$this->omega->id.'/domains', [
                'kind' => 'CUSTOM',
                'host' => 'maintenance.deltaapparels.com',
            ])
            ->assertSessionHasErrors('host');

        $this->assertSame(1, CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', 'maintenance.deltaapparels.com')->count());
    }

    public function test_somebody_from_another_company_is_refused_on_this_address(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');
        $this->fakeDnsFor($this->domain());
        $this->domain()->forceFill(['verified_at' => now()])->save();

        TenantFixture::actingAsTenant($this->omega);
        $stranger = TenantFixture::user($this->omega, 'COMPANY_OWNER', 'owner@omega.test');

        // The host names a company they do not belong to. The address decides
        // which tenant, membership still decides whether they get in.
        $this->actingAs($stranger)
            ->get('http://maintenance.deltaapparels.com/app/dashboard')
            ->assertForbidden();
    }

    public function test_the_address_beats_a_stale_session(): void
    {
        TenantFixture::addMembership($this->owner, $this->omega, 'COMPANY_OWNER');

        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');
        $this->domain()->forceFill(['verified_at' => now()])->save();

        // Session says Omega, the address says Delta. Somebody who opens a
        // company's own address should land in that company, not in whichever
        // one they had open in another tab an hour ago.
        $this->actingAs($this->owner)
            ->withSession(['active_company_id' => $this->omega->id])
            ->get('http://maintenance.deltaapparels.com/app/dashboard')
            ->assertOk();
    }

    public function test_a_nonsense_address_is_refused(): void
    {
        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/tenants/'.$this->delta->id.'/domains', [
                'kind' => 'CUSTOM',
                'host' => 'not a hostname',
            ])
            ->assertSessionHasErrors('host');
    }

    public function test_only_a_working_address_can_be_primary(): void
    {
        $this->addDomain('SUBDOMAIN', 'delta');
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        $custom = CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', 'maintenance.deltaapparels.com')->firstOrFail();

        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/domains/'.$custom->id.'/primary')
            ->assertSessionHasErrors('host');
    }

    public function test_removing_an_address_stops_it_deciding_anything(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');
        $this->domain()->forceFill(['verified_at' => now()])->save();
        $domainId = $this->domain()->id;

        TenantFixture::actingAsTenant($this->omega);
        $stranger = TenantFixture::user($this->omega, 'COMPANY_OWNER', 'owner@omega.test');

        // Refused while the address belongs to Delta.
        $this->actingAs($stranger)
            ->get('http://maintenance.deltaapparels.com/app/dashboard')
            ->assertForbidden();

        // The stranger's request left an active company in the session, and
        // platform staff belong to no company: carrying it over would refuse
        // them their own area.
        $this->signOut();

        // Absolute, and it has to be. A relative URL in a test is resolved
        // against the *previous* request's host, so this one would go out on
        // maintenance.deltaapparels.com — where platform staff, being members
        // of no company, are correctly refused.
        $this->actingAs($this->staff)
            ->delete('http://localhost/platform/domains/'.$domainId)
            ->assertRedirect();

        $this->signOut();

        $this->assertSame(0, CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', 'maintenance.deltaapparels.com')->count());

        // And no longer refused once it belongs to nobody: the host has gone
        // back to being an ordinary name with no say in the matter.
        $this->actingAs($stranger)
            ->get('http://maintenance.deltaapparels.com/app/dashboard')
            ->assertOk();
    }

    private function signOut(): void
    {
        $this->flushSession();

        $this->app['auth']->forgetGuards();
    }

    private function addDomain(string $kind, string $host): void
    {
        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/domains', [
                'kind' => $kind,
                'host' => $host,
            ])
            ->assertRedirect();
    }

    private function domain(): CompanyDomain
    {
        return CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $this->delta->id)
            ->orderByDesc('created_at')
            ->firstOrFail();
    }

    /**
     * Stands in for the record the customer would publish.
     */
    private function fakeDnsFor(CompanyDomain $domain): void
    {
        $this->app->bind(DomainVerifier::class, fn (): DomainVerifier => new class($domain->verification_token) extends DomainVerifier
        {
            public function __construct(private readonly string $token) {}

            protected function txtRecords(string $name): array
            {
                return [$this->token];
            }
        });
    }
}
