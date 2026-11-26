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
 * Knit Production → Production (AnnotechERP 15/04): the machine loading plan
 * — a live card booked on a machine-shift, Plan (Kg) from the machine's
 * capacity plan, one card per cell (planning again replaces), clearing hands
 * the shift back and the card returns to Pending — and an operator's station:
 * its machines ticked, each roll weighed at a machine landing on the card the
 * plan booked, on that card's sheet for the day, numbered on after the card's
 * last roll, and counted as Produced on the plan.
 */
class KnittingMachineLoadTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

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
    }

    public function test_loading_plan_and_station_rolls(): void
    {
        $today = now()->toDateString();
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer->id,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $po = $this->as()->postJson('/api/v1/knitting/prod/orders', ['order_kind' => 'BULK', 'order_id' => $order, 'category_code' => 'FP02', 'fabric_desc' => 'S/J 30/1', 'colour_name' => 'Black',
            'req_qty' => 500, 'status' => 'RUNNING'])->assertCreated()->json('data.po_no');
        $kp = $this->as()->postJson("/api/v1/knitting/prod/orders/{$po}/programs", ['program_date' => $today, 'qty' => 400])->assertCreated()->json('data.rows.0.kp_no');
        $card = ['card_date' => $today, 'kp_no' => $kp, 'assigned_qty' => 150];
        $cards = $this->as()->postJson("/api/v1/knitting/prod/orders/{$po}/cards", $card)->assertCreated()->json('data.rows');
        $this->as()->postJson("/api/v1/knitting/prod/orders/{$po}/cards", ['assigned_qty' => 100] + $card)->assertCreated();
        $kc1 = $cards[0]['kc_no'];
        $kc2 = DB::table('knit_cards')->where('kc_no', '!=', $kc1)->value('kc_no');
        $machine = $this->machine();
        // A shift that is running now, so the station knows which plan applies.
        $day = FactoryCalendarSetup::shift($this->delta->id, 'ALL', 'All day', '00:00', '23:59');
        $this->as()->postJson('/api/v1/erp/knit-capacity-plans', ['machine_id' => $machine, 'effective_from' => '2026-01-01', 'plan_hours' => 12, 'running_days' => 26, 'efficiency' => 85, 'capacity_per_hour' => 8])->assertCreated();

        // The grid: the machine, the window's days, both cards pending.
        $grid = $this->as()->getJson("/api/v1/knitting/prod/machine-loads/grid?from={$today}&range=1W")->assertOk()->json('data');
        $this->assertEquals([7, ['MC-01'], [$kc2, $kc1]], [count($grid['days']), array_column($grid['machines'], 'label'), array_column($grid['pending'], 'kc_no')]);

        // Planning a cell: the plan defaults to capacity × planned hours; one card per cell.
        $cell = ['plan_date' => $today, 'shift_id' => $day, 'machine_id' => $machine];
        $this->as()->postJson('/api/v1/knitting/prod/machine-loads', ['kc_no' => 'KC-NOPE'] + $cell)->assertUnprocessable()->assertJsonValidationErrors('kc_no');
        $planned = $this->as()->postJson('/api/v1/knitting/prod/machine-loads', ['kc_no' => $kc1] + $cell)->assertOk()->json('data');
        $this->assertEquals([$kc1, 96.0, [$kc2]], [$planned['kc_no'], $planned['plan_qty'], array_column($planned['pending'], 'kc_no')]);
        $replaced = $this->as()->postJson('/api/v1/knitting/prod/machine-loads', ['kc_no' => $kc2, 'plan_qty' => 60] + $cell)->assertOk()->json('data');
        $this->assertEquals([60.0, [$kc1]], [$replaced['plan_qty'], array_column($replaced['pending'], 'kc_no')]);
        $this->assertSame(1, DB::table('knit_machine_loads')->count());
        $this->as()->postJson('/api/v1/knitting/prod/machine-loads/clear', $cell)->assertOk();
        $this->assertSame(0, DB::table('knit_machine_loads')->count());
        $this->as()->postJson('/api/v1/knitting/prod/machine-loads', ['kc_no' => $kc1] + $cell)->assertOk();

        // A station: code, name, and the machines it shows as tabs.
        $st = ['code' => 'st-01', 'name' => 'Knit line A', 'active' => true];
        $this->as()->postJson('/api/v1/knitting/prod/stations', ['machine_ids' => []] + $st)->assertUnprocessable()
            ->assertJsonPath('errors.machine_ids.0', 'Tick at least one machine the operator stands at.');
        $made = $this->as()->postJson('/api/v1/knitting/prod/stations', $st)->assertCreated()->json('data');
        $this->assertEquals(['ST-01', []], [$made['code'], $made['machines']]);
        $this->as()->postJson('/api/v1/knitting/prod/stations', $st)->assertUnprocessable()->assertJsonPath('errors.code.0', 'That Station Code is already in use.');
        $mapped = $this->as()->putJson('/api/v1/knitting/prod/stations/ST-01', ['machine_ids' => [$machine]] + $st)->assertOk()->json('data');
        $this->assertEquals([$kc1, true, $day], [$mapped['machines'][0]['card']['kc_no'], $mapped['machines'][0]['planned'], $mapped['current_shift']]);
        // The station form leaves the machine list alone.
        $this->assertCount(1, $this->as()->putJson('/api/v1/knitting/prod/stations/ST-01', ['name' => 'Knit line A (day)'] + $st)->assertOk()->json('data.machines'));

        // Rolls at the machine: on the planned card, numbered on, one sheet for the day.
        $roll = ['machine_id' => $machine, 'kc_no' => $kc1, 'weight' => 22.4, 'deducted' => 0.2];
        $this->as()->postJson('/api/v1/knitting/prod/stations/ST-01/rolls', ['weight' => 1, 'deducted' => 2] + $roll)->assertUnprocessable()
            ->assertJsonPath('errors.deducted.0', 'The deduction is more than the roll weighs.');
        $other = $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => 'MC-02'] + $this->machineBody())->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/knitting/prod/stations/ST-01/rolls', ['machine_id' => $other] + $roll)->assertUnprocessable()
            ->assertJsonPath('errors.machine_id.0', 'That machine is not on station ST-01.');
        $first = $this->as()->postJson('/api/v1/knitting/prod/stations/ST-01/rolls', $roll)->assertCreated()->json('data');
        $second = $this->as()->postJson('/api/v1/knitting/prod/stations/ST-01/rolls', ['weight' => 21.6, 'deducted' => 0] + $roll)->assertCreated()->json('data');
        $this->assertEquals(["{$kc1}/1", "{$kc1}/2"], [$first['roll_code'], $second['roll_code']]);
        $this->assertEquals([["{$kc1}/2", "{$kc1}/1"], 21.6], [array_column($second['machines'][0]['rolls'], 'roll_code'), $second['machines'][0]['rolls'][0]['net']]);
        $this->assertSame(1, DB::table('knit_roll_sheets')->count());
        $this->assertEquals(['RUNNING', $machine, $day], [DB::table('knit_cards')->where('kc_no', $kc1)->value('status'), DB::table('knit_cards')->where('kc_no', $kc1)->value('machine_id'),
            DB::table('knit_rolls')->where('roll_no', 1)->value('shift_id')]);

        // The plan reads what the floor got.
        $rows = $this->as()->getJson('/api/v1/knitting/prod/machine-loads')->assertOk()->json('data');
        $this->assertEquals([[$kc1, 'MC-01', '30" x 24GG', 96.0, 43.8]], array_map(fn ($r) => [$r['kc_no'], $r['machine'], $r['dia_gauge'], $r['plan_qty'], $r['produced']], $rows));
        $this->assertEquals(43.8, $this->as()->getJson("/api/v1/knitting/prod/machine-loads/grid?from={$today}")->json("data.cells.{$today}|{$day}|{$machine}.produced"));
        // The manual roll list sees the station's rolls too.
        $this->assertCount(2, $this->as()->getJson("/api/v1/knitting/prod/rolls?card={$kc1}")->assertOk()->json('data'));
    }

    /** @return array<string, mixed> */
    private function machineBody(): array
    {
        $ref = fn (string $list, string $code) => collect($this->as()->getJson("/api/v1/knitting/ref-lists/{$list}")->json('data'))->firstWhere('code', $code)['id'];
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id')
            ?? collect($this->as()->getJson('/api/v1/erp/origin-countries')->json('data'))->firstWhere('name', 'Germany')['id'];

        return ['machine_type_id' => $ref('MC_TYPE', 'SJ'), 'brand_id' => $ref('BRAND', 'MAYER'), 'origin_country_id' => $country, 'dia_id' => $ref('DIA', 'D30'),
            'gauge_id' => $ref('GG', 'GG24'), 'supported_gauge_ids' => $ref('GG', 'GG24'), 'capacity_kg' => 180, 'feeders' => 96, 'rpm' => 24, 'efficiency' => 85,
            'floor_id' => $this->floor->id, 'mfg_year' => 2019];
    }

    private function machine(): string
    {
        return $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => 'MC-01'] + $this->machineBody())->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
