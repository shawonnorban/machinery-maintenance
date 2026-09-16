<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * A customer's own details, from the platform side (SRS 3.1, 5, 40).
 *
 * The tab-by-tab Blade page this file used to exercise (company management,
 * billing, domains, support, tickets, analytics, danger zone, each its own
 * URL) is gone along with the rest of the Blade admin console — decommissioned
 * once the Next.js platform console reached parity, including the two gaps
 * (impersonation handoff, cross-staff ticket reassignment) that had kept it
 * alive alongside the Next.js one. What that Blade page composed from several
 * API calls, `frontend/src/app/platform/(console)/tenants/[companyId]/page.js`
 * now composes the same way, and is Next.js's own concern to test (thin
 * Playwright coverage today, tracked as a separate, known gap) — this file
 * only has anything left to say about the two API writes that carried real
 * validation logic of their own: extending a company's contact details, and
 * replacing its logo.
 */
class TenantTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private string $staffToken;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->staff = PlatformFixture::staff();
        $this->staffToken = PlatformFixture::token($this->staff);

        Storage::fake('public');
    }

    public function test_company_details_can_be_extended_with_contact_fields(): void
    {
        $this->asStaff()
            ->patchJson('/api/v1/platform/tenants/'.$this->delta->id, [
                'name' => 'Delta Apparels Ltd',
                'legal_name' => 'Delta Apparels Limited',
                'email' => 'accounts@deltaapparels.test',
                'phone' => '+880 1711 000000',
                'country' => 'Bangladesh',
                'address' => 'Plot 14, Dhaka EPZ',
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => 'en',
            ])
            ->assertOk();

        $company = $this->delta->fresh();

        $this->assertSame('accounts@deltaapparels.test', $company->email);
        $this->assertSame('+880 1711 000000', $company->phone);
        $this->assertSame('Bangladesh', $company->country);
        $this->assertSame('Plot 14, Dhaka EPZ', $company->address);
    }

    public function test_a_logo_can_be_uploaded_and_replaced(): void
    {
        $this->asStaff()
            ->post('/api/v1/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $this->fakePng('logo.png')])
            ->assertOk();

        $company = $this->delta->fresh();
        $firstPath = $company->logo_path;

        $this->assertNotNull($firstPath);
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotNull($company->logoUrl());

        // Replaced, not accumulated: the old file is removed rather than left
        // behind as an orphan nobody points at any more.
        $this->asStaff()
            ->post('/api/v1/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $this->fakePng('new-logo.png')])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotSame($firstPath, $company->fresh()->logo_path);
    }

    public function test_a_logo_upload_is_refused_when_too_large_or_wrong_type(): void
    {
        $tooLarge = UploadedFile::fake()->create('logo.png', 1024, 'image/png');

        $this->asStaff()
            ->post('/api/v1/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $tooLarge])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');

        $notAnImage = UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf');

        $this->asStaff()
            ->post('/api/v1/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $notAnImage])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');

        $this->assertNull($this->delta->fresh()->logo_path);
    }

    private function asStaff(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken);

        return $this;
    }

    /**
     * `UploadedFile::fake()->image()` needs GD built with PNG encoding
     * support, which this environment's PHP doesn't have — real minimal PNG
     * bytes via `createWithContent()` sidestep that and still pass the
     * `image` validation rule, which sniffs actual content.
     */
    private function fakePng(string $name): UploadedFile
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $pixel);
    }
}
