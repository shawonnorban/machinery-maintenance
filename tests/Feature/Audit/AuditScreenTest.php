<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Reading the audit log over HTTP (SRS 34).
 */
class AuditScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function user(string $role, string $email): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_an_auditor_can_read_the_log(): void
    {
        $this->vendor();

        $this->actingAs($this->user('AUDITOR', 'auditor@delta.test'))
            ->get('/app/audit-logs')
            ->assertOk()
            ->assertSee('JUKI-BD')
            ->assertSee(__('audit.actions.CREATED'));
    }

    public function test_a_technician_cannot(): void
    {
        $this->actingAs($this->user('TECHNICIAN', 'tech@delta.test'))
            ->get('/app/audit-logs')
            ->assertForbidden();
    }

    public function test_the_log_can_be_filtered_by_action(): void
    {
        $this->vendor();

        $auditor = $this->user('AUDITOR', 'auditor2@delta.test');

        $this->actingAs($auditor)
            ->get('/app/audit-logs?action=LOGIN_FAILED')
            ->assertOk()
            ->assertSee(__('audit.no_entries'))
            ->assertDontSee('JUKI-BD');
    }

    public function test_an_entry_shows_its_diff(): void
    {
        $vendor = $this->vendor();
        $vendor->update(['contact_name' => 'Rafiqul Islam']);

        $log = AuditLog::where('entity_type', 'vendors')
            ->where('action', 'UPDATED')
            ->firstOrFail();

        $this->actingAs($this->user('AUDITOR', 'auditor3@delta.test'))
            ->get(route('app.audit-logs.show', $log))
            ->assertOk()
            ->assertSee('contact_name')
            ->assertSee('Rafiqul Islam');
    }

    public function test_another_companys_entry_is_not_found(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirVendor = Vendor::create([
            'name' => 'Their Vendor',
            'code' => 'THEIRS',
            'vendor_type' => 'SUPPLIER',
            'status' => 'ACTIVE',
        ]);

        $theirLog = AuditLog::where('entity_id', $theirVendor->id)->firstOrFail();

        TenantFixture::actingAsTenant($this->delta);

        // 404 rather than 403: that an entry exists is itself information about
        // another tenant (API 2).
        $this->actingAs($this->user('AUDITOR', 'auditor4@delta.test'))
            ->get(route('app.audit-logs.show', $theirLog))
            ->assertNotFound();
    }

    public function test_the_log_lists_only_this_companys_entries(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        Vendor::create([
            'name' => 'Their Vendor',
            'code' => 'THEIRS-ONLY',
            'vendor_type' => 'SUPPLIER',
            'status' => 'ACTIVE',
        ]);

        TenantFixture::actingAsTenant($this->delta);
        $this->vendor();

        $this->actingAs($this->user('AUDITOR', 'auditor5@delta.test'))
            ->get('/app/audit-logs')
            ->assertOk()
            ->assertSee('JUKI-BD')
            ->assertDontSee('THEIRS-ONLY');
    }

    public function test_the_screens_render_in_bengali(): void
    {
        $auditor = $this->user('AUDITOR', 'auditor6@delta.test');
        $auditor->update(['locale' => 'bn']);

        $this->actingAs($auditor)
            ->get('/app/audit-logs')
            ->assertOk()
            ->assertSee(__('audit.audit_log', locale: 'bn'), false);
    }

    public function test_there_is_no_route_that_writes_to_the_log(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit-logs'))
            ->flatMap(fn ($route) => $route->methods())
            ->unique()
            ->values()
            ->all();

        // Read-only by construction. An audit row that can be written through
        // the UI is not evidence.
        sort($routes);
        $this->assertSame(['GET', 'HEAD'], $routes);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/audit-logs')->assertRedirect('/login');
    }
}
