<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * How this company numbers its documents, over the API (SRS 52) — mirrors
 * the web `NumberingController`'s own `NumberingTest`, which already covers
 * `SaveNumberFormat`'s validation rules directly; this suite only needs to
 * confirm the API wires the same action correctly and gates it the same way.
 */
class NumberingApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_list_carries_the_default_format_and_a_live_sample_for_every_document_type(): void
    {
        $response = $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/numbering')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('document_type', 'WORK_ORDER');

        $this->assertSame('WO-{FACTORY}-{YYYY}{MM}-{SEQ}', $row['format']);
        $this->assertTrue($row['is_default']);
        $this->assertStringContainsString('WO-DHK-', $row['sample']);
        $this->assertSame(0, $row['issued']);
    }

    public function test_saving_a_format_is_read_back_on_the_list_and_flagged_as_no_longer_default(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->patchJson('/api/v1/numbering/WORK_ORDER', ['format' => 'WO/{FACTORY}/{YYYY}/{MM}/{SEQ}', 'padding' => 6])
            ->assertOk();

        $response = $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/numbering')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('document_type', 'WORK_ORDER');

        $this->assertSame('WO/{FACTORY}/{YYYY}/{MM}/{SEQ}', $row['format']);
        $this->assertSame(6, $row['padding']);
        $this->assertFalse($row['is_default']);
    }

    public function test_an_invalid_format_is_refused_by_the_same_validation_the_web_screen_uses(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->patchJson('/api/v1/numbering/WORK_ORDER', ['format' => 'WO-{YYYY}{MM}', 'padding' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');
    }

    public function test_resetting_removes_the_override_and_falls_back_to_the_platform_default(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->patchJson('/api/v1/numbering/WORK_ORDER', ['format' => 'WO/{FACTORY}/{YYYY}/{MM}/{SEQ}', 'padding' => 6])
            ->assertOk();

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson('/api/v1/numbering/WORK_ORDER')
            ->assertNoContent();

        $response = $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/numbering')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('document_type', 'WORK_ORDER');

        $this->assertTrue($row['is_default']);
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_configure_the_company(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/numbering')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
