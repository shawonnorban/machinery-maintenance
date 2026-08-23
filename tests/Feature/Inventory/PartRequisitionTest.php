<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Asking the store for a part (SRS 22).
 *
 * The step between "this machine needs a hook" and "the store handed one
 * over". Without it the only record of a part being needed was the moment it
 * was issued, so a part nobody had in stock left no trace: the job sat there
 * and the reason lived in somebody's memory.
 *
 * A request moves no stock. That is the whole point of it being a separate
 * step with a separate permission — a technician may ask, only the store may
 * hand over.
 */
class PartRequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private SparePart $part;

    private User $storeManager;

    private User $technicianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->part = InventoryFixture::part($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    private function workOrder(): WorkOrder
    {
        TenantFixture::actingAsTenant($this->delta);

        $asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Hook replacement',
        ], $this->storeManager->id);

        $transition = app(TransitionWorkOrder::class);
        $workOrder = $transition->schedule($workOrder, $this->storeManager->id);

        $technician = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001', $this->technicianUser,
        );

        app(AssignTechnicians::class)->handle($workOrder, [$technician->id], $this->storeManager->id);

        return $transition->start($workOrder->fresh(), $this->storeManager->id);
    }

    public function test_a_technician_can_ask_the_store_for_a_part(): void
    {
        $workOrder = $this->workOrder();

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ])
            ->assertRedirect();

        $line = WorkOrderPart::where('work_order_id', $workOrder->id)->firstOrFail();

        $this->assertSame('REQUESTED', $line->status);
        $this->assertSame('2.0000', (string) $line->quantity_requested);

        // Nothing has moved: no stock, no reservation, no cost.
        $this->assertSame('0.0000', (string) $line->quantity_issued);
        $this->assertSame('0.0000', $workOrder->fresh()->actual_parts_cost);
    }

    /**
     * The join that makes the whole flow one story rather than two.
     */
    public function test_issuing_against_a_request_fills_the_same_line(): void
    {
        $workOrder = $this->workOrder();

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ]);

        $this->actingAs($this->storeManager)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/issue', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '2',
            ])
            ->assertRedirect();

        $lines = WorkOrderPart::where('work_order_id', $workOrder->id)->get();

        // One line, not two: what was asked for and what was handed over are
        // the same event seen twice.
        $this->assertCount(1, $lines);
        $this->assertSame('ISSUED', $lines[0]->status);
        $this->assertSame('2.0000', (string) $lines[0]->quantity_requested);
        $this->assertSame('2.0000', (string) $lines[0]->quantity_issued);
        $this->assertSame('500.0000', $workOrder->fresh()->actual_parts_cost);
    }

    public function test_a_request_can_be_withdrawn_while_nothing_has_been_issued(): void
    {
        $workOrder = $this->workOrder();

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ]);

        $line = WorkOrderPart::where('work_order_id', $workOrder->id)->firstOrFail();

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/'.$line->id.'/cancel-request')
            ->assertRedirect();

        $this->assertSame('CANCELLED', $line->fresh()->status);
    }

    /**
     * The line the withdrawal must not cross.
     */
    public function test_a_request_cannot_be_withdrawn_once_parts_are_in_hand(): void
    {
        $workOrder = $this->workOrder();

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ]);

        $this->actingAs($this->storeManager)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/issue', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '2',
            ]);

        $line = WorkOrderPart::where('work_order_id', $workOrder->id)->firstOrFail();

        $this->actingAs($this->technicianUser)
            ->from('/app/work-orders/'.$workOrder->id)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/'.$line->id.'/cancel-request')
            ->assertSessionHasErrors('quantity');

        // Still issued: those units left the shelf, and cancelling the line
        // would leave them unaccounted for.
        $this->assertSame('ISSUED', $line->fresh()->status);
    }

    public function test_a_technician_may_ask_but_may_not_issue(): void
    {
        $workOrder = $this->workOrder();

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/issue', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '2',
            ])
            ->assertForbidden();

        $this->assertSame(0, WorkOrderPart::where('work_order_id', $workOrder->id)->count());
    }

    /**
     * The screen the requisition exists for: a request nobody in the store can
     * see is a request that does not exist.
     */
    public function test_the_store_queue_lists_what_the_floor_is_waiting_for(): void
    {
        $workOrder = $this->workOrder();

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ]);

        $this->actingAs($this->storeManager)
            ->get('/app/inventory/requests')
            ->assertOk()
            ->assertSee($this->part->part_number)
            ->assertSee($workOrder->work_order_number)
            // Nothing on the shelf, which is the case worth surfacing: it is
            // something to go and buy, not something to go and fetch.
            ->assertSee(__('inventory.not_enough_stock'));
    }

    public function test_a_filled_request_leaves_the_queue(): void
    {
        $workOrder = $this->workOrder();

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ]);

        $this->actingAs($this->storeManager)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/issue', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '2',
            ]);

        $this->actingAs($this->storeManager)
            ->get('/app/inventory/requests')
            ->assertOk()
            ->assertSee(__('inventory.no_part_requests'));
    }

    public function test_another_companys_request_never_appears_in_the_queue(): void
    {
        $workOrder = $this->workOrder();

        $this->actingAs($this->technicianUser)
            ->post('/app/work-orders/'.$workOrder->id.'/parts/request', [
                'spare_part_id' => $this->part->id,
                'quantity' => '2',
            ]);

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::factory($other, 'Their Unit', 'BTU');

        // Their context before their user: a role assignment is tenant-scoped,
        // so creating it under our company would give them no roles in theirs.
        TenantFixture::actingAsTenant($other);
        $theirStoreManager = TenantFixture::user($other, 'STORE_MANAGER', 'store@btl.test');

        // A new person signing in gets a new session; the test client keeps the
        // one the last request used, and it still names our company.
        $this->flushSession();

        $this->actingAs($theirStoreManager)
            ->get('/app/inventory/requests')
            ->assertOk()
            ->assertDontSee($this->part->part_number);
    }
}
