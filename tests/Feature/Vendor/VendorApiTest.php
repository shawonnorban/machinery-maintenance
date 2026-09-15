<?php

declare(strict_types=1);

namespace Tests\Feature\Vendor;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Suppliers and service providers, over the API (API 17, SRS 26).
 */
class VendorApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $storeManager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_a_vendor_can_be_created_updated_and_archived(): void
    {
        $created = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/vendors', [
                'name' => 'Zamil Spares Ltd',
                'code' => 'ZAMIL',
                'vendor_type' => 'SUPPLIER',
                'status' => 'ACTIVE',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ZAMIL');

        $vendorId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->patchJson("/api/v1/vendors/{$vendorId}", [
                'name' => 'Zamil Spares & Service Ltd',
                'code' => 'ZAMIL',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
            ])
            ->assertOk()
            ->assertJsonPath('data.vendor_type', 'BOTH');

        $this->withToken($this->tokenFor($this->storeManager))
            ->deleteJson("/api/v1/vendors/{$vendorId}")
            ->assertNoContent();

        $archived = Vendor::withTrashed()->find($vendorId);
        $this->assertNotNull($archived);
        $this->assertSame('INACTIVE', $archived->status);
        $this->assertSoftDeleted($archived);
    }

    public function test_a_second_vendor_with_the_same_code_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/vendors', [
                'name' => 'Zamil Spares Ltd', 'code' => 'ZAMIL', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/vendors', [
                'name' => 'Another Zamil', 'code' => 'ZAMIL', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_manage_vendors(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/vendors')
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/vendors', [
                'name' => 'X', 'code' => 'X', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
            ])
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
