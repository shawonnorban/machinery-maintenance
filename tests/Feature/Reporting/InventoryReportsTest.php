<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The item-library stock reports (Inventory spec §14).
 *
 * `ReportCatalogTest` already proves every registered report has its
 * translations, names a real permission, and yields rows shaped like its
 * columns. What it cannot see is whether the numbers mean anything, and
 * each of these reports turns on one judgement that is easy to get wrong
 * and impossible to notice afterwards: what counts as movement, what
 * counts as available, and which rolls may be added together.
 */
class InventoryReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private ReportRegistry $registry;

    private ?string $receipt = null;

    /** @var array<string, string> */
    private array $receiptLines = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->registry = app(ReportRegistry::class);
    }

    private function reportQuery(array $extra = []): ReportQuery
    {
        return new ReportQuery(
            CarbonImmutable::today()->subYear(),
            CarbonImmutable::today()->endOfDay(),
            null,
            null,
            $extra,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $key, array $extra = []): array
    {
        return iterator_to_array($this->registry->find($key)->rows($this->reportQuery($extra)), false);
    }

    private function rowFor(array $rows, string $itemCode): array
    {
        foreach ($rows as $row) {
            if (($row['item_code'] ?? null) === $itemCode) {
                return $row;
            }
        }

        $this->fail("No row for {$itemCode}.");
    }

    /**
     * Rolls are created directly here rather than through a goods receipt:
     * these tests are about how the reports read the roll table, and
     * driving the whole receiving flow for each one would test receiving.
     * `goods_receipt_id` is still required, so one receipt stands in for
     * the deliveries that would really have produced them.
     */
    private function receiptId(): string
    {
        return $this->receipt ??= GoodsReceipt::create([
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'grn_no' => 'GRN-TEST-0001',
            'grn_date' => CarbonImmutable::today(),
            'grn_type' => 'DIRECT',
            'status' => 'COMPLETED',
        ])->id;
    }

    /** One receipt line per fabric, since a roll must point at one. */
    private function receiptLineId(Item $fabric): string
    {
        return $this->receiptLines[$fabric->id] ??= GoodsReceiptLine::create([
            'goods_receipt_id' => $this->receiptId(),
            'item_id' => $fabric->id,
            'bin_id' => $this->bin->id,
            'item_code' => $fabric->item_code,
            'item_name' => $fabric->name,
            'uom_id' => $fabric->uom_id,
            'received_qty' => '1000',
            'rate' => '4',
        ])->id;
    }

    private function receive(Item $item, string $quantity, string $rate, ?CarbonImmutable $at = null): void
    {
        app(ItemLedger::class)->post($item, $this->bin, 'RECEIPT', $quantity, $rate, [
            'reference_type' => 'test',
            'reference_id' => $item->id,
            'transaction_at' => $at ?? CarbonImmutable::now(),
        ]);
    }

    public function test_valuation_separates_what_is_on_the_shelf_from_what_is_free(): void
    {
        $button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');
        $this->receive($button, '1000', '2.50');

        app(ReserveItemStock::class)->reserve($button, $this->bin, '300', [
            'reservation_type' => 'MANUAL',
            'reference_type' => 'test',
            'reference_id' => $button->id,
        ]);

        $row = $this->rowFor($this->rows('item_stock_valuation'), 'TRM-BTN-18L');

        $this->assertSame('1000.0000', $row['on_hand']);
        $this->assertSame('300.0000', $row['reserved']);
        // The number that decides whether a job can be promised the stock.
        $this->assertSame('700.0000', $row['available']);
        // Valued on everything held, not only on what is free: the reserved
        // units are still the factory's, and still on its balance sheet.
        $this->assertSame('2500.0000', $row['value']);
    }

    public function test_reserved_versus_available_names_the_holder_and_keeps_earmarks_apart(): void
    {
        $thread = ItemFixture::item($this->delta, 'TRM-THR-40', 'Thread 40/2');
        $this->receive($thread, '400', '5');

        $reservations = app(ReserveItemStock::class);

        $reservations->reserve($thread, $this->bin, '120', [
            'reservation_type' => 'MANUAL',
            'reference_type' => 'test',
            'reference_id' => $thread->id,
        ]);

        // A claim on stock that has not landed yet holds nothing on this
        // shelf, so it must not be folded into "reserved".
        $reservations->earmark($thread, '250', [
            'reservation_type' => 'FLOOR_REQUISITION',
            'reference_type' => 'test',
            'reference_id' => $thread->id,
        ]);

        $row = $this->rowFor($this->rows('item_reserved_vs_available'), 'TRM-THR-40');

        $this->assertSame('400.0000', $row['on_hand']);
        $this->assertSame('120.0000', $row['reserved']);
        $this->assertSame('280.0000', $row['available']);
        $this->assertSame('250.0000', $row['pending_arrival']);
        $this->assertNotNull($row['holders']);
    }

    public function test_ageing_dates_stock_from_its_most_recent_arrival(): void
    {
        $dye = ItemFixture::item($this->delta, 'CHM-DYE-RED', 'Reactive Red');

        // Two arrivals: one old, one recent. The pool is dated from the
        // later one, because the ledger keeps an average rather than
        // layers and cannot say which drum is which.
        $this->receive($dye, '100', '10', CarbonImmutable::today()->subDays(200));
        $this->receive($dye, '50', '10', CarbonImmutable::today()->subDays(10));

        $row = $this->rowFor($this->rows('item_stock_ageing'), 'CHM-DYE-RED');

        $this->assertSame(10, $row['days_held']);
        $this->assertSame(CarbonImmutable::today()->subDays(10)->toDateString(), $row['last_receipt']);
        $this->assertSame('Up to 30 days', $row['bracket']);
        $this->assertSame('150.0000', $row['on_hand']);
    }

    public function test_slow_moving_counts_demand_and_not_a_write_off(): void
    {
        $ledger = app(ItemLedger::class);

        $moving = ItemFixture::item($this->delta, 'TRM-ZIP-5', 'Zipper #5');
        $writtenOff = ItemFixture::item($this->delta, 'TRM-LBL-01', 'Care label');
        $untouched = ItemFixture::item($this->delta, 'TRM-ELS-25', 'Elastic 25mm');

        foreach ([$moving, $writtenOff, $untouched] as $item) {
            $this->receive($item, '500', '1', CarbonImmutable::today()->subDays(300));
        }

        // Drawn for work today: moving.
        $ledger->post($moving, $this->bin, 'ISSUE', '10', null, [
            'reference_type' => 'test', 'reference_id' => $moving->id,
        ]);

        // Written off by a count today. Stock somebody wrote off is the
        // opposite of stock that sells, so this must still read as idle.
        $ledger->post($writtenOff, $this->bin, 'ADJUSTMENT_OUT', '10', null, [
            'reference_type' => 'test', 'reference_id' => $writtenOff->id,
        ]);

        $codes = array_column($this->rows('item_slow_moving'), 'item_code');

        $this->assertNotContains('TRM-ZIP-5', $codes);
        $this->assertContains('TRM-LBL-01', $codes);
        $this->assertContains('TRM-ELS-25', $codes);

        // Never drawn on at all says so, rather than leaving a blank.
        $this->assertSame('Never issued', $this->rowFor($this->rows('item_slow_moving'), 'TRM-ELS-25')['last_issue']);
    }

    public function test_lot_expiry_lists_what_is_left_and_flags_what_is_past(): void
    {
        $dye = ItemFixture::item($this->delta, 'CHM-DYE-BLU', 'Reactive Blue', ['tracking_mode' => 'LOT']);

        foreach ([['L-LIVE', 20], ['L-GONE', -5]] as [$lotNo, $days]) {
            ItemLot::create([
                'item_id' => $dye->id,
                'lot_no' => $lotNo,
                'expiry_date' => CarbonImmutable::today()->addDays($days),
                'first_received_date' => CarbonImmutable::today()->subDays(30),
            ]);
        }

        // A third lot with a date but nothing left of it: it cannot go off,
        // and padding the list with it teaches people to ignore the list.
        ItemLot::create([
            'item_id' => $dye->id,
            'lot_no' => 'L-EMPTY',
            'expiry_date' => CarbonImmutable::today()->addDays(3),
            'first_received_date' => CarbonImmutable::today()->subDays(60),
        ]);

        app(ItemLedger::class)->post($dye, $this->bin, 'RECEIPT', '40', '12', [
            'lot_no' => 'L-LIVE', 'reference_type' => 'test', 'reference_id' => $dye->id,
        ]);
        app(ItemLedger::class)->post($dye, $this->bin, 'RECEIPT', '10', '12', [
            'lot_no' => 'L-GONE', 'reference_type' => 'test', 'reference_id' => $dye->id,
        ]);

        $rows = $this->rows('item_lot_expiry');
        $lots = array_column($rows, 'lot_no');

        $this->assertNotContains('L-EMPTY', $lots);
        // Nearest expiry first — and an expired lot is the nearest of all.
        $this->assertSame('L-GONE', $lots[0]);
        $this->assertSame('Expired', $rows[0]['state']);
        $this->assertSame(-5, $rows[0]['days_to_expiry']);
        $this->assertSame('480.0000', $rows[1]['value']);
    }

    public function test_shade_stock_adds_up_only_cloth_that_can_be_cut_together(): void
    {
        $fabric = ItemFixture::item($this->delta, 'FAB-CVC-200', 'CVC Jersey', ['tracking_mode' => 'ROLL']);

        $roll = function (string $no, string $shade, string $length, string $status) use ($fabric): FabricRoll {
            return FabricRoll::create([
                'item_id' => $fabric->id,
                'goods_receipt_id' => $this->receiptId(),
                'goods_receipt_line_id' => $this->receiptLineId($fabric),
                'bin_id' => $this->bin->id,
                'roll_no' => $no,
                'shade' => $shade,
                'lot_no' => 'DL-1',
                'net_length' => $length,
                'gross_length' => $length,
                'status' => $status,
                'unit_cost' => '3',
                'received_date' => CarbonImmutable::today(),
            ]);
        };

        $roll('R-1', 'NAVY-A', '100', 'AVAILABLE');
        $roll('R-2', 'NAVY-A', '80', 'RESERVED');
        $roll('R-3', 'NAVY-B', '150', 'AVAILABLE');
        // Already cut and gone: counting it would promise cloth that is
        // no longer on the rack.
        $roll('R-4', 'NAVY-A', '200', 'ISSUED');

        $rows = $this->rows('fabric_shade_stock');
        $navyA = null;

        foreach ($rows as $row) {
            if ($row['shade'] === 'NAVY-A') {
                $navyA = $row;
            }
        }

        $this->assertNotNull($navyA);
        $this->assertSame(2, $navyA['roll_count']);
        $this->assertSame('180.0000', $navyA['quantity']);
        // On the rack but spoken for — the gap between what is there and
        // what can be promised.
        $this->assertSame(1, $navyA['reserved_rolls']);
        $this->assertSame('540.0000', $navyA['value']);

        // Two shades of one fabric are two rows, never one.
        $this->assertCount(2, $rows);
    }

    public function test_lineage_points_a_child_roll_back_at_the_piece_it_came_from(): void
    {
        $fabric = ItemFixture::item($this->delta, 'FAB-CVC-180', 'CVC Jersey 180', ['tracking_mode' => 'ROLL']);

        $parent = FabricRoll::create([
            'item_id' => $fabric->id, 'goods_receipt_id' => $this->receiptId(), 'goods_receipt_line_id' => $this->receiptLineId($fabric),
            'bin_id' => $this->bin->id, 'roll_no' => 'R-100',
            'shade' => 'WHT', 'lot_no' => 'DL-9', 'net_length' => '300', 'gross_length' => '300',
            'status' => 'SPLIT', 'unit_cost' => '4', 'received_date' => CarbonImmutable::today(),
        ]);

        $child = FabricRoll::create([
            'item_id' => $fabric->id, 'goods_receipt_id' => $this->receiptId(), 'goods_receipt_line_id' => $this->receiptLineId($fabric),
            'bin_id' => $this->bin->id, 'roll_no' => 'R-100-A',
            'shade' => 'WHT', 'lot_no' => 'DL-9', 'net_length' => '120', 'gross_length' => '120',
            'status' => 'AVAILABLE', 'unit_cost' => '4', 'parent_roll_id' => $parent->id,
            'received_date' => CarbonImmutable::today(),
        ]);

        $rows = $this->rows('fabric_roll_lineage');

        // Only rolls with a lineage; the parent itself has none of its own.
        $this->assertCount(1, $rows);
        $this->assertSame('R-100-A', $rows[0]['roll_no']);
        $this->assertSame('Split from', $rows[0]['relation']);
        $this->assertSame('R-100', $rows[0]['related_roll']);

        // Merged on later: the merge is the newer fact, so it wins.
        $into = FabricRoll::create([
            'item_id' => $fabric->id, 'goods_receipt_id' => $this->receiptId(), 'goods_receipt_line_id' => $this->receiptLineId($fabric),
            'bin_id' => $this->bin->id, 'roll_no' => 'R-200',
            'shade' => 'WHT', 'lot_no' => 'DL-9', 'net_length' => '120', 'gross_length' => '120',
            'status' => 'AVAILABLE', 'unit_cost' => '4', 'received_date' => CarbonImmutable::today(),
        ]);

        $child->forceFill(['merged_into_roll_id' => $into->id, 'status' => 'MERGED'])->save();

        $rows = $this->rows('fabric_roll_lineage');

        $this->assertSame('Merged into', $rows[0]['relation']);
        $this->assertSame('R-200', $rows[0]['related_roll']);
    }
}
