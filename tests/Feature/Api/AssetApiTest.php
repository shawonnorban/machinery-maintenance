<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The Asset API (docs/03-API-Specification.md §6).
 *
 * Every write here delegates to the same Action the web `AssetController`
 * and friends call (ADR-003), so this suite is not re-proving the domain
 * rules `AssetLifecycleTest`/`AssetTransferTest` already cover in full — it
 * proves the wiring: request/response shape, permission gates, factory
 * reachability, and version-conflict passthrough.
 */
class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $engineer;

    private User $manager;

    private string $engineerToken;

    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->engineer = TenantFixture::user(
            $this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test', $this->factory->id,
        );
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->engineerToken = app(IssueApiToken::class)
            ->forUser($this->engineer, $this->delta->id, 'Engineer device')['plain'];
        $this->managerToken = app(IssueApiToken::class)
            ->forUser($this->manager, $this->delta->id, 'Manager device')['plain'];
    }

    private function asEngineer(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->engineerToken);

        return $this;
    }

    private function asManager(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->managerToken);

        return $this;
    }

    private function location(): AssetLocation
    {
        return AssetLocation::firstOrCreate(
            ['factory_id' => $this->factory->id, 'code' => $this->factory->code.'-L1'],
            ['name' => 'Line 1'],
        );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'asset_code' => 'SEW-DHK-'.random_int(10000, 99999),
            'name' => 'Juki DDL-9000C',
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->factory->id,
            'asset_location_id' => $this->location()->id,
        ], $overrides);
    }

    // -- Index / show -----------------------------------------------------

    public function test_assets_are_listed_and_shown(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $this->asEngineer()->getJson('/api/v1/assets')
            ->assertOk()
            ->assertJsonFragment(['asset_code' => $asset->asset_code]);

        $this->asEngineer()->getJson('/api/v1/assets/'.$asset->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'RUNNING')
            ->assertJsonPath('data.version', $asset->fresh()->version);
    }

    public function test_an_asset_in_an_unreachable_factory_is_not_found(): void
    {
        $other = TenantFixture::factory($this->delta, 'Savar Unit', 'SAV');
        $asset = WorkOrderFixture::runningAsset($this->delta, $other, 'SEW-SAV-00001');

        // The engineer's only role is scoped to $this->factory; it does not
        // reach $other, so the asset there answers as if it does not exist.
        $this->asEngineer()->getJson('/api/v1/assets/'.$asset->id)->assertNotFound();
    }

    // -- Create / update / delete ------------------------------------------

    public function test_an_asset_is_created(): void
    {
        $response = $this->asEngineer()->postJson('/api/v1/assets', $this->payload())
            ->assertCreated();

        $this->assertSame('DRAFT', $response->json('data.status'));
        $this->assertSame(1, $response->json('data.version'));
        $this->assertDatabaseHas('assets', ['asset_code' => $response->json('data.asset_code')]);
    }

    public function test_creation_refuses_a_category_from_a_different_type(): void
    {
        $wrongCategory = AssetCategory::where('code', '!=', 'LOCKSTITCH')->firstOrFail();

        $this->asEngineer()->postJson('/api/v1/assets', $this->payload([
            'asset_category_id' => $wrongCategory->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('asset_category_id');
    }

    public function test_an_asset_is_updated_under_optimistic_locking(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $this->asEngineer()->patchJson('/api/v1/assets/'.$asset->id, $this->payload([
            'name' => 'Juki DDL-9000C (renamed)',
            'version' => $asset->version,
        ]))->assertOk()->assertJsonPath('data.name', 'Juki DDL-9000C (renamed)');

        // The same version again is now stale.
        $this->asEngineer()->patchJson('/api/v1/assets/'.$asset->id, $this->payload([
            'name' => 'Second edit',
            'version' => $asset->version,
        ]))->assertStatus(409);
    }

    public function test_a_scrapped_asset_cannot_be_updated(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $asset->forceFill(['status' => 'SCRAPPED'])->save();

        $this->asEngineer()->patchJson('/api/v1/assets/'.$asset->id, $this->payload([
            'version' => $asset->version,
        ]))->assertStatus(403);
    }

    public function test_an_asset_with_no_history_is_deleted(): void
    {
        $draft = $this->asEngineer()->postJson('/api/v1/assets', $this->payload())->assertCreated();

        $this->asManager()->deleteJson('/api/v1/assets/'.$draft->json('data.id'))->assertNoContent();

        $this->assertSoftDeleted('assets', ['id' => $draft->json('data.id')]);
    }

    public function test_an_asset_with_work_order_history_cannot_be_deleted(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        WorkOrder::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->factory->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'work_order_number' => 'WO-0001',
            'title' => 'Routine check',
            'status' => 'OPEN',
            'priority' => 'MEDIUM',
            'source' => 'MANUAL',
        ]);

        $this->asManager()->deleteJson('/api/v1/assets/'.$asset->id)->assertStatus(409);
    }

    // -- Status -------------------------------------------------------------

    public function test_status_changes_through_valid_transitions(): void
    {
        $asset = $this->asEngineer()->postJson('/api/v1/assets', $this->payload())->assertCreated();

        $id = $asset->json('data.id');
        $version = $asset->json('data.version');

        $response = $this->asEngineer()->postJson("/api/v1/assets/{$id}/status", [
            'status' => 'PURCHASED',
            'version' => $version,
        ])->assertOk();

        $this->assertSame('PURCHASED', $response->json('data.status'));
        $this->assertSame($version + 1, $response->json('data.version'));
    }

    public function test_an_invalid_transition_is_refused(): void
    {
        $asset = $this->asEngineer()->postJson('/api/v1/assets', $this->payload())->assertCreated();

        // DRAFT may only move to PURCHASED.
        $this->asEngineer()->postJson('/api/v1/assets/'.$asset->json('data.id').'/status', [
            'status' => 'RUNNING',
            'version' => $asset->json('data.version'),
        ])->assertStatus(409);
    }

    public function test_a_stale_version_on_status_change_is_refused(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $this->asEngineer()->postJson('/api/v1/assets/'.$asset->id.'/status', [
            'status' => 'IDLE',
            'version' => $asset->version - 1,
        ])->assertStatus(409);
    }

    public function test_retiring_an_asset_requires_a_reason(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $this->asEngineer()->postJson('/api/v1/assets/'.$asset->id.'/status', [
            'status' => 'RETIRED',
            'version' => $asset->version,
        ])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    // -- Labels -----------------------------------------------------------------

    public function test_bulk_labels_returns_a_scannable_qr_per_selected_asset(): void
    {
        $first = WorkOrderFixture::runningAsset($this->delta, $this->factory, 'SEW-DHK-00801');
        $second = WorkOrderFixture::runningAsset($this->delta, $this->factory, 'SEW-DHK-00802');
        // Not selected — must not appear in the response.
        WorkOrderFixture::runningAsset($this->delta, $this->factory, 'SEW-DHK-00803');

        $response = $this->asEngineer()
            ->getJson('/api/v1/assets/labels?'.http_build_query(['ids' => [$first->id, $second->id]]))
            ->assertOk();

        $response->assertJsonCount(2, 'data.labels');
        $this->assertFalse($response->json('data.truncated'));
        $this->assertSame($first->asset_code, $response->json('data.labels.0.asset_code'));
        $this->assertNotEmpty($response->json('data.labels.0.svg'));
        $this->assertStringContainsString($first->qr_code, (string) $response->json('data.labels.0.scan_url'));
    }

    public function test_bulk_labels_falls_back_to_factory_and_status_filters_with_no_ids(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->factory, 'SEW-DHK-00901');

        $response = $this->asEngineer()
            ->getJson('/api/v1/assets/labels?'.http_build_query(['factory_id' => $this->factory->id, 'status' => 'RUNNING']))
            ->assertOk();

        $this->assertNotEmpty($response->json('data.labels'));
    }

    // -- Transfer -------------------------------------------------------------

    public function test_a_same_factory_transfer_auto_receives(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $destination = AssetLocation::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->factory->id,
            'code' => $this->factory->code.'-L9',
            'name' => 'Line 9',
            'status' => 'ACTIVE',
        ]);

        $response = $this->asEngineer()->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_location_id' => $destination->id,
            'reason' => 'Line rebalance',
            'version' => $asset->version,
        ])->assertCreated();

        $this->assertSame('RECEIVED', $response->json('data.status'));
        $this->assertSame($destination->id, $asset->fresh()->asset_location_id);

        $this->asEngineer()->getJson("/api/v1/assets/{$asset->id}/transfer-history")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_cross_factory_transfer_needs_approval_and_receipt(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $other = TenantFixture::factory($this->delta, 'Savar Unit', 'SAV');
        $destination = AssetLocation::create([
            'company_id' => $this->delta->id,
            'factory_id' => $other->id,
            'code' => $other->code.'-L1',
            'name' => 'Line 1',
            'status' => 'ACTIVE',
        ]);

        $store = $this->asEngineer()->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_location_id' => $destination->id,
            'reason' => 'Relocating to Savar',
            'version' => $asset->version,
        ])->assertCreated();

        $this->assertSame('REQUESTED', $store->json('data.status'));
        $transferId = $store->json('data.id');

        $this->asManager()->postJson("/api/v1/transfers/{$transferId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $this->asManager()->postJson("/api/v1/transfers/{$transferId}/receive")
            ->assertOk()->assertJsonPath('data.status', 'RECEIVED');

        $this->assertSame($other->id, $asset->fresh()->current_factory_id);
    }

    /** No equivalent page exists on the web (everything happens from the index/history lists instead) — the API's own `show()` is a genuinely new detail view for the same data. */
    public function test_a_single_transfer_can_be_read_by_id(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $destination = AssetLocation::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->factory->id,
            'code' => $this->factory->code.'-L9',
            'name' => 'Line 9',
            'status' => 'ACTIVE',
        ]);

        $store = $this->asEngineer()->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_location_id' => $destination->id,
            'reason' => 'Line rebalance',
            'version' => $asset->version,
        ])->assertCreated();

        $this->asEngineer()->getJson("/api/v1/transfers/{$store->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.transfer_number', $store->json('data.transfer_number'))
            ->assertJsonPath('data.reason', 'Line rebalance')
            ->assertJsonPath('data.asset.id', $asset->id);
    }

    public function test_the_pending_transfer_queue_is_listed_company_wide(): void
    {
        // Mirrors `AssetTransferController::index` — every REQUESTED/
        // APPROVED/IN_TRANSIT transfer, not scoped to the caller's own
        // factory, since tracking transfers *between* factories is the
        // point of this screen.
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $other = TenantFixture::factory($this->delta, 'Savar Unit', 'SAV');
        $destination = AssetLocation::create([
            'company_id' => $this->delta->id,
            'factory_id' => $other->id,
            'code' => $other->code.'-L2',
            'name' => 'Line 2',
            'status' => 'ACTIVE',
        ]);

        $store = $this->asEngineer()->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_location_id' => $destination->id,
            'reason' => 'Relocating to Savar',
            'version' => $asset->version,
        ])->assertCreated();

        $response = $this->asManager()->getJson('/api/v1/transfers')->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame($store->json('data.id'), $response->json('data.0.id'));
        $this->assertSame($asset->id, $response->json('data.0.asset.id'));
        $this->assertSame('REQUESTED', $response->json('data.0.status'));

        // Once received it drops off the pending queue.
        $this->asManager()->postJson("/api/v1/transfers/{$store->json('data.id')}/approve")->assertOk();
        $this->asManager()->postJson("/api/v1/transfers/{$store->json('data.id')}/receive")->assertOk();

        $this->asManager()->getJson('/api/v1/transfers')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_requester_cannot_approve_their_own_transfer(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $other = TenantFixture::factory($this->delta, 'Savar Unit', 'SAV');
        $destination = AssetLocation::create([
            'company_id' => $this->delta->id,
            'factory_id' => $other->id,
            'code' => $other->code.'-L1',
            'name' => 'Line 1',
            'status' => 'ACTIVE',
        ]);

        $store = $this->asManager()->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_location_id' => $destination->id,
            'reason' => 'Relocating to Savar',
            'version' => $asset->version,
        ])->assertCreated();

        $this->asManager()->postJson('/api/v1/transfers/'.$store->json('data.id').'/approve')
            ->assertStatus(403);
    }

    // -- Status history / maintenance history / labels -----------------------

    public function test_status_history_is_listed(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        // One row for creation (→DRAFT) plus one per transition
        // WorkOrderFixture::runningAsset() drives it through: PURCHASED,
        // INSTALLED, COMMISSIONED, RUNNING.
        $this->asEngineer()->getJson("/api/v1/assets/{$asset->id}/status-history")
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_maintenance_history_lists_the_assets_work_orders(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        WorkOrder::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->factory->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'work_order_number' => 'WO-0002',
            'title' => 'Belt replacement',
            'status' => 'OPEN',
            'priority' => 'MEDIUM',
            'source' => 'MANUAL',
        ]);

        $this->asEngineer()->getJson("/api/v1/assets/{$asset->id}/maintenance-history")
            ->assertOk()
            ->assertJsonFragment(['work_order_number' => 'WO-0002']);
    }

    public function test_qr_and_barcode_are_returned(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $asset->forceFill(['barcode' => 'BC-00412'])->save();

        $this->asEngineer()->getJson("/api/v1/assets/{$asset->id}/qr")
            ->assertOk()
            ->assertJsonPath('data.qr_code', $asset->qr_code)
            ->assertJsonStructure(['data' => ['qr_code', 'scan_url', 'svg']]);

        $this->asEngineer()->getJson("/api/v1/assets/{$asset->id}/barcode")
            ->assertOk()
            ->assertJsonPath('data.barcode', 'BC-00412');
    }

    public function test_form_options_are_reachable_by_a_caller_who_can_create_but_not_manage_master_data(): void
    {
        // MAINTENANCE_ENGINEER has asset.asset.create but not
        // masterdata.manage/settings.factory.manage — the exact gap this
        // endpoint exists to close. Every existing lookup this form needs
        // (asset types, categories, manufacturers, factories, locations)
        // sits behind one of those two permissions, which would otherwise
        // 403 the create form's own dropdowns for the role the web form
        // has always supported.
        $location = $this->location();

        $response = $this->asEngineer()->getJson('/api/v1/assets/form-options')->assertOk();

        $response->assertJsonStructure([
            'data' => ['types', 'categories', 'manufacturers', 'factories', 'locations', 'criticalities'],
        ]);
        $this->assertContains($this->factory->id, array_column($response->json('data.factories'), 'id'));
        $this->assertContains($location->id, array_column($response->json('data.locations'), 'id'));
    }

    public function test_form_options_omit_factories_outside_reachable_scope(): void
    {
        $omega = TenantFixture::company('Omega Textiles', 'OMT');
        $omegaFactory = TenantFixture::factory($omega, 'Chattogram Unit 1', 'CTG');

        $response = $this->asEngineer()->getJson('/api/v1/assets/form-options')->assertOk();

        $this->assertNotContains($omegaFactory->id, array_column($response->json('data.factories'), 'id'));
    }
}
