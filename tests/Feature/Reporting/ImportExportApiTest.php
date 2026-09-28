<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Upload, validate, preview, confirm, over the API (API 23), and the raw
 * data export that feeds back into it (API 24).
 */
class ImportExportApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $storeManager;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        AssetLocation::create([
            'factory_id' => $this->dhaka->id,
            'name' => 'Line 3',
            'code' => 'DHK-L3',
            'status' => 'ACTIVE',
        ]);

        // FACTORY_ADMIN, not STORE_MANAGER: the asset importer's own
        // permission is asset.asset.create, which STORE_MANAGER does not
        // hold, and Importer::allows() requires both it and
        // import.job.create together.
        $this->storeManager = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'sm@delta.test');
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
    }

    private function assetCsv(): string
    {
        return <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code,criticality,status,acquisition_cost
        IMP-001,Imported lockstitch A,SEWING,LOCKSTITCH,DHK,DHK-L3,HIGH,INSTALLED,250000
        CSV;
    }

    private function file(string $csv, string $name = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $csv);
    }

    public function test_the_template_can_be_downloaded_before_uploading_anything(): void
    {
        $this->withToken($this->tokenFor($this->storeManager))
            ->get('/api/v1/imports/assets/template')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_an_upload_validates_without_writing_anything(): void
    {
        $created = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($this->assetCsv())])
            ->assertCreated()
            ->assertJsonPath('data.status', 'VALIDATED')
            ->assertJsonPath('data.total_rows', 1)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.is_confirmable', true);

        $this->assertSame(0, Asset::where('asset_code', 'IMP-001')->count());

        $jobId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->getJson("/api/v1/imports/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.preview_rows.0.is_valid', true);
    }

    public function test_a_bad_row_is_reported_and_the_job_is_not_confirmable_when_nothing_is_valid(): void
    {
        $csv = "asset_code,name,asset_type_code,asset_category_code,factory_code,location_code\n"
            .'IMP-001,Unknown type,NOSUCHTYPE,LOCKSTITCH,DHK,DHK-L3';

        $created = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($csv)])
            ->assertCreated()
            ->assertJsonPath('data.valid_rows', 0)
            ->assertJsonPath('data.is_confirmable', false);

        $jobId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->getJson("/api/v1/imports/{$jobId}/errors")
            ->assertOk()
            ->assertJsonFragment(['field' => 'asset_type_code', 'value' => 'NOSUCHTYPE']);
    }

    public function test_confirming_writes_the_valid_rows(): void
    {
        $jobId = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($this->assetCsv())])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/imports/{$jobId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.success_rows', 1);

        $asset = Asset::where('asset_code', 'IMP-001')->firstOrFail();
        $this->assertSame('Imported lockstitch A', $asset->name);

        // Not idempotent from the caller's side: a second confirm on the
        // same job must be refused rather than writing the row twice.
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/imports/{$jobId}/confirm")
            ->assertStatus(409);
    }

    public function test_an_uploaded_job_can_be_cancelled(): void
    {
        $jobId = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($this->assetCsv())])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/imports/{$jobId}/cancel")
            ->assertNoContent();

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/imports/{$jobId}/confirm")
            ->assertStatus(409);
    }

    public function test_another_persons_import_job_is_not_reachable(): void
    {
        $jobId = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($this->assetCsv())])
            ->json('data.id');

        $another = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm2@delta.test');

        $this->withToken($this->tokenFor($another))
            ->getJson("/api/v1/imports/{$jobId}")
            ->assertNotFound();
    }

    public function test_a_role_without_import_rights_cannot_upload(): void
    {
        // 404, not 403: an importer the caller may not use is treated the
        // same as one that does not exist (mirrors ImportController::resolve()).
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($this->assetCsv())])
            ->assertNotFound();
    }

    public function test_current_data_can_be_exported_and_imported_back(): void
    {
        $jobId = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/imports/assets', ['file' => $this->file($this->assetCsv())])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/imports/{$jobId}/confirm")
            ->assertOk();

        $export = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/exports', ['type' => 'assets', 'format' => 'CSV'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.row_count', 1);

        $exportId = $export->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->get("/api/v1/exports/{$exportId}/download")
            ->assertOk();
    }

    public function test_maintenance_history_cannot_be_exported(): void
    {
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/exports', ['type' => 'maintenance-history', 'format' => 'CSV'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
