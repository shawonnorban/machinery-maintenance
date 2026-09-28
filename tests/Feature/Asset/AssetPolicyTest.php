<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Instance-level authorisation (Handbook 2.6 rule 2).
 *
 * The tenant scope already hides other companies, so these cover the two
 * things it does not: factory reach and asset state.
 */
class AssetPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private Asset $dhakaAsset;

    private Asset $gazipurAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        TenantFixture::actingAsTenant($this->delta);

        $this->dhakaAsset = $this->asset($this->dhaka, 'SEW-DHK-1', 'DHK-L3');
        $this->gazipurAsset = $this->asset($this->gazipur, 'SEW-GAZ-1', 'GAZ-L1');
    }

    private function asset(Factory $factory, string $code, string $locationCode): Asset
    {
        $location = AssetLocation::create([
            'factory_id' => $factory->id, 'name' => $locationCode, 'code' => $locationCode,
        ]);

        return app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => $code,
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $factory->id,
            'asset_location_id' => $location->id,
        ]);
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);

        app(TenantContext::class)->forget();
        app(TenantContext::class)->set(
            $this->delta->id,
            app(PermissionResolver::class)
                ->accessibleFactoryIds($user, $this->delta->id),
        );
    }

    public function test_a_factory_scoped_manager_cannot_reach_another_factorys_asset(): void
    {
        $manager = TenantFixture::user(
            $this->delta, 'MAINTENANCE_MANAGER', 'dhaka@delta.test', factoryId: $this->dhaka->id,
        );

        $this->actAs($manager);

        $this->assertTrue(Gate::allows('view', $this->dhakaAsset));
        $this->assertTrue(Gate::allows('update', $this->dhakaAsset));

        // Same company, same permission, different factory.
        $this->assertFalse(Gate::allows('view', $this->gazipurAsset));
        $this->assertFalse(Gate::allows('update', $this->gazipurAsset));
    }

    public function test_a_company_wide_role_reaches_every_factory(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->actAs($owner);

        $this->assertTrue(Gate::allows('view', $this->dhakaAsset));
        $this->assertTrue(Gate::allows('view', $this->gazipurAsset));
    }

    public function test_a_technician_cannot_create_or_update_assets(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->actAs($technician);

        $this->assertTrue(Gate::allows('view', $this->dhakaAsset));
        $this->assertFalse(Gate::allows('create', Asset::class));
        $this->assertFalse(Gate::allows('update', $this->dhakaAsset));
        $this->assertFalse(Gate::allows('delete', $this->dhakaAsset));
    }

    public function test_financial_data_is_a_separate_permission(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->actAs($technician);

        // A technician needs the machine record, not its purchase price.
        $this->assertTrue(Gate::allows('view', $this->dhakaAsset));
        $this->assertFalse(Gate::allows('viewFinancial', $this->dhakaAsset));

        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->actAs($manager);

        $this->assertTrue(Gate::allows('viewFinancial', $this->dhakaAsset));
    }

    public function test_a_scrapped_asset_cannot_be_edited(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->actAs($owner);

        $this->assertTrue(Gate::allows('update', $this->dhakaAsset));

        $status = app(ChangeAssetStatus::class);
        $asset = $this->dhakaAsset;

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING', 'BREAKDOWN', 'UNDER_REPAIR'] as $s) {
            $asset = $status->handle($asset, $s);
        }

        $asset = $status->handle($asset, 'SCRAPPED', reason: 'Beyond economic repair');

        // Editing it would rewrite the record of what was actually on the floor.
        $this->assertFalse(Gate::allows('update', $asset));
    }

    public function test_a_terminal_asset_cannot_be_transferred(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->actAs($owner);

        $status = app(ChangeAssetStatus::class);
        $asset = $this->dhakaAsset;

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $s) {
            $asset = $status->handle($asset, $s);
        }

        $this->assertTrue(Gate::allows('transfer', $asset));

        $asset = $status->handle($asset, 'RETIRED', reason: 'End of life');
        $this->assertFalse(Gate::allows('transfer', $asset));
    }

    public function test_a_viewer_holds_no_write_ability_on_an_asset(): void
    {
        $viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        $this->actAs($viewer);

        $this->assertTrue(Gate::allows('view', $this->dhakaAsset));

        foreach (['create', 'update', 'delete', 'changeStatus', 'transfer', 'regenerateQr'] as $ability) {
            $this->assertFalse(
                $ability === 'create'
                    ? Gate::allows($ability, Asset::class)
                    : Gate::allows($ability, $this->dhakaAsset),
                "A viewer must not be able to {$ability} an asset.",
            );
        }
    }
}
