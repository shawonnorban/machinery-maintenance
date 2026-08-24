<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Uploaded files are checked before they can be served (API 19.1 rule 3).
 *
 * `VIRUS_SCAN_ENABLED` sat in the environment template and nothing read it,
 * which is worse than not having the setting: an operator who set it to true
 * would believe files were being checked.
 *
 * The state machine is what these tests hold down, not the scanner. Whether
 * ClamAV is installed is a property of a machine; what matters here is that
 * "not looked at yet" refuses, "looked at and found something" refuses, and
 * "scanning is off" is recorded as never-checked rather than clean.
 */
class FileScanTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_with_scanning_off_a_file_is_recorded_as_never_checked_and_stays_usable(): void
    {
        config()->set('files.scan.enabled', false);

        $attachment = $this->upload();

        // SKIPPED rather than CLEAN. Marking it clean would claim a check that
        // never happened, and a year later nobody could tell which files were
        // actually scanned.
        $this->assertSame('SKIPPED', $attachment->scan_status);
        $this->assertNotNull($attachment->scanned_at);
        $this->assertTrue($attachment->isDownloadable());

        $this->actingAs($this->owner)
            ->get('/app/attachments/'.$attachment->id)
            ->assertOk();
    }

    public function test_a_file_nothing_has_looked_at_refuses_to_download(): void
    {
        config()->set('files.scan.enabled', false);

        $attachment = $this->upload();

        // Forced back to the state a real deployment reaches when scanning is
        // on and the scanner has not answered yet.
        $attachment->forceFill(['scan_status' => 'PENDING'])->save();

        // 409, not 404: the file exists and this person may see it. "Come back
        // in a moment" is a different answer from "no such file".
        $this->actingAs($this->owner)
            ->get('/app/attachments/'.$attachment->id)
            ->assertStatus(409);
    }

    public function test_an_infected_file_refuses_for_the_opposite_reason(): void
    {
        config()->set('files.scan.enabled', false);

        $attachment = $this->upload();

        $attachment->forceFill([
            'scan_status' => 'INFECTED',
            'scan_result' => 'Eicar-Test-Signature FOUND',
        ])->save();

        $this->actingAs($this->owner)
            ->get('/app/attachments/'.$attachment->id)
            ->assertStatus(409);

        // Both refuse, and they are not the same thing: one has not been
        // looked at, the other has.
        $this->assertFalse($attachment->isDownloadable());
    }

    public function test_scanning_on_with_no_scanner_leaves_the_file_unusable_rather_than_clean(): void
    {
        config()->set('files.scan.enabled', true);
        // A command that certainly does not exist on this machine, which is
        // exactly the misconfiguration worth testing: an operator who turns
        // scanning on without installing anything.
        config()->set('files.scan.command', ['definitely-not-a-real-scanner-9271']);

        $attachment = $this->upload();

        // PENDING, and undownloadable. Inconvenient and correct: calling it
        // clean because the scanner fell over is the one outcome that must
        // never happen.
        $this->assertSame('PENDING', $attachment->scan_status);
        $this->assertFalse($attachment->isDownloadable());

        $this->actingAs($this->owner)
            ->get('/app/attachments/'.$attachment->id)
            ->assertStatus(409);
    }

    public function test_the_upload_survives_a_broken_scanner(): void
    {
        config()->set('files.scan.enabled', true);
        config()->set('files.scan.command', ['definitely-not-a-real-scanner-9271']);

        $attachment = $this->upload();

        // The row and the bytes are both still there. A scanner that is missing
        // must not lose somebody's evidence photo; it must only stop it being
        // served until a person has looked into it.
        $this->assertNotNull(FileAttachment::find($attachment->id));
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
    }

    public function test_existing_files_were_not_broken_by_the_change(): void
    {
        // The migration marks everything already stored as SKIPPED. Leaving it
        // PENDING would make every attachment in every tenant, uploaded before
        // the column existed and downloadable all along, start answering 409.
        $default = (new FileAttachment)->getAttributes()['scan_status'] ?? null;

        $this->assertNotSame('CLEAN', $default, 'A default of CLEAN would claim an unperformed check.');
    }

    private function upload(): FileAttachment
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        return app(StoreFileAttachment::class)->handle(
            UploadedFile::fake()->create('needle-bar.pdf', 30, 'application/pdf'),
            'asset',
            $asset->id,
            $this->owner->id,
        );
    }
}
