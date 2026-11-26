<?php

declare(strict_types=1);

namespace Tests\Feature\GeneralStore;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\GeneralStore\Models\GsAuditEntry;
use App\Modules\GeneralStore\Models\GsStockMovement;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * General Store → Inventory and Dashboard (AnnotechERP 10/02–10/03): every
 * document posts to stock when saved; an issue, a transfer and a decrease
 * never take more than the store holds; an issue's price starts at the last
 * purchase price and a changed price is audited; a return against an issue
 * takes back at most what it gave; a cancellation reverses the movement,
 * keeps the document and is refused when the stock has moved on; and
 * Current Stock, the Ledger and the Dashboard read the same movements.
 */
class GeneralStoreInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> code => id */
    private array $stores = [];

    /** @var array<string, string> code => id */
    private array $depts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->stores = collect($this->as()->getJson('/api/v1/erp/store-locations')->json('data'))->pluck('id', 'code')->all();
        $this->depts = collect($this->as()->getJson('/api/v1/erp/store-departments')->json('data'))->pluck('id', 'code')->all();
    }

    public function test_receive_issue_return_and_cancel_follow_the_stock(): void
    {
        $paper = $this->item('ITM-001', 'A4 Paper', 'STAT', 'REAM', 450, 2);
        $pin = $this->item('ITM-022', 'Stapler Pin', 'STAT', 'BOX', 20);
        $year = now()->format('Y');
        $head = ['doc_date' => now()->toDateString(), 'gs_store_id' => $this->stores['MAIN']];

        $options = $this->as()->getJson('/api/v1/general-store/receive/options')->assertOk()->json('data');
        $this->assertSame(["RC-{$year}-000001", 'Main Store'], [$options['next_no'], $options['stores'][0]['label']]);

        // Receive: at least one item, each with a quantity; the price is optional.
        $receive = '/api/v1/general-store/receive/documents';
        $this->as()->postJson($receive, $head + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Pick at least one item.');
        $this->assertSame('Line 1: Quantity must be a number greater than zero.', $this->as()->postJson($receive, $head + ['lines' => [['gs_item_id' => $paper, 'quantity' => '']]])->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $rc = $this->as()->postJson($receive, $head + ['challan_no' => 'CH-77', 'lines' => [['gs_item_id' => $paper, 'quantity' => 10, 'unit_price' => 420], ['gs_item_id' => $pin, 'quantity' => 45]]])
            ->assertCreated()->json('data');
        $this->assertEquals(["RC-{$year}-000001", 'POSTED', 55, 4200, 'CH-77'], [$rc['doc_no'], $rc['status'], $rc['total_qty'], $rc['total_value'], $rc['challan_no']]);
        $this->assertSame("Stock Receive RC-{$year}-000001: 2 item(s), 55 qty, ৳4200", GsAuditEntry::query()->where('action', 'CREATE')->where('screen', 'gs-receive')->sole()->summary);

        // Issue: the last purchase price comes first; the department and a price are required; never past the stock.
        $paperOption = collect($this->as()->getJson('/api/v1/general-store/issue/options')->json('data.items'))->firstWhere('value', $paper);
        $this->assertEquals([420, [$this->stores['MAIN'] => 10]], [$paperOption['price'], (array) $paperOption['stock']]);
        $issue = '/api/v1/general-store/issue/documents';
        $this->as()->postJson($issue, $head + ['lines' => [['gs_item_id' => $paper, 'quantity' => 2, 'unit_price' => 420]]])->assertUnprocessable()->assertJsonPath('errors.gs_department_id.0', 'Department is required.');
        $form = $head + ['gs_department_id' => $this->depts['MAINT'], 'person' => 'Rahim (50001001)'];
        $this->assertSame('Line 1: Unit Price is required.', $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $paper, 'quantity' => 2]]])->assertUnprocessable()->json('errors')['lines.0.unit_price'][0]);
        $this->assertSame('Line 1: only 10 REAM of A4 Paper is available.', $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $paper, 'quantity' => 12, 'unit_price' => 420]]])->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $is = $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $paper, 'quantity' => 4, 'unit_price' => 400]]])->assertCreated()->json('data');
        $this->assertEquals(["IS-{$year}-000001", 'Maintenance', 4, 1600, 420], [$is['doc_no'], $is['department'], $is['total_qty'], $is['total_value'], $is['lines'][0]['suggested_price']]);
        $this->assertSame("Stock Issue IS-{$year}-000001: A4 Paper unit price changed from ৳420 to ৳400", GsAuditEntry::query()->where('action', 'PRICE_CHANGE')->sole()->summary);

        // Return against the issue: its items only, no more than it gave; the issue's store, department and price.
        $this->as()->getJson("/api/v1/general-store/documents/{$is['doc_no']}/returnable")->assertOk()->assertJsonPath('data.lines.0.eligible', 4)->assertJsonPath('data.department_id', $this->depts['MAINT']);
        $return = '/api/v1/general-store/return/documents';
        $back = ['doc_date' => now()->toDateString(), 'gs_store_id' => $this->stores['ELEC'], 'against' => $is['doc_no'], 'person' => 'Rahim'];
        $this->assertSame("Line 1: only 4 REAM of A4 Paper can come back against {$is['doc_no']}.", $this->as()->postJson($return, $back + ['lines' => [['gs_item_id' => $paper, 'quantity' => 5]]])->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $this->assertSame("Line 1: Stapler Pin is not on {$is['doc_no']}.", $this->as()->postJson($return, $back + ['lines' => [['gs_item_id' => $pin, 'quantity' => 1]]])->assertUnprocessable()->json('errors')['lines.0.gs_item_id'][0]);
        $rt = $this->as()->postJson($return, $back + ['lines' => [['gs_item_id' => $paper, 'quantity' => 1]]])->assertCreated()->json('data');
        $this->assertEquals(["RT-{$year}-000001", 'Main Store', 'Maintenance', $is['doc_no'], 400], [$rt['doc_no'], $rt['store'], $rt['department'], $rt['against'], $rt['lines'][0]['unit_price']]);
        $this->assertEquals(7, $this->stockOf($paper));

        // Cancel: a reason; an issue with returns against it waits for them; the document stays, as Cancelled.
        $cancel = fn (string $no, array $body = ['reason' => 'Entered twice']) => $this->as()->postJson("/api/v1/general-store/documents/{$no}/cancel", $body);
        $cancel($is['doc_no'])->assertUnprocessable()->assertJsonPath('errors.document.0', "{$is['doc_no']} has returns against it — cancel them first.");
        $cancel($rt['doc_no'], [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Reason is required.');
        $cancel($rt['doc_no'])->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonPath('data.cancel_reason', 'Entered twice');
        $cancel($rt['doc_no'])->assertUnprocessable()->assertJsonPath('errors.document.0', "{$rt['doc_no']} is already cancelled.");
        $cancel($is['doc_no'])->assertOk();
        $this->assertEquals(10, $this->stockOf($paper));
        $this->assertSame("Stock Issue {$is['doc_no']} cancelled and reversed: Entered twice", GsAuditEntry::query()->where('action', 'UPDATE')->latest('created_at')->first()->summary);

        // A receive whose stock has since gone out cannot be cancelled.
        $this->as()->postJson($issue, $form + ['lines' => [['gs_item_id' => $paper, 'quantity' => 3, 'unit_price' => 420]]])->assertCreated();
        $cancel($rc['doc_no'])->assertUnprocessable()->assertJsonPath('errors.document.0', 'Cannot cancel: only 7 REAM of A4 Paper is left in Main Store.');

        // The lists and their filters.
        $list = fn (string $kind, string $query = '') => $this->as()->getJson("/api/v1/general-store/{$kind}/documents{$query}")->assertOk()->json('data.rows');
        $this->assertSame([["IS-{$year}-000002", 'POSTED'], ["IS-{$year}-000001", 'CANCELLED']], array_map(fn ($r) => [$r['doc_no'], $r['status']], $list('issue')));
        $this->assertSame(['A4 Paper +1'], array_column($list('receive', '?q=stapler'), 'items'));
        $this->assertCount(1, $list('issue', '?status=CANCELLED'));
        $this->assertCount(0, $list('receive', '?store='.$this->stores['ELEC']));
        $this->as()->get('/api/v1/general-store/issue/documents/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // The ledger: every movement with its balance, the reversals as posted.
        $ledger = $this->as()->getJson('/api/v1/general-store/ledger?item=ITM-001')->assertOk()->json('data');
        $this->assertEquals([['RECEIVE', false, 10, 10], ['ISSUE', false, 4, 6], ['RETURN', false, 1, 7], ['RETURN', true, -1, 6], ['ISSUE', true, -4, 10], ['ISSUE', false, 3, 7]],
            array_map(fn ($r) => [$r['type'], $r['reversal'], $r['qty'], $r['balance']], $ledger['rows']));
        $this->assertEquals([0, 15, 8, 7], [$ledger['opening'], $ledger['total_in'], $ledger['total_out'], $ledger['closing']]);
    }

    public function test_opening_adjustment_transfer_and_the_stock_views(): void
    {
        $gloves = $this->item('ITM-030', 'Safety Gloves', 'SAFE', 'PAIR', null, 15);
        $head = ['doc_date' => now()->toDateString(), 'gs_store_id' => $this->stores['MAIN']];
        $this->as()->postJson('/api/v1/general-store/opening/documents', $head + ['reference_no' => 'Shelf count', 'lines' => [['gs_item_id' => $gloves, 'quantity' => 20, 'unit_price' => 10]]])
            ->assertCreated()->assertJsonPath('data.doc_no', 'OP-'.now()->format('Y').'-000001');

        // Adjustment: a type and a reason; a decrease never below zero; a count adjusts by its difference.
        $adjust = '/api/v1/general-store/adjustment/documents';
        $line = fn ($q) => ['lines' => [['gs_item_id' => $gloves, 'quantity' => $q]]];
        $blank = $this->as()->postJson($adjust, $head + $line(1))->assertUnprocessable()->json('errors');
        $this->assertSame(['Adjustment Type is required.', 'Reason is required.'], [$blank['adjustment_type'][0], $blank['remarks'][0]]);
        $this->as()->postJson($adjust, $head + ['adjustment_type' => 'DECREASE', 'remarks' => 'Torn'] + $line(25))->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Not enough stock: Safety Gloves in Main Store has 20 available, 25 needed.');
        $count = $this->as()->postJson($adjust, $head + ['adjustment_type' => 'COUNT', 'remarks' => 'Monthly count'] + $line(18))->assertCreated()->json('data');
        $this->assertEquals([18, -2, 2, 20], [$count['lines'][0]['quantity'], $count['lines'][0]['movement_qty'], $count['total_qty'], $count['total_value']]);
        $this->assertSame('Line 1: the physical count matches the stock — nothing to adjust.', $this->as()->postJson($adjust, $head + ['adjustment_type' => 'COUNT', 'remarks' => 'Again'] + $line(18))->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $this->as()->postJson($adjust, $head + ['adjustment_type' => 'INCREASE', 'remarks' => 'Found'] + $line(5))->assertCreated();
        $this->assertSame('Line 1: Adjustment Qty must not be zero.', $this->as()->postJson($adjust, $head + ['adjustment_type' => 'OTHER', 'remarks' => 'x'] + $line(0))->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $this->as()->postJson($adjust, $head + ['adjustment_type' => 'OTHER', 'remarks' => 'Sample given'] + $line(-3))->assertCreated();
        $this->assertEquals(20, $this->stockOf($gloves));

        // Transfer: two different stores, never more than the From store holds; both legs posted.
        $transfer = '/api/v1/general-store/transfer/documents';
        $this->as()->postJson($transfer, $head + ['to_store_id' => $this->stores['MAIN']] + $line(1))->assertUnprocessable()->assertJsonPath('errors.to_store_id.0', 'To Store must be different from From Store.');
        $this->assertSame('Line 1: only 20 PAIR of Safety Gloves is available.', $this->as()->postJson($transfer, $head + ['to_store_id' => $this->stores['SAFE']] + $line(30))->assertUnprocessable()->json('errors')['lines.0.quantity'][0]);
        $this->as()->postJson($transfer, $head + ['to_store_id' => $this->stores['SAFE']] + $line(8))->assertCreated()->assertJsonPath('data.to_store', 'Safety Store');
        $this->assertSame([['Main Store', 'Safety Store']], array_map(fn ($r) => [$r['store'], $r['to_store']], $this->as()->getJson('/api/v1/general-store/transfer/documents')->json('data.rows')));

        // Current Stock: quantity, value at the last purchase price, by store, status against the minimum.
        $stock = $this->as()->getJson('/api/v1/general-store/current-stock')->assertOk()->json('data');
        $row = collect($stock['rows'])->firstWhere('item_code', 'ITM-030');
        $this->assertEquals([20, 10, 200, 15, 'IN_STOCK'], [$row['qty'], $row['price'], $row['value'], $row['min'], $row['status']]);
        $this->assertEquals([['store' => 'Main Store', 'qty' => 12], ['store' => 'Safety Store', 'qty' => 8]], $row['by_store']);
        $this->assertEquals([1, 20, 200, 1, 0, 0], array_values($stock['tiles']));
        $this->assertSame(['LOW', 8], [collect($this->as()->getJson('/api/v1/general-store/current-stock?store='.$this->stores['SAFE'])->json('data.rows'))->sole()['status'], 8]);
        $this->assertCount(0, $this->as()->getJson('/api/v1/general-store/current-stock?status=OUT')->json('data.rows'));
        $this->as()->get('/api/v1/general-store/current-stock/excel')->assertOk();

        // The dashboard reads the same movements.
        $dash = $this->as()->getJson('/api/v1/general-store/dashboard')->assertOk()->json('data');
        $this->assertEquals([1, 20, 200, 200, 2], [$dash['tiles']['items'], $dash['tiles']['qty'], $dash['tiles']['value'], $dash['tiles']['received_value'], $dash['tiles']['today']['ADJUST'] - 1]);
        $this->assertCount(30, $dash['movement']);
        $this->assertSame('Safety', $dash['by_category'][0]['category']);

        // Who may: the storekeeper posts but does not cancel; the factory manager only looks.
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->as($keeper)->postJson($adjust, $head + ['adjustment_type' => 'INCREASE', 'remarks' => 'Found'] + $line(1))->assertCreated();
        $this->as($keeper)->postJson('/api/v1/general-store/documents/OP-'.now()->format('Y').'-000001/cancel', ['reason' => 'x'])->assertForbidden();
        $this->as($manager)->getJson('/api/v1/general-store/current-stock')->assertOk();
        $this->as($manager)->postJson($adjust, $head + ['adjustment_type' => 'INCREASE', 'remarks' => 'Found'] + $line(1))->assertForbidden();

        // The books are permanent.
        $this->expectException(RuntimeException::class);
        GsAuditEntry::query()->firstOrFail()->update(['summary' => 'x']);
    }

    private function stockOf(string $item): float
    {
        return (float) GsStockMovement::query()->where('gs_item_id', $item)->sum('quantity');
    }

    private function item(string $code, string $name, string $category, string $unit, ?float $price, float $min = 0): string
    {
        $cat = collect($this->as()->getJson('/api/v1/erp/store-categories')->json('data'))->firstWhere('code', $category)['id'];
        $uom = collect($this->as()->getJson('/api/v1/erp/store-units')->json('data'))->firstWhere('code', $unit)['id'];

        return $this->as()->postJson('/api/v1/erp/store-items', ['item_code' => $code, 'name' => $name, 'gs_category_id' => $cat, 'gs_unit_id' => $uom, 'default_price' => $price, 'minimum_stock' => $min])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
