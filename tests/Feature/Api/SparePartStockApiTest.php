<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The standalone Spare Part stock actions (docs/03-API-Specification.md
 * §13) — reserve/release/issue/return/adjust, as distinct from the
 * work-order-tied versions on `WorkOrderPartApiController`. `reserve` and
 * `release` mirror the web `WorkOrderPartsController`; `issue`, `return`
 * and `adjust` mirror `StockController`, all unchanged per ADR-003.
 */
class SparePartStockApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private Bin $bin;

    private SparePart $part;

    private WorkOrder $workOrder;

    private User $storekeeper;

    private string $storekeeperToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->factory);
        $this->part = InventoryFixture::part($this->delta);
        app(ReceiveStock::class)->handle($this->part, $this->bin, '20', '500');

        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $this->workOrder = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'work_order_number' => 'WO-0001',
            'title' => 'Bearing replacement',
            'status' => 'IN_PROGRESS',
            'priority' => 'HIGH',
            'source' => 'MANUAL',
        ]);

        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test');
        $this->storekeeperToken = app(IssueApiToken::class)->forUser($this->storekeeper, $this->delta->id, 'x')['plain'];
    }

    private function asStorekeeper(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->storekeeperToken);

        return $this;
    }

    public function test_stock_is_reserved_and_released(): void
    {
        $reserve = $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/reserve", [
            'work_order_id' => $this->workOrder->id,
            'bin_id' => $this->bin->id,
            'quantity' => 5,
        ])->assertCreated();

        $this->assertSame('ACTIVE', $reserve->json('data.status'));
        $this->assertSame('5.0000', $reserve->json('data.quantity'));

        $release = $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/release", [
            'reservation_id' => $reserve->json('data.id'),
        ])->assertOk();

        $this->assertSame('RELEASED', $release->json('data.status'));
    }

    public function test_a_reservation_from_another_part_cannot_be_released_through_this_one(): void
    {
        $reserve = $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/reserve", [
            'work_order_id' => $this->workOrder->id,
            'bin_id' => $this->bin->id,
            'quantity' => 5,
        ])->assertCreated();

        $otherPart = InventoryFixture::part($this->delta, 'JK-OTHER', 'A different part');

        $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$otherPart->id}/release", [
            'reservation_id' => $reserve->json('data.id'),
        ])->assertNotFound();
    }

    public function test_stock_is_issued_and_returned_without_a_work_order(): void
    {
        $issue = $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/issue", [
            'bin_id' => $this->bin->id,
            'quantity' => 2,
            'notes' => 'Two pairs of gloves to the dye house',
        ])->assertCreated();

        $this->assertSame('ISSUE', $issue->json('data.transaction_type'));
        $this->assertSame('-2.0000', $issue->json('data.signed_quantity'));

        $return = $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/return", [
            'bin_id' => $this->bin->id,
            'quantity' => 1,
            'notes' => 'One pair unused, returned to store',
        ])->assertCreated();

        $this->assertSame('RETURN', $return->json('data.transaction_type'));

        $stock = $this->asStorekeeper()->getJson("/api/v1/spare-parts/{$this->part->id}/stock")->assertOk();
        // 20 received, 2 issued, 1 returned = 19 on hand.
        $this->assertSame('19.0000', $stock->json('data.total_on_hand'));
    }

    public function test_issuing_stock_requires_a_reason(): void
    {
        $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/issue", [
            'bin_id' => $this->bin->id,
            'quantity' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('notes');
    }

    public function test_a_downward_adjustment_requires_a_reason_and_moves_the_ledger(): void
    {
        // Not the storekeeper: adjustment is a factory-manager permission
        // (`inventory.adjustment.create`), separate from day-to-day
        // issue/return, which storekeeper holds instead.
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $token = app(IssueApiToken::class)->forUser($manager, $this->delta->id, 'x')['plain'];
        $this->withHeader('Authorization', 'Bearer '.$token);

        $this->postJson("/api/v1/spare-parts/{$this->part->id}/adjust", [
            'bin_id' => $this->bin->id,
            'quantity' => 1,
            'transaction_type' => 'SCRAP',
        ])->assertStatus(422)->assertJsonValidationErrors('notes');

        $adjust = $this->postJson("/api/v1/spare-parts/{$this->part->id}/adjust", [
            'bin_id' => $this->bin->id,
            'quantity' => 1,
            'transaction_type' => 'SCRAP',
            'notes' => 'Found damaged during physical count',
        ])->assertCreated();

        $this->assertSame('SCRAP', $adjust->json('data.transaction_type'));

        $stock = $this->getJson("/api/v1/spare-parts/{$this->part->id}/stock")->assertOk();
        $this->assertSame('19.0000', $stock->json('data.total_on_hand'));
    }

    public function test_issuing_stock_requires_the_permission(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $token = app(IssueApiToken::class)->forUser($engineer, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/spare-parts/{$this->part->id}/issue", [
                'bin_id' => $this->bin->id,
                'quantity' => 1,
                'notes' => 'x',
            ])->assertStatus(403);
    }

    public function test_reserving_more_than_available_is_refused(): void
    {
        $this->asStorekeeper()->postJson("/api/v1/spare-parts/{$this->part->id}/reserve", [
            'work_order_id' => $this->workOrder->id,
            'bin_id' => $this->bin->id,
            'quantity' => 1000,
        ])->assertStatus(409);
    }
}
