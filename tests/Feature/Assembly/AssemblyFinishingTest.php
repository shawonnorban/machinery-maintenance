<?php

declare(strict_types=1);

namespace Tests\Feature\Assembly;

use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Assembly Floor → Finishing (AnnotechERP 20/02): a bundle out of sewing
 * received onto a finishing line once, with what sewing sent out; scanned
 * off the same line with Good + Reject + Rework adding up to it; the rework
 * a supervisor moves on to completion; the trace from cutting to finishing;
 * the Finishing Dashboard for a range, a shift and a view — and who may.
 */
class AssemblyFinishingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $order;

    private PlanningLine $sew1;

    private ProductionLine $fin1;

    private ProductionLine $fin2;

    private ProductionLine $fin3;

    private string $section;

    private Floor $finFloor;

    private string $dayShift;

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
        $sewFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing 1', 'code' => 'FLR-001']);
        $this->finFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FLR-FIN']);
        $this->sew1 = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $sewFloor->id, 'code' => 'LINE01', 'name' => 'Line LINE01', 'std_manpower' => 40, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);

        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Finishing Department', 'code' => 'FIN', 'created_at' => now(), 'updated_at' => now()]);
        $this->section = (string) Str::ulid();
        DB::table('sections')->insert(['id' => $this->section, 'company_id' => $this->delta->id, 'department_id' => $dept, 'name' => 'Finishing', 'code' => 'FIN-01', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $line = fn (string $code, array $more = []) => ProductionLine::query()->create(array_merge(['company_id' => $this->delta->id, 'department_id' => $dept, 'section_id' => $this->section, 'floor_id' => $this->finFloor->id,
            'code' => $code, 'name' => "Finishing {$code}", 'line_type' => 'FINISHING', 'manpower' => 20, 'target_per_day' => 300, 'status' => 'ACTIVE'], $more));
        $this->fin1 = $line('FIN01');
        $this->fin2 = $line('FIN02', ['target_per_day' => 200]);
        $this->fin3 = $line('FIN03', ['section_id' => null]);
        $line('FIN04', ['status' => 'INACTIVE']);
        $line('SEW99', ['line_type' => 'SEWING']);

        // FIN01 works the day shift (08:00-20:00, 12 h); FIN02 has no shift plan, so it works every shift.
        // Shifts are HR & Payroll's; a line's shift plan is Capacity Planning's line shifts.
        $this->dayShift = FactoryCalendarSetup::shift($this->delta->id, 'DAY', 'Day shift', '08:00', '20:00');
        FactoryCalendarSetup::shift($this->delta->id, 'NIGHT', 'Night shift', '20:00', '08:00');
        FactoryCalendarSetup::linePlan($this->delta->id, $this->fin1->id, $this->dayShift);

        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'ORD-11565705', 'job_no' => '868MI', 'style_no' => '868MI', 'buyer_id' => $lpp, 'order_type' => 'BULK_NEW', 'total_qty' => 500,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => '11565705', 'item' => 'T-Shirt'])->id;
        foreach (['S', 'M', 'L'] as $i => $size) {
            DB::table('merch_order_sizes')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'order_id' => $this->order, 'colour' => 'Black', 'size' => $size, 'qty' => 200, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
        // The order's master plan: a draft, then the locked version the desks read.
        foreach ([[1, 'REVISED', 9.0], [2, 'LOCKED', 10.5]] as [$v, $status, $smv]) {
            DB::table('planning_master_plans')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'plan_no' => 'MP-2026110001', 'version' => $v, 'order_id' => $this->order, 'status' => $status, 'smv' => $smv, 'smv_source' => 'MANUAL', 'sam' => $smv, 'sam_source' => 'MANUAL',
                'direction' => 'FORWARD', 'order_qty' => 600, 'allocated_qty' => 600, 'sewing_start' => '2026-11-04', 'sewing_end' => '2026-11-20', 'ship_date' => '2026-12-15', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_a_bundle_out_of_sewing_is_received_onto_a_finishing_line_once(): void
    {
        $opts = $this->as()->getJson('/api/v1/assembly/finishing/options')->assertOk()->json('data');
        $this->assertSame([['Finishing']], [array_column($opts['sections'], 'label')]);
        $this->assertSame(['Finishing · FLR-FIN'], array_column($opts['floors'], 'label'));
        $this->assertSame(['FIN01 — Finishing FIN01', 'FIN02 — Finishing FIN02'], array_column($opts['lines'], 'label'));
        $this->assertSame(['FIN03 — Finishing FIN03'], $opts['unfiled']);
        $this->assertSame(['Day shift (DAY)', 'Night shift (NIGHT)'], array_column($opts['shifts'], 'label'));

        // The line's target and today's progress, before any scan: 300 a day over a 12-hour shift.
        $p = $this->as()->getJson('/api/v1/assembly/finishing/line?line_id='.$this->fin1->id)->assertOk()->json('data');
        $this->assertEquals([20, 300, 12, 25, 0, 0, 0], [$p['manpower'], $p['daily_target'], $p['hours'], $p['hourly_target'], $p['today_in'], $p['wip'], $p['achievement']]);
        $this->as()->getJson('/api/v1/assembly/finishing/line?line_id='.$this->fin3->id)->assertOk(); // unfiled lines still answer; they just cannot be scanned onto

        $a = $this->sewn('S', 1, 36, 2, 30);   // in 34, short output 30
        $raw = $this->card('M', 1, 20);         // printed, never sewn

        // Not out of sewing yet: shown, but not receivable.
        $r = $this->resolve($raw, $this->fin1, 'in')->assertOk()->json('data');
        $this->assertSame(['NOT_READY', 20, "Bundle {$raw} has not come out of sewing yet."], [$r['bundle']['status'], $r['bundle']['qty'], $r['blocker']]);
        $this->receive($raw, $this->fin1)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$raw} has not come out of sewing yet.");

        // Out of sewing: the Sewing OUT check passes, what sewing sent out is the quantity, the order's locked plan comes with it.
        $r = $this->resolve(str_replace('-', '', strtolower($a)), $this->fin1, 'in')->assertOk()->json('data');
        $this->assertSame(['READY', 30, 36, 'ORD-11565705', 'LPP', 'SEW_OUT', null], [$r['bundle']['status'], $r['bundle']['qty'], $r['bundle']['bundle_qty'], $r['bundle']['order_no'], $r['bundle']['buyer'], $r['bundle']['previous']['stage'], $r['blocker']]);
        $this->assertEquals(['MP-2026110001', 10.5, '2026-11-04', '2026-11-20', '2026-12-15', 600, 0, 600], [$r['plan']['plan_no'], $r['plan']['smv'], $r['plan']['start'], $r['plan']['end'], $r['plan']['ship_date'], $r['plan']['planned_qty'], $r['plan']['completed'], $r['plan']['remaining']]);
        $this->assertSame(0, DB::table('asm_finishing_ins')->count());
        $this->resolve('BND-X', $this->fin1, 'in')->assertUnprocessable()->assertJsonPath('errors.code.0', 'No bundle card BND-X.');

        // Section > Floor > Line must name an active, filed finishing line.
        $this->receive($a, $this->fin3)->assertUnprocessable()->assertJsonPath('errors.line_id.0', 'Pick an active finishing line filed under that section and floor.');
        $this->receive($a, $this->fin1, ['section_id' => null])->assertUnprocessable()->assertJsonPath('errors.section_id.0', 'Select the section.');
        $in = $this->receive($a, $this->fin1)->assertCreated()->json('data');
        $this->assertSame([30, 36, 'FIN01 — Finishing FIN01', 'Finishing', 'Finishing · FLR-FIN', '10:00'], [$in['qty'], $in['bundle_qty'], $in['line'], $in['section'], $in['floor'], $in['time']]);
        $this->receive($a, $this->fin2)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} was already received into finishing on 06-11-2026 (FIN01 — Finishing FIN01).");
        $this->assertSame('IN_FINISHING', $this->resolve($a, $this->fin1, 'in')->json('data.bundle.status'));

        // Sewing can no longer take its Bundle OUT back.
        $this->as()->getJson('/api/v1/assembly/sewing/outs?date=2026-11-06')->assertJsonPath('data.rows.0.bundle_status', 'IN_FINISHING')->assertJsonPath('data.rows.0.can_withdraw', false);
        $this->as()->postJson('/api/v1/assembly/sewing/outs/withdraw', ['bundles' => [$a]])->assertUnprocessable()->assertJsonPath('errors.bundles.0', "Bundle {$a} has been received into finishing — it can no longer be withdrawn.");

        $recent = $this->as()->getJson('/api/v1/assembly/finishing/recent?kind=in')->assertOk()->json('data');
        $this->assertSame(['2026-11-06', 1, $a], [$recent['date'], count($recent['rows']), $recent['rows'][0]['bundle_no']]);
        $this->as()->getJson('/api/v1/assembly/finishing/recent?kind=out')->assertJsonCount(0, 'data.rows');
        $this->as()->getJson('/api/v1/assembly/finishing/line?line_id='.$this->fin1->id)->assertJsonPath('data.today_in', 1)->assertJsonPath('data.wip_qty', 30);
    }

    public function test_a_bundle_goes_out_with_good_reject_and_rework_adding_up_and_the_rework_is_followed(): void
    {
        $a = $this->sewn('S', 1, 36, 2, 30);
        $b = $this->sewn('M', 1, 20, 0, 20);
        $c = $this->sewn('L', 1, 10, 0, 10);
        $this->receive($a, $this->fin1)->assertCreated();
        $this->receive($b, $this->fin2)->assertCreated();

        $this->out($c, $this->fin1, 10, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$c} has not been received into finishing.");
        $this->out($b, $this->fin1, 20, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$b} is on FIN02 — Finishing FIN02 — scan it off there.");
        $this->assertSame("Bundle {$b} is on FIN02 — Finishing FIN02 — scan it off there.", $this->resolve($b, $this->fin1, 'out')->json('data.blocker'));
        $this->out($a, $this->fin1, 25, 2, 2)->assertUnprocessable()->assertJsonPath('errors.good_qty.0', 'Good + Reject + Rework (29) must equal the bundle quantity (30).');

        $out = $this->out($a, $this->fin1, 25, 2, 3)->assertCreated()->json('data');
        $this->assertSame([30, 25, 2, 3, 'PENDING'], [$out['qty'], $out['good'], $out['reject'], $out['rework'], $out['rework_status']]);
        $this->out($a, $this->fin1, 30, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} already went OUT of finishing on 06-11-2026.");
        $this->out($b, $this->fin2, 20, 0, 0)->assertCreated()->assertJsonPath('data.rework_status', null);
        $this->assertSame('FINISHING_OUT', $this->resolve($a, $this->fin1, 'out')->json('data.bundle.status'));

        $p = $this->as()->getJson('/api/v1/assembly/finishing/line?line_id='.$this->fin1->id)->json('data');
        $this->assertEquals([1, 1, 25, 2, 3, 0, 8.3], [$p['today_in'], $p['today_out'], $p['good'], $p['reject'], $p['rework'], $p['wip'], $p['achievement']]);
        $this->assertSame([45, 555], [$this->resolve($c, $this->fin1, 'in')->json('data.plan.completed'), $this->resolve($c, $this->fin1, 'in')->json('data.plan.remaining')]);
        $this->assertSame([$b, $a], array_column($this->as()->getJson('/api/v1/assembly/finishing/recent?kind=out')->json('data.rows'), 'bundle_no'));

        // Only a supervisor moves the rework on: pending → processing → completed, never back.
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'PROCESSING'])->assertForbidden();
        $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $b, 'status' => 'PROCESSING'])->assertUnprocessable()->assertJsonPath('errors.bundle_no.0', "Bundle {$b} has no rework to follow up.");
        $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'PROCESSING'])->assertOk()->assertJsonPath('data.rework_status', 'PROCESSING');
        $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'PROCESSING'])->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "Bundle {$a}'s rework is Rework Processing — it cannot move to Rework Processing.");
        $this->travelTo(CarbonImmutable::parse('2026-11-06 11:30:00', 'Asia/Dhaka'));
        $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'COMPLETED'])->assertOk()->assertJsonPath('data.rework_status', 'COMPLETED');
        $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'COMPLETED'])->assertUnprocessable();

        // The bundle's way from cutting to the end of its rework.
        $trace = $this->as()->getJson('/api/v1/assembly/finishing/trace?code='.str_replace('-', '', $a))->assertOk()->json('data');
        $this->assertSame(['CUT', 'PRINT', 'SEW_IN', 'SEW_OUT', 'FIN_IN', 'FIN_OUT', 'REWORK_START', 'REWORK_DONE'], array_column($trace['steps'], 'stage'));
        $this->assertSame(['FINISHING_OUT', 'FIN01 — Finishing FIN01', 34, 25, '2026-11-06 11:30'], [$trace['bundle']['status'], $trace['bundle']['line'], $trace['steps'][2]['params']['qty'], $trace['steps'][5]['params']['good'], $trace['steps'][7]['at']]);
        $this->assertSame(['CUT', 'PRINT', 'SEW_IN', 'SEW_OUT'], array_column($this->as()->getJson('/api/v1/assembly/finishing/trace?code='.$c)->json('data.steps'), 'stage'));
    }

    public function test_the_dashboard_reads_a_range_a_shift_and_a_view(): void
    {
        // Yesterday 21:00 (night shift) FIN02 received and finished d; today 10:00 (day shift) FIN01 a, FIN02 b.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 21:00:00', 'Asia/Dhaka'));
        $d = $this->sewn('L', 1, 10, 0, 10, '2026-11-05');
        $this->receive($d, $this->fin2)->assertCreated();
        $this->out($d, $this->fin2, 8, 1, 1)->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
        $a = $this->sewn('S', 1, 36, 2, 30);
        $b = $this->sewn('M', 1, 20, 0, 20);
        $e = $this->sewn('M', 21, 32, 0, 12);  // out of sewing, never received: a pending bundle
        $this->receive($a, $this->fin1)->assertCreated();
        $this->receive($b, $this->fin2)->assertCreated();
        $this->out($a, $this->fin1, 27, 3, 0)->assertCreated();

        $today = $this->board()->assertOk()->json('data');
        $this->assertSame(['2026-11-06', '2026-11-06', 'line', null], [$today['from'], $today['to'], $today['view'], $today['shift']]);
        $s = $today['summary'];
        $this->assertEquals([2, 50, 1, 1, 20, 27, 500, 5.4, 3, 0, 1, 1, 12, 2], [$s['bundle_in'], $s['bundle_in_qty'], $s['bundle_out'], $s['wip_bundles'], $s['wip_qty'], $s['good'], $s['target'], $s['achievement'], $s['reject'], $s['rework'], $s['rework_pending'], $s['pending_bundles'], $s['pending_qty'], $s['lines']]);
        $this->assertSame([[$e, 12, 'LINE01 · Line LINE01']], array_map(fn ($p) => [$p['bundle_no'], $p['qty'], $p['sewing_line']], $today['pending']));
        $perf = collect($today['performance'])->keyBy('label');
        $this->assertEquals([300, 27, 9.0, 1, 1, 0, 0, 3, 'Finishing · Finishing · FLR-FIN'], [$perf['FIN01 — Finishing FIN01']['target'], $perf['FIN01 — Finishing FIN01']['actual'], $perf['FIN01 — Finishing FIN01']['achievement'], $perf['FIN01 — Finishing FIN01']['bundle_in'],
            $perf['FIN01 — Finishing FIN01']['bundle_out'], $perf['FIN01 — Finishing FIN01']['wip'], $perf['FIN01 — Finishing FIN01']['wip_qty'], $perf['FIN01 — Finishing FIN01']['reject'], $perf['FIN01 — Finishing FIN01']['sub']]);
        $this->assertSame([1, 20], [$perf['FIN02 — Finishing FIN02']['wip'], $perf['FIN02 — Finishing FIN02']['wip_qty']]);
        $this->assertSame([$b], array_column($today['wip'], 'bundle_no'));
        $this->assertSame([$d], array_column($today['rework'], 'bundle_no')); // open rework from yesterday shows on every range
        $this->assertSame(['FIN03 — Finishing FIN03'], $today['unfiled']);

        // Two days: a 2-day target, yesterday's row in Daily Production.
        $two = $this->board(['from' => '2026-11-05', 'to' => '2026-11-06'])->json('data');
        $this->assertEquals([3, 2, 35, 1000, 3.5, 4, 1], [$two['summary']['bundle_in'], $two['summary']['bundle_out'], $two['summary']['good'], $two['summary']['target'], $two['summary']['achievement'], $two['summary']['reject'], $two['summary']['rework']]);
        $this->assertSame([['2026-11-05', 1, 1, 8, 1, 1], ['2026-11-06', 2, 1, 27, 3, 0]], array_map(fn ($r) => [$r['day'], $r['bundle_in'], $r['bundle_out'], $r['good'], $r['reject'], $r['rework']], $two['daily']));
        $this->assertSame([$a, $d], array_column($two['outs'], 'bundle_no'));

        // As of yesterday, d had already left; a and b were not yet in.
        $this->assertSame(0, $this->board(['from' => '2026-11-05', 'to' => '2026-11-05'])->json('data.summary.wip_bundles'));

        // The day shift leaves out yesterday's 21:00 scans; FIN02 (no shift plan) stays in view.
        $day = $this->board(['from' => '2026-11-05', 'to' => '2026-11-06', 'shift_id' => $this->dayShift])->json('data');
        $this->assertSame(['Day shift', 2, 2, 1], [$day['shift'], $day['summary']['lines'], $day['summary']['bundle_in'], $day['summary']['bundle_out']]);

        // Section, floor, unit views fold the lines together; a line filter narrows them.
        $sec = $this->board(['view' => 'section'])->json('data.performance');
        $this->assertSame([['Finishing', 2, 500, 27]], array_map(fn ($r) => [$r['label'], $r['lines'], $r['target'], $r['actual']], $sec));
        $this->assertSame('Dhaka Unit 1', $this->board(['view' => 'unit'])->json('data.performance.0.label'));
        $this->assertSame('Finishing · FLR-FIN', $this->board(['view' => 'floor'])->json('data.performance.0.label'));
        $this->assertSame(1, $this->board(['line_id' => $this->fin2->id])->json('data.summary.lines'));

        $this->board(['from' => '2026-11-06', 'to' => '2026-11-05'])->assertUnprocessable()->assertJsonPath('errors.to.0', 'To must be on or after From.');
        $this->board(['from' => '2026-06-01', 'to' => '2026-11-06'])->assertUnprocessable()->assertJsonPath('errors.to.0', 'Pick a range of 92 days at most.');

        // Completed rework shows only in the range it was completed in.
        $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $d, 'status' => 'COMPLETED'])->assertOk();
        $this->assertSame([$d], array_column($this->board()->json('data.rework'), 'bundle_no'));
        $this->assertSame([], $this->board(['from' => '2026-11-05', 'to' => '2026-11-05'])->json('data.rework'));
        $this->assertSame(0, $this->board()->json('data.summary.rework_pending'));
    }

    public function test_who_may_scan_and_who_may_supervise(): void
    {
        $a = $this->sewn('S', 1, 36, 0, 36);
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/assembly/finishing/ins', ['code' => $a, 'section_id' => $this->section, 'floor_id' => $this->finFloor->id, 'line_id' => $this->fin1->id])->assertCreated();
        $this->withToken($chief)->getJson('/api/v1/assembly/finishing/dashboard')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/assembly/finishing/outs', ['code' => $a, 'section_id' => $this->section, 'floor_id' => $this->finFloor->id, 'line_id' => $this->fin1->id, 'good_qty' => 30, 'reject_qty' => 0, 'rework_qty' => 6])->assertCreated();
        $this->withToken($chief)->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'COMPLETED'])->assertForbidden();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $a, 'status' => 'COMPLETED'])->assertOk();
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/assembly/finishing/options')->assertForbidden();
        $tech = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($tech)->postJson('/api/v1/assembly/finishing/ins', ['code' => $a, 'section_id' => $this->section, 'floor_id' => $this->finFloor->id, 'line_id' => $this->fin1->id])->assertForbidden();
    }

    /** A printed card taken through sewing: received with a short, sent out (in full when the output equals what went on). */
    private function sewn(string $size, int $from, int $to, int $short, int $output, string $date = '2026-11-06'): string
    {
        $no = $this->card($size, $from, $to);
        $this->as()->postJson('/api/v1/assembly/sewing/ins', ['code' => $no, 'receive_date' => $date, 'section' => 'Sewing', 'floor_id' => $this->sew1->floor_id, 'line_id' => $this->sew1->id, 'short_qty' => $short])->assertCreated();
        $full = $output === ($to - $from + 1) - $short;
        $this->as()->postJson('/api/v1/assembly/sewing/outs', ['code' => $no, 'out_date' => $date, 'mode' => $full ? 'FULL' : 'SHORT', 'output_qty' => $full ? null : $output])->assertCreated();

        return $no;
    }

    private function card(string $size, int $from, int $to): string
    {
        $no = $this->as()->postJson('/api/v1/cutting/bundles', ['order_id' => $this->order, 'cut_date' => '2026-11-04', 'colour' => 'Black', 'cutting_no' => 1, 'size' => $size, 'serial_from' => $from, 'serial_to' => $to, 'parts_name' => 'Front Panel', 'batch_no' => 'B-1'])
            ->assertCreated()->json('data.bundle_no');
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$no]])->assertOk();

        return $no;
    }

    private function resolve(string $code, ProductionLine $line, string $kind): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/finishing/resolve?'.http_build_query(['code' => $code, 'line_id' => $line->id, 'kind' => $kind]));
    }

    private function receive(string $no, ProductionLine $line, array $extra = []): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/finishing/ins', array_merge(['code' => $no, 'section_id' => $line->section_id ?? $this->section, 'floor_id' => $line->floor_id, 'line_id' => $line->id], $extra));
    }

    private function out(string $no, ProductionLine $line, int $good, int $reject, int $rework): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/finishing/outs', ['code' => $no, 'section_id' => $line->section_id, 'floor_id' => $line->floor_id, 'line_id' => $line->id, 'good_qty' => $good, 'reject_qty' => $reject, 'rework_qty' => $rework]);
    }

    private function board(array $q = []): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/finishing/dashboard?'.http_build_query($q));
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
