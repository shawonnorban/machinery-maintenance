<?php

declare(strict_types=1);

namespace Tests\Feature\Vendor;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * AMC and service contracts, over the API (API 18, SRS 26).
 */
class ServiceContractApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Asset $asset;

    private User $manager;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);

        $this->vendor = Vendor::create([
            'company_id' => $this->delta->id,
            'name' => 'Zamil Spares & Service Ltd',
            'code' => 'ZAMIL',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_a_contract_can_be_created_and_read(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/service-contracts', [
                'vendor_id' => $this->vendor->id,
                'asset_id' => $this->asset->id,
                'contract_type' => 'AMC',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'value' => 50000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.contract_type', 'AMC');

        $contractId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/service-contracts/{$contractId}")
            ->assertOk()
            ->assertJsonPath('data.id', $contractId)
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_a_contract_naming_no_scope_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/service-contracts', [
                'vendor_id' => $this->vendor->id,
                'contract_type' => 'AMC',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scope');
    }

    public function test_a_supplier_only_vendor_cannot_be_given_a_service_contract(): void
    {
        $supplierOnly = Vendor::create([
            'company_id' => $this->delta->id,
            'name' => 'Parts Only Ltd',
            'code' => 'PARTSONLY',
            'vendor_type' => 'SUPPLIER',
            'status' => 'ACTIVE',
        ]);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/service-contracts', [
                'vendor_id' => $supplierOnly->id,
                'asset_id' => $this->asset->id,
                'contract_type' => 'AMC',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vendor_id');
    }

    public function test_a_contract_can_be_renewed_leaving_the_old_one_marked_renewed(): void
    {
        $originalResponse = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/service-contracts', [
                'vendor_id' => $this->vendor->id,
                'asset_id' => $this->asset->id,
                'contract_type' => 'AMC',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'value' => 50000,
            ]);
        $original = $originalResponse->json('data.id');
        $originalNumber = $originalResponse->json('data.contract_number');

        $renewal = $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/service-contracts/{$original}/renew", [
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
                'value' => 55000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertSame($original, $renewal->json('data.renewed_from_contract_id'));

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/service-contracts/{$original}")
            ->assertOk()
            ->assertJsonPath('data.status', 'RENEWED')
            // A superseded contract is read-only — nothing left to renew or cancel on it.
            ->assertJsonPath('data.can_manage', false);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/service-contracts/'.$renewal->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.renewed_from_contract_number', $originalNumber)
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_a_contract_can_be_cancelled_with_a_reason(): void
    {
        $contractId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/service-contracts', [
                'vendor_id' => $this->vendor->id,
                'asset_id' => $this->asset->id,
                'contract_type' => 'AMC',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/service-contracts/{$contractId}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/service-contracts/{$contractId}/cancel", [
                'reason' => 'Vendor could not meet response time SLA',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
