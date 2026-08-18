<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Services\QrTokenGenerator;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * QR identity, scan resolution and labels
 * (SRS 8, Data Dictionary 5).
 */
class QrScanTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $rival;

    private Factory $dhaka;

    private AssetLocation $line;

    private Asset $asset;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->rival = TenantFixture::company('Rival Garments Ltd', 'RGL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');

        TenantFixture::actingAsTenant($this->delta);

        $this->line = AssetLocation::create([
            'factory_id' => $this->dhaka->id,
            'name' => 'Line 3',
            'code' => 'DHK-L3',
            // Generated, not hand-written: the alphabet excludes I, L, O and U,
            // so an invented token would not even pass the shape check.
            'qr_code' => app(QrTokenGenerator::class)->forLocation($this->delta->id),
        ]);

        $this->asset = app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => 'SEW-DHK-00412',
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->dhaka->id,
            'asset_location_id' => $this->line->id,
        ]);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_scanning_an_asset_resolves_it(): void
    {
        $response = $this->actingAs($this->owner)->get("/s/{$this->asset->qr_code}");

        $response->assertOk();
        $response->assertSee($this->asset->asset_code);
        $response->assertSee($this->asset->name);
    }

    public function test_a_guest_is_sent_to_login_and_returned_after(): void
    {
        // The token identifies; it does not authorise. A supervisor scanning a
        // machine for the first time lands on login and comes back here
        // (Data Dictionary 5.2).
        $this->get("/s/{$this->asset->qr_code}")->assertRedirect(route('login'));

        $this->assertSame(
            url("/s/{$this->asset->qr_code}"),
            session()->get('url.intended'),
        );
    }

    public function test_another_companys_token_does_not_resolve(): void
    {
        $rivalFactory = TenantFixture::factory($this->rival, 'Rival Plant', 'RVP');
        TenantFixture::actingAsTenant($this->rival);

        $rivalLocation = AssetLocation::create([
            'factory_id' => $rivalFactory->id, 'name' => 'Line 1', 'code' => 'RVP-L1',
        ]);

        $theirs = app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => 'SEW-RIVAL-1',
            'name' => 'Rival machine',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $rivalFactory->id,
            'asset_location_id' => $rivalLocation->id,
        ]);

        // A photographed label from another factory resolves to nothing.
        $this->actingAs($this->owner)->get("/s/{$theirs->qr_code}")->assertNotFound();
    }

    public function test_a_malformed_token_is_not_found(): void
    {
        foreach (['ABC', 'IIIIIIIIIIII', 'abcdefghijkl', '../../etc/passwd'] as $bad) {
            $this->actingAs($this->owner)
                ->get('/s/'.urlencode($bad))
                ->assertNotFound();
        }
    }

    public function test_the_scan_screen_offers_role_aware_actions(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $ownerView = $this->actingAs($this->owner)->get("/s/{$this->asset->qr_code}");
        $ownerView->assertSee(__('scan.view_asset'));
        $ownerView->assertSee(__('scan.transfer'));

        // A technician reports breakdowns but does not transfer machines.
        $techView = $this->actingAs($technician)->get("/s/{$this->asset->qr_code}");
        $techView->assertSee(__('scan.report_breakdown'));
        $techView->assertDontSee(__('scan.transfer'));
    }

    public function test_a_terminal_asset_offers_no_breakdown_action(): void
    {
        $status = app(ChangeAssetStatus::class);
        $asset = $this->asset;

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $s) {
            $asset = $status->handle($asset, $s);
        }

        $asset = $status->handle($asset, 'RETIRED', reason: 'End of life');

        $this->actingAs($this->owner)
            ->get("/s/{$asset->qr_code}")
            ->assertOk()
            ->assertDontSee(__('scan.report_breakdown'));
    }

    public function test_scanning_a_location_lists_what_stands_there(): void
    {
        $response = $this->actingAs($this->owner)->get("/s/l/{$this->line->qr_code}");

        $response->assertOk();
        $response->assertSee($this->line->name);
        $response->assertSee($this->asset->asset_code);
    }

    public function test_the_label_sheet_renders_a_scannable_qr(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('app.assets.labels', ['ids' => [$this->asset->id]]));

        $response->assertOk();
        $response->assertSee($this->asset->asset_code);
        // Inline SVG, so labels stay sharp at print size and need no GD.
        $response->assertSee('<svg', false);
        $response->assertSee('<rect', false);
    }

    public function test_regenerating_the_token_invalidates_the_old_label(): void
    {
        $old = $this->asset->qr_code;

        $this->actingAs($this->owner)
            ->post(route('app.assets.qr.regenerate', $this->asset))
            ->assertRedirect();

        $new = $this->asset->fresh()->qr_code;

        $this->assertNotSame($old, $new);

        // The label stuck to the machine now scans to nothing, which is the
        // point, and the change is recorded so a failed scan has an
        // explanation (Data Dictionary 5.5).
        $this->actingAs($this->owner)->get("/s/{$old}")->assertNotFound();
        $this->actingAs($this->owner)->get("/s/{$new}")->assertOk();

        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $this->asset->id,
            'source' => 'SYSTEM',
        ]);
    }

    public function test_regenerating_requires_the_elevated_permission(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->actingAs($technician)
            ->post(route('app.assets.qr.regenerate', $this->asset))
            ->assertForbidden();
    }

    public function test_the_asset_detail_shows_its_qr(): void
    {
        $response = $this->actingAs($this->owner)->get("/app/assets/{$this->asset->id}");

        $response->assertOk();
        $response->assertSee($this->asset->qr_code);
        $response->assertSee('<svg', false);
    }
}
