<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Modules\Api\Actions\IssueApiToken;
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
 * QR identity, scan resolution and labels (SRS 8, Data Dictionary 5).
 *
 * The scan landing page itself is Next.js now (Phase F completion) —
 * `/s/{code}`/`/s/l/{code}` are bare redirects kept alive only for a label
 * printed before the port (`ScanController`'s own docblock), so they carry
 * no auth gate and no resolution of their own any more; every real
 * assertion here — resolution, tenant isolation, malformed tokens,
 * role-aware actions — runs against `ScanApiController` instead, the same
 * way the Next.js page itself reaches it.
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

    private function tokenFor(User $user): string
    {
        return app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test')['plain'];
    }

    private function api(User $user): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user));

        return $this;
    }

    // -- The legacy Blade redirect ------------------------------------------

    public function test_the_legacy_asset_route_redirects_into_next_js(): void
    {
        // No auth gate here any more — resolving the code and sending a
        // guest to sign in and back are both the Next.js page's own job
        // now (`ScanController`'s docblock: doing it twice would mean a
        // Blade session and a Next.js session must both be live at once).
        $this->get("/s/{$this->asset->qr_code}")
            ->assertRedirect(rtrim((string) config('tenancy.frontend_url'), '/').'/scan/'.$this->asset->qr_code);
    }

    public function test_the_legacy_location_route_redirects_into_next_js(): void
    {
        $this->get("/s/l/{$this->line->qr_code}")
            ->assertRedirect(rtrim((string) config('tenancy.frontend_url'), '/').'/scan/location/'.$this->line->qr_code);
    }

    // -- The real resolution, now over the API -------------------------------

    public function test_scanning_an_asset_resolves_it(): void
    {
        $response = $this->api($this->owner)->getJson("/api/v1/scan/assets/{$this->asset->qr_code}");

        $response->assertOk();
        $response->assertJsonPath('data.asset.asset_code', $this->asset->asset_code);
        $response->assertJsonPath('data.asset.name', $this->asset->name);
    }

    public function test_a_guest_token_is_rejected(): void
    {
        $this->getJson("/api/v1/scan/assets/{$this->asset->qr_code}")->assertUnauthorized();
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
        $this->api($this->owner)->getJson("/api/v1/scan/assets/{$theirs->qr_code}")->assertNotFound();
    }

    public function test_a_malformed_token_is_not_found(): void
    {
        foreach (['ABC', 'IIIIIIIIIIII', 'abcdefghijkl', '..%2F..%2Fetc%2Fpasswd'] as $bad) {
            $this->api($this->owner)->getJson('/api/v1/scan/assets/'.$bad)->assertNotFound();
        }
    }

    public function test_the_scan_response_offers_role_aware_actions_with_the_asset_preselected(): void
    {
        $lineChief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'linechief@delta.test');
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $ownerKeys = $this->actionKeys($this->owner);
        $this->assertContains('view', $ownerKeys);
        $this->assertContains('transfer', $ownerKeys);

        // Reporting is Line Chief's job now (`RoleSeeder`), not the repair
        // technician's — the floor supervisor standing next to the machine
        // is the one scanning it to report it, same as this endpoint
        // already does for anyone else `breakdown.breakdown.create` gates in.
        $lineChiefResponse = $this->api($lineChief)->getJson("/api/v1/scan/assets/{$this->asset->qr_code}");
        $lineChiefKeys = collect($lineChiefResponse->json('data.actions'))->pluck('key');
        $this->assertContains('report_breakdown', $lineChiefKeys);
        $this->assertNotContains('transfer', $lineChiefKeys);

        $reportAction = collect($lineChiefResponse->json('data.actions'))->firstWhere('key', 'report_breakdown');
        // The one thing this whole feature exists to fix: the scanned
        // machine arrives preselected, not as one more thing to pick again.
        $this->assertSame("/breakdowns/create?asset_id={$this->asset->id}", $reportAction['route']);

        // A technician still logs meter readings and does their own repair
        // work off an already-reported breakdown, but no longer reports one.
        $techKeys = $this->actionKeys($technician);
        $this->assertNotContains('report_breakdown', $techKeys);
        $this->assertNotContains('transfer', $techKeys);
        $this->assertContains('log_meter', $techKeys);

        $techResponse = $this->api($technician)->getJson("/api/v1/scan/assets/{$this->asset->qr_code}");
        $meterAction = collect($techResponse->json('data.actions'))->firstWhere('key', 'log_meter');
        // Lands straight on the Metering tab, not Overview.
        $this->assertSame("/assets/{$this->asset->id}?tab=metering", $meterAction['route']);
    }

    private function actionKeys(User $user)
    {
        return collect(
            $this->api($user)->getJson("/api/v1/scan/assets/{$this->asset->qr_code}")->json('data.actions'),
        )->pluck('key');
    }

    public function test_a_terminal_asset_offers_no_breakdown_action(): void
    {
        $status = app(ChangeAssetStatus::class);
        $asset = $this->asset;

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $s) {
            $asset = $status->handle($asset, $s);
        }

        $asset = $status->handle($asset, 'RETIRED', reason: 'End of life');

        $keys = collect(
            $this->api($this->owner)->getJson("/api/v1/scan/assets/{$asset->qr_code}")->json('data.actions'),
        )->pluck('key');

        $this->assertNotContains('report_breakdown', $keys);
    }

    public function test_scanning_a_location_lists_what_stands_there(): void
    {
        $response = $this->api($this->owner)->getJson("/api/v1/scan/locations/{$this->line->qr_code}");

        $response->assertOk();
        $response->assertJsonPath('data.location.name', $this->line->name);
        $response->assertJsonFragment(['asset_code' => $this->asset->asset_code]);
    }

    // `test_the_label_sheet_renders_a_scannable_qr` lived here — the
    // Blade `/app/assets/labels` sheet is gone (Phase D/F), and the same
    // guarantee (a scannable QR per selected asset) is proven at
    // `Api/AssetApiTest::test_bulk_labels_returns_a_scannable_qr_per_
    // selected_asset` against its Next.js-facing replacement.

    public function test_regenerating_the_token_invalidates_the_old_label(): void
    {
        $old = $this->asset->qr_code;

        $this->api($this->owner)
            ->postJson("/api/v1/assets/{$this->asset->id}/qr/regenerate")
            ->assertOk();

        $new = $this->asset->fresh()->qr_code;

        $this->assertNotSame($old, $new);

        // The label stuck to the machine now scans to nothing, which is the
        // point, and the change is recorded so a failed scan has an
        // explanation (Data Dictionary 5.5).
        $this->api($this->owner)->getJson("/api/v1/scan/assets/{$old}")->assertNotFound();
        $this->api($this->owner)->getJson("/api/v1/scan/assets/{$new}")->assertOk();

        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $this->asset->id,
            'source' => 'SYSTEM',
        ]);
    }

    public function test_regenerating_requires_the_elevated_permission(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->api($technician)
            ->postJson("/api/v1/assets/{$this->asset->id}/qr/regenerate")
            ->assertForbidden();
    }

    // `test_the_asset_detail_shows_its_qr` lived here — the Blade `/app/
    // assets/{id}` detail screen is gone (Phase D/F). `Api/AssetApiTest`
    // already asserts the detail response carries `qr_code`; the
    // server-rendered `<svg>` half doesn't carry over; the Next.js asset
    // page renders its own QR client-side from that same string.
}
