<?php

declare(strict_types=1);

namespace Tests\Feature\GeneralStore;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * General Store → Reports (AnnotechERP 10/06): the thirteen reports read the
 * same documents and movements the screens post — a cancelled document only
 * under the Cancelled status, a cancellation netted out of the movement
 * figures — each with its total row, as Excel and as a PDF.
 */
class GeneralStoreReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_the_reports_read_the_store_s_documents(): void
    {
        $stores = collect($this->as()->getJson('/api/v1/erp/store-locations')->json('data'))->pluck('id', 'code');
        $depts = collect($this->as()->getJson('/api/v1/erp/store-departments')->json('data'))->pluck('id', 'code');
        $paper = $this->item('ITM-001', 'A4 Paper', 'STAT', 'REAM', 400, 5);
        $gloves = $this->item('ITM-030', 'Gloves', 'SAFE', 'PAIR', null, 50);
        $post = fn (string $kind, array $body) => $this->as()->postJson("/api/v1/general-store/{$kind}/documents", ['doc_date' => now()->toDateString(), 'gs_store_id' => $stores['MAIN']] + $body)->assertCreated()->json('data');

        $post('opening', ['lines' => [['gs_item_id' => $paper, 'quantity' => 10, 'unit_price' => 400]]]);
        $post('receive', ['challan_no' => 'CH-9', 'lines' => [['gs_item_id' => $paper, 'quantity' => 20, 'unit_price' => 420], ['gs_item_id' => $gloves, 'quantity' => 30]]]);
        $cancelled = $post('receive', ['lines' => [['gs_item_id' => $paper, 'quantity' => 5, 'unit_price' => 420]]]);
        $this->as()->postJson("/api/v1/general-store/documents/{$cancelled['doc_no']}/cancel", ['reason' => 'Duplicate'])->assertOk();
        $issue = $post('issue', ['gs_department_id' => $depts['MAINT'], 'person' => 'Rahim', 'lines' => [['gs_item_id' => $paper, 'quantity' => 8, 'unit_price' => 420]]]);
        $post('issue', ['gs_department_id' => $depts['IT'], 'lines' => [['gs_item_id' => $paper, 'quantity' => 2, 'unit_price' => 420]]]);
        $post('return', ['against' => $issue['doc_no'], 'lines' => [['gs_item_id' => $paper, 'quantity' => 3]]]);
        $post('adjustment', ['adjustment_type' => 'DAMAGE', 'remarks' => 'Wet', 'lines' => [['gs_item_id' => $paper, 'quantity' => 1]]]);
        $post('transfer', ['to_store_id' => $stores['ELEC'], 'lines' => [['gs_item_id' => $paper, 'quantity' => 4]]]);
        $report = fn (string $key, string $query = '') => $this->as()->getJson("/api/v1/general-store/reports/{$key}{$query}")->assertOk()->json('data');

        // Stock: every movement in its own column, the cancelled receive netted out.
        $stock = $report('stock');
        $row = collect($stock['rows'])->firstWhere('item_code', 'ITM-001');
        $this->assertEquals([10, 20, 3, 4, 10, 4, -1, 22, 420, 9240, 'IN_STOCK'], [$row['opening'], $row['received'], $row['returned'], $row['transfer_in'], $row['issued'], $row['transfer_out'], $row['adjusted'], $row['current'], $row['unit_cost'], $row['current_value'], $row['status']]);
        $this->assertSame('Current Stock Value (৳)', collect($stock['columns'])->firstWhere('key', 'current_value')['label']);
        $main = collect($report('stock', '?store='.$stores['MAIN'])['rows'])->firstWhere('item_code', 'ITM-001');
        $this->assertEquals([0, 4, 18], [$main['transfer_in'], $main['transfer_out'], $main['current']]);

        // Receive: posted lines only, unless Cancelled is asked for; a total row.
        $receive = $report('receive');
        $this->assertCount(2, $receive['rows']);
        $this->assertEquals(['qty' => 50, 'value' => 8400], $receive['total']);
        $this->assertSame('CH-9', $receive['rows'][0]['challan_ref']);
        $this->assertSame([$cancelled['doc_no']], array_column($report('receive', '?status=CANCELLED')['rows'], 'doc_no'));
        $this->assertCount(1, $report('receive', '?q=gloves')['rows']);

        // Issue, Return, Adjustment, Transfer.
        $issues = $report('issue');
        $this->assertEquals([['Maintenance', 8, 3360, 'Rahim', 'POSTED'], ['IT', 2, 840, null, 'POSTED']], array_map(fn ($r) => [$r['department'], $r['qty'], $r['issue_value'], $r['receiver_req'], $r['doc_status']], $issues['rows']));
        $this->assertCount(1, $report('issue', '?department='.$depts['IT'])['rows']);
        $this->assertSame([$issue['doc_no']], array_column($report('return')['rows'], 'against'));
        $this->assertEquals([['DAMAGE', -1, 420]], array_map(fn ($r) => [$r['adjustment_type'], $r['signed_qty'], $r['value']], $report('adjustment')['rows']));
        $this->assertEquals([['Main Store', 'Electrical Store', 4]], array_map(fn ($r) => [$r['from_store'], $r['to_store'], $r['qty']], $report('transfer')['rows']));

        // Department-wise and Item-wise consumption: issued less returned, value at the lines' prices.
        $this->assertEquals([['Maintenance', 8, 3, 5, 2100], ['IT', 2, 0, 2, 840]], array_map(fn ($r) => [$r['department'], $r['issued'], $r['returned'], $r['net'], $r['value']], $report('department')['rows']));
        $this->assertEquals(['issued' => 10, 'returned' => 3, 'net' => 7, 'value' => 2940], $report('consumption')['total']);

        // Category (largest stock first, as Brickland lists it), Valuation, Low Stock.
        $this->assertEquals([['Safety', 1, 30, 0, 0, 1, 0], ['Stationery', 1, 22, 1, 9240, 0, 0]], array_map(fn ($r) => [$r['category'], $r['items'], $r['stock_qty'], $r['priced'], $r['value'], $r['low'], $r['out_of_stock']], $report('category')['rows']));
        $this->assertEquals(['stock_qty' => 52, 'value' => 9240], $report('valuation')['total']);
        $this->assertEquals([['ITM-030', 30, 50, 20, 'LOW']], array_map(fn ($r) => [$r['item_code'], $r['current_stock'], $r['min'], $r['shortage'], $r['status']], $report('low-stock')['rows']));

        // Monthly: receive with opening, consumption = issue − return; transfers left out.
        $month = $report('monthly')['rows'][0];
        $this->assertEquals([now()->format('Y-m'), 60, 10, 3, 0, 1, 7], [$month['month'], $month['receive'], $month['issue'], $month['return'], $month['adj_in'], $month['adj_out'], $month['consumption']]);

        // Transaction History: newest first, each with the store balance after it; by type.
        $history = $report('history', '?item='.$paper);
        $this->assertSame(['TRANSFER', 'Electrical Store', 4.0], [$history['rows'][0]['type'], $history['rows'][0]['store'], (float) $history['rows'][0]['balance']]);
        $this->assertSame([true, 'RECEIVE'], [collect($history['rows'])->firstWhere('reversal', true) !== null, collect($history['rows'])->firstWhere('reversal', true)['type']]);
        $this->assertCount(2, $report('history', '?type=ISSUE')['rows']);
        $this->assertNull($history['total']);

        // Excel and PDF.
        $this->as()->get('/api/v1/general-store/reports/receive/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf = $this->as()->get('/api/v1/general-store/reports/stock/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->as()->getJson('/api/v1/general-store/reports/nothing')->assertNotFound();
    }

    private function item(string $code, string $name, string $category, string $unit, ?float $price, float $min): string
    {
        $cat = collect($this->as()->getJson('/api/v1/erp/store-categories')->json('data'))->firstWhere('code', $category)['id'];
        $uom = collect($this->as()->getJson('/api/v1/erp/store-units')->json('data'))->firstWhere('code', $unit)['id'];

        return $this->as()->postJson('/api/v1/erp/store-items', ['item_code' => $code, 'name' => $name, 'gs_category_id' => $cat, 'gs_unit_id' => $uom, 'default_price' => $price, 'minimum_stock' => $min])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
