<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\MenuRegistry;
use App\Modules\Platform\Support\LicensedModules;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Every module of the company app comes under the platform (user,
 * 2026-11-24): an existing company keeps everything it had; switching a
 * module off takes it out of the sidebar, shuts its screens and refuses the
 * permissions only it uses (not those an on module shares); core modules
 * cannot be switched; a company onboarded from the platform starts with the
 * core modules only.
 */
class CompanyModulesTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        LicensedModules::forget();
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        $this->staff = PlatformFixture::staff();
    }

    public function test_an_existing_company_keeps_its_modules_and_a_switched_off_one_disappears(): void
    {
        // Everything the company always had is on; the separately sold Quality Tally is not.
        $nav = $this->moduleCodes();
        $this->assertContains('PRODUCTION_TRACKING', $nav);
        $this->assertContains('MACHINERY', $nav);
        $this->assertNotContains('QUALITY_TALLY', $nav);
        $this->api($this->owner)->getJson('/api/v1/production-tracking/lookups')->assertOk();

        $list = collect($this->platform()->getJson($this->modulesUrl())->assertOk()->json('data'))->keyBy('code');
        $this->assertTrue($list['PRODUCTION_TRACKING']['enabled']);
        $this->assertTrue($list['ACCESS']['core']);
        $this->assertFalse($list['QUALITY_TALLY']['enabled']);

        $this->platform()->putJson($this->modulesUrl().'/PRODUCTION_TRACKING', ['enabled' => false])->assertOk();

        $this->assertNotContains('PRODUCTION_TRACKING', $this->moduleCodes());
        $this->api($this->owner)->getJson('/api/v1/production-tracking/lookups')->assertForbidden();
        $this->assertContains('/production-tracking/all-dashboard', $this->api($this->owner)->getJson('/api/v1/access/locked-modules')->json('data'));
        // The other modules are untouched.
        $this->assertContains('MACHINERY', $this->moduleCodes());

        // Several at once, and back on.
        $this->platform()->putJson($this->modulesUrl(), ['codes' => ['PRODUCTION_TRACKING', 'QUALITY_TALLY'], 'enabled' => true])->assertOk();
        $nav = $this->moduleCodes();
        $this->assertContains('PRODUCTION_TRACKING', $nav);
        $this->assertContains('QUALITY_TALLY', $nav);
        $this->api($this->owner)->getJson('/api/v1/production-tracking/lookups')->assertOk();
    }

    public function test_core_modules_cannot_be_switched_off(): void
    {
        $this->platform()->putJson($this->modulesUrl().'/ACCESS', ['enabled' => false])->assertNotFound();
        $this->platform()->putJson($this->modulesUrl(), ['codes' => ['HR_PAYROLL'], 'enabled' => false])->assertUnprocessable();
        $this->assertTrue(LicensedModules::isEnabled($this->delta->id, 'HR_PAYROLL'));
    }

    public function test_a_permission_another_on_module_uses_is_not_refused(): void
    {
        // A permission two non-core modules' screens both use.
        $users = [];
        foreach (MenuRegistry::menus() as [, $module, , , , , $view]) {
            foreach (array_filter((array) $view) as $p) {
                if (! in_array($module, LicensedModules::CORE, true)) {
                    $users[$p][$module] = true;
                }
            }
        }
        $shared = collect($users)->first(fn (array $m): bool => count($m) >= 2);
        $this->assertNotNull($shared, 'no permission is shared by two modules');
        $permission = array_search($shared, $users, true);
        [$a, $b] = array_keys($shared);

        $this->platform()->putJson($this->modulesUrl(), ['codes' => [$a], 'enabled' => false])->assertOk();
        $this->assertTrue(LicensedModules::permits($this->delta->id, $permission), "{$permission} is still used by {$b}");

        $this->platform()->putJson($this->modulesUrl(), ['codes' => array_keys($shared), 'enabled' => false])->assertOk();
        $this->assertFalse(LicensedModules::permits($this->delta->id, $permission));
    }

    public function test_a_company_onboarded_from_the_platform_starts_with_the_core_modules_only(): void
    {
        $this->platform()->postJson('/api/v1/platform/tenants', [
            'name' => 'Rival Textiles Ltd', 'code' => 'RTL', 'base_currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'default_locale' => 'bn',
            'factory_name' => 'Savar Unit', 'factory_code' => 'SAV', 'owner_name' => 'Nusrat Jahan', 'owner_email' => 'nusrat@rival.test',
        ])->assertCreated();
        $rival = Company::withoutGlobalScope(TenantScope::class)->where('code', 'RTL')->firstOrFail();
        LicensedModules::forget();

        $this->assertSame([], array_values(array_intersect(LicensedModules::CORE, LicensedModules::disabled($rival->id))));
        $this->assertSame(
            array_values(array_diff(array_keys(MenuRegistry::modules()), LicensedModules::CORE)),
            LicensedModules::disabled($rival->id),
        );
        $this->assertSame(0, DB::table('company_modules')->where('company_id', $rival->id)->where('enabled', true)->count());
    }

    private function modulesUrl(): string
    {
        return '/api/v1/platform/tenants/'.$this->delta->id.'/modules';
    }

    private function platform(): static
    {
        $this->flushHeaders();

        return $this->withToken(PlatformFixture::token($this->staff));
    }

    /** @return list<string> */
    private function moduleCodes(): array
    {
        return array_column($this->api($this->owner)->getJson('/api/v1/access/navigation')->assertOk()->json('data'), 'code');
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
