<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Files on a ticket, reusing the generic `file_attachments` system with the
 * discriminator `'support_ticket'` — no permission of its own (SRS: not an
 * administrative act), but the upload path from the platform side needs its
 * own proof: `StoreFileAttachment` resolves storage from the *current*
 * tenant context, which a platform request carries none of.
 */
class SupportTicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->ownerToken = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'x')['plain'];
    }

    private function ticket(): SupportTicket
    {
        return app(ManageSupportTicket::class)->open(
            $this->delta,
            $this->owner,
            'Cannot export the audit log',
            'The export button spins forever.',
        );
    }

    private function asOwner(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken);

        return $this;
    }

    public function test_a_customer_can_upload_and_list_a_file_on_their_own_ticket(): void
    {
        $ticket = $this->ticket();

        $this->asOwner()
            ->postJson("/api/v1/support/tickets/{$ticket->id}/attachments", [
                'file' => UploadedFile::fake()->create('screenshot.pdf', 50, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'screenshot.pdf');

        $this->asOwner()
            ->getJson("/api/v1/support/tickets/{$ticket->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_customer_cannot_reach_another_companys_ticket_attachments(): void
    {
        $ticket = $this->ticket();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        TenantFixture::actingAsTenant($omega);
        $stranger = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');
        $strangerToken = app(IssueApiToken::class)->forUser($stranger, $omega->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$strangerToken)
            ->getJson("/api/v1/support/tickets/{$ticket->id}/attachments")
            ->assertNotFound();
    }

    public function test_platform_staff_upload_lands_under_the_tickets_own_company(): void
    {
        $ticket = $this->ticket();
        $staff = PlatformFixture::staff();
        $staffToken = PlatformFixture::token($staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson("/api/v1/platform/tickets/{$ticket->id}/attachments", [
                'file' => UploadedFile::fake()->create('fix-confirmation.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        // Proves the TenantContext::set()/forget() wiring: the stored path
        // is scoped to the *ticket's* company, not "no company" (which
        // StoreFileAttachment would otherwise resolve to for a platform
        // caller with no tenant context of their own).
        $stored = FileAttachment::withoutGlobalScope(\App\Shared\Scopes\TenantScope::class)
            ->findOrFail($response->json('data.id'));
        $this->assertSame($this->delta->id, $stored->company_id);
        $this->assertStringStartsWith("attachments/{$this->delta->id}/", $stored->path);

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->getJson("/api/v1/platform/tickets/{$ticket->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_uploader_can_delete_their_own_attachment_but_another_customer_cannot(): void
    {
        $ticket = $this->ticket();

        $upload = $this->asOwner()->postJson("/api/v1/support/tickets/{$ticket->id}/attachments", [
            'file' => UploadedFile::fake()->create('note.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $fileId = $upload->json('data.id');

        $otherOwner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'coowner@delta.test');
        $otherToken = app(IssueApiToken::class)->forUser($otherOwner, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->deleteJson("/api/v1/files/{$fileId}")
            ->assertForbidden();

        $this->asOwner()->deleteJson("/api/v1/files/{$fileId}")->assertNoContent();
    }

    public function test_platform_staff_can_delete_a_customers_attachment(): void
    {
        $ticket = $this->ticket();

        $upload = $this->asOwner()->postJson("/api/v1/support/tickets/{$ticket->id}/attachments", [
            'file' => UploadedFile::fake()->create('note.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $staffToken = PlatformFixture::token(PlatformFixture::staff());

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->deleteJson('/api/v1/platform/files/'.$upload->json('data.id'))
            ->assertNoContent();
    }
}
