<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
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
 * The inventory screens over HTTP, including who is allowed to move stock.
 */
class InventoryScreensTest extends TestCase
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

    private function balance(): InventoryBalance
    {
        return InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail();
    }

    public function test_the_parts_listing_renders(): void
    {
        $this->actingAs($this->storeManager)
            ->get('/app/inventory/parts')
            ->assertOk()
            ->assertSee($this->part->part_number);
    }

    public function test_a_part_can_be_created(): void
    {
        $category = SparePartCategory::where('code', 'BEARINGS')->firstOrFail();

        $this->actingAs($this->storeManager)
            ->post('/app/inventory/parts', [
                'part_number' => 'SKF-6203',
                'name' => 'Deep groove ball bearing 6203',
                'category_id' => $category->id,
                'unit' => 'PCS',
                'minimum_stock' => '4',
                'reorder_level' => '10',
                'is_critical_spare' => '1',
            ])
            ->assertRedirect();

        $part = SparePart::withoutGlobalScopes()->where('part_number', 'SKF-6203')->firstOrFail();

        $this->assertTrue($part->is_critical_spare);
        // A new part starts with no stock at all: every unit enters through the
        // ledger, so each one has a movement behind it.
        $this->assertSame('0.0000', $part->totalOnHand());
    }

    public function test_a_duplicate_part_number_is_refused(): void
    {
        $this->actingAs($this->storeManager)
            ->post('/app/inventory/parts', [
                'part_number' => $this->part->part_number,
                'name' => 'Another hook',
                'unit' => 'PCS',
            ])
            ->assertSessionHasErrors('part_number');
    }

    public function test_stock_can_be_received_over_http(): void
    {
        $this->actingAs($this->storeManager)
            ->post('/app/inventory/stock/receive', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '12',
                'unit_cost' => '250',
                'transaction_type' => 'RECEIPT',
            ])
            ->assertRedirect();

        $this->assertSame('12.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('250.0000', (string) $this->balance()->weighted_average_cost);
    }

    public function test_receiving_without_a_cost_is_refused(): void
    {
        // Receiving at no price would drag the average down and make every
        // later issue look free.
        $this->actingAs($this->storeManager)
            ->post('/app/inventory/stock/receive', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '12',
                'transaction_type' => 'RECEIPT',
            ])
            ->assertSessionHasErrors('unit_cost');
    }

    public function test_an_adjustment_without_a_reason_is_refused_by_the_form(): void
    {
        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '100');

        $this->actingAs($this->storeManager)
            ->post('/app/inventory/stock/adjust', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '2',
                'transaction_type' => 'ADJUSTMENT_OUT',
            ])
            ->assertSessionHasErrors('notes');

        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_the_part_screen_shows_the_ledger_and_proves_it_replays(): void
    {
        $ledger = app(InventoryLedger::class);
        $ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $ledger->post($this->part, $this->bin, 'ISSUE', '3');

        $this->actingAs($this->storeManager)
            ->get("/app/inventory/parts/{$this->part->id}")
            ->assertOk()
            ->assertSee(__('inventory.ledger'))
            ->assertSee(__('inventory.balance_after'))
            // Proof, not assertion: the screen states whether the ledger still
            // replays to the balance it is showing.
            ->assertSee(trans_choice('inventory.ledger_matches', 2, ['count' => 2]));
    }

    public function test_the_stock_screen_renders_with_a_valuation(): void
    {
        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->storeManager)
            ->get('/app/inventory/stock')
            ->assertOk()
            ->assertSee($this->part->part_number)
            ->assertSee(__('inventory.weighted_average_cost'));
    }

    public function test_low_stock_lists_parts_at_or_below_the_reorder_level(): void
    {
        $healthy = InventoryFixture::part($this->delta, 'JK-BELT-9000', 'Drive belt', [
            'reorder_level' => '2',
        ]);

        $ledger = app(InventoryLedger::class);
        $ledger->post($healthy, $this->bin, 'RECEIPT', '20', '80');
        // The hook has a reorder level of 5 and only three on the shelf.
        $ledger->post($this->part, $this->bin, 'RECEIPT', '3', '250');

        $this->actingAs($this->storeManager)
            ->get('/app/inventory/low-stock')
            ->assertOk()
            ->assertSee($this->part->part_number)
            ->assertDontSee($healthy->part_number);
    }

    public function test_a_movement_can_be_reversed_over_http(): void
    {
        $ledger = app(InventoryLedger::class);
        $ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $issue = $ledger->post($this->part, $this->bin, 'ISSUE', '4');

        $this->actingAs($this->storeManager)
            ->post("/app/inventory/transactions/{$issue->id}/reverse", [
                'reason' => 'Issued against the wrong work order',
            ])
            ->assertRedirect();

        // The original is untouched and a new opposing row explains it.
        $this->assertSame('ISSUE', $issue->fresh()->transaction_type);
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame(3, InventoryTransaction::count());
    }

    public function test_a_technician_can_see_stock_but_not_receive_it(): void
    {
        // A technician needs to know whether a part is on the shelf before
        // walking to the store. They do not need to be able to receive
        // deliveries.
        $this->actingAs($this->technicianUser)->get('/app/inventory/parts')->assertOk();

        $this->actingAs($this->technicianUser)
            ->post('/app/inventory/stock/receive', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '5',
                'unit_cost' => '100',
                'transaction_type' => 'RECEIPT',
            ])
            ->assertForbidden();
    }

    public function test_a_storekeeper_cannot_adjust_stock(): void
    {
        $storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '100');

        // Receiving and issuing are daily work. Writing stock off is not, and
        // it is the one movement with no counterparty to notice it.
        $this->actingAs($storekeeper)
            ->post('/app/inventory/stock/adjust', [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '2',
                'transaction_type' => 'ADJUSTMENT_OUT',
                'notes' => 'Damaged',
            ])
            ->assertForbidden();
    }

    public function test_another_company_cannot_reach_this_part(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        // 404, not 403: a 403 confirms the id names a real part.
        $this->actingAs($intruder)
            ->get("/app/inventory/parts/{$this->part->id}")
            ->assertNotFound();
    }

    public function test_parts_can_be_issued_to_a_work_order_over_http(): void
    {
        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '20', '250');

        $workOrder = $this->inProgressWorkOrder();

        // A technician does not issue stock to themselves. Requesting is
        // theirs; handing it across the counter is the storekeeper's, and that
        // separation is the only control a store has over its own shelves.
        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/parts/issue", [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '4',
            ])
            ->assertForbidden();

        $this->actingAs($this->storeManager)
            ->post("/app/work-orders/{$workOrder->id}/parts/issue", [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '4',
            ])
            ->assertRedirect();

        $this->assertSame('16.0000', (string) $this->balance()->quantity_on_hand);
        // The parts cost reaches the work order without anybody typing it.
        $this->assertSame('1000.0000', (string) $workOrder->fresh()->actual_parts_cost);
    }

    public function test_the_work_order_screen_shows_the_unaccounted_quantity(): void
    {
        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '20', '250');

        $workOrder = $this->inProgressWorkOrder();

        $this->actingAs($this->storeManager)
            ->post("/app/work-orders/{$workOrder->id}/parts/issue", [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => '4',
            ]);

        // The technician sees what is out against their job even though they
        // could not issue it themselves.
        $this->actingAs($this->technicianUser)
            ->get("/app/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee($this->part->part_number)
            ->assertSee(__('inventory.outstanding'));
    }

    /**
     * A catalogue you cannot correct is a catalogue that goes stale.
     *
     * Part numbers are mistyped, reorder levels are set before anybody knows
     * the real lead time, and a part gets recategorised. Until now the only
     * way to fix any of it was a spreadsheet import.
     */
    public function test_a_part_can_be_corrected_after_it_is_created(): void
    {
        $this->actingAs($this->storeManager)
            ->get('/app/inventory/parts/'.$this->part->id.'/edit')
            ->assertOk()
            ->assertSee($this->part->part_number);

        $category = SparePartCategory::whereNull('company_id')->where('code', 'KNITTING_PARTS')->firstOrFail();

        $this->actingAs($this->storeManager)
            ->patch('/app/inventory/parts/'.$this->part->id, [
                'part_number' => $this->part->part_number,
                'name' => 'Rotary hook, corrected',
                'unit' => 'PCS',
                'category_id' => $category->id,
                'reorder_level' => '12',
                'lead_time_days' => '30',
                'is_critical_spare' => '1',
            ])
            ->assertRedirect('/app/inventory/parts/'.$this->part->id);

        $part = $this->part->fresh();

        $this->assertSame('Rotary hook, corrected', $part->name);
        $this->assertSame($category->id, $part->category_id);
        $this->assertSame(30, $part->lead_time_days);
        $this->assertTrue($part->is_critical_spare);
    }

    public function test_editing_a_part_leaves_the_stock_it_holds_alone(): void
    {
        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '2450');

        $before = $this->balance()->quantity_on_hand;

        $this->actingAs($this->storeManager)
            ->patch('/app/inventory/parts/'.$this->part->id, [
                'part_number' => $this->part->part_number,
                'name' => 'Renamed',
                'unit' => 'PCS',
            ])
            ->assertRedirect();

        // The catalogue and the ledger are different things. Editing what a
        // part is must never move what is on the shelf.
        $this->assertSame($before, $this->balance()->quantity_on_hand);
    }

    public function test_a_part_number_cannot_be_changed_to_one_already_in_use(): void
    {
        $other = InventoryFixture::part($this->delta, 'JK-OTHER-001');

        $this->actingAs($this->storeManager)
            ->from('/app/inventory/parts/'.$this->part->id.'/edit')
            ->patch('/app/inventory/parts/'.$this->part->id, [
                'part_number' => $other->part_number,
                'name' => $this->part->name,
                'unit' => 'PCS',
            ])
            ->assertSessionHasErrors('part_number');
    }

    /**
     * The part typed in twice, which retiring would leave in every list for
     * ever labelled as though it had once been real.
     */
    public function test_a_part_created_by_mistake_can_be_deleted(): void
    {
        $mistake = InventoryFixture::part($this->delta, 'JK-TYPO-001', 'Typed in twice');

        $this->actingAs($this->storeManager)
            ->delete('/app/inventory/parts/'.$mistake->id)
            ->assertRedirect('/app/inventory/parts');

        $this->assertNull(SparePart::find($mistake->id));
    }

    /**
     * The line the delete must not cross.
     */
    public function test_a_part_the_ledger_names_cannot_be_deleted(): void
    {
        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $this->actingAs($this->storeManager)
            ->from('/app/inventory/parts/'.$this->part->id.'/edit')
            ->delete('/app/inventory/parts/'.$this->part->id)
            ->assertSessionHasErrors('part_number');

        // Still there, and so is the movement that names it: a valuation has to
        // stay replayable.
        $this->assertNotNull(SparePart::find($this->part->id));
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_deleting_a_part_needs_the_permission_for_it(): void
    {
        $mistake = InventoryFixture::part($this->delta, 'JK-TYPO-002', 'Typed in twice');

        $this->actingAs($this->technicianUser)
            ->delete('/app/inventory/parts/'.$mistake->id)
            ->assertForbidden();

        $this->assertNotNull(SparePart::find($mistake->id));
    }

    public function test_a_part_is_retired_rather_than_deleted(): void
    {
        $this->actingAs($this->storeManager)
            ->post('/app/inventory/parts/'.$this->part->id.'/toggle')
            ->assertRedirect();

        // Still there, with its ledger intact: what was fitted to a machine two
        // years ago has to keep reading correctly.
        $this->assertFalse($this->part->fresh()->active);
        $this->assertNotNull(SparePart::find($this->part->id));
    }

    public function test_a_technician_cannot_edit_the_catalogue(): void
    {
        $this->actingAs($this->technicianUser)
            ->get('/app/inventory/parts/'.$this->part->id.'/edit')
            ->assertForbidden();

        $this->actingAs($this->technicianUser)
            ->patch('/app/inventory/parts/'.$this->part->id, [
                'part_number' => $this->part->part_number,
                'name' => 'Renamed by a technician',
                'unit' => 'PCS',
            ])
            ->assertForbidden();

        $this->assertNotSame('Renamed by a technician', $this->part->fresh()->name);
    }

    public function test_a_part_cannot_be_filed_under_another_companys_category(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirCategory = SparePartCategory::create([
            'company_id' => $other->id,
            'name' => 'Their private category',
            'code' => 'BTL_ONLY',
            'active' => true,
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->storeManager)
            ->from('/app/inventory/parts/'.$this->part->id.'/edit')
            ->patch('/app/inventory/parts/'.$this->part->id, [
                'part_number' => $this->part->part_number,
                'name' => $this->part->name,
                'unit' => 'PCS',
                'category_id' => $theirCategory->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertNotSame($theirCategory->id, $this->part->fresh()->category_id);
    }

    private function inProgressWorkOrder(): WorkOrder
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
}
