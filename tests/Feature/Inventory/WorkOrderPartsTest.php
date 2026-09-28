<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Asset\Models\Asset;
use App\Modules\Inventory\Actions\IssuePartsToWorkOrder;
use App\Modules\Inventory\Actions\ReserveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Services\WorkOrderCostCalculator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Parts moving between the store and a machine (SRS 19, ERD Section 13).
 *
 * Issue, consume and return are three separate facts. "Four came out of the
 * store" and "four went into the machine" are different claims, and the gap
 * between them is stock in somebody's toolbox that the system believes is
 * fitted.
 */
class WorkOrderPartsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Bin $bin;

    private SparePart $part;

    private InventoryLedger $ledger;

    private IssuePartsToWorkOrder $parts;

    private ReserveStock $reservations;

    private TransitionWorkOrder $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->part = InventoryFixture::part($this->delta);

        $this->ledger = app(InventoryLedger::class);
        $this->parts = app(IssuePartsToWorkOrder::class);
        $this->reservations = app(ReserveStock::class);
        $this->transition = app(TransitionWorkOrder::class);

        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '20', '250');
    }

    private function inProgress(): WorkOrder
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Hook replacement',
        ], 'user-a');

        $workOrder = $this->transition->schedule($workOrder, 'user-a');

        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        app(AssignTechnicians::class)->handle($workOrder, [$technician->id], 'user-a');

        return $this->transition->start($workOrder->fresh(), 'user-a');
    }

    private function balance(): InventoryBalance
    {
        return InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail();
    }

    public function test_issuing_moves_stock_and_freezes_the_cost(): void
    {
        $workOrder = $this->inProgress();

        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');

        $this->assertSame('4.0000', (string) $line->quantity_issued);
        $this->assertSame('250.0000', (string) $line->unit_cost);
        $this->assertSame('1000.0000', (string) $line->total_cost);
        $this->assertSame('16.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_a_later_price_change_does_not_rewrite_what_the_repair_cost(): void
    {
        $workOrder = $this->inProgress();

        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');

        // The store buys more at triple the price.
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '20', '750');

        // The repair still cost 250 each. Costing it at the new average would
        // change history every time somebody restocks.
        $this->assertSame('250.0000', (string) $line->fresh()->unit_cost);
        $this->assertSame('1000.0000', (string) $line->fresh()->total_cost);
    }

    public function test_the_parts_cost_reaches_the_work_order(): void
    {
        $workOrder = $this->inProgress();

        $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');

        app(WorkOrderCostCalculator::class)->recalculate($workOrder->fresh());

        // Derived from the line, which is derived from the ledger, so the total
        // can always be traced back to a movement (ADR-064).
        $this->assertSame('1000.0000', (string) $workOrder->fresh()->actual_parts_cost);
        $this->assertSame('1000.0000', (string) $workOrder->fresh()->actual_cost);
    }

    public function test_consuming_does_not_move_stock_a_second_time(): void
    {
        $workOrder = $this->inProgress();

        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');
        $before = InventoryTransaction::count();

        $line = $this->parts->consume($line, '4', 'user-a');

        // The stock left the store at issue time. Posting again here would
        // double-count it out of the bin.
        $this->assertSame($before, InventoryTransaction::count());
        $this->assertSame('16.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('CONSUMED', $line->status);
    }

    public function test_consuming_more_than_was_issued_is_refused(): void
    {
        $workOrder = $this->inProgress();
        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '2', 'user-a');

        try {
            // Otherwise a work order can consume stock that never left the shelf.
            $this->parts->consume($line, '5', 'user-a');
            $this->fail('Consuming more than issued must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        }
    }

    public function test_returning_puts_stock_back_and_reduces_the_charge(): void
    {
        $workOrder = $this->inProgress();

        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');
        $line = $this->parts->consume($line, '1', 'user-a');
        $line = $this->parts->returnToStore($line, '3', 'user-a');

        // Three back on the shelf at the price they left at, and the work order
        // is charged only for the one that stayed in the machine.
        $this->assertSame('19.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('250.0000', (string) $this->balance()->weighted_average_cost);
        $this->assertSame('250.0000', (string) $line->total_cost);
    }

    public function test_consumed_plus_returned_may_not_exceed_issued(): void
    {
        $workOrder = $this->inProgress();

        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');
        $line = $this->parts->consume($line, '3', 'user-a');

        // One is still out. Returning two would account for five out of four
        // (ERD Section 13 rule 1).
        $this->expectException(ValidationException::class);
        $this->parts->returnToStore($line, '2', 'user-a');
    }

    public function test_a_work_order_cannot_close_holding_unaccounted_stock(): void
    {
        $workOrder = $this->inProgress();

        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');
        $this->parts->consume($line, '1', 'user-a');

        $workOrder = $this->transition->complete($workOrder->fresh(), 'user-a');

        try {
            // Three are neither fitted nor back on the shelf. Closing over that
            // writes the loss off silently, which is how a store's figures drift
            // away from its shelves (SRS 13.3).
            $this->transition->close($workOrder, 'user-b');
            $this->fail('Closing over unaccounted stock must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertArrayHasKey('parts', $e->errors());
        }

        $this->parts->returnToStore($line->fresh(), '3', 'user-a');

        $closed = $this->transition->close($workOrder->fresh(), 'user-b');
        $this->assertSame('CLOSED', $closed->status);
    }

    public function test_a_reservation_holds_stock_without_moving_it(): void
    {
        $workOrder = $this->inProgress();

        $reservation = $this->reservations->handle($this->part, $this->bin, $workOrder, '6', 'user-a');

        // Nothing has physically moved, so nothing is written to the ledger. A
        // row here would mean replaying it no longer reproduces the shelf.
        $this->assertSame(1, InventoryTransaction::count());
        $this->assertSame('20.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('6.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('14.0000', $this->ledger->available($this->part, $this->bin));
        $this->assertSame('ACTIVE', $reservation->status);
    }

    public function test_reserving_more_than_is_free_is_refused(): void
    {
        $workOrder = $this->inProgress();

        $this->reservations->handle($this->part, $this->bin, $workOrder, '18', 'user-a');

        try {
            $this->reservations->handle($this->part, $this->bin, $workOrder, '5', 'user-a');
            $this->fail('Reserving past what is free must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_issuing_against_a_reservation_releases_the_hold(): void
    {
        $workOrder = $this->inProgress();

        $reservation = $this->reservations->handle($this->part, $this->bin, $workOrder, '6', 'user-a');

        $this->parts->issue($workOrder, $this->part, $this->bin, '6', 'user-a', $reservation);

        // Six left the shelf and the hold on them went with it. Leaving the
        // reservation standing would make six units invisible to everyone else
        // for good.
        $this->assertSame('14.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('ISSUED', $reservation->fresh()->status);
    }

    public function test_a_partial_issue_leaves_the_rest_reserved(): void
    {
        $workOrder = $this->inProgress();

        $reservation = $this->reservations->handle($this->part, $this->bin, $workOrder, '6', 'user-a');
        $this->parts->issue($workOrder, $this->part, $this->bin, '2', 'user-a', $reservation);

        $this->assertSame('4.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('PARTIALLY_ISSUED', $reservation->fresh()->status);
    }

    public function test_releasing_a_reservation_frees_the_stock(): void
    {
        $workOrder = $this->inProgress();

        $reservation = $this->reservations->handle($this->part, $this->bin, $workOrder, '6', 'user-a');
        $this->reservations->release($reservation, null, 'user-a');

        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('RELEASED', $reservation->fresh()->status);
        // Closed, not deleted: "why was this held for two days" is a question
        // somebody asks.
        $this->assertNotNull($reservation->fresh()->released_at);
    }

    public function test_closing_a_work_order_releases_what_it_was_holding(): void
    {
        $workOrder = $this->inProgress();

        $this->reservations->handle($this->part, $this->bin, $workOrder, '6', 'user-a');

        $workOrder = $this->transition->complete($workOrder->fresh(), 'user-a');
        $this->transition->close($workOrder, 'user-b');

        // Parts set aside for work that is over go back on the shelf rather
        // than staying invisible to the rest of the factory.
        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame(
            'RELEASED',
            SparePartReservation::where('work_order_id', $workOrder->id)->firstOrFail()->status,
        );
    }

    public function test_a_substitution_is_recorded_against_the_part_it_replaced(): void
    {
        $workOrder = $this->inProgress();

        $specified = InventoryFixture::part($this->delta, 'JK-DDL9000-HOOK-OEM', 'Rotary hook, OEM');

        $line = $this->parts->request($workOrder, $this->part, '2', $specified);

        // What was actually fitted, and what it stood in for. Recording only
        // the part used loses the reason a machine failed early (SRS 20).
        $this->assertSame($this->part->id, $line->spare_part_id);
        $this->assertSame($specified->id, $line->substitute_for_spare_part_id);
    }

    public function test_a_closed_work_order_accepts_no_more_parts(): void
    {
        $workOrder = $this->inProgress();
        $workOrder = $this->transition->complete($workOrder, 'user-a');
        $workOrder = $this->transition->close($workOrder, 'user-b');

        try {
            $this->parts->issue($workOrder, $this->part, $this->bin, '1', 'user-a');
            $this->fail('Issuing to a closed work order must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_the_ledger_still_replays_after_a_full_parts_cycle(): void
    {
        $workOrder = $this->inProgress();

        $reservation = $this->reservations->handle($this->part, $this->bin, $workOrder, '6', 'user-a');
        $line = $this->parts->issue($workOrder, $this->part, $this->bin, '6', 'user-a', $reservation);
        $line = $this->parts->consume($line, '4', 'user-a');
        $this->parts->returnToStore($line, '2', 'user-a');

        $result = $this->ledger->verify($this->part, $this->bin);

        // 20 received, 6 issued, 2 returned.
        $this->assertTrue($result['matches']);
        $this->assertSame('16.0000', $result['balance']);
    }
}
