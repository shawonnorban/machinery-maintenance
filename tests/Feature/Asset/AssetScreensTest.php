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
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The asset screens end to end: authorization, tenant scoping and the rules
 * a user actually hits (SRS 55.1 rules 1 and 2).
 */
class AssetScreensTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $rival;

    private Factory $dhaka;

    private Factory $gazipur;

    private AssetLocation $dhakaLine;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->rival = TenantFixture::company('Rival Garments Ltd', 'RGL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        TenantFixture::actingAsTenant($this->delta);
        $this->dhakaLine = AssetLocation::create([
            'factory_id' => $this->dhaka->id, 'name' => 'Line 3', 'code' => 'DHK-L3',
        ]);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'asset_code' => 'SEW-DHK-00412',
            'name' => 'Juki DDL-9000C',
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->dhaka->id,
            'asset_location_id' => $this->dhakaLine->id,
        ], $overrides);
    }

    private function seedAsset(?Company $company = null, ?Factory $factory = null, string $code = 'SEW-1'): Asset
    {
        $company ??= $this->delta;
        $factory ??= $this->dhaka;

        TenantFixture::actingAsTenant($company);

        $location = AssetLocation::firstOrCreate(
            ['company_id' => $company->id, 'code' => $factory->code.'-L1'],
            ['factory_id' => $factory->id, 'name' => 'Line 1'],
        );

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

    public function test_the_list_renders_and_shows_only_this_companys_assets(): void
    {
        $mine = $this->seedAsset(code: 'SEW-MINE');

        $rivalFactory = TenantFixture::factory($this->rival, 'Rival Plant', 'RVP');
        $theirs = $this->seedAsset($this->rival, $rivalFactory, 'SEW-THEIRS');

        $response = $this->actingAs($this->owner)->get('/app/assets');

        $response->assertOk();
        $response->assertSee($mine->asset_code);
        $response->assertDontSee($theirs->asset_code);
    }

    public function test_an_asset_can_be_created_through_the_form(): void
    {
        $response = $this->actingAs($this->owner)->post('/app/assets', $this->payload());

        $asset = Asset::withoutGlobalScopes()->where('asset_code', 'SEW-DHK-00412')->firstOrFail();

        $response->assertRedirect(route('app.assets.show', $asset));
        $this->assertSame($this->delta->id, $asset->company_id);
        $this->assertSame('DRAFT', $asset->status);
        $this->assertSame(12, strlen($asset->qr_code));
    }

    public function test_a_technician_cannot_reach_the_create_form_or_post_to_it(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        // A hidden button is not a control. The route must refuse too
        // (Frontend 9.1, SRS 55.1 rule 2).
        $this->actingAs($technician)->get('/app/assets/create')->assertForbidden();
        $this->actingAs($technician)->post('/app/assets', $this->payload())->assertForbidden();
    }

    public function test_the_create_button_is_hidden_from_a_technician(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->seedAsset();

        $response = $this->actingAs($technician)->get('/app/assets');

        $response->assertOk();
        $response->assertDontSee(route('app.assets.create'));
    }

    public function test_another_companys_asset_is_not_found_even_with_its_id(): void
    {
        $rivalFactory = TenantFixture::factory($this->rival, 'Rival Plant', 'RVP');
        $theirs = $this->seedAsset($this->rival, $rivalFactory, 'SEW-THEIRS');

        // 404, not 403: cross-tenant probing must not distinguish "exists
        // elsewhere" from "does not exist" (API 2).
        $this->actingAs($this->owner)->get("/app/assets/{$theirs->id}")->assertNotFound();
    }

    public function test_a_factory_scoped_user_cannot_open_another_factorys_asset(): void
    {
        $gazipurAsset = $this->seedAsset($this->delta, $this->gazipur, 'SEW-GAZ-1');

        $scoped = TenantFixture::user(
            $this->delta, 'MAINTENANCE_MANAGER', 'dhaka@delta.test', factoryId: $this->dhaka->id,
        );

        $this->actingAs($scoped)->get("/app/assets/{$gazipurAsset->id}")->assertForbidden();
    }

    public function test_the_detail_screen_renders_status_and_location(): void
    {
        $asset = $this->seedAsset();

        $response = $this->actingAs($this->owner)->get("/app/assets/{$asset->id}");

        $response->assertOk();
        $response->assertSee($asset->asset_code);
        $response->assertSee($asset->qr_code);
        $response->assertSee(__('asset.status_draft'));
    }

    public function test_financial_figures_are_hidden_without_the_permission(): void
    {
        TenantFixture::actingAsTenant($this->delta);
        $asset = app(CreateAsset::class)->handle($this->payload([
            'acquisition_cost' => '285000',
            'currency' => 'BDT',
        ]));

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        // A technician needs the machine record, not its purchase price.
        $this->actingAs($technician)
            ->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertDontSee('285000.0000');

        $this->actingAs($this->owner)
            ->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertSee('285000.0000');
    }

    public function test_a_status_change_is_recorded_and_the_version_advances(): void
    {
        $asset = $this->seedAsset();

        $this->actingAs($this->owner)
            ->post("/app/assets/{$asset->id}/status", [
                'status' => 'PURCHASED',
                'version' => $asset->version,
            ])
            ->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertSame('PURCHASED', $fresh->status);
        $this->assertSame(2, $fresh->version);
    }

    public function test_a_stale_version_is_rejected_with_a_conflict(): void
    {
        $asset = $this->seedAsset();

        // Someone else moved it on while this form was open.
        app(ChangeAssetStatus::class)->handle($asset, 'PURCHASED');

        $this->actingAs($this->owner)
            ->post("/app/assets/{$asset->id}/status", [
                'status' => 'INSTALLED',
                'version' => 1,
            ])
            ->assertSessionHasErrors('version');

        $this->assertSame('PURCHASED', $asset->fresh()->status);
    }

    public function test_an_illegal_transition_is_refused_by_the_route(): void
    {
        $asset = $this->seedAsset();

        $this->actingAs($this->owner)
            ->post("/app/assets/{$asset->id}/status", [
                'status' => 'RUNNING',
                'version' => $asset->version,
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_a_transfer_can_be_requested_and_received(): void
    {
        $asset = $this->seedAsset();

        TenantFixture::actingAsTenant($this->delta);
        $destination = AssetLocation::create([
            'factory_id' => $this->gazipur->id, 'name' => 'Line 1', 'code' => 'GAZ-L1',
        ]);

        $this->actingAs($this->owner)
            ->post("/app/assets/{$asset->id}/transfer", [
                'to_location_id' => $destination->id,
                'reason' => 'Line rebalancing for winter order',
                'version' => $asset->version,
            ])
            ->assertRedirect(route('app.assets.show', $asset));

        // Between factories, so it waits for receipt and the asset has not moved.
        $this->assertSame($this->dhaka->id, $asset->fresh()->current_factory_id);

        $transfer = $asset->transfers()->firstOrFail();
        $this->actingAs($this->owner)
            ->post("/app/transfers/{$transfer->id}/receive")
            ->assertRedirect();

        $this->assertSame($this->gazipur->id, $asset->fresh()->current_factory_id);
    }

    public function test_the_pending_transfer_queue_renders(): void
    {
        $asset = $this->seedAsset();

        TenantFixture::actingAsTenant($this->delta);
        $destination = AssetLocation::create([
            'factory_id' => $this->gazipur->id, 'name' => 'Line 1', 'code' => 'GAZ-L1',
        ]);

        $this->actingAs($this->owner)->post("/app/assets/{$asset->id}/transfer", [
            'to_location_id' => $destination->id,
            'reason' => 'Line rebalancing',
            'version' => $asset->version,
        ]);

        $this->actingAs($this->owner)
            ->get('/app/assets/transfers')
            ->assertOk()
            ->assertSee($asset->asset_code);
    }

    public function test_the_asset_code_cannot_be_changed_by_editing(): void
    {
        $asset = $this->seedAsset(code: 'SEW-ORIGINAL');

        $this->actingAs($this->owner)->patch("/app/assets/{$asset->id}", [
            'asset_code' => 'SEW-RENAMED',
            'name' => 'Renamed machine',
            'asset_type_id' => $asset->asset_type_id,
            'asset_category_id' => $asset->asset_category_id,
            'criticality' => 'HIGH',
            'current_factory_id' => $asset->current_factory_id,
            'asset_location_id' => $asset->asset_location_id,
            'version' => $asset->version,
        ])->assertRedirect();

        $fresh = $asset->fresh();

        // It is printed on the machine and referenced by every historical
        // record, so it stays put while the rest of the edit applies.
        $this->assertSame('SEW-ORIGINAL', $fresh->asset_code);
        $this->assertSame('Renamed machine', $fresh->name);
        $this->assertSame('HIGH', $fresh->criticality);
    }

    public function test_a_cost_without_a_currency_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/assets', $this->payload(['acquisition_cost' => '285000']))
            ->assertSessionHasErrors('currency');
    }

    public function test_the_sidebar_now_lists_the_asset_module(): void
    {
        $response = $this->actingAs($this->owner)->get('/app/dashboard');

        $response->assertOk();
        $response->assertSee(route('app.assets.index'));
    }
}
