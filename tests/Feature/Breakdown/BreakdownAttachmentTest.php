<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Photo evidence on a breakdown (SRS 13.4, gap-report Priority 2): a photo
 * taken with the report itself travels inline as base64 on `POST
 * /breakdowns` (the one write the offline queue protects — see
 * frontend/src/lib/offline/queue.js), while anything added afterwards goes
 * through the normal multipart `POST /breakdowns/{breakdown}/attachments`,
 * same shape as `WorkOrderAttachmentApiController`.
 */
class BreakdownAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
    }

    public function test_a_photo_reported_inline_is_attached_and_downloadable(): void
    {
        Storage::fake('local');

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $response = $this->withToken($this->tokenFor($this->engineer))->postJson('/api/v1/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'Needle bar seized, motor still runs',
            'photo_base64' => 'data:image/png;base64,'.base64_encode($pixel),
            'photo_filename' => 'seized-needle.png',
        ])->assertCreated();

        $breakdown = Breakdown::findOrFail($response->json('data.id'));

        $attachment = FileAttachment::where('attachable_type', 'breakdown')
            ->where('attachable_id', $breakdown->id)
            ->sole();

        $this->assertSame('seized-needle.png', $attachment->original_name);
        $this->assertStringStartsWith("attachments/{$this->delta->id}/", $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/breakdowns/{$breakdown->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'seized-needle.png');
    }

    public function test_malformed_inline_photo_does_not_fail_the_report(): void
    {
        $response = $this->withToken($this->tokenFor($this->engineer))->postJson('/api/v1/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'Reported without a usable photo',
            'photo_base64' => 'not-actually-base64!!! not valid',
        ])->assertCreated();

        $breakdown = Breakdown::findOrFail($response->json('data.id'));

        $this->assertSame(
            0,
            FileAttachment::where('attachable_type', 'breakdown')->where('attachable_id', $breakdown->id)->count(),
        );
    }

    public function test_reporting_without_a_photo_still_works(): void
    {
        $this->withToken($this->tokenFor($this->engineer))->postJson('/api/v1/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'No photo taken',
        ])->assertCreated();
    }

    public function test_a_photo_can_be_added_afterwards_online(): void
    {
        Storage::fake('local');

        $breakdown = $this->reported();

        $response = $this->withToken($this->tokenFor($this->engineer))->post(
            "/api/v1/breakdowns/{$breakdown->id}/attachments",
            ['file' => $this->fakePng('after-repair.png')],
        )->assertCreated();

        $this->assertSame('after-repair.png', $response->json('data.original_name'));
    }

    public function test_a_stranger_cannot_read_another_companys_breakdown_photo(): void
    {
        Storage::fake('local');

        $breakdown = $this->reported();

        $this->withToken($this->tokenFor($this->engineer))->post(
            "/api/v1/breakdowns/{$breakdown->id}/attachments",
            ['file' => $this->fakePng('evidence.png')],
        )->assertCreated();

        $outsider = TenantFixture::company('Outside Textiles', 'OUT');
        $outsiderUser = TenantFixture::user($outsider, 'MAINTENANCE_ENGINEER', 'outsider@outside.test');

        $this->withToken($this->tokenFor($outsiderUser))
            ->getJson("/api/v1/breakdowns/{$breakdown->id}/attachments")
            ->assertNotFound();
    }

    private function reported(): Breakdown
    {
        $response = $this->withToken($this->tokenFor($this->engineer))->postJson('/api/v1/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'Machine stops mid-seam, motor hums but shaft does not turn',
        ])->assertCreated();

        return Breakdown::findOrFail($response->json('data.id'));
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * `UploadedFile::fake()->image()` needs GD built with JPEG/PNG encoding
     * support, which this environment's PHP doesn't have — real minimal PNG
     * bytes via `createWithContent()` sidestep that and still sniff as a
     * genuine `image/png` through `StoreFileAttachment`'s content-based
     * mime check.
     */
    private function fakePng(string $name): UploadedFile
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $pixel);
    }
}
