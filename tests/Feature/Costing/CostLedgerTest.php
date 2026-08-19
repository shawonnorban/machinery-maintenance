<?php

declare(strict_types=1);

namespace Tests\Feature\Costing;

use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Costing\Services\AssetLifecycleCost;
use App\Modules\Costing\Services\CostPoster;
use App\Modules\Inventory\Actions\IssuePartsToWorkOrder;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\RecordLaborEntry;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Services\WorkOrderCostCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Cost entries (SRS 23-24, ERD Section 14).
 *
 * Append-only after posting, for the same reason as the stock ledger: a cost
 * figure that can be edited is one somebody will edit to match a budget, and
 * the first time that happens the cost-per-machine report stops being evidence.
 */
class CostLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Bin $bin;

    private SparePart $part;

    private Technician $technician;

    private CostPoster $costs;

    private TransitionWorkOrder $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->asset->forceFill(['acquisition_cost' => '285000', 'currency' => 'BDT'])->save();
        $this->asset = $this->asset->fresh();

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->part = InventoryFixture::part($this->delta);

        $grade = WorkOrderFixture::grade($this->delta, '120.0000');
        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka, $grade);

        $this->costs = app(CostPoster::class);
        $this->transition = app(TransitionWorkOrder::class);

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '20', '250');
    }

    private function inProgress(): WorkOrder
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Hook replacement',
        ], 'user-a');

        $workOrder = $this->transition->schedule($workOrder, 'user-a');
        app(AssignTechnicians::class)->handle($workOrder->fresh(), [$this->technician->id], 'user-a');

        return $this->transition->start($workOrder->fresh(), 'user-a');
    }

    private function category(string $code): CostCategory
    {
        return CostCategory::where('code', $code)->firstOrFail();
    }

    public function test_labour_posts_a_cost_entry_without_anyone_typing_one(): void
    {
        $workOrder = $this->inProgress();

        app(RecordLaborEntry::class)->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 11:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        app(WorkOrderCostCalculator::class)->recalculate($workOrder->fresh());

        $entry = CostEntry::where('source_type', 'LABOR')->firstOrFail();

        // Two hours at 120. Derived from the labour entry, so the cost ledger
        // and the work order cannot disagree (ERD Section 14 rule 3).
        $this->assertSame('240.0000', (string) $entry->amount);
        $this->assertSame($this->asset->id, $entry->asset_id);
        $this->assertSame($workOrder->id, $entry->work_order_id);
    }

    public function test_a_derived_entry_is_rewritten_rather_than_duplicated(): void
    {
        $workOrder = $this->inProgress();
        $calculator = app(WorkOrderCostCalculator::class);

        app(RecordLaborEntry::class)->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 11:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        $calculator->recalculate($workOrder->fresh());
        $calculator->recalculate($workOrder->fresh());
        $calculator->recalculate($workOrder->fresh());

        // A projection, not an independent fact. Running the sync three times
        // must leave one live row, or the machine's cost triples every time
        // somebody opens the screen.
        $this->assertSame(1, CostEntry::where('source_type', 'LABOR')->count());
        $this->assertSame('240.0000', (string) CostEntry::where('source_type', 'LABOR')->firstOrFail()->amount);
    }

    public function test_deleting_the_source_clears_the_derived_cost(): void
    {
        $workOrder = $this->inProgress();
        $labour = app(RecordLaborEntry::class);

        $entry = $labour->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 11:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        app(WorkOrderCostCalculator::class)->recalculate($workOrder->fresh());
        $this->assertSame(1, CostEntry::where('source_type', 'LABOR')->count());

        $labour->delete($entry);

        // Removing a projection is not rewriting history. Leaving it would
        // charge the machine for an hour nobody worked.
        $this->assertSame(0, CostEntry::where('source_type', 'LABOR')->count());
    }

    public function test_labour_and_parts_costs_cannot_be_posted_by_hand(): void
    {
        try {
            // Otherwise a user posts a labour cost alongside the derived one and
            // the work order is charged twice for the same hour.
            $this->costs->post([
                'asset_id' => $this->asset->id,
                'cost_category_id' => $this->category('LABOR')->id,
                'amount' => '5000',
                'source_type' => 'LABOR',
            ], 'user-a');
            $this->fail('A derived source type must not be postable by hand.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        }
    }

    public function test_a_manual_entry_is_posted_with_a_frozen_base_amount(): void
    {
        $entry = $this->costs->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => $this->category('EXTERNAL_SERVICE')->id,
            'amount' => '1000',
            'currency' => 'USD',
            'exchange_rate' => '120',
            'source_type' => 'EXTERNAL_SERVICE',
            'description' => 'Servo driver repaired by the supplier',
            'invoice_reference' => 'INV-2026-118',
        ], 'user-a');

        // Computed once. A later rate change must never rewrite what a closed
        // period reported (SRS 24).
        $this->assertSame('120000.0000', (string) $entry->base_amount);
        $this->assertNotNull($entry->posted_at);
        $this->assertFalse($entry->is_reversal);
    }

    public function test_a_reversal_is_negative_and_leaves_the_original_alone(): void
    {
        $entry = $this->costs->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => $this->category('VENDOR')->id,
            'amount' => '8000',
            'source_type' => 'VENDOR',
        ], 'user-a');

        $reversal = $this->costs->reverse($entry, 'user-b', 'Invoiced to the wrong machine');

        // Stored negative rather than relying on every report to subtract it: a
        // report that forgets is a report that overstates the machine.
        $this->assertSame('-8000.0000', (string) $reversal->amount);
        $this->assertSame('-8000.0000', (string) $reversal->base_amount);
        $this->assertSame($entry->id, $reversal->reverses_cost_entry_id);
        $this->assertTrue($reversal->is_reversal);

        // The original is untouched.
        $this->assertSame('8000.0000', (string) $entry->fresh()->amount);
        $this->assertFalse($entry->fresh()->is_reversal);
    }

    public function test_an_entry_cannot_be_reversed_twice(): void
    {
        $entry = $this->costs->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => $this->category('TRANSPORT')->id,
            'amount' => '1500',
            'source_type' => 'TRANSPORT',
        ], 'user-a');

        $this->costs->reverse($entry, 'user-b');

        try {
            $this->costs->reverse($entry->fresh(), 'user-b');
            $this->fail('A second reversal must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_a_reversal_cannot_itself_be_reversed(): void
    {
        $entry = $this->costs->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => $this->category('TRANSPORT')->id,
            'amount' => '1500',
            'source_type' => 'TRANSPORT',
        ], 'user-a');

        $reversal = $this->costs->reverse($entry, 'user-b');

        $this->expectException(ValidationException::class);
        $this->costs->reverse($reversal, 'user-b');
    }

    public function test_a_reversal_uses_the_original_rate_not_todays(): void
    {
        $entry = $this->costs->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => $this->category('VENDOR')->id,
            'amount' => '100',
            'currency' => 'USD',
            'exchange_rate' => '110',
            'source_type' => 'VENDOR',
        ], 'user-a');

        $reversal = $this->costs->reverse($entry, 'user-b');

        // Reversing at a new rate leaves a residue that looks like a real cost.
        $this->assertSame('110.00000000', (string) $reversal->exchange_rate);
        $this->assertSame('0.0000', bcadd(
            (string) $entry->base_amount,
            (string) $reversal->base_amount,
            4,
        ));
    }

    public function test_parts_reach_the_cost_ledger_through_the_work_order(): void
    {
        $workOrder = $this->inProgress();

        $line = app(IssuePartsToWorkOrder::class)
            ->issue($workOrder, $this->part, $this->bin, '4', 'user-a');

        app(WorkOrderCostCalculator::class)->recalculate($workOrder->fresh());

        $entry = CostEntry::where('source_type', 'PARTS')->firstOrFail();

        $this->assertSame('1000.0000', (string) $entry->amount);
        $this->assertSame($workOrder->id, $entry->work_order_id);
        unset($line);
    }

    public function test_returning_parts_reduces_the_posted_cost(): void
    {
        $workOrder = $this->inProgress();
        $parts = app(IssuePartsToWorkOrder::class);
        $calculator = app(WorkOrderCostCalculator::class);

        $line = $parts->issue($workOrder, $this->part, $this->bin, '4', 'user-a');
        $calculator->recalculate($workOrder->fresh());

        $line = $parts->consume($line, '1', 'user-a');
        $parts->returnToStore($line->fresh(), '3', 'user-a');
        $calculator->recalculate($workOrder->fresh());

        // The machine is charged for what it kept, not for what passed through
        // a technician's hands.
        $this->assertSame('250.0000', (string) CostEntry::where('source_type', 'PARTS')->firstOrFail()->amount);
    }

    public function test_the_lifetime_cost_assembles_purchase_and_spend(): void
    {
        $workOrder = $this->inProgress();

        app(RecordLaborEntry::class)->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 11:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        app(IssuePartsToWorkOrder::class)->issue($workOrder, $this->part, $this->bin, '4', 'user-a');
        app(WorkOrderCostCalculator::class)->recalculate($workOrder->fresh());

        $summary = app(AssetLifecycleCost::class)->forAsset($this->asset->fresh());

        // 240 labour + 1000 parts.
        $this->assertSame('1240.0000', $summary['total_spend']);
        // The purchase price counts even though nobody posted an entry for it:
        // most machines are bought before this system exists.
        $this->assertSame('285000.0000', $summary['acquisition']);
        $this->assertSame('286240.0000', $summary['lifetime_total']);
    }

    public function test_spend_against_value_is_null_without_a_purchase_price(): void
    {
        $this->asset->forceFill(['acquisition_cost' => null])->save();

        // A percentage of an unknown is not a small percentage, it is no answer
        // at all (SRS 31.2 rule 2).
        $this->assertNull(app(AssetLifecycleCost::class)->spendAgainstValue($this->asset->fresh()));
    }

    public function test_spend_against_value_is_the_decision_figure(): void
    {
        $this->costs->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => $this->category('EMERGENCY')->id,
            'amount' => '142500',
            'source_type' => 'EXTERNAL_SERVICE',
        ], 'user-a');

        // Half what the machine cost to buy, spent keeping it alive.
        $this->assertSame(50.0, app(AssetLifecycleCost::class)->spendAgainstValue($this->asset->fresh()));
    }

    public function test_depreciation_is_deliberately_absent(): void
    {
        // Accounting depreciation is a different question answered on a
        // different basis. Mixing it in produces a number wrong for both
        // purposes (SRS 23).
        $columns = Schema::getColumnListing('cost_entries');

        foreach (['depreciation', 'depreciated_amount', 'book_value'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }
}
