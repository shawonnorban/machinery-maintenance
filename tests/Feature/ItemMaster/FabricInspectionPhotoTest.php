<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Evidence photos on fabric inspection (Inventory spec §4.3) — the gap the
 * as-built note left open: "Photo আপলোড এখনো নেই — শুধু লিখিত Remarks".
 *
 * Mirrors `BreakdownAttachmentTest`'s shape, against both entry points a
 * roll can be inspected through: the single-roll `fabric_roll` path (a
 * delivery nobody four-points) and the lot-level `fabric_inspection` sheet.
 */
class FabricInspectionPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $viewer;

    private Vendor $vendor;

    private Item $fabric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A1');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');

        $this->vendor = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Foshan Textiles', 'code' => 'FOSHAN',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-DNM-IND', 'Denim indigo', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $this->fabric->id,
            'fabric_type' => 'WOVEN', 'gsm' => '340', 'width' => '60', 'composition' => '100% cotton',
        ]);
    }

    public function test_a_photo_can_be_attached_to_a_roll_and_appears_on_its_detail(): void
    {
        Storage::fake('local');

        $roll = $this->receiveOneRoll($this->tokenFor($this->storekeeper));

        $this->withToken($this->tokenFor($this->storekeeper))->post(
            "/api/v1/fabric-rolls/{$roll}/attachments",
            ['file' => $this->fakePng('roll-shade.png')],
        )->assertCreated()->assertJsonPath('data.original_name', 'roll-shade.png');

        $this->withToken($this->tokenFor($this->storekeeper))
            ->getJson("/api/v1/fabric-rolls/{$roll}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'roll-shade.png');

        $this->withToken($this->tokenFor($this->storekeeper))
            ->getJson("/api/v1/fabric-rolls/{$roll}")
            ->assertOk()
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('data.photos.0.original_name', 'roll-shade.png');
    }

    public function test_a_photo_can_be_attached_to_a_four_point_sheet_and_appears_on_its_detail(): void
    {
        Storage::fake('local');

        $token = $this->tokenFor($this->storekeeper);
        $grnId = $this->receiveOneRollReturningGrn($token);

        $inspection = $this->withToken($token)->postJson('/api/v1/fabric-inspections', [
            'goods_receipt_id' => $grnId,
            'item_id' => $this->fabric->id,
            'colour' => 'Indigo',
        ])->assertCreated()->json('data');

        $this->withToken($token)->post(
            "/api/v1/fabric-inspections/{$inspection['id']}/attachments",
            ['file' => $this->fakePng('lot-defect.png')],
        )->assertCreated()->assertJsonPath('data.original_name', 'lot-defect.png');

        $detail = $this->withToken($token)
            ->getJson("/api/v1/fabric-inspections/{$inspection['id']}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $detail['photos']);
        $this->assertSame('lot-defect.png', $detail['photos'][0]['original_name']);
        // Not gated on the sheet still being DRAFT — see the field's own
        // docblock note in the controller.
        $this->assertTrue($detail['can_manage_photos']);
    }

    public function test_a_viewer_without_the_inspect_permission_cannot_upload_a_photo(): void
    {
        Storage::fake('local');

        $roll = $this->receiveOneRoll($this->tokenFor($this->storekeeper));

        // Reading is fine — VIEWER holds every `.view_any`.
        $this->withToken($this->tokenFor($this->viewer))
            ->getJson("/api/v1/fabric-rolls/{$roll}/attachments")
            ->assertOk();

        // Adding evidence is not.
        $this->withToken($this->tokenFor($this->viewer))->post(
            "/api/v1/fabric-rolls/{$roll}/attachments",
            ['file' => $this->fakePng('not-allowed.png')],
        )->assertForbidden();
    }

    public function test_a_stranger_cannot_read_another_companys_roll_photos(): void
    {
        Storage::fake('local');

        $roll = $this->receiveOneRoll($this->tokenFor($this->storekeeper));

        $this->withToken($this->tokenFor($this->storekeeper))->post(
            "/api/v1/fabric-rolls/{$roll}/attachments",
            ['file' => $this->fakePng('evidence.png')],
        )->assertCreated();

        $outsider = TenantFixture::company('Outside Textiles', 'OUT');
        $outsiderUser = TenantFixture::user($outsider, 'STOREKEEPER', 'outsider@outside.test');

        $this->withToken($this->tokenFor($outsiderUser))
            ->getJson("/api/v1/fabric-rolls/{$roll}/attachments")
            ->assertNotFound();
    }

    public function test_the_shared_file_endpoint_serves_a_roll_photo_to_someone_who_may_see_the_roll(): void
    {
        Storage::fake('local');

        $roll = $this->receiveOneRoll($this->tokenFor($this->storekeeper));

        $attachmentId = $this->withToken($this->tokenFor($this->storekeeper))->post(
            "/api/v1/fabric-rolls/{$roll}/attachments",
            ['file' => $this->fakePng('via-shared-endpoint.png')],
        )->assertCreated()->json('data.id');

        $this->withToken($this->tokenFor($this->viewer))
            ->getJson("/api/v1/files/{$attachmentId}")
            ->assertOk()
            ->assertJsonPath('data.original_name', 'via-shared-endpoint.png');

        $attachment = FileAttachment::findOrFail($attachmentId);
        $this->assertSame('fabric_roll', $attachment->attachable_type);
        $this->assertSame($roll, $attachment->attachable_id);
    }

    // ---------------------------------------------------------------- helpers

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    private function receiveOneRoll(string $token): string
    {
        return $this->receiveOneRollViaGrn($token, returnRollInstead: true);
    }

    private function receiveOneRollReturningGrn(string $token): string
    {
        return $this->receiveOneRollViaGrn($token, returnRollInstead: false);
    }

    /**
     * One roll received against a direct GRN. Returns the roll id, or the
     * GRN id when `$returnRollInstead` is false — a single small helper
     * rather than two near-identical ones.
     */
    private function receiveOneRollViaGrn(string $token, bool $returnRollInstead): string
    {
        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->vendor->id,
            'lines' => [['item_id' => $this->fabric->id, 'received_qty' => '100', 'rate' => '250']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => [
                ['roll_no' => 'R-0001', 'net_length' => '100', 'gross_length' => '100', 'shade' => 'Indigo', 'bin_id' => $this->bin->id],
            ]],
        ])->assertOk();

        if ($returnRollInstead) {
            return FabricRoll::where('goods_receipt_id', $grn['id'])->sole()->id;
        }

        return $grn['id'];
    }

    /**
     * `UploadedFile::fake()->image()` needs GD built with JPEG/PNG encoding
     * support, which this environment's PHP doesn't have — real minimal PNG
     * bytes via `createWithContent()` sidestep that and still sniff as a
     * genuine `image/png` through `StoreFileAttachment`'s content-based
     * mime check.
     */
    private function fakePng(string $name): UploadedFile
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $pixel);
    }
}
