<?php

declare(strict_types=1);

namespace Tests\Feature\Requisition;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Notification\Models\Notification;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Requisition\Models\FloorRequisitionIssue;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The store's side of a floor requisition (Floor/Store Requisition spec
 * §4–§7, §9; Phase 3A).
 *
 * The rules that matter here are the ones a factory notices when they are
 * missing: stock held for somebody else is not issuable, a shortage always
 * raises a purchase requisition and a claim on the goods coming, and two
 * storekeepers cannot both issue the last unit.
 */
class FloorRequisitionFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $technician;

    private User $storekeeper;

    private ItemDepartment $general;

    private Item $bolt;

    private Item $gauge;

    private ItemLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        // The shortage notification is addressed to whoever submits the
        // automatic purchase requisition, so there has to be one.
        TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');

        $this->general = ItemFixture::department($this->delta);
        $this->bolt = ItemFixture::item($this->delta);
        $this->gauge = ItemFixture::item($this->delta, 'GEN-GAUGE-01', 'Seam gauge', [
            'is_returnable' => true,
        ]);

        $this->ledger = app(ItemLedger::class);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @param  list<array{item_id: string, requested_qty: string}>  $lines
     * @return array<string, mixed>
     */
    private function submittedRequisition(array $lines): array
    {
        $token = $this->tokenFor($this->technician);

        $created = $this->withToken($token)->postJson('/api/v1/floor-requisitions', [
            'factory_id' => $this->dhaka->id,
            'target_item_department_id' => $this->general->id,
            'lines' => $lines,
        ])->assertCreated()->json('data');

        return $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$created['id']}/submit")
            ->assertOk()->json('data');
    }

    private function balance(?Item $item = null): ItemStockBalance
    {
        return ItemStockBalance::where('item_id', ($item ?? $this->bolt)->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail();
    }

    public function test_a_full_issue_posts_one_ledger_row_and_closes_the_requisition(): void
    {
        $this->ledger->post($this->bolt, $this->bin, 'RECEIPT', '50', '120');

        $requisition = $this->submittedRequisition([
            ['item_id' => $this->bolt->id, 'requested_qty' => '20'],
        ]);
        $token = $this->tokenFor($this->storekeeper);

        // The availability screen reads real stock, not the requested number.
        $this->withToken($token)->getJson("/api/v1/floor-requisitions/{$requisition['id']}/availability")
            ->assertOk()
            ->assertJsonPath('data.lines.0.available_qty', '50.0000')
            ->assertJsonPath('data.lines.0.outstanding_qty', '20.0000');

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/review", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'approved_qty' => '20']],
        ])->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');

        $issued = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '20', 'bin_id' => $this->bin->id]],
        ])->assertOk()->json('data');

        $this->assertSame('FULLY_FULFILLED', $issued['status']);
        $this->assertSame('ISSUED', $issued['lines'][0]['status']);
        $this->assertSame('20.0000', $issued['lines'][0]['issued_qty']);

        // One movement, and the shelf agrees with the ledger.
        $this->assertSame(1, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());
        $this->assertSame('30.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('floor_requisition', ItemStockTransaction::where('transaction_type', 'ISSUE')->first()->reference_type);

        // The hand-over is recorded with the bin it came out of.
        $this->assertSame($this->bin->id, $issued['lines'][0]['issues'][0]['bin']['id']);
        $this->assertSame('120.0000', $issued['lines'][0]['issues'][0]['unit_cost']);

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/close")
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_a_shortage_raises_one_purchase_requisition_and_a_claim_per_short_line(): void
    {
        $this->ledger->post($this->bolt, $this->bin, 'RECEIPT', '5', '120');

        $requisition = $this->submittedRequisition([
            ['item_id' => $this->bolt->id, 'requested_qty' => '20'],
            ['item_id' => $this->gauge->id, 'requested_qty' => '2'],
        ]);
        $token = $this->tokenFor($this->storekeeper);

        $issued = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '5', 'bin_id' => $this->bin->id]],
        ])->assertOk()->json('data');

        $this->assertSame('PARTIALLY_FULFILLED', $issued['status']);
        $this->assertSame('PARTIALLY_ISSUED', $issued['lines'][0]['status']);
        $this->assertSame('15.0000', $issued['lines'][0]['shortage_qty']);
        // The second line had no stock at all and is entirely short.
        $this->assertSame('SHORT', $issued['lines'][1]['status']);
        $this->assertSame('2.0000', $issued['lines'][1]['shortage_qty']);

        // One purchase requisition for both short lines, left in draft for
        // the store manager to look at (spec §5.3).
        $requisitions = PurchaseRequisition::all();
        $this->assertCount(1, $requisitions);
        $this->assertSame('DRAFT', $requisitions->first()->status);
        $this->assertSame('FROM_FLOOR_REQUISITION', $requisitions->first()->pr_type);
        $this->assertSame($requisition['id'], $requisitions->first()->source_reference);
        $this->assertSame(2, $requisitions->first()->lines()->count());
        $this->assertSame($requisitions->first()->id, $issued['shortage_pr']['id']);

        // A claim per short line, on stock that has not arrived.
        $claims = ItemReservation::where('status', 'PENDING_ARRIVAL')->get();
        $this->assertCount(2, $claims);
        $this->assertSame(['15.0000', '2.0000'], $claims->sortByDesc('quantity')->map(fn ($c) => (string) $c->quantity)->values()->all());

        // And the store manager is told there is a draft waiting.
        $this->assertSame(1, Notification::where('event_type', 'FLOOR_REQUISITION_SHORTAGE')->count());
    }

    public function test_stock_held_for_somebody_else_cannot_be_issued(): void
    {
        $this->ledger->post($this->bolt, $this->bin, 'RECEIPT', '10', '120');

        // Somebody else is already holding eight of the ten.
        app(ReserveItemStock::class)->reserve($this->bolt, $this->bin, '8', [
            'reserved_by' => $this->storekeeper->id,
        ]);

        $requisition = $this->submittedRequisition([
            ['item_id' => $this->bolt->id, 'requested_qty' => '10'],
        ]);
        $token = $this->tokenFor($this->storekeeper);

        $this->withToken($token)->getJson("/api/v1/floor-requisitions/{$requisition['id']}/availability")
            ->assertOk()
            ->assertJsonPath('data.lines.0.available_qty', '2.0000');

        // Physically there, and not issuable.
        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '10', 'bin_id' => $this->bin->id]],
        ])->assertStatus(409);

        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame(0, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());
    }

    public function test_two_requisitions_cannot_both_take_the_last_unit(): void
    {
        $this->ledger->post($this->bolt, $this->bin, 'RECEIPT', '3', '120');

        $first = $this->submittedRequisition([['item_id' => $this->bolt->id, 'requested_qty' => '3']]);
        $second = $this->submittedRequisition([['item_id' => $this->bolt->id, 'requested_qty' => '3']]);
        $token = $this->tokenFor($this->storekeeper);

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$first['id']}/issue", [
            'lines' => [['line_id' => $first['lines'][0]['id'], 'quantity' => '3', 'bin_id' => $this->bin->id]],
        ])->assertOk();

        // The second reads zero available and is refused rather than taking
        // the shelf negative.
        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$second['id']}/issue", [
            'lines' => [['line_id' => $second['lines'][0]['id'], 'quantity' => '3', 'bin_id' => $this->bin->id]],
        ])->assertStatus(409);

        $this->assertSame('0.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame(1, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());
    }

    public function test_a_receipt_fills_the_claim_and_the_rest_is_issued_from_it(): void
    {
        $this->ledger->post($this->bolt, $this->bin, 'RECEIPT', '5', '120');

        $requisition = $this->submittedRequisition([
            ['item_id' => $this->bolt->id, 'requested_qty' => '12'],
        ]);
        $token = $this->tokenFor($this->storekeeper);
        $lineId = $requisition['lines'][0]['id'];

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $lineId, 'quantity' => '5', 'bin_id' => $this->bin->id]],
        ])->assertOk();

        // The purchase arrives through a goods receipt.
        $this->withToken($token)->postJson("/api/v1/goods-receipts", [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'lines' => [['item_id' => $this->bolt->id, 'received_qty' => '7', 'rate' => '130', 'bin_id' => $this->bin->id]],
        ])->assertCreated();

        $grn = $this->withToken($token)->getJson('/api/v1/goods-receipts')->assertOk()->json('data.0');
        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        // The claim became a real hold on the stock that landed, and the
        // store was told it can finish the requisition.
        $claim = ItemReservation::where('reference_id', $lineId)->firstOrFail();
        $this->assertSame('ACTIVE', $claim->status);
        $this->assertSame($this->bin->id, $claim->bin_id);
        $this->assertSame('7.0000', (string) $this->balance()->quantity_reserved);
        // The whole store team is told; one message each.
        $this->assertSame(1, Notification::where('event_type', 'EARMARK_STOCK_ARRIVED')
            ->where('user_id', $this->storekeeper->id)->count());

        // Issuing the rest draws the hold down instead of colliding with it.
        $done = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $lineId, 'quantity' => '7', 'bin_id' => $this->bin->id]],
        ])->assertOk()->json('data');

        $this->assertSame('FULLY_FULFILLED', $done['status']);
        $this->assertSame('ISSUED', $done['lines'][0]['status']);
        $this->assertSame('0.0000', $done['lines'][0]['shortage_qty']);
        $this->assertSame('0.0000', (string) $this->balance()->quantity_reserved);
        $this->assertSame('0.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame('ISSUED', $claim->fresh()->status);

        // Still only one purchase requisition — the second round raised none.
        $this->assertCount(1, PurchaseRequisition::all());
    }

    public function test_a_returnable_item_is_borrowed_and_comes_back_at_the_price_it_left_at(): void
    {
        $this->ledger->post($this->gauge, $this->bin, 'RECEIPT', '4', '900');

        $requisition = $this->submittedRequisition([
            ['item_id' => $this->gauge->id, 'requested_qty' => '2'],
        ]);
        $token = $this->tokenFor($this->storekeeper);

        $issued = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '2', 'bin_id' => $this->bin->id]],
        ])->assertOk()->json('data');

        // Lent, not consumed: the line is not finished (spec §7).
        $this->assertSame('BORROWED', $issued['lines'][0]['status']);
        $this->assertSame('2.0000', (string) $this->balance($this->gauge)->quantity_on_hand);

        // A borrowed line blocks the close until it comes back.
        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/close")
            ->assertStatus(409);

        $issueId = $issued['lines'][0]['issues'][0]['id'];

        $returned = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/returns", [
            'issue_id' => $issueId,
            'quantity' => '2',
        ])->assertOk()->json('data');

        $this->assertSame('RETURNED', $returned['lines'][0]['status']);
        $this->assertSame('4.0000', (string) $this->balance($this->gauge)->quantity_on_hand);
        // Back at the price it left at, so the average did not drift.
        $this->assertSame('900.0000', (string) ItemStockTransaction::where('transaction_type', 'RETURN')->first()->unit_cost);
        $this->assertSame('900.0000', (string) $this->balance($this->gauge)->weighted_average_cost);

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/close")->assertOk();
    }

    public function test_the_sweeper_chases_a_borrowed_line_once(): void
    {
        $this->ledger->post($this->gauge, $this->bin, 'RECEIPT', '4', '900');

        $requisition = $this->submittedRequisition([
            ['item_id' => $this->gauge->id, 'requested_qty' => '2'],
        ]);
        $token = $this->tokenFor($this->storekeeper);

        $issued = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '2', 'bin_id' => $this->bin->id]],
        ])->assertOk()->json('data');

        // Nothing is overdue on the day it was issued. The command sweeps
        // every company and drops the tenant context on the way out, so the
        // test puts it back before reading anything.
        $this->artisan('requisition:remind-outstanding')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame(0, $this->chases());

        FloorRequisitionIssue::whereKey($issued['lines'][0]['issues'][0]['id'])
            ->update(['issued_at' => now()->subDays(10)]);

        $this->artisan('requisition:remind-outstanding')->assertSuccessful();
        $this->artisan('requisition:remind-outstanding')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);

        // Chased once, not once per sweep.
        $this->assertSame(1, $this->chases());
    }

    /** How many times the storekeeper has been chased about borrowed stock. */
    private function chases(): int
    {
        return Notification::where('event_type', 'RETURNABLE_OVERDUE')
            ->where('user_id', $this->storekeeper->id)
            ->count();
    }

    public function test_rejecting_releases_the_claims_and_the_floor_cannot_issue(): void
    {
        $requisition = $this->submittedRequisition([
            ['item_id' => $this->bolt->id, 'requested_qty' => '4'],
        ]);

        // The floor may raise one; only the store may issue against it.
        $this->withToken($this->tokenFor($this->technician))
            ->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
                'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '1', 'bin_id' => $this->bin->id]],
            ])->assertForbidden();

        $token = $this->tokenFor($this->storekeeper);

        $rejected = $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/reject", [
            'reason' => 'Ask the chemical store instead',
        ])->assertOk()->json('data');

        $this->assertSame('REJECTED', $rejected['status']);
        $this->assertSame('REJECTED', $rejected['lines'][0]['status']);
        $this->assertSame('Ask the chemical store instead', $rejected['rejection_reason']);

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$requisition['id']}/issue", [
            'lines' => [['line_id' => $requisition['lines'][0]['id'], 'quantity' => '1', 'bin_id' => $this->bin->id]],
        ])->assertStatus(409);
    }
}
