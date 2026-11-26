<?php

declare(strict_types=1);

namespace Tests\Feature\Assembly;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Assembly Floor → Sewing (AnnotechERP 20/01): a printed cut bundle card
 * scanned onto a line once, with the line's plan; scanned off the line
 * again, never more than went on; withdrawn while the day is open; and the
 * Sewing Dashboard reading every line's day — and who may scan.
 */
class AssemblySewingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $order;

    private PlanningLine $line1;

    private PlanningLine $line2;

    private PlanningLine $line9;

    private Floor $floor1;

    private Floor $floor2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $this->floor1 = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing 1', 'code' => 'FLR-001']);
        $this->floor2 = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing 2', 'code' => 'FLR-002']);
        $line = fn (Floor $f, string $code) => PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $f->id, 'code' => $code, 'name' => "Line {$code}", 'std_manpower' => 40, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);
        $this->line1 = $line($this->floor1, 'LINE01');
        $this->line2 = $line($this->floor1, 'LINE02');
        $this->line9 = $line($this->floor2, 'LINE09');

        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'ORD-11565705', 'job_no' => '868MI', 'style_no' => '868MI', 'buyer_id' => $lpp, 'order_type' => 'BULK_NEW', 'total_qty' => 500,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => '11565705', 'item' => 'T-Shirt'])->id;
        foreach (['S', 'M', 'L'] as $i => $size) {
            DB::table('merch_order_sizes')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'order_id' => $this->order, 'colour' => 'Black', 'size' => $size, 'qty' => 200, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
        // LINE01 runs 868MI today on Work Study's target sheet; LINE09 has only an open allocation.
        WsSewingTarget::query()->create(['company_id' => $this->delta->id, 'target_date' => '2026-11-06', 'floor_id' => $this->floor1->id, 'planning_line_id' => $this->line1->id, 'buyer_id' => $lpp, 'order_style' => '868MI', 'gmt_item' => 'T-Shirt',
            'smv' => 10, 't_effi' => 65, 'wh' => 10, 'used_op' => 30, 'used_aso' => 6, 'target_gen' => 300, 'target_ot' => 60, 'sort_order' => 1]);
        DB::table('ws_line_allocations')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'floor_id' => $this->floor2->id, 'planning_line_id' => $this->line9->id, 'order_style' => 'ORD-11565705', 'style' => '868MI',
            'item' => 'T-Shirt', 'smv' => 9.5, 'input_date' => '2026-11-01', 'allocation' => 500, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_bundle_goes_into_sewing_once_with_the_lines_plan(): void
    {
        $a = $this->card('S', 1, 36);
        $unprinted = $this->card('M', 1, 20, false);
        $cancelled = $this->card('L', 1, 20);
        $this->as()->postJson("/api/v1/cutting/bundles/{$cancelled}/cancel")->assertOk();
        $d = $this->card('M', 21, 30);

        $opts = $this->as()->getJson('/api/v1/assembly/sewing/options')->assertOk()->json('data');
        $this->assertSame(['2026-11-06', 7, 2, 3], [$opts['today'], count($opts['days']), count($opts['floors']), count($opts['lines'])]);
        $this->assertSame('2026-10-31', end($opts['days']));

        // Resolved by number or by barcode, with the line's plan, before anything is saved.
        $r = $this->as()->getJson('/api/v1/assembly/sewing/resolve?'.http_build_query(['code' => str_replace('-', '', strtolower($a)), 'date' => '2026-11-06', 'line_id' => $this->line1->id]))->assertOk()->json('data');
        $this->assertSame([$a, 'LPP', 36, '1-36'], [$r['bundle']['bundle_no'], $r['bundle']['buyer'], $r['bundle']['qty'], $r['bundle']['card_no']]);
        $this->assertEquals(['868MI', 10, 36, 360, 'TARGET', false], [$r['plan']['style'], $r['plan']['smv'], $r['plan']['manpower'], $r['plan']['target'], $r['plan']['source'], $r['style_differs']]);
        $this->assertSame(0, DB::table('asm_bundle_ins')->count());
        $resolve = fn (string $code) => $this->as()->getJson('/api/v1/assembly/sewing/resolve?'.http_build_query(['code' => $code, 'date' => '2026-11-06', 'line_id' => $this->line1->id]));
        $resolve($unprinted)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$unprinted} has not been printed yet — print its card on the cutting floor first.");
        $resolve($cancelled)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$cancelled} is cancelled.");
        $resolve('BND-X')->assertUnprocessable()->assertJsonPath('errors.code.0', 'No bundle card BND-X.');

        // The line must be on the floor, the day open, the short less than the bundle.
        $this->receive($a, $this->line9, ['floor_id' => $this->floor1->id])->assertUnprocessable()->assertJsonPath('errors.line_id.0', 'The line is not an active line of that floor.');
        $this->receive($a, $this->line1, ['receive_date' => '2026-10-30'])->assertUnprocessable()->assertJsonPath('errors.receive_date.0', 'The desk works on today and the 7 days before it only.');
        $this->receive($a, $this->line1, ['short_qty' => 36])->assertUnprocessable()->assertJsonPath('errors.short_qty.0', 'Short must be less than the bundle quantity (36).');
        $this->receive($a, $this->line1, ['floor_id' => null])->assertUnprocessable()->assertJsonPath('errors.floor_id.0', 'Select the floor.');

        $in = $this->receive($a, $this->line1, ['short_qty' => 2])->assertCreated()->json('data');
        $this->assertEquals([34, 2, 36, 10, 36, 360, 'IN_SEWING', 'LINE01 · Line LINE01', 'Sewing 1', true], [$in['in_qty'], $in['short'], $in['bundle_qty'], $in['smv'], $in['manpower'], $in['daily_target'], $in['bundle_status'], $in['line'], $in['floor'], $in['can_withdraw']]);
        $this->receive($a, $this->line2)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} was already received into sewing on 06-11-2026 (LINE01 · Line LINE01).");

        // Without a target sheet the line's open allocation and its standard manpower stand in.
        $r9 = $this->as()->getJson('/api/v1/assembly/sewing/resolve?'.http_build_query(['code' => $d, 'date' => '2026-11-05', 'line_id' => $this->line9->id]))->json('data.plan');
        $this->assertSame(['868MI', 9.5, 40, null, 'ALLOCATION'], [$r9['style'], $r9['smv'], $r9['manpower'], $r9['target'], $r9['source']]);
        $this->receive($d, $this->line9, ['receive_date' => '2026-11-05', 'section' => 'Sewing B'])->assertCreated()->assertJsonPath('data.daily_target', null)->assertJsonPath('data.section', 'Sewing B');

        $day = $this->as()->getJson('/api/v1/assembly/sewing/ins?date=2026-11-06')->assertOk()->json('data');
        $this->assertSame([1, 34, 2, true], [$day['bundles'], $day['qty'], $day['short'], $day['open']]);
        $this->assertSame([['LINE01 · Line LINE01', 1, 36, 2, 34]], array_map(fn ($l) => [$l['line'], $l['bundles'], $l['bundle_qty'], $l['short'], $l['qty']], $day['by_line']));
        $this->assertSame([1, '10:00'], [$day['rows'][0]['sl'], $day['rows'][0]['time']]);
        $this->as()->getJson('/api/v1/assembly/sewing/ins?date=2026-11-05')->assertJsonPath('data.bundles', 1)->assertJsonPath('data.rows.0.bundle_no', $d);
    }

    public function test_a_bundle_comes_off_the_line_never_more_than_went_on(): void
    {
        $a = $this->card('S', 1, 36);
        $d = $this->card('M', 1, 10);
        $e = $this->card('L', 1, 10);
        $this->receive($a, $this->line1, ['short_qty' => 2])->assertCreated();
        $this->receive($d, $this->line9, ['floor_id' => $this->floor2->id])->assertCreated();

        $this->out($e, 'FULL')->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$e} has not been received into sewing.");
        $this->out($a, 'SHORT')->assertUnprocessable()->assertJsonPath('errors.output_qty.0', 'Type the output quantity for a short output.');
        $this->out($a, 'SHORT', 35)->assertUnprocessable()->assertJsonPath('errors.output_qty.0', 'Output cannot exceed what the line was handed (34).');
        $this->out($a, 'FULL', null, '2026-11-05')->assertUnprocessable()->assertJsonPath('errors.out_date.0', 'The bundle went into sewing on 06-11-2026 — it cannot come off before that.');
        $short = $this->out($a, 'SHORT', 30)->assertCreated()->json('data');
        $this->assertSame([34, 30, 4, 'QC_PENDING', 'SHORT'], [$short['in_qty'], $short['output'], $short['balance'], $short['bundle_status'], $short['status']]);
        $this->out($a, 'FULL')->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} has already gone out of sewing.");
        $this->out(str_replace('-', '', $d), 'FULL')->assertCreated()->assertJsonPath('data.output', 10)->assertJsonPath('data.balance', 0);

        $day = $this->as()->getJson('/api/v1/assembly/sewing/outs?date=2026-11-06')->assertOk()->json('data');
        $this->assertSame([2, 40, 4, 1, 1], [$day['bundles'], $day['output'], $day['balance'], $day['full'], $day['short']]);
        $this->assertSame([['LINE01 · Line LINE01', 1, 0, 34, 30, 4], ['LINE09 · Line LINE09', 1, 0, 10, 10, 0]], array_map(fn ($l) => [$l['line'], $l['bundles'], $l['running'], $l['in_qty'], $l['output'], $l['wip']], $day['wip']));
        $this->as()->getJson('/api/v1/assembly/sewing/ins?date=2026-11-06')->assertJsonPath('data.rows.0.bundle_status', 'QC_PENDING')->assertJsonPath('data.rows.0.can_withdraw', false);

        // A receipt whose bundle went out stays until the out is withdrawn; then both go.
        $this->as()->postJson('/api/v1/assembly/sewing/ins/withdraw', ['bundles' => [$a]])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "Bundle {$a} has already gone out of sewing — withdraw its Bundle OUT first.");
        $this->as()->postJson('/api/v1/assembly/sewing/outs/withdraw', ['bundles' => [$e]])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "Bundle {$e} has not gone out of sewing.");
        $this->as()->postJson('/api/v1/assembly/sewing/outs/withdraw', ['bundles' => []])->assertUnprocessable()->assertJsonPath('errors.bundles.0', 'Tick the bundles first.');
        $this->as()->postJson('/api/v1/assembly/sewing/outs/withdraw', ['bundles' => [$a]])->assertOk()->assertJsonPath('data.withdrawn', 1);
        $this->as()->postJson('/api/v1/assembly/sewing/ins/withdraw', ['bundles' => [$a]])->assertOk()->assertJsonPath('data.withdrawn', 1);
        $this->assertSame(1, DB::table('asm_bundle_ins')->count());
        $this->receive($a, $this->line2)->assertCreated()->assertJsonPath('data.in_qty', 36);

        // A week on, the day is closed: nothing of it can be withdrawn.
        $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00:00', 'Asia/Dhaka'));
        $this->as()->postJson('/api/v1/assembly/sewing/outs/withdraw', ['bundles' => [$d]])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "Bundle {$d}'s day is closed — it can no longer be withdrawn.");
        $this->as()->postJson('/api/v1/assembly/sewing/ins/withdraw', ['bundles' => [$a]])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "Bundle {$a}'s day is closed — it can no longer be withdrawn.");
        $this->as()->getJson('/api/v1/assembly/sewing/ins?date=2026-11-06')->assertJsonPath('data.open', false)->assertJsonPath('data.rows.0.can_withdraw', false);
    }

    public function test_the_dashboard_reads_every_lines_day(): void
    {
        $a = $this->card('S', 1, 36);
        $f = $this->card('S', 37, 56);
        $d = $this->card('M', 1, 10);
        $this->receive($f, $this->line1, ['receive_date' => '2026-11-05'])->assertCreated();
        $this->receive($a, $this->line1, ['short_qty' => 2])->assertCreated();
        $this->receive($d, $this->line9, ['floor_id' => $this->floor2->id])->assertCreated();
        $this->out($a, 'SHORT', 30)->assertCreated();
        $this->out($f, 'FULL')->assertCreated();

        $rows = collect($this->as()->getJson('/api/v1/assembly/sewing/dashboard?date=2026-11-06')->assertOk()->json('data.rows'))->keyBy('line');
        $this->assertCount(3, $rows);
        $l1 = $rows['LINE01 · Line LINE01'];
        $this->assertSame(['Sewing 1', '868MI', 360, 50, 1, 0, 2, 4, 50, 'RUNNING'], [$l1['floor'], $l1['style'], $l1['target'], $l1['output'], $l1['bundles_in'], $l1['running'], $l1['bundles_out'], $l1['wip'], $l1['qc_pending'], $l1['status']]);
        // 50 / 360 achieved; 50 × 10 SMV over 36 hands × 10 h × 60.
        $this->assertEquals([13.89, 2.31], [$l1['achievement'], $l1['efficiency']]);
        $l9 = $rows['LINE09 · Line LINE09'];
        $this->assertSame([0, 0, null, 1, 10, 'RUNNING'], [$l9['target'], $l9['output'], $l9['achievement'], $l9['running'], $l9['wip'], $l9['status']]);
        $this->assertSame(['IDLE', 0], [$rows['LINE02 · Line LINE02']['status'], $rows['LINE02 · Line LINE02']['wip']]);

        // The day before: F went on and was still running.
        $y = collect($this->as()->getJson('/api/v1/assembly/sewing/dashboard?date=2026-11-05')->json('data.rows'))->keyBy('line')['LINE01 · Line LINE01'];
        $this->assertSame([1, 0, 1, 20, 0, 0, 'RUNNING'], [$y['bundles_in'], $y['output'], $y['running'], $y['wip'], $y['qc_pending'], $y['target'], $y['status']]);
        $this->as()->getJson('/api/v1/assembly/sewing/dashboard?date=2026-11-06&line_id='.$this->line9->id)->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.wip', 10);
    }

    public function test_who_may_scan_bundles_on_and_off_the_lines(): void
    {
        $a = $this->card('S', 1, 36);
        $chief = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($chief)->postJson('/api/v1/assembly/sewing/ins', ['code' => $a, 'receive_date' => '2026-11-06', 'floor_id' => $this->floor1->id, 'line_id' => $this->line1->id])->assertCreated();
        $this->withToken($chief)->getJson('/api/v1/assembly/sewing/dashboard')->assertOk();
        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/assembly/sewing/ins')->assertForbidden();
        $tech = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($tech)->postJson('/api/v1/assembly/sewing/outs', ['code' => $a, 'out_date' => '2026-11-06', 'mode' => 'FULL'])->assertForbidden();
    }

    /** A bundle card cut today (and printed, unless told otherwise); its number back. */
    private function card(string $size, int $from, int $to, bool $print = true): string
    {
        $no = $this->as()->postJson('/api/v1/cutting/bundles', ['order_id' => $this->order, 'cut_date' => '2026-11-04', 'colour' => 'Black', 'cutting_no' => 1, 'size' => $size, 'serial_from' => $from, 'serial_to' => $to, 'parts_name' => 'Front Panel', 'batch_no' => 'B-1'])
            ->assertCreated()->json('data.bundle_no');
        if ($print) {
            $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$no]])->assertOk();
        }

        return $no;
    }

    private function receive(string $no, PlanningLine $line, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/sewing/ins', array_merge(['code' => $no, 'receive_date' => '2026-11-06', 'section' => 'Sewing', 'floor_id' => $line->floor_id, 'line_id' => $line->id], $extra));
    }

    private function out(string $no, string $mode, ?int $qty = null, string $date = '2026-11-06'): \Illuminate\Testing\TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/sewing/outs', ['code' => $no, 'out_date' => $date, 'mode' => $mode, 'output_qty' => $qty]);
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
