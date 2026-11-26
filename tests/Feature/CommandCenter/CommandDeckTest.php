<?php

declare(strict_types=1);

namespace Tests\Feature\CommandCenter;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Command Center → Chief Executive Command Deck (AnnotechERP 27/01): the
 * figures come from the modules' own records — commercial invoices, open
 * orders, the daily sewing plans, shipments, posted expense vouchers, the
 * audit trail, order alerts and approvals — converted to taka at the
 * document's rate or the currency master's. The rows are written straight
 * into the source tables (with the test session's foreign-key checks off),
 * so each number can be worked by hand.
 */
class CommandDeckTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        // Wednesday 11 November 2026; the factory's week began on Saturday the 7th.
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
    }

    public function test_an_empty_company_shows_no_data(): void
    {
        $d = $this->as()->getJson('/api/v1/command-center/deck')->assertOk()->json('data');
        $this->assertEquals([null, null, null, null], [$d['kpis']['revenue']['value'], $d['kpis']['order_book']['value'], $d['kpis']['efficiency']['value'], $d['kpis']['on_time']['value']]);
        $this->assertCount(12, $d['trend']);
        $this->assertSame(['2025-12', '2026-11'], [$d['trend'][0]['month'], $d['trend'][11]['month']]);
        $this->assertSame([[], [], [], []], [$d['buyers'], $d['output']['rows'], $d['lines'], $d['alerts']]);
        $this->assertSame('2026-11-07', $d['output']['from']);
    }

    public function test_the_figures_are_worked_from_the_modules(): void
    {
        DB::statement('SET session_replication_role = replica');
        $c = $this->delta->id;
        $id = fn () => strtolower((string) Str::ulid());
        $row = fn (string $table, array $values) => DB::table($table)->insert(['id' => $values['id'] ?? $id(), 'company_id' => $c, 'created_at' => now(), 'updated_at' => now()] + $values);
        $row('currencies', ['code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 120]);
        $hm = Buyer::query()->create(['company_id' => $c, 'code' => 'HM', 'name' => 'H&M Ltd', 'short_name' => 'H&M', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $zara = Buyer::query()->create(['company_id' => $c, 'code' => 'ITX', 'name' => 'Zara Ltd', 'short_name' => 'Zara', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;

        // Revenue: 10,000 USD at its own rate 118 this month; last month's 1–11 Oct 5,000 USD at the master's 120; a 12 Oct invoice is outside the comparison.
        $inv = fn (string $date, float $amount, ?float $rate) => $row('tx_commercial_invoices', ['lc_id' => $id(), 'invoice_prefix' => 'CI', 'auto_no' => 1, 'invoice_no' => 'CI-'.$date, 'invoice_date' => $date, 'currency' => 'USD', 'loc_exchange_rate' => $rate,
            'exp_no' => 'E', 'exp_date' => $date, 'ex_factory_date' => $date, 'handover_date' => $date, 'payment_term' => 'LC', 'port_of_loading' => 'CTG', 'port_of_discharge' => 'HAM', 'shipment_mode' => 'SEA', 'net_amount' => $amount]);
        $inv('2026-11-03', 10000, 118);
        $inv('2026-10-05', 5000, null);
        $inv('2026-10-12', 9000, null);

        // Order book: confirmed / in production, shipping from today on; a cancelled one and a past one do not count.
        $order = fn (string $buyer, string $status, string $ship, int $qty, float $price, ?float $rate = null) => tap($id(), fn ($oid) => $row('merch_orders', ['id' => $oid, 'order_no' => 'SO-'.substr($oid, -8), 'job_no' => 'J', 'style_no' => 'ST', 'buyer_id' => $buyer, 'status' => $status, 'ship_date' => $ship, 'total_qty' => $qty, 'unit_price' => $price, 'currency' => 'USD', 'exchange_rate' => $rate, 'projection_only' => false]));
        $o1 = $order($hm, 'CONFIRMED', '2027-01-15', 1000, 5.0);
        $order($zara, 'IN_PRODUCTION', '2027-02-01', 500, 4.0, 100);
        $order($hm, 'CANCELLED', '2027-01-20', 9999, 9.0);
        $o4 = $order($zara, 'CONFIRMED', '2026-11-01', 100, 3.0);
        $order($hm, 'CONFIRMED', '2027-09-30', 200, 2.0);

        // Sewing: line A on floor 1 — 300 pcs × 12 min over 25 × 8 h; line B — 400 pcs × 10 min over 20 × 10 h.
        $floor = $id();
        $row('floors', ['id' => $floor, 'building_id' => $id(), 'name' => 'Floor 1', 'code' => 'F1']);
        $lineA = $id();
        $lineB = $id();
        $row('planning_lines', ['id' => $lineA, 'floor_id' => $floor, 'code' => 'A', 'name' => 'Line A']);
        $row('planning_lines', ['id' => $lineB, 'floor_id' => $floor, 'code' => 'B', 'name' => 'Line B']);
        $plan = fn (string $line, string $date, int $manpower, float $hours, float $smv, float $eff, int $target, ?int $output) => $row('planning_daily_plans', ['plan_date' => $date, 'order_id' => $o1, 'master_plan_id' => $id(), 'planning_line_id' => $line,
            'manpower' => $manpower, 'machines' => $manpower, 'hours' => $hours, 'efficiency' => $eff, 'smv' => $smv, 'daily_capacity' => $target, 'master_target' => $target, 'daily_target' => $target, 'typed_output' => $output]);
        $plan($lineA, '2026-11-09', 25, 8, 12, 60, 350, 300);
        $plan($lineB, '2026-11-02', 20, 10, 10, 50, 500, 400);
        $plan($lineB, '2026-11-12', 20, 10, 10, 50, 500, null);

        // Shipments: one on time, one late (order o4's ship date 1 Nov); the shipped value lands in November.
        $ship = fn (string $order, string $date, int $qty, float $rate) => tap($id(), function ($sid) use ($row, $id, $order, $date, $qty, $rate): void {
            $row('ss_shipments', ['id' => $sid, 'shipment_no' => 'SH-'.substr($sid, -8), 'order_id' => $order, 'plan_date' => $date, 'shipment_date' => $date, 'shipment_mode' => 'SEA', 'stage' => 'CLOSED']);
            $row('ss_shipment_lines', ['shipment_id' => $sid, 'size_id' => $id(), 'qty' => $qty, 'shipped_qty' => $qty, 'rate' => $rate]);
        });
        $ship($o1, '2026-11-05', 200, 5.0);
        $ship($o4, '2026-11-06', 100, 3.0);

        // Cost: a posted expense voucher, a draft one and a revenue line that do not count.
        $expense = $id();
        $income = $id();
        $row('acc_accounts', ['id' => $expense, 'code' => '5100', 'name' => 'Wages', 'type' => 'EXPENSE']);
        $row('acc_accounts', ['id' => $income, 'code' => '4100', 'name' => 'Sales', 'type' => 'REVENUE']);
        $voucher = fn (string $status, string $account, float $debit, float $credit) => tap($id(), function ($vid) use ($row, $status, $account, $debit, $credit): void {
            $row('acc_vouchers', ['id' => $vid, 'voucher_ref_no' => 'V-'.substr($vid, -8), 'voucher_date' => '2026-11-04', 'voucher_type' => 'JV', 'posting_status' => $status]);
            $row('acc_voucher_lines', ['voucher_id' => $vid, 'account_id' => $account, 'debit' => $debit, 'credit' => $credit]);
        });
        $voucher('POSTED', $expense, 50000, 2000);
        $voucher('UNPOSTED', $expense, 99999, 0);
        $voucher('POSTED', $income, 0, 70000);

        // Alerts and approvals; activity — a sign-in is not work.
        $row('merch_ai_alerts', ['order_id' => $o1, 'kind' => 'TASKS', 'severity' => 'HIGH', 'title' => '3 T&A tasks past plan', 'detail' => 'x', 'advice' => 'y', 'first_run_id' => $id(), 'last_run_id' => $id(), 'status' => 'OPEN']);
        $row('merch_ai_alerts', ['order_id' => $o1, 'kind' => 'MATERIAL', 'severity' => 'LOW', 'title' => 'Resolved one', 'detail' => 'x', 'advice' => 'y', 'first_run_id' => $id(), 'last_run_id' => $id(), 'status' => 'RESOLVED']);
        DB::table('approval_requests')->insert(['id' => $id(), 'company_id' => $c, 'workflow_id' => $id(), 'entity_type' => 'IMPORT_FILE', 'entity_id' => $id(), 'status' => 'PENDING', 'current_step' => 1, 'total_steps' => 1, 'requested_at' => '2026-11-10 09:00:00', 'context_json' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('audit_logs')->insert([['id' => $id(), 'company_id' => $c, 'user_id' => $this->owner->id, 'action' => 'CREATED', 'entity_type' => 'MERCH_ORDER', 'entity_label' => 'SO-1', 'created_at' => '2026-11-11 03:00:00'],
            ['id' => $id(), 'company_id' => $c, 'user_id' => $this->owner->id, 'action' => 'LOGIN', 'entity_type' => 'USER', 'entity_label' => 'owner', 'created_at' => '2026-11-11 03:30:00']]);
        DB::statement('SET session_replication_role = DEFAULT');

        $d = $this->as()->getJson('/api/v1/command-center/deck')->assertOk()->json('data');
        $k = $d['kpis'];
        // 10,000 × 118 = 1,180,000 against 5,000 × 120 = 600,000 → +96.7%.
        $this->assertEquals([1180000, 600000, 96.7, 1], [$k['revenue']['value'], $k['revenue']['previous'], $k['revenue']['change'], $k['revenue']['count']]);
        // 1,000 × 5 × 120 + 500 × 4 × 100 + 200 × 2 × 120 = 600,000 + 200,000 + 48,000.
        $this->assertEquals([848000, 3], [$k['order_book']['value'], $k['order_book']['count']]);
        // (300 × 12 + 400 × 10) / (25 × 8 × 60 + 20 × 10 × 60) = 7,600 / 24,000 = 31.7%; planned (60 × 12,000 + 50 × 12,000) / 24,000 = 55%.
        $this->assertEquals([31.7, 55], [$k['efficiency']['value'], $k['efficiency']['target']]);
        $this->assertEquals([50, 2, 1], [$k['on_time']['value'], $k['on_time']['shipped'], $k['on_time']['on_time']]);

        $nov = $d['trend'][11];
        $this->assertEquals(['2026-11', 1180000, 48000, 200 * 5 * 120 + 100 * 3 * 120], [$nov['month'], $nov['revenue'], $nov['cost'], $nov['shipment']]);
        $this->assertEquals(600000 + 9000 * 120, $d['trend'][10]['revenue']);
        // The next six months only (the September 2027 order is beyond them), largest first.
        $this->assertSame([['buyer' => 'H&M', 'value' => 600000], ['buyer' => 'Zara', 'value' => 200000]], $d['buyers']);
        // This week to date (Saturday 7 Nov to today): line A's 9 Nov plan; tomorrow's plan is not yet due — 350 planned, 300 made.
        $this->assertSame([['floor' => 'Floor 1', 'plan' => 350, 'actual' => 300]], $d['output']['rows']);
        // 300 × 12 / 12,000 = 30% (target 60); 400 × 10 / 12,000 = 33.3% (target 50).
        $this->assertEquals([['line' => 'Line A', 'achieved' => 30, 'target' => 60], ['line' => 'Line B', 'achieved' => 33.3, 'target' => 50]], $d['lines']);

        // The order's creation is there (the fixtures' own entries too); a sign-in is not work and never is.
        $this->assertContains(['CREATED', 'SO-1', $this->owner->name], array_map(fn ($a) => [$a['action'], $a['label'], $a['user']], $d['activity']));
        $this->assertNotContains('LOGIN', array_column($d['activity'], 'action'));
        $this->assertSame([['ORDER_ALERT', 'HIGH', '3 T&A tasks past plan'], ['APPROVAL', 'MEDIUM', 'IMPORT_FILE']], array_map(fn ($a) => [$a['kind'], $a['severity'], $a['title']], $d['alerts']));
        $this->assertSame(0, $d['unconverted']);
    }

    public function test_who_may(): void
    {
        $this->as(TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test'))->getJson('/api/v1/command-center/deck')->assertOk();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/command-center/deck')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
