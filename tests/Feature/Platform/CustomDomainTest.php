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
use Tests\Support\PlatformFixture;
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
 *
 * Every case probes `/app/support/tickets` rather than the `/app/dashboard`
 * this used to check — that screen is gone (Phase D/F, docs/12-Stack-
 * Migration-Implementation-Plan.md), fully replaced by the Next.js app.
 * `/app/support/tickets` is the one Blade screen this module keeps alive
 * (Platform's own tenant-facing "my support tickets", not yet ported),
 * needs no permission beyond company membership, and — unlike `/app/
 * locale`/`/app/switch-company` — isn't exempt from `ResolveTenantContext`'s
 * own checks, so it still proves exactly which company the host/session
 * resolved to.
 */
class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private string $staffToken;

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

        $this->staff = PlatformFixture::staff();
        $this->staffToken = PlatformFixture::token($this->staff);

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
            ->get('http://maintenance.deltaapparels.com/app/support/tickets')
            ->assertOk();

        $this->assertFalse($this->domain()->isVerified());
    }

    public function test_a_proven_domain_puts_the_customer_in_their_own_system(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');
        $this->fakeDnsFor($this->domain());

        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->postJson('/api/v1/platform/domains/'.$this->domain()->id.'/verify')
            ->assertOk();

        $this->assertTrue($this->domain()->isVerified());

        $this->actingAs($this->owner)
            ->get('http://maintenance.deltaapparels.com/app/support/tickets')
            ->assertOk();
    }

    public function test_the_check_says_not_yet_rather_than_wrong(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        // No record published. DNS takes time, and the honest answer is "not
        // yet" — a customer who has done everything right should not be told
        // they have got it wrong. 409, not a validation error: nothing about
        // the request is wrong, the DNS record simply has not propagated yet
        // (`PlatformDomainApiController::verify`).
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->postJson('/api/v1/platform/domains/'.$this->domain()->id.'/verify')
            ->assertStatus(409);

        $this->assertFalse($this->domain()->isVerified());
    }

    public function test_one_customer_cannot_take_anothers_address(): void
    {
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->postJson('/api/v1/platform/tenants/'.$this->omega->id.'/domains', [
                'kind' => 'CUSTOM',
                'host' => 'maintenance.deltaapparels.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('host');

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
            ->get('http://maintenance.deltaapparels.com/app/support/tickets')
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
            ->get('http://maintenance.deltaapparels.com/app/support/tickets')
            ->assertOk();
    }

    public function test_a_nonsense_address_is_refused(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/domains', [
                'kind' => 'CUSTOM',
                'host' => 'not a hostname',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('host');
    }

    public function test_only_a_working_address_can_be_primary(): void
    {
        $this->addDomain('SUBDOMAIN', 'delta');
        $this->addDomain('CUSTOM', 'maintenance.deltaapparels.com');

        $custom = CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', 'maintenance.deltaapparels.com')->firstOrFail();

        // 409, not a validation error — same reasoning as the verify() case
        // above (`PlatformDomainApiController::primary`).
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->postJson('/api/v1/platform/domains/'.$custom->id.'/primary')
            ->assertStatus(409);
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
            ->get('http://maintenance.deltaapparels.com/app/support/tickets')
            ->assertForbidden();

        // The stranger's request left an active company in the session, and
        // platform staff belong to no company: carrying it over would refuse
        // them their own area.
        $this->signOut();

        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->deleteJson('/api/v1/platform/domains/'.$domainId)
            ->assertNoContent();

        $this->assertSame(0, CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', 'maintenance.deltaapparels.com')->count());

        // And no longer refused once it belongs to nobody: the host has gone
        // back to being an ordinary name with no say in the matter.
        $this->actingAs($stranger)
            ->get('http://maintenance.deltaapparels.com/app/support/tickets')
            ->assertOk();
    }

    private function signOut(): void
    {
        $this->flushSession();

        $this->app['auth']->forgetGuards();
    }

    private function addDomain(string $kind, string $host): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken)
            ->postJson('/api/v1/platform/tenants/'.$this->delta->id.'/domains', [
                'kind' => $kind,
                'host' => $host,
            ])
            ->assertCreated();
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
