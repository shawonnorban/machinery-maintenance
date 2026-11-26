<?php

declare(strict_types=1);

namespace Tests\Feature\StoreMaterial;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\StoreMaterial\Models\LedgerEntry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Store & Material → In-House / GRN (AnnotechERP 09/01): only an approved PR
 * is received against, never past what is pending; a direct receive stocks
 * material that came without a PR for a buyer and style or for general
 * stock; the Store Manager reverses a GRN, which restores the PR's pending;
 * the warehouses, the ledger and the dashboard read the same books; and the
 * receiver is an employee found by ID or card.
 */
class StoreGrnTest extends TestCase
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

    public function test_a_grn_against_an_approved_pr_stocks_the_fabric_and_a_reversal_restores_the_pending(): void
    {
        [$pr, $fabricLine, $trimLine, $draft] = $this->approved();
        $this->employee('50001001', 'Mohammad Sayem');

        // Only an approved PR, in Brickland's words.
        $this->as()->getJson('/api/v1/store-material/requisitions/PR-2026-99999')->assertUnprocessable()->assertJsonPath('errors.pr_no.0', 'No Purchase Requisition PR-2026-99999 was found.');
        $this->as()->getJson("/api/v1/store-material/requisitions/{$draft}")->assertUnprocessable()->assertJsonPath('errors.pr_no.0', "{$draft} is submitted — the store can receive only against an approved PR.");
        $found = $this->as()->getJson("/api/v1/store-material/requisitions/{$pr}")->assertOk()->json('data');
        $this->assertSame(['APPROVED', 'Poly Plan Ltd'], [$found['status'], $found['supplier']]);
        $this->assertEquals([['FABRIC', 'Cotton Jersey (White)', 'KG', 100, 0, 100], ['TRIMS', 'Main Label', 'PCS', 500, 0, 500]], array_map(fn ($i) => [$i['kind'], $i['material'], $i['uom'], $i['pr_qty'], $i['received'], $i['pending']], $found['items']));

        $url = '/api/v1/store-material/grns/against-pr';
        $this->as()->postJson($url, ['pr_no' => $pr, 'lines' => [['requisition_line_id' => $fabricLine, 'qty' => 0]]])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Enter the receive quantity of at least one item.');
        $over = $this->as()->postJson($url, ['pr_no' => $pr, 'lines' => [['requisition_line_id' => $fabricLine, 'qty' => 120]]])->assertUnprocessable()->json('errors');
        $this->assertSame(['Cotton Jersey: no more than the pending 100 KG can be received.'], $over["lines.{$fabricLine}"]);
        $this->as()->postJson($url, ['pr_no' => $pr, 'received_by' => '99999', 'lines' => [['requisition_line_id' => $fabricLine, 'qty' => 40]]])->assertUnprocessable()->assertJsonPath('errors.received_by.0', 'No employee with ID or card no. 99999 was found.');

        $grn = $this->as()->postJson($url, ['pr_no' => $pr, 'received_by' => '50001001', 'remarks' => 'First lot', 'lines' => [['requisition_line_id' => $fabricLine, 'qty' => 40], ['requisition_line_id' => $trimLine, 'qty' => 0]]])->assertCreated()->json('data');
        $this->assertSame(['GRN-'.now()->format('Y').'-00001', 'CONFIRMED', 'PR', $pr, 'Poly Plan Ltd', '50001001 (Mohammad Sayem)', ['FABRIC']], [$grn['grn_no'], $grn['status'], $grn['kind'], $grn['pr_no'], $grn['supplier'], $grn['received_by'], $grn['warehouse']]);
        $this->assertEquals([1, 'Cotton Jersey (White)', 100, 0, 40, 40, 60], [$grn['lines'][0]['sl'], $grn['lines'][0]['material'], $grn['lines'][0]['pr_qty'], $grn['lines'][0]['prev'], $grn['lines'][0]['qty'], $grn['lines'][0]['total'], $grn['lines'][0]['pending']]);
        $this->assertEquals(60, $this->as()->getJson("/api/v1/store-material/requisitions/{$pr}")->json('data.items.0.pending'));
        $second = $this->as()->postJson($url, ['pr_no' => $pr, 'lines' => [['requisition_line_id' => $fabricLine, 'qty' => 25]]])->assertCreated()->json('data');
        $this->assertEquals([40, 65, 35], [$second['lines'][0]['prev'], $second['lines'][0]['total'], $second['lines'][0]['pending']]);

        // The warehouse, the ledger and the dashboard read the same books.
        $this->assertEquals([['Cotton Jersey', 'White', 'KG', 65, 0, 65]], array_map(fn ($r) => [$r['material'], $r['color'], $r['unit'], $r['recv'], $r['out'], $r['available']], $this->as()->getJson('/api/v1/store-material/fabric-warehouse')->json('data.rows')));
        $this->assertSame([], $this->as()->getJson('/api/v1/store-material/fabric-warehouse?view=roll')->json('data.rows'));
        $ledger = $this->as()->getJson('/api/v1/store-material/stock-ledger')->json('data.rows');
        $this->assertEquals([[$grn['grn_no'], 'GRN', 40, 40], [$second['grn_no'], 'GRN', 25, 65]], array_map(fn ($r) => [$r['reference'], $r['type'], $r['in'], $r['balance']], $ledger));
        $dash = $this->as()->getJson('/api/v1/store-material/dashboard')->json('data');
        $this->assertEquals(['pr_qty' => 600, 'in_house' => 65, 'pending' => 535, 'out' => 0, 'returned' => 0, 'stock' => 65], $dash['tiles']);
        $this->assertSame([$second['grn_no'], $pr], [$dash['movements'][0]['reference'], $dash['movements'][0]['pr_no']]);

        // Reversal: the Store Manager's, with a reason; a storekeeper may not.
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($keeper)->postJson("/api/v1/store-material/grns/{$second['grn_no']}/reverse", ['reason' => 'Wrong lot'])->assertForbidden();
        $this->as()->postJson("/api/v1/store-material/grns/{$second['grn_no']}/reverse", [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Reason is required.');
        $reversed = $this->as()->postJson("/api/v1/store-material/grns/{$second['grn_no']}/reverse", ['reason' => 'Wrong lot'])->assertOk()->json('data');
        $this->assertSame(['REVERSED', 'Wrong lot'], [$reversed['status'], $reversed['reverse_reason']]);
        $this->as()->postJson("/api/v1/store-material/grns/{$second['grn_no']}/reverse", ['reason' => 'Again'])->assertUnprocessable();
        $this->assertEquals(60, $this->as()->getJson("/api/v1/store-material/requisitions/{$pr}")->json('data.items.0.pending'));
        $this->assertEquals([40, 'REVERSAL'], [$this->as()->getJson('/api/v1/store-material/stock-ledger')->json('data.rows.2.balance'), $this->as()->getJson('/api/v1/store-material/stock-ledger')->json('data.rows.2.type')]);
        // The books are never edited.
        $this->expectException(RuntimeException::class);
        LedgerEntry::query()->firstOrFail()->update(['in_qty' => 1]);
    }

    public function test_a_direct_receive_stocks_material_for_a_buyer_or_general_stock_and_takes_attachments(): void
    {
        Storage::fake(config('filesystems.default'));
        $this->approved();
        $options = $this->as()->getJson('/api/v1/store-material/grns/options')->assertOk()->json('data');
        $label = collect($options['items'])->firstWhere('label', 'ACC-001 — Main Label');
        $jersey = collect($options['items'])->firstWhere('label', 'FAB-001 — Cotton Jersey (White)');

        $url = '/api/v1/store-material/grns/direct';
        $blank = $this->as()->postJson($url, ['lines' => [['item_id' => null, 'qty' => null]]])->assertUnprocessable()->json('errors');
        $this->assertSame([['Material is required.'], ['Receive Qty is required.']], [$blank['lines.0.item_id'], $blank['lines.0.qty']]);
        $grn = $this->as()->postJson($url, ['buyer' => 'LPP', 'style' => '927', 'supplier' => 'Local Market', 'currency' => 'BDT', 'lines' => [['item_id' => $label['value'], 'qty' => 300, 'unit_price' => 1.2], ['item_id' => $jersey['value'], 'qty' => 10]]])->assertCreated()->json('data');
        $this->assertSame(['DIRECT', null, 'LPP', '927', 'Local Market', ['TRIMS', 'FABRIC']], [$grn['kind'], $grn['pr_no'], $grn['buyer'], $grn['style'], $grn['supplier'], $grn['warehouse']]);
        // General stock: no buyer.
        $this->as()->postJson($url, ['lines' => [['item_id' => $label['value'], 'qty' => 50]]])->assertCreated();
        $trims = $this->as()->getJson('/api/v1/store-material/trims-warehouse')->json('data.rows');
        $this->assertEquals([[null, 50], ['LPP', 300]], array_map(fn ($r) => [$r['buyer'], $r['available']], $trims));
        $filters = $this->as()->getJson('/api/v1/store-material/filters')->json('data');
        $this->assertSame([['LPP'], ['927']], [array_column($filters['buyers'], 'value'), array_column($filters['styles'], 'value')]);
        $this->assertEquals(300, $this->as()->getJson('/api/v1/store-material/trims-warehouse?buyer=LPP')->json('data.rows.0.available'));

        // Attachments: a PDF or an image, five megabytes at most.
        $this->as()->postJson("/api/v1/store-material/grns/{$grn['grn_no']}/attachments", [])->assertUnprocessable()->assertJsonPath('errors.file.0', 'Choose a file to attach.');
        $this->as()->post("/api/v1/store-material/grns/{$grn['grn_no']}/attachments", ['file' => UploadedFile::fake()->create('challan.docx', 10), 'name' => 'Challan'], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.file.0', 'Attach a PDF or an image.');
        $attached = $this->as()->post("/api/v1/store-material/grns/{$grn['grn_no']}/attachments", ['file' => UploadedFile::fake()->create('challan.pdf', 50, 'application/pdf'), 'name' => 'Delivery challan'], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame(['Delivery challan'], array_column($attached['attachments'], 'original_name'));

        // The receiver lookup.
        $this->employee('50001001', 'Mohammad Sayem');
        $this->as()->getJson('/api/v1/store-material/employee?id=50001001')->assertOk()->assertJsonPath('data.label', '50001001 (Mohammad Sayem)');
        $this->as()->getJson('/api/v1/store-material/employee?id=EMP-000001')->assertOk()->assertJsonPath('data.name', 'Mohammad Sayem');
        $this->as()->getJson('/api/v1/store-material/employee?id=1001')->assertUnprocessable()->assertJsonPath('errors.id.0', 'No employee with ID or card no. 1001 was found.');
        $this->as(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test'))->postJson($url, ['lines' => [['item_id' => $label['value'], 'qty' => 1]]])->assertForbidden();
    }

    /**
     * A direct requisition of a fabric (100 KG) and an accessory (500 PCS),
     * both approved; and another, submitted only.
     *
     * @return array{string, string, string, string} the approved PR, its fabric line, its accessory line, the submitted PR
     */
    private function approved(): array
    {
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-002', 'name' => 'Poly Plan Ltd', 'supplier_type' => 'LOCAL_NON_EPZ'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => 'Poly Desk', 'email' => 'desk@polyplan.test', 'password' => 'Quote-Ready-2026!'])->assertCreated();
        $poly = User::query()->where('email', 'desk@polyplan.test')->firstOrFail();
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'colour' => 'White', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $trim = $this->as()->postJson('/api/v1/erp/material-trims', ['item_code' => 'ACC-001', 'name' => 'Main Label', 'category' => 'Label', 'uom' => 'Pcs'])->assertCreated()->json('data.id');
        $lines = [['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => 100, 'uom' => 'KG', 'req_date' => '2026-11-20'], ['kind' => 'TRIMS', 'item_id' => $trim, 'req_qty' => 500, 'uom' => 'PCS', 'req_date' => '2026-11-20']];
        $data = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => $lines])->assertCreated()->json('data');
        $draft = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [$lines[0]]])->assertCreated()->json('data.pr_no');
        [$fabricLine, $trimLine] = array_column($data['lines'], 'id');
        foreach ([[$fabricLine, 100, 2.5], [$trimLine, 500, 0.08]] as [$line, $qty, $price]) {
            $qt = $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$line}/quote", ['quoted_qty' => $qty, 'unit_price' => $price, 'currency' => 'USD', 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15'])->assertCreated()->json('data.qt_no');
            $id = Quotation::query()->where('qt_no', $qt)->value('id');
            $this->as()->postJson("/api/v1/material-procurement/price-review/quotations/{$id}/approve", ['handover_to' => $merch->id])->assertOk();
        }

        return [$data['pr_no'], $fabricLine, $trimLine, $draft];
    }

    private function employee(string $card, string $name): void
    {
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => $card, 'name' => $name, 'nid' => '1234567890', 'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
