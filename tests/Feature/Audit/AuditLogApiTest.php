<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The audit trail, over the API (API 26, SRS 34) — read-only.
 */
class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $admin;

    private User $technician;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->admin = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'admin@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
    }

    private function log(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'company_id' => $this->delta->id,
            'user_id' => $this->admin->id,
            'action' => 'UPDATED',
            'entity_type' => 'asset',
            'entity_id' => 'irrelevant-id',
            'entity_label' => 'SEW-DHK-00412',
            'changed_fields_json' => ['status'],
            'old_values_json' => ['status' => 'RUNNING'],
            'new_values_json' => ['status' => 'DOWN'],
            'context' => 'API',
            'request_id' => 'req-1',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_logs_can_be_listed_and_filtered_by_action(): void
    {
        $this->log(['action' => 'UPDATED']);
        $this->log(['action' => 'CREATED']);

        // Scoped to entity_type=asset throughout: setUp() itself creates
        // users and role assignments, which the app's own AuditObserver
        // logs, so an unscoped count would include rows this test never
        // wrote.
        $this->withToken($this->tokenFor($this->admin))
            ->getJson('/api/v1/audit-logs?entity_type=asset')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($this->tokenFor($this->admin))
            ->getJson('/api/v1/audit-logs?entity_type=asset&action=CREATED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'CREATED');
    }

    /**
     * `meta.filters` is what a client builds the action/entity-type
     * dropdowns from — neither is a master-data list of its own, so this is
     * the only place either is exposed.
     */
    public function test_the_list_carries_the_filter_options(): void
    {
        $this->log(['action' => 'UPDATED', 'entity_type' => 'asset']);
        $this->log(['action' => 'CREATED', 'entity_type' => 'work_order']);

        $response = $this->withToken($this->tokenFor($this->admin))
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('meta.filters.actions', AuditLog::ACTIONS)
            ->json('meta.filters.entity_types');

        $this->assertContains('asset', $response);
        $this->assertContains('work_order', $response);
    }

    /**
     * AUDITOR holds `audit.log.view` but not `admin.user.manage` — RoleSeeder
     * grants it only every `.view`/`.view_any` permission plus this one — so
     * the "who" filter's options have to travel with this endpoint rather
     * than through `GET /users`, or an auditor filtering by user would hit
     * the same permission gap Teams' `formOptions()` was built to avoid.
     */
    public function test_an_auditor_without_user_management_still_gets_the_who_filter_options(): void
    {
        $this->withToken($this->tokenFor($this->auditor))
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('meta.filters.users.0.name', fn ($name) => is_string($name));

        $names = $this->withToken($this->tokenFor($this->auditor))
            ->getJson('/api/v1/audit-logs')
            ->json('meta.filters.users');

        $this->assertContains('Auditor', array_column($names, 'name'));
    }

    public function test_a_log_can_be_read_with_its_related_rows(): void
    {
        $main = $this->log(['request_id' => 'req-shared']);
        $this->log(['request_id' => 'req-shared', 'action' => 'STATUS_CHANGED']);
        $this->log(['request_id' => 'req-other']);

        $this->withToken($this->tokenFor($this->admin))
            ->getJson("/api/v1/audit-logs/{$main->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $main->id)
            ->assertJsonPath('data.changed_fields.status.0', 'RUNNING')
            ->assertJsonPath('data.changed_fields.status.1', 'DOWN')
            ->assertJsonCount(1, 'data.related');
    }

    /**
     * A create has nothing to diff against — `changed_fields_json` is null
     * for it (`AuditRecorder::changedFields` returns `[]` when `$old` is
     * null) — so the API falls back to the raw values written, same as the
     * web detail page's own fallback.
     */
    public function test_a_created_entry_exposes_the_values_it_was_written_with(): void
    {
        $created = $this->log([
            'action' => 'CREATED',
            'changed_fields_json' => null,
            'old_values_json' => null,
            'new_values_json' => ['status' => 'RUNNING', 'name' => 'New asset'],
        ]);

        $this->withToken($this->tokenFor($this->admin))
            ->getJson("/api/v1/audit-logs/{$created->id}")
            ->assertOk()
            ->assertJsonPath('data.changed_fields', [])
            ->assertJsonPath('data.new_values.status', 'RUNNING')
            ->assertJsonPath('data.new_values.name', 'New asset');
    }

    /**
     * A real regression: `show()` eager-loads `user` on the main row but the
     * `related` query didn't, and a related row written by a queued job
     * (`user_id` null — `auth()->id()` has nothing to read outside a web
     * session) tripped `Model::preventLazyLoading()` the moment `summary()`
     * touched `$log->user` on it. Caught against real seeded data, not by
     * this test file as originally written — every row here shared the same
     * `user_id`, which happened not to trigger it.
     */
    public function test_a_related_row_with_no_user_does_not_trip_lazy_loading(): void
    {
        $main = $this->log(['request_id' => 'req-mixed']);
        $this->log(['request_id' => 'req-mixed', 'action' => 'CREATED', 'user_id' => null]);

        $this->withToken($this->tokenFor($this->admin))
            ->getJson("/api/v1/audit-logs/{$main->id}")
            ->assertOk()
            ->assertJsonPath('data.related.0.user', null);
    }

    public function test_another_companys_log_is_not_found(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::actingAsTenant($omega);
        $theirLog = $this->log(['company_id' => $omega->id]);
        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($this->admin))
            ->getJson("/api/v1/audit-logs/{$theirLog->id}")
            ->assertNotFound();
    }

    public function test_the_endpoints_are_closed_to_a_role_without_audit_access(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
