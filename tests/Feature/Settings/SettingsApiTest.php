<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * How this company wants the product to behave, over the API (API 19.2,
 * SRS 53).
 */
class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'metrics.planned_downtime_counts_against_availability';

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_an_unanswered_setting_falls_back_to_the_platform_default(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonFragment(['key' => self::KEY, 'value' => false, 'level' => 'PLATFORM']);
    }

    public function test_a_company_level_answer_is_read_back_and_applies_to_every_factory(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->putJson('/api/v1/settings/'.self::KEY, ['value' => true])
            ->assertOk()
            ->assertJsonPath('data.value', true)
            ->assertJsonPath('data.level', 'COMPANY');

        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/settings?factory_id='.$this->dhaka->id)
            ->assertOk()
            ->assertJsonFragment(['key' => self::KEY, 'value' => true, 'level' => 'COMPANY']);
    }

    public function test_a_factory_answer_overrides_the_company_one_and_resetting_falls_back(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->putJson('/api/v1/settings/'.self::KEY, ['value' => true])
            ->assertOk();

        $this->withToken($this->tokenFor($this->owner))
            ->putJson('/api/v1/settings/'.self::KEY, ['value' => false, 'factory_id' => $this->dhaka->id])
            ->assertOk()
            ->assertJsonPath('data.level', 'FACTORY');

        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/settings?factory_id='.$this->dhaka->id)
            ->assertOk()
            ->assertJsonFragment(['key' => self::KEY, 'value' => false, 'level' => 'FACTORY']);

        // Dropping the factory's own answer falls back to the company's.
        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson('/api/v1/settings/'.self::KEY.'?factory_id='.$this->dhaka->id)
            ->assertNoContent();

        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/settings?factory_id='.$this->dhaka->id)
            ->assertOk()
            ->assertJsonFragment(['key' => self::KEY, 'value' => true, 'level' => 'COMPANY']);
    }

    public function test_the_definitions_catalog_lists_the_key_with_its_type(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/settings/definitions')
            ->assertOk()
            ->assertJsonFragment(['key' => self::KEY, 'value_type' => 'BOOL']);
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_configure_the_company(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/settings')
            ->assertForbidden();
    }

    public function test_the_company_owner_can_upload_and_remove_a_logo(): void
    {
        Storage::fake('public');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/settings/company/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn (?string $url) => $url !== null);

        $path = $this->delta->fresh()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        // Uploading a second time replaces the first rather than leaving an
        // orphaned file behind on disk.
        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/settings/company/logo', ['logo' => UploadedFile::fake()->image('logo2.png')])
            ->assertOk();

        Storage::disk('public')->assertMissing($path);

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson('/api/v1/settings/company/logo')
            ->assertNoContent();

        $this->assertNull($this->delta->fresh()->logo_path);
    }

    public function test_uploading_a_logo_is_closed_to_a_role_that_does_not_configure_the_company(): void
    {
        Storage::fake('public');

        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/settings/company/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();
    }

    public function test_a_non_image_file_is_refused(): void
    {
        Storage::fake('public');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/settings/company/logo', ['logo' => UploadedFile::fake()->create('logo.pdf', 100)])
            ->assertUnprocessable();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
