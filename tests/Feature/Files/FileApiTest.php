<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderChecklistResult;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Evidence, over the API (API 19.1, Gap Analysis 3.4 #35).
 *
 * Upload rides on the resource a file is attached to (asset documents, work
 * order attachments); everything after that — metadata, the signed download,
 * deletion — is one generic surface regardless of what the file is evidence
 * for.
 */
class FileApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $engineer;

    private User $technician;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
    }

    public function test_a_document_can_be_uploaded_to_an_asset_and_listed(): void
    {
        $response = $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/assets/{$this->asset->id}/documents", [
                'file' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.attachable_type', 'asset')
            ->assertJsonPath('data.attachable_id', $this->asset->id)
            ->assertJsonPath('data.original_name', 'manual.pdf')
            ->assertJsonPath('data.downloadable', true);

        $fileId = $response->json('data.id');

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/assets/{$this->asset->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fileId);
    }

    public function test_a_technician_cannot_manage_asset_documents(): void
    {
        // A technician holds asset.asset.view (can read the asset) but not
        // asset.document.manage, which is what uploading rides on.
        $this->withToken($this->tokenFor($this->technician))
            ->postJson("/api/v1/assets/{$this->asset->id}/documents", [
                'file' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_evidence_can_be_attached_to_a_work_order_and_listed(): void
    {
        $workOrder = $this->workOrder();

        $response = $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/attachments", [
                'file' => UploadedFile::fake()->image('finished.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.attachable_type', 'work_order')
            ->assertJsonPath('data.attachable_id', $workOrder->id);

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/work-orders/{$workOrder->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $response->json('data.id'));
    }

    public function test_a_technician_cannot_attach_a_standalone_file_to_a_work_order(): void
    {
        $workOrder = $this->workOrder();

        // The technician may start and complete the job (work_order.work_order.start,
        // .complete) but not edit the record itself (.update), which is what a
        // standalone attachment, as opposed to a checklist photo, rides on.
        $this->withToken($this->tokenFor($this->technician))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/attachments", [
                'file' => UploadedFile::fake()->image('finished.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_an_oversized_file_is_refused_with_the_dedicated_code(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/assets/{$this->asset->id}/documents", [
                'file' => UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf'),
            ])
            ->assertStatus(413)
            ->assertJsonPath('code', 'FILE_TOO_LARGE');
    }

    public function test_an_unsupported_file_type_is_refused_with_the_dedicated_code(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/assets/{$this->asset->id}/documents", [
                'file' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text'),
            ])
            ->assertStatus(415)
            ->assertJsonPath('code', 'UNSUPPORTED_FILE_TYPE');
    }

    public function test_metadata_can_be_read_without_the_bytes(): void
    {
        $file = $this->uploadAssetDocument();

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonMissingPath('data.path');
    }

    public function test_download_redirects_to_a_signed_url_that_needs_no_bearer_token(): void
    {
        $file = $this->uploadAssetDocument();

        $redirect = $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/files/{$file->id}/download")
            ->assertRedirect();

        $signedUrl = $redirect->headers->get('Location');
        $this->assertStringContainsString("/files/{$file->id}/signed", $signedUrl);

        // No Authorization header at all: the signature is the credential.
        $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_tampered_signed_url_is_refused(): void
    {
        $file = $this->uploadAssetDocument();

        $this->get("/api/v1/files/{$file->id}/signed?expires=9999999999&signature=not-the-real-one")
            ->assertForbidden();
    }

    public function test_a_pending_scan_refuses_download_with_409(): void
    {
        $pending = FileAttachment::create([
            'company_id' => $this->delta->id,
            'attachable_type' => 'asset',
            'attachable_id' => $this->asset->id,
            'disk' => 'local',
            'path' => 'attachments/x/pending.pdf',
            'original_name' => 'pending.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'sha256' => str_repeat('a', 64),
            'scan_status' => 'PENDING',
        ]);

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/files/{$pending->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('code', 'FILE_SCAN_PENDING');
    }

    public function test_a_file_can_be_deleted(): void
    {
        $file = $this->uploadAssetDocument();

        $this->withToken($this->tokenFor($this->engineer))
            ->deleteJson("/api/v1/files/{$file->id}")
            ->assertNoContent();

        $this->assertNull(FileAttachment::find($file->id));
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_a_file_still_named_by_a_checklist_result_cannot_be_deleted(): void
    {
        $workOrder = $this->workOrder();

        $photo = $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/attachments", [
                'file' => UploadedFile::fake()->image('reading.jpg'),
            ])
            ->json('data.id');

        $checklistItem = WorkOrderFixture::publishedChecklist($this->delta)->items->first();

        WorkOrderChecklistResult::create([
            'company_id' => $this->delta->id,
            'work_order_id' => $workOrder->id,
            'checklist_item_id' => $checklistItem->id,
            'result' => 'PASS',
            'file_id' => $photo,
            'completed_by' => $this->engineer->id,
            'completed_at' => now(),
        ]);

        $this->withToken($this->tokenFor($this->engineer))
            ->deleteJson("/api/v1/files/{$photo}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'DEPENDENT_RECORDS_EXIST');

        $this->assertNotNull(FileAttachment::find($photo));
    }

    public function test_another_companys_caller_cannot_reach_the_file(): void
    {
        $file = $this->uploadAssetDocument();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        $this->withToken($this->tokenFor($intruder))
            ->getJson("/api/v1/files/{$file->id}")
            ->assertNotFound();
    }

    private function uploadAssetDocument(): FileAttachment
    {
        $id = $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/assets/{$this->asset->id}/documents", [
                'file' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
            ])
            ->json('data.id');

        return FileAttachment::findOrFail($id);
    }

    private function workOrder(): WorkOrder
    {
        return app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->engineer->id);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
