<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The append-only stock ledger (ERD Section 13, SRS 19).
 *
 * The promise this ledger makes is that replaying it reproduces the balance
 * exactly. A store whose figures cannot be explained is a store nobody
 * defends at an audit, and "the system says twelve" is not an explanation.
 */
class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private SparePart $part;

    private InventoryLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->part = InventoryFixture::part($this->delta);
        $this->ledger = app(InventoryLedger::class);
    }

    private function balance(): InventoryBalance
    {
        return InventoryBalance::where('spare_part_id', $this->part->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail();
    }

    public function test_a_receipt_raises_the_balance_and_sets_the_average(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '250');

        $balance = $this->balance();

        $this->assertSame('10.0000', (string) $balance->quantity_on_hand);
        $this->assertSame('250.0000', (string) $balance->weighted_average_cost);
    }

    public function test_the_weighted_average_blends_two_receipts(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '200');

        // (10 x 100 + 10 x 200) / 20 = 150. Not 200, and not the last price
        // paid: a store holding stock bought at two prices owns the average.
        $this->assertSame('150.0000', (string) $this->balance()->weighted_average_cost);
        $this->assertSame('20.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_an_issue_draws_at_the_average_and_does_not_move_it(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '200');

        $issue = $this->ledger->post($this->part, $this->bin, 'ISSUE', '5');

        // The issue is costed at 150 and leaves the average alone. If issues
        // moved it, the cost of a repair would depend on how much happened to
        // be in the bin that day (ERD Section 13 rule 4).
        $this->assertSame('150.0000', (string) $issue->unit_cost);
        $this->assertSame('750.0000', (string) $issue->total_cost);
        $this->assertSame('150.0000', (string) $this->balance()->weighted_average_cost);
        $this->assertSame('15.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_every_row_carries_the_balance_and_average_that_resulted(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $this->ledger->post($this->part, $this->bin, 'ISSUE', '3');
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '5', '160');

        $rows = InventoryTransaction::orderBy('transaction_at')->orderBy('id')->get();

        // Written with the row, not derived at read time: this is what lets a
        // storekeeper be shown why the number is what it is.
        $this->assertSame(['10.0000', '7.0000', '12.0000'], $rows->pluck('balance_after')->map(fn ($v) => (string) $v)->all());
        // (7 x 100 + 5 x 160) / 12 = 125.
        $this->assertSame('125.0000', (string) $rows->last()->wac_after);
    }

    public function test_replaying_the_ledger_reproduces_the_balance(): void
    {
        $this->ledger->post($this->part, $this->bin, 'OPENING_BALANCE', '20', '90');
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '15', '110');
        $this->ledger->post($this->part, $this->bin, 'ISSUE', '8');
        $this->ledger->post($this->part, $this->bin, 'RETURN', '3', '100');
        $this->ledger->post($this->part, $this->bin, 'SCRAP', '2');

        $result = $this->ledger->verify($this->part, $this->bin);

        // The whole design rests on this. A drift means a movement was written
        // outside the ledger, and it should be found the day it happens.
        $this->assertTrue($result['matches'], 'The ledger no longer replays to the stored balance.');
        $this->assertSame('28.0000', $result['balance']);
        $this->assertSame(5, $result['transactions']);
    }

    public function test_an_issue_beyond_stock_is_refused(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '3', '100');

        try {
            $this->ledger->post($this->part, $this->bin, 'ISSUE', '5');
            $this->fail('Issuing more than exists must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        // Nothing partially applied: the balance is untouched.
        $this->assertSame('3.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertSame(1, InventoryTransaction::count());
    }

    public function test_reserved_stock_is_not_available_to_an_issue(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');

        $this->balance()->forceFill(['quantity_reserved' => '8'])->save();

        // Ten on the shelf, eight promised elsewhere. Two are actually free.
        try {
            $this->ledger->post($this->part, $this->bin, 'ISSUE', '5');
            $this->fail('Issuing into a reservation must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $this->ledger->post($this->part, $this->bin, 'ISSUE', '2');
        $this->assertSame('8.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_negative_stock_is_allowed_only_when_the_factory_says_so(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '2', '100');

        app(SetSetting::class)->handle(
            'inventory.allow_negative_stock', true, factoryId: $this->dhaka->id,
        );

        // Some factories run this way and reconcile later. It is a setting
        // rather than a silent default, because a store that hands out what it
        // does not have will eventually be asked how.
        $this->ledger->post($this->part, $this->bin, 'ISSUE', '5');

        $this->assertSame('-3.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertTrue($this->ledger->verify($this->part, $this->bin)['matches']);
    }

    public function test_a_reversal_leaves_the_original_untouched(): void
    {
        $receipt = $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $issue = $this->ledger->post($this->part, $this->bin, 'ISSUE', '4');

        $reversal = $this->ledger->reverse($issue, 'user-a', 'Issued against the wrong work order');

        // The original stays exactly as posted. Deleting it would leave the
        // balance right and the history wrong, and a missing row is harder to
        // find than a wrong number.
        $this->assertSame('4.0000', (string) $issue->fresh()->quantity);
        $this->assertSame('ISSUE', $issue->fresh()->transaction_type);
        $this->assertSame($issue->id, $reversal->reverses_transaction_id);
        $this->assertSame('RETURN', $reversal->transaction_type);
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);

        unset($receipt);
    }

    public function test_a_movement_cannot_be_reversed_twice(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $issue = $this->ledger->post($this->part, $this->bin, 'ISSUE', '4');

        $this->ledger->reverse($issue, 'user-a');

        try {
            $this->ledger->reverse($issue->fresh(), 'user-a');
            $this->fail('A second reversal must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_a_reversal_returns_stock_at_the_price_it_left_at(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $issue = $this->ledger->post($this->part, $this->bin, 'ISSUE', '5');

        // Price rises after the issue.
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '5', '300');

        $this->ledger->reverse($issue, 'user-a');

        // The returned five come back at 100, the price they left at. Returning
        // them at the new average would let a reversal quietly revalue the
        // shelf upwards.
        $reversal = InventoryTransaction::where('reverses_transaction_id', $issue->id)->firstOrFail();
        $this->assertSame('100.0000', (string) $reversal->unit_cost);
    }

    public function test_a_repeated_request_posts_the_movement_once(): void
    {
        $first = $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100', [
            'idempotency_key' => 'grn-2026-0001',
        ]);

        $second = $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100', [
            'idempotency_key' => 'grn-2026-0001',
        ]);

        // A retried request must not receive the delivery twice (ADR-056).
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InventoryTransaction::count());
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
    }

    public function test_a_zero_or_negative_quantity_is_refused(): void
    {
        // A negative quantity would silently invert the meaning of the type.
        $this->expectException(ValidationException::class);
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '-5', '100');
    }

    public function test_an_unknown_movement_type_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->ledger->post($this->part, $this->bin, 'SHRINKAGE', '5', '100');
    }

    public function test_available_excludes_reserved_stock(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '12', '100');
        $this->balance()->forceFill(['quantity_reserved' => '5'])->save();

        $this->assertSame('7.0000', $this->ledger->available($this->part, $this->bin));
    }

    public function test_balances_are_held_per_bin(): void
    {
        $second = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A2');

        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');
        $this->ledger->post($this->part, $second, 'RECEIPT', '4', '150');

        // "We have fourteen" is not useful to a technician who then has to open
        // every drawer.
        $this->assertSame('14.0000', $this->part->fresh()->totalOnHand());
        $this->assertSame('10.0000', (string) $this->balance()->quantity_on_hand);
        $this->assertTrue($this->ledger->verify($this->part, $second)['matches']);
    }

    public function test_the_part_holds_no_quantity_of_its_own(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');

        // Storing a quantity on the part as well as in the balances would give
        // two answers to one question, and they would eventually disagree.
        $columns = Schema::getColumnListing('spare_parts');

        foreach (['quantity', 'quantity_on_hand', 'stock', 'current_stock'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    public function test_receiving_updates_the_reference_price_but_not_the_costing(): void
    {
        app(ReceiveStock::class)->handle($this->part, $this->bin, '10', '100', 'user-a');
        app(ReceiveStock::class)->handle($this->part->fresh(), $this->bin, '10', '300', 'user-a');

        // unit_cost on the part is the last price paid, for reference. Costing
        // uses the ledger's average, which is 200.
        $this->assertSame('300.0000', (string) $this->part->fresh()->unit_cost);
        $this->assertSame('200.0000', (string) $this->balance()->weighted_average_cost);
    }

    public function test_an_adjustment_out_needs_a_reason(): void
    {
        $this->ledger->post($this->part, $this->bin, 'RECEIPT', '10', '100');

        try {
            // Stock that moves without an explanation is indistinguishable
            // from loss.
            app(ReceiveStock::class)->adjustOut($this->part, $this->bin, '2', '   ', 'user-a');
            $this->fail('An adjustment with no reason must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('notes', $e->errors());
        }

        $transaction = app(ReceiveStock::class)
            ->adjustOut($this->part, $this->bin, '2', 'Damaged in storage, water ingress', 'user-a');

        $this->assertSame('Damaged in storage, water ingress', $transaction->notes);
        $this->assertSame('8.0000', (string) $this->balance()->quantity_on_hand);
    }
}
