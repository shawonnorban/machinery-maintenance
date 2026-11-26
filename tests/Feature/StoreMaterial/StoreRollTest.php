<?php

declare(strict_types=1);

namespace Tests\Feature\StoreMaterial;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\StoreMaterial\Models\RollMove;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Store & Material's rolls (AnnotechERP 09): a fabric line may be received
 * roll by roll — number, dye lot, GSM, width — its rolls adding up to the
 * line and no number held twice; Material Out takes from rolls, never more
 * than a roll has; a return comes back on the roll it went out on; a GRN
 * whose rolls have gone out cannot be reversed; and the Fabric Warehouse's
 * Roll / Dye Lot views, Trace by Roll and the Fabric Roll and Material Out
 * reports read the same rolls.
 */
class StoreRollTest extends TestCase
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

    public function test_a_fabric_line_is_received_roll_by_roll_and_the_warehouse_shows_each_roll_and_dye_lot(): void
    {
        [$pr, $line, $fabric] = $this->approved();
        $url = '/api/v1/store-material/grns/against-pr';
        $grn = fn (array $rolls, float $qty = 40) => $this->as()->postJson($url, ['pr_no' => $pr, 'lines' => [['requisition_line_id' => $line, 'qty' => $qty, 'rolls' => $rolls]]]);
        $roll = fn (string $no, float $qty, ?string $lot = null) => ['roll_no' => $no, 'dye_lot' => $lot, 'gsm' => '180', 'width' => '72"', 'qty' => $qty];

        // The rolls must add up to the line, each with its number and quantity, no number twice.
        $this->assertSame('The rolls add up to 30 KG; the line receives 40 KG.', $grn([$roll('R-01', 30)])->assertUnprocessable()->json('errors')["lines.{$line}.rolls"][0]);
        $this->assertSame('Roll 2: enter the roll number.', $grn([$roll('R-01', 30), $roll(' ', 10)])->assertUnprocessable()->json('errors')["lines.{$line}.rolls"][0]);
        $this->assertSame('Roll R-02: enter its quantity.', $grn([$roll('R-01', 40), ['roll_no' => 'R-02', 'qty' => 0]])->assertUnprocessable()->json('errors')["lines.{$line}.rolls"][0]);
        $this->assertSame('Roll R-01 is entered twice.', $grn([$roll('R-01', 30), $roll('r-01', 10)])->assertUnprocessable()->json('errors')["lines.{$line}.rolls"][0]);

        $first = $grn([$roll('r-01', 25, 'L1'), $roll('R-02', 15, 'L2')])->assertCreated()->json('data');
        $this->assertSame([['roll_no' => 'R-01', 'dye_lot' => 'L1', 'gsm' => '180', 'width' => '72"'], ['roll_no' => 'R-02', 'dye_lot' => 'L2', 'gsm' => '180', 'width' => '72"']],
            array_map(fn (array $r) => array_diff_key($r, ['qty' => 0]), $first['lines'][0]['rolls']));
        $this->assertSame("Roll R-01 is already in stock on {$first['grn_no']}.", $grn([$roll('R-01', 10)], 10)->assertUnprocessable()->json('errors')["lines.{$line}.rolls"][0]);

        // A line received without rolls is still received as a whole; Direct Receive takes rolls the same way.
        $this->as()->postJson('/api/v1/store-material/grns/direct', ['lines' => [['item_id' => $fabric, 'qty' => 10, 'rolls' => [$roll('R-03', 6)]]]])->assertUnprocessable()
            ->assertJsonPath('errors', ['lines.0.rolls' => ['The rolls add up to 6 KG; the line receives 10 KG.']]);
        $this->as()->postJson('/api/v1/store-material/grns/direct', ['lines' => [['item_id' => $fabric, 'qty' => 10, 'rolls' => [$roll('R-03', 6), $roll('R-04', 4, 'L1')]]]])->assertCreated();

        // Roll Wise, Dye Lot Wise, Color Wise and the stock list all read the rolls.
        $view = fn (string $v) => $this->as()->getJson("/api/v1/store-material/fabric-warehouse?view={$v}")->assertOk()->json('data.rows');
        $this->assertEquals([['R-01', 'L1', 25, 25], ['R-02', 'L2', 15, 15], ['R-03', null, 6, 6], ['R-04', 'L1', 4, 4]],
            array_map(fn (array $r) => [$r['roll_no'], $r['dye_lot'], $r['recv'], $r['available']], $view('roll')));
        $this->assertEquals([[null, 1, 6], ['L1', 2, 29], ['L2', 1, 15]], array_map(fn (array $r) => [$r['dye_lot'], $r['rolls'], $r['available']], $view('lot')));
        $this->assertEquals(4, $view('color')[0]['rolls']);
        $this->assertSame('R-01, R-02, R-03 +1', $view('stock')[0]['roll_no']);
    }

    public function test_rolls_go_out_come_back_and_are_traced_and_reported(): void
    {
        [$pr, $line, $fabric] = $this->approved();
        $this->employee('50001001', 'Mohammad Sayem');
        $grn = $this->as()->postJson('/api/v1/store-material/grns/against-pr', ['pr_no' => $pr, 'lines' => [['requisition_line_id' => $line, 'qty' => 40, 'rolls' => [
            ['roll_no' => 'R-01', 'dye_lot' => 'L1', 'qty' => 25], ['roll_no' => 'R-02', 'dye_lot' => 'L2', 'qty' => 15],
        ]]]])->assertCreated()->json('data.grn_no');
        $stock = collect($this->as()->getJson('/api/v1/store-material/issue-options')->assertOk()->json('data.stocks'))->sole();
        $rolls = collect($stock['rolls'])->pluck('id', 'roll_no');
        $this->assertEquals([['R-01', 25], ['R-02', 15]], array_map(fn (array $r) => [$r['roll_no'], $r['available']], $stock['rolls']));

        // Material Out by roll: never more than a roll has, and the rolls make the quantity.
        $out = '/api/v1/store-material/material-outs';
        $form = ['stock_id' => $stock['value'], 'purpose' => 'CUTTING', 'receiver' => '50001001'] + $this->place;
        $this->as()->postJson($out, ['qty' => 20, 'rolls' => [['roll_id' => $rolls['R-02'], 'qty' => 20]]] + $form)->assertUnprocessable()->assertJsonPath('errors.rolls.0', 'Roll R-02 has only 15 KG.');
        $this->as()->postJson($out, ['qty' => 20, 'rolls' => [['roll_id' => $rolls['R-01'], 'qty' => 10], ['roll_id' => $rolls['R-02'], 'qty' => 5]]] + $form)->assertUnprocessable()
            ->assertJsonPath('errors.rolls.0', 'The rolls add up to 15 KG; 20 KG is going out.');
        $mo = $this->as()->postJson($out, ['qty' => 15, 'rolls' => [['roll_id' => $rolls['R-01'], 'qty' => 10], ['roll_id' => $rolls['R-02'], 'qty' => 5], ['roll_id' => $rolls['R-02'], 'qty' => 0]]] + $form)->assertCreated()->json('data');
        $this->assertEquals([['R-01', 10, 10], ['R-02', 5, 5]], array_map(fn (array $r) => [$r['roll_no'], $r['out'], $r['eligible']], $mo['rolls']));
        $this->assertEquals([15, 10], array_column($mo['stocks'][0]['rolls'], 'available'));

        // Material Return: on one of the rolls it went out on, up to what that roll took.
        $ret = '/api/v1/store-material/material-returns';
        $back = ['mo_no' => $mo['mo_no'], 'reason' => 'EXCESS', 'line_name' => 'Cutting Table 1'];
        $this->as()->postJson($ret, ['qty' => 2] + $back)->assertUnprocessable()->assertJsonPath('errors.roll_id.0', 'Pick the roll it comes back on.');
        $this->as()->postJson($ret, ['qty' => 6, 'roll_id' => $rolls['R-02']] + $back)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Roll R-02 may take back no more than 5 KG.');
        $this->as()->postJson($ret, ['qty' => 4, 'roll_id' => $rolls['R-02']] + $back)->assertCreated()->assertJsonPath('data.material_out.rolls.1.eligible', 1);
        $view = $this->as()->getJson('/api/v1/store-material/fabric-warehouse?view=roll')->assertOk()->json('data.rows');
        $this->assertEquals([['R-01', 25, 10, 15], ['R-02', 15, 5, 14]], array_map(fn (array $r) => [$r['roll_no'], $r['recv'], $r['out'], $r['available']], $view));

        // A GRN whose roll has gone out cannot be reversed, though the bucket holds enough.
        $second = $this->as()->postJson('/api/v1/store-material/grns/direct', ['lines' => [['item_id' => $fabric, 'qty' => 10, 'rolls' => [['roll_no' => 'R-09', 'qty' => 10]]]]])->assertCreated()->json('data.grn_no');
        $r09 = collect($this->as()->getJson('/api/v1/store-material/issue-options')->json('data.stocks.0.rolls'))->firstWhere('roll_no', 'R-09')['id'];
        $this->as()->postJson($out, ['qty' => 2, 'rolls' => [['roll_id' => $r09, 'qty' => 2]]] + $form)->assertCreated();
        $this->as()->postJson("/api/v1/store-material/grns/{$second}/reverse", ['reason' => 'Wrong'])->assertUnprocessable()
            ->assertJsonPath('errors.grn.0', "{$second} cannot be reversed — some of it has already been issued.");

        // A GRN whose rolls are untouched reverses, and its roll number may be received again.
        $third = $this->as()->postJson('/api/v1/store-material/grns/direct', ['lines' => [['item_id' => $fabric, 'qty' => 5, 'rolls' => [['roll_no' => 'R-20', 'qty' => 5]]]]])->assertCreated()->json('data.grn_no');
        $this->as()->postJson("/api/v1/store-material/grns/{$third}/reverse", ['reason' => 'Wrong supplier'])->assertOk();
        $this->assertNotContains('R-20', collect($this->as()->getJson('/api/v1/store-material/issue-options')->json('data.stocks.0.rolls'))->pluck('roll_no')->all());

        // Trace by Roll: that roll's own chain.
        $this->as()->getJson('/api/v1/store-material/traceability?by=ROLL&q=R-77')->assertUnprocessable()->assertJsonPath('errors.q.0', 'No roll R-77 is recorded.');
        $trace = $this->as()->getJson('/api/v1/store-material/traceability?by=ROLL&q=r-02')->assertOk()->json('data');
        $this->assertSame(['R-02', 'L2'], [$trace['roll']['roll_no'], $trace['roll']['dye_lot']]);
        $this->assertSame($pr, $trace['pr']['pr_no']);
        $chain = $trace['chains'][0];
        $this->assertEquals([[$grn], 15, [[$mo['mo_no'], 5]], 5, 4, 14], [$chain['grns'], $chain['in_house'], array_map(fn ($o) => [$o['mo_no'], $o['qty']], $chain['outs']), $chain['out'], $chain['returned'], $chain['available']]);

        // Fabric Roll and Material Out reports.
        $report = $this->as()->getJson('/api/v1/store-material/reports/fabric-roll')->assertOk()->json('data.rows');
        $this->assertEquals([['R-01', $grn, 'L1', 25, 10, 0, 15, 'PARTLY_OUT'], ['R-02', $grn, 'L2', 15, 5, 4, 14, 'PARTLY_OUT'], ['R-09', $second, null, 10, 2, 0, 8, 'PARTLY_OUT'], ['R-20', $third, null, 5, 0, 0, 0, 'REVERSED']],
            array_map(fn (array $r) => [$r['roll_no'], $r['grn_no'], $r['dye_lot'], $r['received'], $r['out'], $r['returned'], $r['available'], $r['status']], $report));
        $outs = collect($this->as()->getJson('/api/v1/store-material/reports/material-out')->assertOk()->json('data.rows'))->pluck('roll', 'mo_no');
        $this->assertSame('R-01, R-02', $outs[$mo['mo_no']]);

        // A roll's movements are the books: never edited.
        $this->expectException(RuntimeException::class);
        RollMove::query()->firstOrFail()->update(['in_qty' => 1]);
    }

    /** @return array{0: string, 1: string, 2: string} PR no, its fabric line, the fabric */
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

        return [$data['pr_no'], $line, $fabric];
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
