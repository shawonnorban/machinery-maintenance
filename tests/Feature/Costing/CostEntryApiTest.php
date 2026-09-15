<?php

declare(strict_types=1);

namespace Tests\Feature\Costing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * What a machine has cost, over the API (API 15, SRS 23-24).
 */
class CostEntryApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Asset $asset;

    private User $manager;

    private User $engineer;

    private User $maintenanceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        // Holds cost.entry.view but not cost.entry.create — only a manager
        // posts a cost, per RoleSeeder's matrix.
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);
        $this->maintenanceManager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
    }

    private function categoryId(string $code): string
    {
        return CostCategory::whereNull('company_id')->where('code', $code)->firstOrFail()->id;
    }

    public function test_a_cost_can_be_posted_and_listed(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->categoryId('VENDOR'),
                'amount' => 1500,
                'currency' => 'BDT',
                'source_type' => 'VENDOR',
                'description' => 'Belt replacement, third-party vendor',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1500.0000')
            ->assertJsonPath('data.is_reversal', false);

        $entryId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/costs?asset_id='.$this->asset->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entryId);
    }

    public function test_a_derived_source_type_cannot_be_posted_by_hand(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->categoryId('PARTS'),
                'amount' => 500,
                'currency' => 'BDT',
                'source_type' => 'PARTS',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_type');
    }

    public function test_a_zero_amount_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->categoryId('VENDOR'),
                'amount' => 0,
                'currency' => 'BDT',
                'source_type' => 'VENDOR',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_posted_cost_can_be_reversed_but_not_twice(): void
    {
        $entryId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->categoryId('VENDOR'),
                'amount' => 1500,
                'currency' => 'BDT',
                'source_type' => 'VENDOR',
            ])
            ->json('data.id');

        $reversal = $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/costs/{$entryId}/reverse", ['reason' => 'Invoice was duplicated'])
            ->assertCreated()
            ->assertJsonPath('data.is_reversal', true)
            ->assertJsonPath('data.amount', '-1500.0000');

        $this->assertSame($entryId, $reversal->json('data.reverses_cost_entry_id'));

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/costs/{$entryId}/reverse", ['reason' => 'Again'])
            ->assertStatus(409);
    }

    /**
     * MAINTENANCE_MANAGER holds `cost.entry.create` a tier below
     * `masterdata.manage` (RoleSeeder: the permission only arrives at
     * `factoryManager`, which extends it) — the post-a-cost form's category
     * dropdown has to be its own lookup, gated on `cost.entry.view`, rather
     * than `masterdata.manage`-gated `GET /settings/master-data/...`.
     */
    public function test_a_maintenance_manager_can_reach_the_category_options_without_masterdata_permission(): void
    {
        $this->assertFalse($this->maintenanceManager->can('masterdata.manage'));
        $this->assertTrue($this->maintenanceManager->can('cost.entry.create'));

        $names = $this->withToken($this->tokenFor($this->maintenanceManager))
            ->getJson('/api/v1/cost-categories')
            ->assertOk()
            ->json('data.*.name');

        $this->assertContains('Vendor charges', $names);
    }

    /**
     * The web computes `canPost`/an equivalent reverse-check locally via
     * `$request->user()->can(...)` before deciding whether to render the
     * post-cost form and the reverse buttons; a client has no such local
     * check, so both travel in the lifecycle-cost response instead — same
     * reasoning as Approval's `can_act`.
     */
    public function test_lifecycle_cost_reports_whether_the_caller_can_post_and_reverse(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/assets/{$this->asset->id}/lifecycle-cost")
            ->assertOk()
            ->assertJsonPath('data.can_post', true)
            ->assertJsonPath('data.can_reverse', true);

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/assets/{$this->asset->id}/lifecycle-cost")
            ->assertOk()
            ->assertJsonPath('data.can_post', false)
            ->assertJsonPath('data.can_reverse', false);
    }

    public function test_an_engineer_can_read_costs_but_not_post_them(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/costs')
            ->assertOk();

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->categoryId('VENDOR'),
                'amount' => 100,
                'currency' => 'BDT',
                'source_type' => 'VENDOR',
            ])
            ->assertForbidden();
    }

    public function test_lifecycle_cost_includes_the_asset_purchase_price(): void
    {
        $this->asset->forceFill(['acquisition_cost' => 200000])->save();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->categoryId('VENDOR'),
                'amount' => 1500,
                'currency' => 'BDT',
                'source_type' => 'VENDOR',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/assets/{$this->asset->id}/lifecycle-cost")
            ->assertOk()
            ->assertJsonPath('data.acquisition', '200000.0000')
            ->assertJsonPath('data.lifetime_total', '201500.0000');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
