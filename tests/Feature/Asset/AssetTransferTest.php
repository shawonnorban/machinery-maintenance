<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Actions\TransferAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetTransfer;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Transfer workflow: SRS 7, ERD Section 4.
 *
 * The asset moves only at RECEIVED. Updating its location at request time
 * would record it somewhere it is not yet standing.
 */
class AssetTransferTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private AssetLocation $dhakaLine3;

    private AssetLocation $dhakaLine4;

    private AssetLocation $gazipurLine1;

    private Asset $asset;

    private TransferAsset $transfers;

    private string $requester = '01JQAAAAAAAAAAAAAAAAAAAAAA';

    private string $approver = '01JQBBBBBBBBBBBBBBBBBBBBBB';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        TenantFixture::actingAsTenant($this->delta);

        $this->dhakaLine3 = AssetLocation::create(['factory_id' => $this->dhaka->id, 'name' => 'Line 3', 'code' => 'DHK-L3']);
        $this->dhakaLine4 = AssetLocation::create(['factory_id' => $this->dhaka->id, 'name' => 'Line 4', 'code' => 'DHK-L4']);
        $this->gazipurLine1 = AssetLocation::create(['factory_id' => $this->gazipur->id, 'name' => 'Line 1', 'code' => 'GAZ-L1']);

        $this->transfers = app(TransferAsset::class);
        $this->asset = $this->runningAsset();
    }

    private function runningAsset(): Asset
    {
        $type = AssetType::where('code', 'SEWING')->firstOrFail();
        $category = AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail();

        $asset = app(CreateAsset::class)->handle([
            'asset_type_id' => $type->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'SEW-DHK-00412',
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->dhaka->id,
            'asset_location_id' => $this->dhakaLine3->id,
        ]);

        $status = app(ChangeAssetStatus::class);

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $s) {
            $asset = $status->handle($asset, $s);
        }

        return $asset;
    }

    public function test_a_transfer_gets_a_formatted_number(): void
    {
        $transfer = $this->transfers->request(
            $this->asset, $this->gazipurLine1->id, 'Line rebalancing', $this->requester,
        );

        // Data Dictionary 6: AT-{FACTORY}-{YYYY}-{SEQ:5}
        $this->assertMatchesRegularExpression('/^AT-DHK-\d{4}-\d{5}$/', $transfer->transfer_number);
        $this->assertSame('REQUESTED', $transfer->status);
    }

    public function test_transfer_numbers_do_not_repeat(): void
    {
        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $transfer = $this->transfers->request(
                $this->asset,
                $i % 2 === 0 ? $this->gazipurLine1->id : $this->dhakaLine4->id,
                'Rebalancing',
                $this->requester,
            );
            $numbers[] = $transfer->transfer_number;
        }

        $this->assertSame($numbers, array_unique($numbers));
    }

    public function test_the_asset_does_not_move_until_the_transfer_is_received(): void
    {
        $transfer = $this->transfers->request(
            $this->asset, $this->gazipurLine1->id, 'Line rebalancing', $this->requester,
        );

        // Still standing in Dhaka.
        $this->assertSame($this->dhaka->id, $this->asset->fresh()->current_factory_id);
        $this->assertSame($this->dhakaLine3->id, $this->asset->fresh()->asset_location_id);

        $this->transfers->approve($transfer, $this->approver);
        $this->assertSame($this->dhakaLine3->id, $this->asset->fresh()->asset_location_id);

        $this->transfers->receive($transfer, $this->approver);

        $moved = $this->asset->fresh();
        $this->assertSame($this->gazipur->id, $moved->current_factory_id);
        $this->assertSame($this->gazipurLine1->id, $moved->asset_location_id);
    }

    public function test_the_requester_cannot_approve_their_own_transfer(): void
    {
        $transfer = $this->transfers->request(
            $this->asset, $this->gazipurLine1->id, 'Line rebalancing', $this->requester,
        );

        try {
            $this->transfers->approve($transfer, $this->requester);
            $this->fail('Self-approval should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(403, $e->status);
        }
    }

    public function test_a_received_transfer_is_immutable(): void
    {
        $transfer = $this->transfers->request(
            $this->asset, $this->gazipurLine1->id, 'Line rebalancing', $this->requester,
        );
        $this->transfers->receive($transfer, $this->approver);

        // A correction is a reversing transfer, never an edit (ERD Section 4).
        try {
            $this->transfers->approve($transfer, $this->approver);
            $this->fail('A received transfer should be immutable.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_a_terminal_asset_cannot_be_transferred(): void
    {
        $status = app(ChangeAssetStatus::class);
        $asset = $status->handle($this->asset, 'RETIRED', reason: 'End of life');

        $this->expectException(ValidationException::class);
        $this->transfers->request($asset, $this->gazipurLine1->id, 'Move to store', $this->requester);
    }

    public function test_transferring_to_the_current_location_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->transfers->request(
            $this->asset, $this->dhakaLine3->id, 'No-op', $this->requester,
        );
    }

    public function test_a_location_in_another_company_is_not_reachable(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $narayanganj = TenantFixture::factory($omega, 'Narayanganj', 'NGJ');

        TenantFixture::actingAsTenant($omega);
        $foreign = AssetLocation::create([
            'factory_id' => $narayanganj->id, 'name' => 'Line 1', 'code' => 'NGJ-L1',
        ]);

        TenantFixture::actingAsTenant($this->delta);

        // Cross-tenant transfer is impossible by construction: the tenant
        // scope makes the foreign id resolve to nothing (ERD 25 rule 4).
        $this->expectException(ValidationException::class);
        $this->transfers->request($this->asset, $foreign->id, 'Attempted', $this->requester);
    }

    public function test_a_rejected_transfer_leaves_the_asset_in_place(): void
    {
        $transfer = $this->transfers->request(
            $this->asset, $this->gazipurLine1->id, 'Line rebalancing', $this->requester,
        );

        $this->transfers->reject($transfer, $this->approver, 'Receiving factory has no space');

        $this->assertSame('REJECTED', $transfer->fresh()->status);
        $this->assertSame($this->dhakaLine3->id, $this->asset->fresh()->asset_location_id);
    }

    public function test_an_intra_factory_move_can_complete_in_one_step(): void
    {
        // Two custodians are involved in a factory-to-factory move, so it
        // needs the approval hop. A move between lines in one factory does not.
        $transfer = $this->transfers->request(
            $this->asset, $this->dhakaLine4->id, 'Line rebalancing', $this->requester, autoReceive: true,
        );

        $this->assertSame('RECEIVED', $transfer->status);
        $this->assertSame($this->dhakaLine4->id, $this->asset->fresh()->asset_location_id);
    }

    public function test_transfer_history_records_where_the_asset_came_from(): void
    {
        $transfer = $this->transfers->request(
            $this->asset, $this->gazipurLine1->id, 'Line rebalancing', $this->requester,
        );
        $this->transfers->receive($transfer, $this->approver);

        $history = AssetTransfer::where('asset_id', $this->asset->id)->firstOrFail();

        $this->assertSame($this->dhaka->id, $history->from_factory_id);
        $this->assertSame($this->dhakaLine3->id, $history->from_location_id);
        $this->assertSame($this->gazipur->id, $history->to_factory_id);
        $this->assertSame($this->gazipurLine1->id, $history->to_location_id);
        $this->assertNotNull($history->received_at);
    }
}
