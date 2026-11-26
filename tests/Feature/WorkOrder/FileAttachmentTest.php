<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Private attachments (SRS 13.4, 37).
 *
 * Scoped down deliberately: signed URLs and thumbnails belong with the full
 * storage workstream. What is tested here is the part that cannot wait — a
 * safety failure has somewhere to put its evidence, and nobody else can read it.
 */
class FileAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    private StoreFileAttachment $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->store = app(StoreFileAttachment::class);
    }

    public function test_an_image_is_stored_under_its_company_with_a_hash(): void
    {
        $file = UploadedFile::fake()->image('gauge.png', 400, 300); // the container's GD encodes no JPEG

        $attachment = $this->store->handle($file, 'work_order', (string) Str::ulid(), $this->manager->id);

        // Under the company, so a leaked path still cannot reach another
        // tenant's evidence.
        $this->assertStringStartsWith("attachments/{$this->delta->id}/", $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame('gauge.png', $attachment->original_name);
        $this->assertSame(64, strlen($attachment->sha256));
        $this->assertGreaterThan(0, $attachment->size_bytes);
    }

    public function test_an_executable_disguised_as_an_image_is_refused(): void
    {
        // The declared type is a claim. Trusting it is how a .php lands on disk
        // as an "image/jpeg".
        $file = UploadedFile::fake()->createWithContent(
            'shell.php',
            '<?php echo "hello"; ?>',
        );

        $this->expectException(ValidationException::class);
        $this->store->handle($file, 'work_order', (string) Str::ulid(), $this->manager->id);
    }

    public function test_a_filename_that_could_break_a_header_is_sanitised(): void
    {
        $file = UploadedFile::fake()->image('../../etc/pass"wd.png');

        $attachment = $this->store->handle($file, 'work_order', (string) Str::ulid(), $this->manager->id);

        // The name is echoed in a Content-Disposition header, so no quotes and
        // no path segments survive.
        $this->assertStringNotContainsString('"', $attachment->original_name);
        $this->assertStringNotContainsString('/', $attachment->original_name);
        $this->assertStringNotContainsString('..', $attachment->original_name);
    }

    public function test_an_oversized_file_is_refused(): void
    {
        $file = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

        try {
            $this->store->handle($file, 'work_order', (string) Str::ulid(), $this->manager->id);
            $this->fail('A file over the limit must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
    }

    public function test_another_company_cannot_read_an_attachment(): void
    {
        $attachment = $this->store->handle(
            UploadedFile::fake()->image('gauge.png'),
            'work_order',
            (string) Str::ulid(),
            $this->manager->id,
        );

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        // Served through the API now (the Blade /app/attachments screen is retired): download → a signed URL → the bytes.
        $this->fetch($this->manager, $this->delta, $attachment->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // 404, not 403: a 403 would confirm the id names a real file.
        $this->withToken($this->token($intruder, $omega))
            ->getJson("/api/v1/files/{$attachment->id}")
            ->assertNotFound();
    }

    public function test_an_attachment_is_never_served_from_a_shared_cache(): void
    {
        $attachment = $this->store->handle(
            UploadedFile::fake()->image('gauge.png'),
            'work_order',
            (string) Str::ulid(),
            $this->manager->id,
        );

        $response = $this->fetch($this->manager, $this->delta, $attachment->id);

        $response->assertOk();
        // Evidence should not sit in a proxy, and the browser should not sniff
        // a different type out of it.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_a_missing_file_on_disk_is_a_404_not_a_500(): void
    {
        $orphan = FileAttachment::create([
            'attachable_type' => 'work_order',
            'attachable_id' => (string) Str::ulid(),
            'disk' => 'local',
            'path' => 'attachments/gone/missing.jpg',
            'original_name' => 'missing.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'sha256' => str_repeat('c', 64),
            // As a real orphan would be. Rows that predate the scan columns
            // were marked SKIPPED by the migration, and one written today goes
            // through the scanner; only a row conjured straight into the table
            // is left at the PENDING default, which would be refused with a 409
            // before the disk is ever consulted.
            'scan_status' => 'SKIPPED',
        ]);

        $this->fetch($this->manager, $this->delta, $orphan->id)->assertNotFound();
    }

    private function token(User $user, Company $company): string
    {
        return app(\App\Modules\Api\Actions\IssueApiToken::class)->forUser($user, $company->id, 'Test token')['plain'];
    }

    /** The file as a browser gets it: the download's redirect to a short-lived signed URL, then that URL. */
    private function fetch(User $user, Company $company, string $fileId): \Illuminate\Testing\TestResponse
    {
        $response = $this->withToken($this->token($user, $company))->get("/api/v1/files/{$fileId}/download");
        if ($response->isRedirect()) {
            $this->flushHeaders();
            $response = $this->get((string) $response->headers->get('Location'));
        }

        return $response;
    }
}
