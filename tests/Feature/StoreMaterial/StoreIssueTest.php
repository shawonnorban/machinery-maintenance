<?php

declare(strict_types=1);

namespace Tests\Feature\StoreMaterial;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Store & Material's movements off the shelf (AnnotechERP 09/01): Material
 * Out never issues more than is in stock and is handed to an employee;
 * Material Return takes back no more than went out; a GRN whose material is
 * issued can no longer be reversed; a count reaches the books only when the
 * Store Manager approves; traceability follows a PR, a GRN or a Material Out
 * through the chain; and the reports read the same books, as an Excel
 * workbook too.
 */
class StoreIssueTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $place = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $row = fn (string $table, array $data): string => tap((string) Str::ulid(), fn (string $id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $building = $row('buildings', ['factory_id' => $factory->id, 'name' => 'Main Building', 'code' => 'B1']);
        $floor = $row('floors', ['building_id' => $building, 'name' => 'Cutting Floor', 'code' => 'F1']);
        $dept = $row('departments', ['factory_id' => $factory->id, 'name' => 'Cutting', 'code' => 'CUT']);
        $section = $row('sections', ['department_id' => $dept, 'floor_id' => $floor, 'name' => 'Cutting', 'code' => 'S1']);
        $this->place = ['floor_id' => $floor, 'section_id' => $section, 'line_id' => $row('production_lines', ['department_id' => $dept, 'section_id' => $section, 'floor_id' => $floor, 'name' => 'Cutting Table 1', 'code' => 'CT1'])];
    }

    public function test_material_goes_out_comes_back_and_the_chain_and_reports_follow_it(): void
    {
        [$pr, $fabricLine] = $this->approved();
        $this->employee('50001001', 'Mohammad Sayem');
        $grn = $this->as()->postJson('/api/v1/store-material/grns/against-pr', ['pr_no' => $pr, 'lines' => [['requisition_line_id' => $fabricLine, 'qty' => 40]]])->assertCreated()->json('data.grn_no');
        $stock = collect($this->as()->getJson('/api/v1/store-material/issue-options')->assertOk()->json('data.stocks'))->sole();
        $this->assertEquals(['Cotton Jersey (White)', 40, 'KG'], [$stock['label'], $stock['available'], $stock['uom']]);

        // Material Out: everything required, never past the stock, handed to a real employee.
        $out = '/api/v1/store-material/material-outs';
        $blank = $this->as()->postJson($out, [])->assertUnprocessable()->json('errors');
        $this->assertSame(['Material is required.', 'Purpose is required.', 'Out Qty is required.', 'Floor is required.', 'Section is required.', 'Line is required.', 'Receiver is required.'],
            [$blank['stock_id'][0], $blank['purpose'][0], $blank['qty'][0], $blank['floor_id'][0], $blank['section_id'][0], $blank['line_id'][0], $blank['receiver'][0]]);
        $form = ['stock_id' => $stock['value'], 'purpose' => 'CUTTING', 'receiver' => '50001001'] + $this->place;
        $this->as()->postJson($out, ['qty' => 50] + $form)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'No more than the 40 KG in stock can go out.');
        $this->as()->postJson($out, ['qty' => 5, 'receiver' => '1001'] + $form)->assertUnprocessable()->assertJsonPath('errors.receiver.0', 'No employee with ID or card no. 1001 was found.');
        $mo = $this->as()->postJson($out, ['qty' => 30] + $form)->assertCreated()->json('data');
        $this->assertSame(['MO-'.now()->format('Y').'-00001', 'Cutting Floor', 'Cutting Table 1', '50001001 (Mohammad Sayem)'], [$mo['mo_no'], $mo['floor'], $mo['line'], $mo['receiver']]);
        $this->assertEquals(10, $mo['stocks'][0]['available']);

        // The GRN is issued now: it can no longer be reversed.
        $this->as()->postJson("/api/v1/store-material/grns/{$grn}/reverse", ['reason' => 'Late'])->assertUnprocessable()->assertJsonPath('errors.grn.0', "{$grn} cannot be reversed — some of it has already been issued.");

        // Material Return: no more than went out.
        $ret = '/api/v1/store-material/material-returns';
        $this->as()->getJson('/api/v1/store-material/material-outs/MO-2026-99999')->assertUnprocessable()->assertJsonPath('errors.mo_no.0', 'No Material Out MO-2026-99999 was found.');
        $this->assertEquals([30, 0, 30], array_values(array_intersect_key($this->as()->getJson("/api/v1/store-material/material-outs/{$mo['mo_no']}")->json('data'), array_flip(['qty', 'returned', 'eligible']))));
        $this->as()->postJson($ret, ['mo_no' => $mo['mo_no'], 'qty' => 31, 'reason' => 'EXCESS', 'line_name' => 'Cutting Table 1'])->assertUnprocessable()->assertJsonPath('errors.qty.0', 'No more than the eligible 30 KG can come back.');
        $back = $this->as()->postJson($ret, ['mo_no' => $mo['mo_no'], 'qty' => 6, 'reason' => 'EXCESS', 'line_name' => 'Cutting Table 1', 'returned_by' => '50001001'])->assertCreated()->json('data');
        $this->assertEquals(['MR-'.now()->format('Y').'-00001', 6, 24], [$back['mr_no'], $back['material_out']['returned'], $back['material_out']['eligible']]);

        // Stock Adjustment: the count reaches the books when the Store Manager approves it.
        $this->as()->postJson('/api/v1/store-material/adjustments', ['stock_id' => $stock['value'], 'physical_qty' => 16, 'reason' => 'COUNT_DIFFERENCE'])->assertUnprocessable()->assertJsonPath('errors.physical_qty.0', 'The physical count matches the system — there is nothing to adjust.');
        $sa = $this->as()->postJson('/api/v1/store-material/adjustments', ['stock_id' => $stock['value'], 'physical_qty' => 15, 'reason' => 'DAMAGED', 'remarks' => 'One kilo wet'])->assertCreated()->json('data.adjustments.0');
        $this->assertEquals(['PENDING', 16, 15, -1], [$sa['status'], $sa['system'], $sa['physical'], $sa['difference']]);
        $this->assertEquals(16, $this->as()->getJson('/api/v1/store-material/dashboard')->json('data.tiles.stock'));
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($keeper)->postJson("/api/v1/store-material/adjustments/{$sa['sa_no']}/decide", ['decision' => 'APPROVED'])->assertForbidden();
        $this->as()->postJson("/api/v1/store-material/adjustments/{$sa['sa_no']}/decide", ['decision' => 'APPROVED'])->assertOk()->assertJsonPath('data.adjustments.0.status', 'APPROVED');
        $this->as()->postJson("/api/v1/store-material/adjustments/{$sa['sa_no']}/decide", ['decision' => 'REJECTED'])->assertUnprocessable();
        $this->assertEquals(15, $this->as()->getJson('/api/v1/store-material/dashboard')->json('data.tiles.stock'));

        // Traceability, from the PR, the GRN or the Material Out.
        foreach ([['PR', $pr], ['GRN', $grn], ['MO', $mo['mo_no']]] as [$by, $q]) {
            $trace = $this->as()->getJson("/api/v1/store-material/traceability?by={$by}&q={$q}")->assertOk()->json('data');
            $chain = $trace['chains'][0];
            $this->assertEquals([$pr, [$grn], 40, 30, 6, 15, 'Cutting Floor', '50001001 (Mohammad Sayem)'], [$trace['pr']['pr_no'], $chain['grns'], $chain['in_house'], $chain['out'], $chain['returned'], $chain['available'], $chain['outs'][0]['floor'], $chain['outs'][0]['receiver']]);
        }
        $this->as()->getJson('/api/v1/store-material/traceability?by=ROLL&q=R-1')->assertUnprocessable()->assertJsonPath('errors.q.0', 'No roll R-1 is recorded.');

        // Reports read the same books.
        $report = fn (string $key) => $this->as()->getJson("/api/v1/store-material/reports/{$key}")->assertOk()->json('data.rows');
        $this->assertEquals([['Cotton Jersey (White)', 'KG', 100, 40, 30, 6, 15]], array_map(fn ($r) => array_values($r), $report('stock-summary')));
        $this->assertEquals([[$grn, $pr, 100, 40, 60, 'CONFIRMED']], array_map(fn ($r) => [$r['grn_no'], $r['pr_no'], $r['pr_qty'], $r['received'], $r['pending'], $r['status']], $report('grn')));
        $this->assertEquals([[$mo['mo_no'], 'Cutting', 30, 6]], array_map(fn ($r) => [$r['mo_no'], $r['purpose'], $r['out'], $r['returned']], $report('material-out')));
        $this->assertEquals([[$back['mr_no'], 'Excess Material', 6, '50001001 (Mohammad Sayem)']], array_map(fn ($r) => [$r['mr_no'], $r['reason'], $r['returned'], $r['returned_by']], $report('material-return')));
        $this->assertEquals([['GRN', 40, 0, 40], ['Material Out', 0, 0, 10], ['Material Return', 0, 0, 16], ['Adjustment', 0, -1, 15]], array_map(fn ($r) => [$r['type'], $r['in'], $r['adj'], $r['balance']], $report('stock-ledger')));
        $this->assertEquals([[$pr, $grn, 40, 30, 6, 15]], array_map(fn ($r) => [$r['pr_no'], $r['grn_no'], $r['in_house'], $r['out'], $r['returned'], $r['current']], $report('traceability')));
        $this->assertSame([], $report('fabric-roll'));
        $this->assertSame([], $report('low-stock'));
        $item = $this->as()->getJson('/api/v1/store-material/issue-options')->json('data.items.0.value');
        $this->as()->putJson('/api/v1/store-material/min-levels', ['item_id' => $item, 'min_qty' => 50])->assertOk()->assertJsonPath('data.min_levels.0.min_qty', 50);
        $this->assertEquals([['Cotton Jersey', 50, 15, 35, 'LOW']], array_map(fn ($r) => [$r['material'], $r['minimum'], $r['available'], $r['shortfall'], $r['status']], $report('low-stock')));
        $this->assertSame([], $this->as()->getJson('/api/v1/store-material/reports/grn?from=2099-01-01')->json('data.rows'));
        $this->as()->get('/api/v1/store-material/reports/stock-summary/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->as()->getJson('/api/v1/store-material/reports/nope')->assertNotFound();
        // Export PDF: a real file from the server, the return report with its total.
        $pdf = $this->as()->get('/api/v1/store-material/reports/material-return/pdf?buyer=')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('attachment; filename="store-material-return-', (string) $pdf->headers->get('Content-Disposition'));
        $this->as()->get('/api/v1/store-material/reports/nope/pdf')->assertNotFound();
    }

    /** @return array{string, string} an approved PR of 100 KG of Cotton Jersey, and its line */
    private function approved(): array
    {
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => 'SUP-002', 'name' => 'Poly Plan Ltd', 'supplier_type' => 'LOCAL_NON_EPZ'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => 'Poly Desk', 'email' => 'desk@polyplan.test', 'password' => 'Quote-Ready-2026!'])->assertCreated();
        $poly = User::query()->where('email', 'desk@polyplan.test')->firstOrFail();
        $fabric = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'colour' => 'White', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $data = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => [['kind' => 'FABRIC', 'item_id' => $fabric, 'req_qty' => 100, 'uom' => 'KG', 'req_date' => '2026-11-20']]])->assertCreated()->json('data');
        $line = $data['lines'][0]['id'];
        $qt = $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$line}/quote", ['quoted_qty' => 100, 'unit_price' => 2.5, 'currency' => 'USD', 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15'])->assertCreated()->json('data.qt_no');
        $id = Quotation::query()->where('qt_no', $qt)->value('id');
        $this->as()->postJson("/api/v1/material-procurement/price-review/quotations/{$id}/approve", ['handover_to' => $merch->id])->assertOk();

        return [$data['pr_no'], $line];
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
