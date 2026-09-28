<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\ItemMaster\Models\StockCount;
use App\Modules\ItemMaster\Models\StockCountLine;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Physical inventory (Inventory spec §12).
 *
 * What these tests hold down is the three places a stock take can quietly
 * lie: a system figure read at the wrong moment, an unvisited shelf
 * treated as an empty one, and an adjustment that overwrites the balance
 * instead of moving the difference. Each of those turns a count into a
 * machine for manufacturing the discrepancies it was meant to find.
 */
class StockCountTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $storeManager;

    private Item $button;

    private Item $thread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');

        $this->button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');
        $this->thread = ItemFixture::item($this->delta, 'TRM-THR-40', 'Thread 40/2');

        // Stock on the shelf for both, at known rates.
        $this->receive($this->button, '1000', '2');
        $this->receive($this->thread, '400', '5');
    }

    private function receive(Item $item, string $quantity, string $rate): void
    {
        app(ItemLedger::class)->post($item, $this->bin, 'RECEIPT', $quantity, $rate, [
            'reference_type' => 'test',
            'reference_id' => $item->id,
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @return array<string, mixed>
     */
    private function openCount(array $overrides = []): array
    {
        return $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson('/api/v1/stock-counts', array_merge([
                'factory_id' => $this->dhaka->id,
                'store_id' => $this->bin->store_id,
                'count_type' => 'FULL',
                'is_blind' => false,
            ], $overrides))->assertCreated()->json('data');
    }

    private function onHand(Item $item): string
    {
        return (string) ItemStockBalance::where('item_id', $item->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail()->quantity_on_hand;
    }

    /** A line is a shelf, so an item stocked in two bins has two of them. */
    private function lineFor(array $count, Item $item, ?Bin $bin = null): array
    {
        foreach ($count['lines'] as $line) {
            if ($line['item_id'] !== $item->id) {
                continue;
            }

            if ($bin !== null && $line['bin']['id'] !== $bin->id) {
                continue;
            }

            return $line;
        }

        $this->fail("No count line for {$item->item_code}.");
    }

    public function test_a_full_count_finds_a_variance_and_posts_the_difference(): void
    {
        $sk = $this->tokenFor($this->storekeeper);
        $sm = $this->tokenFor($this->storeManager);

        $count = $this->openCount();

        $this->assertStringStartsWith('CNT-DHK-', $count['count_no']);
        $this->assertSame('COUNTING', $count['status']);
        $this->assertCount(2, $count['lines']);
        $this->assertSame(2, $count['uncounted_count']);

        $buttonLine = $this->lineFor($count, $this->button);
        $threadLine = $this->lineFor($count, $this->thread);

        // The sheet froze what the system believed.
        $this->assertSame('1000.0000', $buttonLine['system_qty']);
        $this->assertNull($buttonLine['counted_qty']);

        // A movement while the aisles are being walked is a real movement,
        // not a finding — the frozen figure must not follow it.
        $this->receive($this->button, '100', '2');

        $counted = $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [
                // Forty short: the whole point of the exercise.
                ['line_id' => $buttonLine['id'], 'counted_qty' => '960', 'notes' => 'Two boxes missing from rack 3.'],
                ['line_id' => $threadLine['id'], 'counted_qty' => '400'],
            ],
        ])->assertOk()->json('data');

        $this->assertSame(0, $counted['uncounted_count']);
        $this->assertSame(1, $counted['variance_count']);
        $this->assertSame('1000.0000', $this->lineFor($counted, $this->button)['system_qty']);
        $this->assertSame('-40.0000', $this->lineFor($counted, $this->button)['variance_qty']);
        $this->assertSame('MATCHED', $this->lineFor($counted, $this->thread)['status']);

        $submitted = $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/submit")
            ->assertOk()->json('data');

        // 40 buttons at 2 = 80, and no workflow matched so it is approved.
        $this->assertSame('80.0000', $submitted['variance_value']);
        $this->assertSame('APPROVED', $submitted['status']);

        // Counting is the storekeeper's; posting the adjustment is not.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/post")->assertForbidden();

        $posted = $this->withToken($sm)->postJson("/api/v1/stock-counts/{$count['id']}/post")
            ->assertOk()->json('data');

        $this->assertSame('POSTED', $posted['status']);
        $this->assertSame('ADJUSTED', $this->lineFor($posted, $this->button)['status']);

        // 1,000 frozen + 100 received during the count − 40 adjustment.
        // Forcing the balance to the counted 960 would have erased the
        // receipt; moving the difference does not.
        $this->assertSame('1060.0000', $this->onHand($this->button));
        $this->assertSame('400.0000', $this->onHand($this->thread));

        $movement = ItemStockTransaction::where('reference_type', 'stock_count')->firstOrFail();
        $this->assertSame('ADJUSTMENT_OUT', $movement->transaction_type);
        $this->assertSame('40.0000', (string) $movement->quantity);
        // At the value the stock held when the sheet was frozen.
        $this->assertSame('2.0000', (string) $movement->unit_cost);
        $this->assertSame(
            $movement->id,
            StockCountLine::findOrFail($this->lineFor($posted, $this->button)['id'])->item_stock_transaction_id,
        );

        // A matched line posts nothing at all.
        $this->assertSame(1, ItemStockTransaction::where('reference_type', 'stock_count')->count());

        // And the books know what the count did to the value of the store:
        // 80 short, so stock is credited and the variance expensed.
        $entry = \App\Modules\Finance\Models\GlJournalEntry::where('event_type', 'STOCK_ADJUSTMENT')
            ->with('lines.account:id,code')->firstOrFail();

        $this->assertSame('80.0000', (string) $entry->amount);
        $this->assertSame($count['count_no'], $entry->source_no);
        $this->assertTrue($entry->isBalanced());
        $this->assertSame('SUS-VAR', $entry->lines->firstWhere('side', 'DEBIT')->account->code);
        $this->assertSame('SUS-STOCK', $entry->lines->firstWhere('side', 'CREDIT')->account->code);
    }

    public function test_a_sheet_with_unvisited_shelves_cannot_be_closed(): void
    {
        $sk = $this->tokenFor($this->storekeeper);
        $count = $this->openCount();

        // Only one of the two shelves.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [['line_id' => $this->lineFor($count, $this->button)['id'], 'counted_qty' => '1000']],
        ])->assertOk();

        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/submit")
            ->assertStatus(409)->assertJsonValidationErrors(['lines']);

        // An empty shelf is a counted zero, and that is a different answer
        // from never having gone.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [['line_id' => $this->lineFor($count, $this->thread)['id'], 'counted_qty' => '0']],
        ])->assertOk();

        $submitted = $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/submit")
            ->assertOk()->json('data');

        // 400 thread at 5 written off entirely.
        $this->assertSame('2000.0000', $submitted['variance_value']);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/stock-counts/{$count['id']}/post")->assertOk();

        $this->assertSame('0.0000', $this->onHand($this->thread));
    }

    public function test_a_blind_sheet_withholds_the_system_figure_until_the_counting_is_over(): void
    {
        $sk = $this->tokenFor($this->storekeeper);
        $count = $this->openCount(['is_blind' => true]);

        $this->assertFalse($count['reveals_system_qty']);

        // Not merely hidden by the screen: never sent. A number the client
        // never receives cannot be copied into the box beside it.
        $line = $this->lineFor($count, $this->button);
        $this->assertArrayNotHasKey('system_qty', $line);
        $this->assertArrayNotHasKey('variance_qty', $line);

        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [
                ['line_id' => $line['id'], 'counted_qty' => '995'],
                ['line_id' => $this->lineFor($count, $this->thread)['id'], 'counted_qty' => '400'],
            ],
        ])->assertOk();

        // Still blind while the sheet is open.
        $open = $this->withToken($sk)->getJson("/api/v1/stock-counts/{$count['id']}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('system_qty', $this->lineFor($open, $this->button));

        // Once it is in for review the variance is the whole subject.
        $submitted = $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/submit")
            ->assertOk()->json('data');

        $this->assertTrue($submitted['reveals_system_qty']);
        $this->assertSame('1000.0000', $this->lineFor($submitted, $this->button)['system_qty']);
        $this->assertSame('-5.0000', $this->lineFor($submitted, $this->button)['variance_qty']);
    }

    public function test_stock_found_on_a_shelf_the_system_never_knew_about(): void
    {
        $sk = $this->tokenFor($this->storekeeper);
        $zip = ItemFixture::item($this->delta, 'TRM-ZIP-5', 'Zipper #5');

        // The same zip is stocked in another bin at 3, which is what the
        // found stock has to be valued at: an ADJUSTMENT_IN moves the
        // weighted average, so bringing it in at zero would quietly
        // mis-value everything already on the shelf.
        $spare = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-B2');
        app(ItemLedger::class)->post($zip, $spare, 'RECEIPT', '10', '3', [
            'reference_type' => 'test', 'reference_id' => $zip->id,
        ]);

        // But no row in the bin being counted: invisible there until found.
        $this->assertSame(0, ItemStockBalance::where('item_id', $zip->id)
            ->where('bin_id', $this->bin->id)->count());

        $count = $this->openCount();

        $found = $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/found", [
            'item_id' => $zip->id,
            'bin_id' => $this->bin->id,
            'counted_qty' => '250',
        ])->assertCreated()->json('data');

        $zipLine = $this->lineFor($found, $zip, $this->bin);
        $this->assertTrue($zipLine['is_unexpected']);
        $this->assertSame('0.0000', $zipLine['system_qty']);
        $this->assertSame('250.0000', $zipLine['variance_qty']);
        // Valued at what the item is worth elsewhere, not at nothing.
        $this->assertSame('3.0000', $zipLine['unit_cost']);
        $this->assertSame('750.0000', $zipLine['variance_value']);

        // The spare bin's zip is on the sheet as well — every shelf has to
        // be visited before the sheet can close.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [['line_id' => $this->lineFor($found, $zip, $spare)['id'], 'counted_qty' => '10']],
        ])->assertOk();

        foreach ([$this->button, $this->thread] as $item) {
            $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
                'lines' => [[
                    'line_id' => $this->lineFor($found, $item, $this->bin)['id'],
                    'counted_qty' => $this->onHand($item),
                ]],
            ])->assertOk();
        }

        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/submit")->assertOk();
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/stock-counts/{$count['id']}/post")->assertOk();

        $this->assertSame('250.0000', $this->onHand($zip));
        // Brought in at 3, so the bin's average is 3 and not a fraction of it.
        $this->assertSame('3.0000', (string) ItemStockBalance::where('item_id', $zip->id)
            ->where('bin_id', $this->bin->id)->firstOrFail()->weighted_average_cost);

        $movement = ItemStockTransaction::where('item_id', $zip->id)
            ->where('reference_type', 'stock_count')->firstOrFail();
        $this->assertSame('ADJUSTMENT_IN', $movement->transaction_type);

        // More on the shelf than the books said, so the sides swap.
        $entry = \App\Modules\Finance\Models\GlJournalEntry::where('event_type', 'STOCK_ADJUSTMENT')
            ->with('lines.account:id,code')->firstOrFail();

        $this->assertTrue($entry->isBalanced());
        $this->assertSame('SUS-STOCK', $entry->lines->firstWhere('side', 'DEBIT')->account->code);
        $this->assertSame('SUS-VAR', $entry->lines->firstWhere('side', 'CREDIT')->account->code);
    }

    public function test_one_store_takes_one_count_at_a_time_and_a_cycle_count_needs_a_scope(): void
    {
        $sk = $this->tokenFor($this->storekeeper);
        $count = $this->openCount();

        // Two open sheets would each freeze a different figure, and
        // whichever posted second would adjust against a moved one.
        $this->withToken($sk)->postJson('/api/v1/stock-counts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'count_type' => 'FULL',
        ])->assertStatus(409)->assertJsonValidationErrors(['store_id']);

        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/cancel", [
            'reason' => 'Aisle blocked by a delivery.',
        ])->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        // A cycle count with no scope is a full stock take mislabelled.
        $this->withToken($sk)->postJson('/api/v1/stock-counts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'count_type' => 'CYCLE',
        ])->assertStatus(422)->assertJsonValidationErrors(['count_type']);

        $scoped = $this->withToken($sk)->postJson('/api/v1/stock-counts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'count_type' => 'CYCLE',
            'item_ids' => [$this->thread->id],
        ])->assertCreated()->json('data');

        $this->assertCount(1, $scoped['lines']);
        $this->assertSame($this->thread->id, $scoped['lines'][0]['item_id']);
        $this->assertSame(['item_ids' => [$this->thread->id]], $scoped['scope']);

        // A cancelled sheet takes nothing more.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [['line_id' => $this->lineFor($count, $this->button)['id'], 'counted_qty' => '1']],
        ])->assertStatus(409);
    }

    public function test_a_line_can_be_sent_back_to_the_shelf(): void
    {
        $sk = $this->tokenFor($this->storekeeper);
        $count = $this->openCount();
        $buttonLine = $this->lineFor($count, $this->button);

        // Both shelves counted, so the recount below is the only thing
        // left outstanding.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/lines", [
            'lines' => [
                ['line_id' => $buttonLine['id'], 'counted_qty' => '12'],
                ['line_id' => $this->lineFor($count, $this->thread)['id'], 'counted_qty' => '400'],
            ],
        ])->assertOk();

        $recount = $this->withToken($sk)->postJson("/api/v1/stock-count-lines/{$buttonLine['id']}/recount", [
            'reason' => 'That cannot be right — check rack 3 again.',
        ])->assertOk()->json('data');

        $line = $this->lineFor($recount, $this->button);
        $this->assertSame('RECOUNT', $line['status']);
        // The doubted figure is cleared, not left sitting there looking
        // like an answer.
        $this->assertNull($line['counted_qty']);
        $this->assertSame(1, $recount['uncounted_count']);

        // And the sheet cannot close while it is outstanding.
        $this->withToken($sk)->postJson("/api/v1/stock-counts/{$count['id']}/submit")->assertStatus(409);
    }

    public function test_the_daily_sweep_chases_a_sheet_left_open(): void
    {
        $count = $this->openCount();

        $this->artisan('itemmaster:chase-counts')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame(0, $this->chases());

        StockCount::whereKey($count['id'])->update(['created_at' => now()->subDays(5)]);

        $this->artisan('itemmaster:chase-counts')->assertSuccessful();
        $this->artisan('itemmaster:chase-counts')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);

        // Chased once, not once per sweep.
        $this->assertSame(1, $this->chases());
    }

    private function chases(): int
    {
        return \App\Modules\Notification\Models\Notification::where('event_type', 'STOCK_COUNT_STALE')
            ->where('user_id', $this->storeManager->id)
            ->count();
    }
}
