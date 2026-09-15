<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Reports, over the API (API 22, SRS 32).
 *
 * `GET /reports/{key}` is the same capped preview the run screen shows;
 * `POST /report-jobs` is where the full, unbounded file is asked for.
 */
class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $storeManager;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');

        WorkOrderFixture::runningAsset($this->delta, $dhaka, 'SEW-DHK-00412');
    }

    public function test_the_catalogue_lists_only_reports_this_caller_may_run(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonFragment(['key' => 'asset_register']);
    }

    public function test_the_asset_register_preview_lists_the_seeded_machine(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/reports/asset_register')
            ->assertOk()
            ->assertJsonPath('data.truncated', false)
            ->assertJsonFragment(['asset_code' => 'SEW-DHK-00412']);
    }

    public function test_the_preview_carries_reachable_factories_without_settings_factory_manage(): void
    {
        // MAINTENANCE_ENGINEER holds report.report.view a tier below where
        // settings.factory.manage is first granted (RoleSeeder's own
        // $factoryManager tier) — the factory filter must not depend on it.
        $response = $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/reports/pm_compliance')
            ->assertOk();

        $this->assertNotEmpty($response->json('data.factories'));
    }

    public function test_the_preview_carries_the_formats_the_export_button_may_offer(): void
    {
        // Mirrors `ReportController::run()`'s own `formats` — the API's
        // preview must not leave the frontend to guess or hardcode which
        // writers ReportRunner actually has registered.
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/reports/asset_register')
            ->assertOk()
            ->assertJsonPath('data.formats', fn ($formats) => in_array('CSV', $formats, true));
    }

    public function test_an_unknown_report_key_is_not_found(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/reports/not-a-real-report')
            ->assertNotFound();
    }

    public function test_a_report_job_can_be_requested_and_downloaded(): void
    {
        $created = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/report-jobs', [
                'report' => 'asset_register',
                'format' => 'CSV',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.is_downloadable', true);

        $jobId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->getJson("/api/v1/report-jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.report_type', 'asset_register');

        $this->withToken($this->tokenFor($this->storeManager))
            ->get("/api/v1/report-jobs/{$jobId}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_a_role_with_view_but_not_export_cannot_request_a_job(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/report-jobs', [
                'report' => 'asset_register',
                'format' => 'CSV',
            ])
            ->assertForbidden();
    }

    public function test_a_report_job_cannot_be_downloaded_by_someone_else(): void
    {
        $jobId = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/report-jobs', ['report' => 'asset_register', 'format' => 'CSV'])
            ->json('data.id');

        $another = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm2@delta.test');

        $this->withToken($this->tokenFor($another))
            ->getJson("/api/v1/report-jobs/{$jobId}")
            ->assertNotFound();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
