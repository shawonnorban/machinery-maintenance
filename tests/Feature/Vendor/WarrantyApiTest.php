<?php

declare(strict_types=1);

namespace Tests\Feature\Vendor;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Cover on a machine, over the API (API 18, SRS 26) — the reason a
 * technician at a broken machine can be told the repair is already paid for.
 */
class WarrantyApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Asset $asset;

    private User $manager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);
    }

    public function test_a_warranty_can_be_recorded_and_a_technician_can_read_it(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/warranties', [
                'asset_id' => $this->asset->id,
                'warranty_type' => 'MANUFACTURER',
                'start_date' => '2026-01-01',
                'end_date' => '2027-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE');

        $warrantyId = $created->json('data.id');

        // A technician needs to see cover exists without a management
        // permission — asset.asset.view_any is what the policy actually
        // requires (WarrantyPolicy::viewAny).
        $this->withToken($this->tokenFor($this->technician))
            ->getJson("/api/v1/warranties/{$warrantyId}")
            ->assertOk()
            ->assertJsonPath('data.id', $warrantyId);
    }

    /**
     * The web decides this with `@can('update', $warranty)`; a client has
     * no such local check, so `can_manage` travels in the response instead
     * — same reasoning as Approval's `can_act`.
     */
    public function test_the_detail_reports_whether_the_caller_can_manage_it(): void
    {
        $warrantyId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/warranties', [
                'asset_id' => $this->asset->id,
                'warranty_type' => 'MANUFACTURER',
                'start_date' => '2026-01-01',
                'end_date' => '2027-01-01',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/warranties/{$warrantyId}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true);

        $this->withToken($this->tokenFor($this->technician))
            ->getJson("/api/v1/warranties/{$warrantyId}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
    }

    public function test_an_end_date_before_the_start_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/warranties', [
                'asset_id' => $this->asset->id,
                'warranty_type' => 'MANUFACTURER',
                'start_date' => '2026-06-01',
                'end_date' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_a_technician_cannot_record_a_warranty(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/warranties', [
                'asset_id' => $this->asset->id,
                'warranty_type' => 'MANUFACTURER',
                'start_date' => '2026-01-01',
                'end_date' => '2027-01-01',
            ])
            ->assertForbidden();
    }

    public function test_a_claim_can_be_filed_and_moved_through_its_states(): void
    {
        $warrantyId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/warranties', [
                'asset_id' => $this->asset->id,
                'warranty_type' => 'MANUFACTURER',
                'start_date' => '2026-01-01',
                'end_date' => '2027-01-01',
            ])
            ->json('data.id');

        $claim = $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/warranties/{$warrantyId}/claims", [
                'claim_date' => '2026-03-01',
                'description' => 'Motor bearing failed under normal load',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'SUBMITTED');

        $claimId = $claim->json('data.id');

        // SUBMITTED -> SETTLED directly is not a legal transition.
        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/warranty-claims/{$claimId}", ['status' => 'SETTLED', 'settled_amount' => 500])
            ->assertStatus(409);

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/warranty-claims/{$claimId}", ['status' => 'APPROVED'])
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/warranty-claims/{$claimId}", ['status' => 'SETTLED', 'settled_amount' => 500])
            ->assertOk()
            ->assertJsonPath('data.status', 'SETTLED')
            ->assertJsonPath('data.settled_amount', '500.0000');
    }

    public function test_a_claim_outside_the_cover_window_is_refused(): void
    {
        $warrantyId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/warranties', [
                'asset_id' => $this->asset->id,
                'warranty_type' => 'MANUFACTURER',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-01',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/warranties/{$warrantyId}/claims", [
                'claim_date' => '2026-06-01',
                'incident_date' => '2026-06-01',
                'description' => 'Failed after cover ended',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('warranty_id');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
