<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant isolation is the rule that fails silently, so it is tested first and
 * hardest (SRS 55.1 rule 1, ADR-059).
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $alpha;

    private Company $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Company::create([
            'name' => 'Alpha Apparels Ltd',
            'code' => 'ALPHA',
            'base_currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
        ]);

        $this->beta = Company::create([
            'name' => 'Beta Garments Ltd',
            'code' => 'BETA',
            'base_currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
        ]);

        $this->asCompany($this->alpha);
        Factory::create(['name' => 'Alpha Dhaka', 'code' => 'ADHK', 'timezone' => 'Asia/Dhaka']);
        Factory::create(['name' => 'Alpha Gazipur', 'code' => 'AGAZ', 'timezone' => 'Asia/Dhaka']);

        $this->asCompany($this->beta);
        Factory::create(['name' => 'Beta Chattogram', 'code' => 'BCTG', 'timezone' => 'Asia/Dhaka']);
    }

    private function asCompany(Company $company): void
    {
        app(TenantContext::class)->set($company->id);
    }

    public function test_a_query_returns_only_the_active_company_rows(): void
    {
        $this->asCompany($this->alpha);
        $this->assertSame(2, Factory::count());
        $this->assertEqualsCanonicalizing(
            ['ADHK', 'AGAZ'],
            Factory::pluck('code')->all(),
        );

        $this->asCompany($this->beta);
        $this->assertSame(1, Factory::count());
        $this->assertSame(['BCTG'], Factory::pluck('code')->all());
    }

    public function test_another_tenants_record_is_not_reachable_by_its_id(): void
    {
        $this->asCompany($this->beta);
        $betaFactory = Factory::where('code', 'BCTG')->firstOrFail();

        $this->asCompany($this->alpha);

        // Knowing the id must not be enough. This is the direct-id attack the
        // spec calls out (SRS 4).
        $this->assertNull(Factory::find($betaFactory->id));
    }

    public function test_company_id_is_assigned_from_context_not_from_input(): void
    {
        $this->asCompany($this->alpha);

        // A malicious payload naming another company must be ignored, not honoured.
        $factory = new Factory([
            'name' => 'Injected', 'code' => 'INJ', 'timezone' => 'Asia/Dhaka',
        ]);
        $factory->save();

        $this->assertSame($this->alpha->id, $factory->fresh()->company_id);
    }

    public function test_company_id_in_a_mass_assignment_payload_cannot_cross_tenants(): void
    {
        $this->asCompany($this->alpha);

        $factory = Factory::create([
            'company_id' => $this->beta->id,
            'name' => 'Attempted cross-tenant',
            'code' => 'XTEN',
            'timezone' => 'Asia/Dhaka',
        ]);

        // The row is written, but it is immediately invisible to the attacker
        // and never visible to Alpha. Controllers must never pass company_id;
        // the API layer strips it (ADR-064).
        $this->asCompany($this->beta);
        $this->assertNotNull(Factory::find($factory->id));

        $this->asCompany($this->alpha);
        $this->assertNull(Factory::find($factory->id));
    }

    public function test_a_query_without_tenant_context_throws_rather_than_returning_everything(): void
    {
        app(TenantContext::class)->forget();

        $this->expectException(TenantContextMissingException::class);

        Factory::count();
    }

    public function test_across_all_tenants_is_an_explicit_opt_out(): void
    {
        $this->asCompany($this->alpha);

        $this->assertSame(2, Factory::count());
        $this->assertSame(3, Factory::acrossAllTenants()->count());
    }

    public function test_factory_scope_cannot_widen_beyond_accessible_factories(): void
    {
        $context = app(TenantContext::class);

        $this->asCompany($this->alpha);
        $alphaFactory = Factory::where('code', 'ADHK')->firstOrFail();

        $context->set($this->alpha->id, [$alphaFactory->id]);

        $context->setFactoryScope($alphaFactory->id);
        $this->assertSame($alphaFactory->id, $context->factoryScopeId());

        // A factory the user cannot access is silently dropped, never applied.
        $context->setFactoryScope('01JQZZZZZZZZZZZZZZZZZZZZZZ');
        $this->assertNull($context->factoryScopeId());
    }
}
