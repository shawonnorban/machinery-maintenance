<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Production (AnnotechERP 15/04): a bulk production order
 * (Req Qty ± Adjusted); programmes released against it, never past what it
 * owes; knit cards cut off a programme, never past what it has left; rolls
 * weighed against a card and numbered per card; the Roll List's QC and grey
 * store moves; a four-point inspection grading a roll and its decision
 * written onto the roll; and the day's output against the machine's plan.
 */
class KnittingProductionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $order;

    private Floor $floor;

    private Factory $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->unit = Factory::query()->first();
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knit', 'code' => 'B1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Knit Floor', 'code' => 'KF-1']);
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer->id,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
    }

    public function test_order_programmes_cards_rolls_inspection_and_output(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $options = $this->as()->getJson('/api/v1/knitting/prod/options')->assertOk()->json('data');
        $this->assertContains('FP02', array_column($options['categories'], 'value'));
        $this->assertSame(['770MR · LPP-2026-000060'], array_column($options['orders'], 'label'));

        // The order: Fab. and Req Qty are required; Req Qty ± Adjusted is what the floor owes.
        $po = ['order_kind' => 'BULK', 'order_id' => $this->order, 'category_code' => 'FP02', 'ship_month' => '2026-12', 'fabric_desc' => 'S/J 30/1 Combed 160 GSM',
            'colour_name' => 'Black', 'finish_dia' => '72', 'gsm' => 160, 'req_qty' => 850, 'adjusted_qty' => -20, 'yarn_stock_qty' => 120, 'status' => 'RUNNING'];
        $this->as()->postJson('/api/v1/knitting/prod/orders', ['fabric_desc' => ''] + $po)->assertUnprocessable()->assertJsonPath('errors.fabric_desc.0', 'Fab. is required.');
        $made = $this->as()->postJson('/api/v1/knitting/prod/orders', $po)->assertCreated()->json('data');
        $no = $made['po_no'];
        $this->assertEquals(["FPO-{$ym}0001", 830.0, 830.0, 'Bulk Booking', 'BULK_NEW'], [$no, $made['figures']['req_qty'], $made['figures']['kp_balance'], $made['figures']['category'], $made['figures']['order_type']]);
        $this->assertSame([], $this->as()->getJson('/api/v1/knitting/prod/orders?kind=YD')->json('data'));

        // Programmes: never more than the order still owes.
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/programs", ['program_date' => $today, 'qty' => 400, 'floor_id' => $this->floor->id])->assertCreated();
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/programs", ['program_date' => $today, 'qty' => 500])->assertUnprocessable()
            ->assertJsonPath('errors.qty.0', 'A programme cannot release more than the order still owes (430 Kg).');
        $kps = $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/programs", ['program_date' => $today, 'qty' => 300])->assertCreated()->json('data.rows');
        $this->assertEquals([["KP-{$ym}0001", 400.0, 'OPEN'], ["KP-{$ym}0002", 300.0, 'OPEN']], array_map(fn ($r) => [$r['kp_no'], $r['qty'], $r['status']], $kps));
        $this->as()->putJson("/api/v1/knitting/prod/orders/{$no}", ['req_qty' => 600] + $po)->assertUnprocessable()
            ->assertJsonPath('errors.req_qty.0', 'The order cannot owe less than the 700 Kg already released in programmes.');

        // Cards: never more than the programme has left; the programme runs once one is cut.
        $card = ['card_date' => $today, 'kp_no' => "KP-{$ym}0001", 'assigned_qty' => 150, 'machine_dia' => '30', 'gauge' => '24', 'yarn_details' => '30/1 Combed Cotton · lot A-2261'];
        $cards = $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/cards", $card)->assertCreated()->json('data');
        $this->assertEquals(["KC-{$ym}0001", 'Black', '72'], [$cards['rows'][0]['kc_no'], $cards['rows'][0]['colour_name'], $cards['rows'][0]['finish_dia']]);
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/cards", ['assigned_qty' => 300] + $card)->assertUnprocessable()
            ->assertJsonPath('errors.assigned_qty.0', 'A card cannot be cut for more than its programme has left (250 Kg).');
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/cards", ['assigned_qty' => 250] + $card)->assertCreated();
        $kc = "KC-{$ym}0001";
        $this->as()->deleteJson("/api/v1/knitting/prod/programs/KP-{$ym}0001")->assertUnprocessable()->assertJsonValidationErrors('kp_no');
        $edit = fn ($kp, $qty, $status) => ['kp_no' => $kp, 'program_date' => $today, 'qty' => $qty, 'status' => $status, 'remarks' => null];
        $this->as()->putJson("/api/v1/knitting/prod/orders/{$no}/programs", ['rows' => [$edit("KP-{$ym}0001", 300, 'RUNNING')]])->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.qty' => "KP-{$ym}0001 cannot drop below the 400 Kg already carded."]);
        $saved = $this->as()->putJson("/api/v1/knitting/prod/orders/{$no}/programs", ['rows' => [$edit("KP-{$ym}0002", 300, 'CANCELLED')]])->assertOk()->json('data');
        $this->assertEquals([400.0, 430.0, 400.0, 0.0, 'RUNNING'], [$saved['order']['kp_made'], $saved['order']['kp_balance'], $saved['order']['kc_done'], $saved['order']['kc_balance'], $saved['rows'][0]['status']]);

        // On hold, nothing more is released.
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/hold")->assertOk()->assertJsonPath('data.status', 'HOLD');
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/programs", ['program_date' => $today, 'qty' => 10])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$no}/resume")->assertOk()->assertJsonPath('data.status', 'RUNNING');

        // A machine with a capacity plan, and a shift.
        $machine = $this->machine();
        $this->as()->postJson('/api/v1/erp/knit-capacity-plans', ['machine_id' => $machine, 'effective_from' => '2026-01-01', 'plan_hours' => 22, 'running_days' => 26, 'efficiency' => 85, 'capacity_per_hour' => 8.5])->assertCreated();
        $shift = FactoryCalendarSetup::shift($this->delta->id, 'DAY', 'Day shift', '08:00', '20:00');

        // Rolls on a sheet: numbered per card, deducted never more than the weight.
        $roll = ['roll_date' => $today, 'shift_id' => $shift, 'machine_id' => $machine];
        $sheet = ['kc_no' => $kc, 'rolls' => [['weight' => 22.5] + $roll, ['weight' => 21.8, 'deducted' => 0.3] + $roll]];
        $this->as()->postJson('/api/v1/knitting/prod/roll-sheets', ['rolls' => [['weight' => 2, 'deducted' => 3] + $roll]] + $sheet)->assertUnprocessable()
            ->assertJsonValidationErrors(['rolls.0.deducted' => 'Roll 1 deducts more than it weighs.']);
        $rs = $this->as()->postJson('/api/v1/knitting/prod/roll-sheets', $sheet)->assertCreated()->json('data');
        $this->assertEquals(["RS-{$ym}0001", ["{$kc}/1", "{$kc}/2"], 44.0, 106.0], [$rs['sheet_no'], array_column($rs['rolls'], 'roll_code'), $rs['card']['rolled'], $rs['card']['left']]);
        $this->assertSame(['RUNNING', $machine], [DB::table('knit_cards')->where('kc_no', $kc)->value('status'), DB::table('knit_cards')->where('kc_no', $kc)->value('machine_id')]);
        // Corrected in place; a number already taken on the card is refused; a blank one gets the next.
        $this->as()->putJson("/api/v1/knitting/prod/roll-sheets/{$rs['sheet_no']}", ['rolls' => [['roll_no' => 1, 'weight' => 22.5] + $roll, ['roll_no' => 2, 'weight' => 22.0, 'deducted' => 0.3] + $roll]] + $sheet)->assertOk();
        $this->as()->postJson('/api/v1/knitting/prod/roll-sheets', ['rolls' => [['roll_no' => 2, 'weight' => 20] + $roll]] + $sheet)->assertUnprocessable()
            ->assertJsonValidationErrors(['rolls.0.roll_no' => "{$kc}/2 is already on another roll sheet."]);
        $third = $this->as()->postJson('/api/v1/knitting/prod/roll-sheets', ['rolls' => [['weight' => 20.4] + $roll]] + $sheet)->assertCreated()->json('data.rolls.0.roll_code');
        $this->assertSame("{$kc}/3", $third);

        // The Roll List's moves: to the grey store once; a rejected roll does not go.
        $this->assertCount(3, $this->as()->getJson("/api/v1/knitting/prod/rolls?card={$kc}")->assertOk()->json('data'));
        $this->as()->postJson('/api/v1/knitting/prod/rolls/grey', ['roll' => "{$kc}/1"])->assertOk()->assertJsonPath('data.movement', 'GREY_STORE');
        $this->as()->postJson('/api/v1/knitting/prod/rolls/grey', ['roll' => "{$kc}/1"])->assertUnprocessable()->assertJsonPath('errors.roll.0', "{$kc}/1 is already in Grey Store.");
        $this->as()->postJson('/api/v1/knitting/prod/rolls/reject', ['roll' => "{$kc}/3"])->assertOk()->assertJsonPath('data.quality', 'REJECTED');
        $this->as()->postJson('/api/v1/knitting/prod/rolls/grey', ['roll' => "{$kc}/3"])->assertUnprocessable()->assertJsonPath('errors.roll.0', "{$kc}/3 is rejected — it does not go to the grey store.");
        $this->assertEquals(64.6, $this->as()->getJson('/api/v1/knitting/prod/orders?kind=BULK')->json('data.0.rolled'));
        // A card with rolls on it can be neither deleted nor cancelled.
        $this->as()->deleteJson("/api/v1/knitting/prod/cards/{$kc}")->assertUnprocessable()->assertJsonValidationErrors('kc_no');
        $this->as()->putJson("/api/v1/knitting/prod/orders/{$no}/cards", ['rows' => [['kc_no' => $kc, 'card_date' => $today, 'assigned_qty' => 150, 'status' => 'CANCELLED']]])->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.status' => "{$kc} has rolls on it — the rolls are fabric somebody is holding."]);

        // Inspection: Slub ×3 (1 pt) + Hole ×1 (4 pt) = 7 points on 22.3 kg → 31.39, grade B.
        $qc = ['roll_code' => "{$kc}/1", 'inspected_on' => $today, 'shift_id' => $shift, 'deduct_weight' => 0.2, 'lines' => [['defect' => 'SLUB', 'count' => 3], ['defect' => 'HOLE', 'count' => 1]]];
        $this->as()->postJson('/api/v1/knitting/prod/inspections', ['inspected_on' => null] + $qc)->assertUnprocessable()->assertJsonPath('errors.inspected_on.0', 'Inspection Date is required.');
        $first = $this->as()->postJson('/api/v1/knitting/prod/inspections', $qc)->assertCreated()->json('data');
        $this->assertEquals(["QCI-{$ym}0001", 7, 31.39, 'B', 'DRAFT', 22.3], [$first['qci_no'], $first['total_points'], $first['grade_point'], $first['grade'], $first['status'], $first['register']['net']]);
        $this->assertEquals([[1, 3, 'YARN'], [4, 1, 'STRUCTURAL']], array_map(fn ($l) => [$l['points_each'], $l['count'], $l['group']], $first['lines']));
        $this->as()->postJson('/api/v1/knitting/prod/inspections', $qc)->assertUnprocessable()->assertJsonPath('errors.roll_code.0', "{$kc}/1 already has its sheet, QCI-{$ym}0001.");
        // Hole ×3 on 21.7 kg → 55.30, grade C; set by hand, A stands.
        $second = $this->as()->postJson('/api/v1/knitting/prod/inspections', ['roll_code' => "{$kc}/2", 'lines' => [['defect' => 'HOLE', 'count' => 3]], 'deduct_weight' => 0] + $qc)->json('data');
        $this->assertEquals([12, 55.3, 'C'], [$second['total_points'], $second['grade_point'], $second['grade']]);
        $override = $this->as()->putJson("/api/v1/knitting/prod/inspections/{$second['qci_no']}", ['roll_code' => "{$kc}/2", 'grade_override' => 'A', 'lines' => [['defect' => 'HOLE', 'count' => 3]], 'deduct_weight' => 0] + $qc)->assertOk()->json('data');
        $this->assertEquals([55.3, 'A'], [$override['grade_point'], $override['grade']]);
        // A clean roll is a grade A sheet.
        $clean = $this->as()->postJson('/api/v1/knitting/prod/inspections', ['roll_code' => "{$kc}/3", 'lines' => [], 'deduct_weight' => 0] + $qc)->assertCreated()->json('data');
        $this->assertEquals([0, 'A'], [$clean['total_points'], $clean['grade']]);
        // The decision is the roll's.
        $this->as()->postJson("/api/v1/knitting/prod/inspections/{$first['qci_no']}/pass")->assertOk()->assertJsonPath('data.status', 'PASSED');
        $this->assertSame('PASSED', DB::table('knit_rolls')->where('roll_code', "{$kc}/1")->value('quality'));
        $this->as()->putJson("/api/v1/knitting/prod/inspections/{$first['qci_no']}", $qc)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/inspections/{$second['qci_no']}/hold")->assertOk();
        $this->as()->postJson("/api/v1/knitting/prod/inspections/{$second['qci_no']}/reject")->assertOk();
        $this->assertSame('REJECTED', DB::table('knit_rolls')->where('roll_code', "{$kc}/2")->value('quality'));
        $this->assertEquals([['QCI-'.$ym.'0002', 'REJECTED']], array_map(fn ($r) => [$r['qci_no'], $r['status']], $this->as()->getJson('/api/v1/knitting/prod/inspections?status=REJECTED')->json('data')));
        // An inspected roll stays on its sheet.
        $this->as()->putJson("/api/v1/knitting/prod/roll-sheets/{$rs['sheet_no']}", ['rolls' => [['roll_no' => 1, 'weight' => 22.5] + $roll]] + $sheet)->assertUnprocessable()->assertJsonValidationErrors('rolls');

        // The day's output: the card's day across shifts, the shift's own, the plan's 8.5 × 22, what the card still has.
        $out = $this->as()->getJson("/api/v1/knitting/prod/output?from={$today}&to={$today}")->assertOk()->json('data');
        $this->assertEquals([[64.6, 64.6, 187.0, 85.4, 'MC-01', 'Bulk']], array_map(fn ($r) => [$r['all_shift'], $r['total'], $r['target'], $r['balance'], $r['machine'], $r['kind'] === 'BULK' ? 'Bulk' : $r['kind']], $out));

        // A line chief has no part in production.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/knitting/prod/orders?kind=BULK')->assertForbidden();
    }

    private function machine(): string
    {
        $ref = fn (string $list, string $code) => collect($this->as()->getJson("/api/v1/knitting/ref-lists/{$list}")->json('data'))->firstWhere('code', $code)['id'];
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id')
            ?? collect($this->as()->getJson('/api/v1/erp/origin-countries')->json('data'))->firstWhere('name', 'Germany')['id'];

        return $this->as()->postJson('/api/v1/erp/knit-machines', [
            'machine_no' => 'MC-01', 'machine_type_id' => $ref('MC_TYPE', 'SJ'), 'brand_id' => $ref('BRAND', 'MAYER'), 'origin_country_id' => $country,
            'dia_id' => $ref('DIA', 'D30'), 'gauge_id' => $ref('GG', 'GG24'), 'supported_gauge_ids' => $ref('GG', 'GG24'),
            'capacity_kg' => 180, 'feeders' => 96, 'rpm' => 24, 'efficiency' => 85, 'floor_id' => $this->floor->id, 'mfg_year' => 2019,
        ])->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
