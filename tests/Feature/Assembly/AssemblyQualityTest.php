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
 * Assembly Floor → Quality Management (AnnotechERP 20/03): a bundle out of
 * Finishing received onto a quality line once, with the good pieces finishing
 * sent on; inspected into Pass + Reject + Rework adding up to what is being
 * judged, with the defect; the rework a supervisor completes coming back for
 * another round; Quality OUT refused while anything is left to judge; the
 * trace from cutting to Quality OUT; the Quality Dashboard — and who may.
 */
class AssemblyQualityTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $order;

    private PlanningLine $sew1;

    private ProductionLine $fin1;

    private ProductionLine $qc1;

    private ProductionLine $qc2;

    private string $qcSection;

    private Floor $qcFloor;

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
        $finFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FLR-FIN']);
        $this->qcFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Quality', 'code' => 'FLR-QC']);
        $this->sew1 = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $sewFloor->id, 'code' => 'LINE01', 'name' => 'Line LINE01', 'std_manpower' => 40, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true]);

        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Finishing Department', 'code' => 'FIN', 'created_at' => now(), 'updated_at' => now()]);
        $section = fn (string $name, string $code) => tap((string) Str::ulid(), fn ($id) => DB::table('sections')->insert(['id' => $id, 'company_id' => $this->delta->id, 'department_id' => $dept, 'name' => $name, 'code' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]));
        $finSection = $section('Finishing', 'FIN-01');
        $this->qcSection = $section('Final Inspection', 'QC-01');
        $line = fn (string $code, string $type, array $more = []) => ProductionLine::query()->create(array_merge(['company_id' => $this->delta->id, 'department_id' => $dept, 'section_id' => $type === 'QUALITY' ? $this->qcSection : $finSection,
            'floor_id' => $type === 'QUALITY' ? $this->qcFloor->id : $finFloor->id, 'code' => $code, 'name' => ($type === 'QUALITY' ? 'Quality ' : 'Finishing ').$code, 'line_type' => $type, 'manpower' => 12, 'target_per_day' => 400, 'status' => 'ACTIVE'], $more));
        $this->fin1 = $line('FIN01', 'FINISHING');
        $this->qc1 = $line('QC01', 'QUALITY');
        $this->qc2 = $line('QC02', 'QUALITY', ['target_per_day' => 300]);
        $line('QC03', 'QUALITY', ['floor_id' => null]);
        $line('QC04', 'QUALITY', ['status' => 'INACTIVE']);

        // QC01 works the day shift (08:00-20:00); QC02 has no shift plan, so it works every shift.
        // Shifts are HR & Payroll's; a line's shift plan is Capacity Planning's line shifts.
        $this->dayShift = FactoryCalendarSetup::shift($this->delta->id, 'DAY', 'Day shift', '08:00', '20:00');
        FactoryCalendarSetup::shift($this->delta->id, 'NIGHT', 'Night shift', '20:00', '08:00');
        FactoryCalendarSetup::linePlan($this->delta->id, $this->qc1->id, $this->dayShift);

        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'ORD-11565705', 'job_no' => '868MI', 'style_no' => '868MI', 'buyer_id' => $lpp, 'order_type' => 'BULK_NEW', 'total_qty' => 500,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => '11565705', 'item' => 'T-Shirt'])->id;
        foreach (['S', 'M', 'L'] as $i => $size) {
            DB::table('merch_order_sizes')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'order_id' => $this->order, 'colour' => 'Black', 'size' => $size, 'qty' => 200, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_a_bundle_out_of_finishing_is_received_into_quality_once(): void
    {
        $opts = $this->as()->getJson('/api/v1/assembly/quality/options')->assertOk()->json('data');
        $this->assertSame(['Final Inspection'], array_column($opts['sections'], 'label'));
        $this->assertSame(['Quality · FLR-QC'], array_column($opts['floors'], 'label'));
        $this->assertSame(['QC01 — Quality QC01', 'QC02 — Quality QC02'], array_column($opts['lines'], 'label'));
        $this->assertSame(['QC03 — Quality QC03'], $opts['unfiled']);
        $this->assertSame(['STITCHING', 'MEASUREMENT', 'FABRIC', 'SEWING', 'PRINTING', 'EMBROIDERY', 'WASHING', 'FINISHING', 'OTHER'], $opts['defect_types']);

        $a = $this->finished('S', 1, 36, 30, 2, 4);   // finishing: good 30
        $z = $this->finished('M', 1, 10, 0, 10, 0);   // finishing: nothing good
        $raw = $this->sewn('L', 1, 12);               // never out of finishing

        $r = $this->resolve($raw, $this->qc1, 'in')->assertOk()->json('data');
        $this->assertSame(['NOT_READY', "Bundle {$raw} has not gone OUT of Finishing yet."], [$r['bundle']['stage'], $r['blocker']]);
        $this->receive($raw, $this->qc1)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$raw} has not gone OUT of Finishing yet.");
        $this->assertSame("Bundle {$z} came out of Finishing with no good pieces.", $this->resolve($z, $this->qc1, 'in')->json('data.blocker'));

        // Out of finishing: the good pieces are what Quality receives.
        $r = $this->resolve(str_replace('-', '', strtolower($a)), $this->qc1, 'in')->assertOk()->json('data');
        $this->assertSame(['READY', 30, 36, 'LPP', 'FIN_OUT', null, null], [$r['bundle']['stage'], $r['bundle']['qty'], $r['bundle']['bundle_qty'], $r['bundle']['buyer'], $r['bundle']['previous']['stage'], $r['blocker'], $r['inspection']]);
        $this->assertSame(0, DB::table('asm_quality_ins')->count());

        $this->receive($a, $this->fin1)->assertUnprocessable()->assertJsonPath('errors.line_id.0', 'Pick an active quality line filed under that section and floor.');
        $in = $this->receive($a, $this->qc1)->assertCreated()->json('data');
        $this->assertSame([30, 'QC01 — Quality QC01', 'Final Inspection', 'Quality · FLR-QC', '10:00', '2026-11-06 10:00'], [$in['qty'], $in['line'], $in['section'], $in['floor'], $in['time'], $in['previous']]);
        $this->receive($a, $this->qc2)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} was already received into Quality on 06-11-2026 (QC01 — Quality QC01).");
        $this->assertSame('IN_QUALITY', $this->resolve($a, $this->qc1, 'in')->json('data.bundle.stage'));

        $this->assertSame([$a], array_column($this->as()->getJson('/api/v1/assembly/quality/recent?kind=in')->json('data.rows'), 'bundle_no'));
        $this->as()->getJson('/api/v1/assembly/quality/recent?kind=inspect')->assertJsonCount(0, 'data.rows');
        $l = $this->as()->getJson('/api/v1/assembly/quality/line?line_id='.$this->qc1->id)->assertOk()->json('data');
        $this->assertSame([1, 30, 0, 1, 30, 400], [$l['today_in'], $l['today_in_qty'], $l['inspected'], $l['wip_bundles'], $l['pending_qty'], $l['daily_target']]);
    }

    public function test_inspection_rounds_the_rework_and_quality_out(): void
    {
        $a = $this->finished('S', 1, 36, 30, 2, 4);
        $b = $this->finished('M', 1, 20, 20, 0, 0);
        $c = $this->finished('L', 1, 10, 10, 0, 0);
        $this->receive($a, $this->qc1)->assertCreated();
        $this->receive($b, $this->qc2)->assertCreated();

        $this->inspect($c, $this->qc1, 10, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$c} has not been received into Quality.");
        $this->inspect($b, $this->qc1, 20, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$b} is on QC02 — Quality QC02 — scan it there.");
        $this->out($a, $this->qc1)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} has not been inspected yet.");

        // What the desk would judge: the whole bundle, first round.
        $r = $this->resolve($a, $this->qc1, 'inspect')->json('data');
        $this->assertSame([30, 1], [$r['inspection']['qty'], $r['inspection']['round']]);
        $this->inspect($a, $this->qc1, 25, 2, 2, 'STITCHING')->assertUnprocessable()->assertJsonPath('errors.pass_qty.0', 'Pass + Reject + Rework (29) must equal the quantity being inspected (30).');
        $this->inspect($a, $this->qc1, 25, 2, 3)->assertUnprocessable()->assertJsonPath('errors.defect_type.0', 'Pick the defect type for the reject or rework.');
        $this->inspect($a, $this->qc1, 25, 2, 3, 'OTHER')->assertUnprocessable()->assertJsonPath('errors.remark.0', 'Describe the defect in the remark when the type is Other.');
        $this->inspect($b, $this->qc2, 20, 0, 0, 'FABRIC')->assertUnprocessable()->assertJsonPath('errors.defect_type.0', 'A defect type goes only with reject or rework.');

        $first = $this->inspect($a, $this->qc1, 25, 2, 3, 'STITCHING', 'Open seam')->assertCreated()->json('data');
        $this->assertSame([1, 30, 25, 2, 3, 'STITCHING', 'Open seam', 'PENDING'], [$first['round'], $first['inspected'], $first['pass'], $first['reject'], $first['rework'], $first['defect_type'], $first['remark'], $first['rework_status']]);
        $this->inspect($b, $this->qc2, 20, 0, 0)->assertCreated()->assertJsonPath('data.rework_status', null);

        // While the rework is open the bundle can be neither inspected again nor released.
        $r = $this->resolve($a, $this->qc1, 'out')->json('data');
        $this->assertSame(['REWORK_PENDING', 'REWORK_PENDING', 3, 'PENDING', 3, "Bundle {$a} has 3 pcs of rework still open (Rework Pending) — it must be completed and re-inspected first."],
            [$r['bundle']['stage'], $r['bundle']['final_status'], $r['bundle']['open_rework'], $r['rework']['status'], $r['rework']['qty'], $r['blocker']]);
        $this->inspect($a, $this->qc1, 3, 0, 0)->assertUnprocessable();
        $this->out($a, $this->qc1)->assertUnprocessable();

        // Only a supervisor moves the rework on; once completed the 3 pieces come back for round 2.
        $rework = $r['rework']['inspection_id'];
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $rework, 'status' => 'PROCESSING'])->assertForbidden();
        $this->as()->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $rework, 'status' => 'PROCESSING'])->assertOk()->assertJsonPath('data.rework_status', 'PROCESSING')->assertJsonPath('data.bundle_no', $a);
        $this->assertSame('REWORK_PROCESSING', $this->resolve($a, $this->qc1, 'inspect')->json('data.bundle.stage'));
        $this->travelTo(CarbonImmutable::parse('2026-11-06 11:30:00', 'Asia/Dhaka'));
        $this->as()->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $rework, 'status' => 'COMPLETED'])->assertOk();
        $this->as()->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $rework, 'status' => 'COMPLETED'])->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "Bundle {$a}'s rework is Rework Completed — it cannot move to Rework Completed.");
        $this->out($a, $this->qc1)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} has 3 pcs of rework back for re-inspection — inspect them first.");
        $r = $this->resolve($a, $this->qc1, 'inspect')->json('data');
        $this->assertSame(['READY_REINSPECT', 3, 2, null], [$r['bundle']['stage'], $r['inspection']['qty'], $r['inspection']['round'], $r['rework']]);
        $this->inspect($a, $this->qc1, 2, 1, 0, 'MEASUREMENT')->assertCreated()->assertJsonPath('data.round', 2);
        $this->inspect($a, $this->qc1, 1, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} has been inspected and nothing is left to judge — release it at Quality OUT.");

        // Quality OUT: what the rounds came to.
        $r = $this->resolve($a, $this->qc1, 'out')->json('data');
        $this->assertSame(['INSPECTED', 'PASS', 33, 27, 3, 3, 0], [$r['bundle']['stage'], $r['bundle']['final_status'], $r['bundle']['inspected'], $r['bundle']['pass'], $r['bundle']['reject'], $r['bundle']['rework'], $r['bundle']['open_rework']]);
        $this->out($a, $this->qc2)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} is on QC01 — Quality QC01 — scan it there.");
        $out = $this->out($a, $this->qc1, '  Shade ok ')->assertCreated()->json('data');
        $this->assertSame([30, 27, 3, 3, 'PASS', 'Shade ok'], [$out['qty'], $out['pass'], $out['reject'], $out['rework'], $out['final_status'], $out['remarks']]);
        $this->out($a, $this->qc1)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} has already gone Quality OUT.");
        $this->inspect($a, $this->qc1, 1, 0, 0)->assertUnprocessable()->assertJsonPath('errors.code.0', "Bundle {$a} has already gone Quality OUT.");

        // A bundle rejected outright still leaves Quality, as a reject.
        $this->receive($c, $this->qc1)->assertCreated();
        $this->inspect($c, $this->qc1, 0, 10, 0, 'FABRIC')->assertCreated();
        $this->out($c, $this->qc1)->assertCreated()->assertJsonPath('data.final_status', 'REJECT');

        $this->assertSame([$c, $a], array_column($this->as()->getJson('/api/v1/assembly/quality/recent?kind=out')->json('data.rows'), 'bundle_no'));
        $this->assertSame([[2, 1], [1, 2]], array_map(fn ($r) => [$r['round'], $r['reject']], array_values(array_filter($this->as()->getJson('/api/v1/assembly/quality/recent?kind=inspect')->json('data.rows'), fn ($r) => $r['bundle_no'] === $a))));

        // The bundle's way from cutting to Quality OUT.
        $trace = $this->as()->getJson('/api/v1/assembly/quality/trace?code='.str_replace('-', '', $a))->assertOk()->json('data');
        $this->assertSame(['CUT', 'PRINT', 'SEW_IN', 'SEW_OUT', 'FIN_IN', 'FIN_OUT', 'REWORK_DONE', 'QM_IN', 'QM_INSPECT', 'QM_REWORK_START', 'QM_REWORK_DONE', 'QM_INSPECT', 'QM_OUT'], array_column($trace['steps'], 'stage'));
        $this->assertSame(['QUALITY_OUT', 'Stitching Defect', 2, 'Pass / Ready for Next Process', '2026-11-06 11:30'], [$trace['bundle']['stage'], $trace['steps'][8]['params']['defect'], $trace['steps'][11]['params']['round'], $trace['steps'][12]['params']['status'], $trace['steps'][10]['at']]);
    }

    public function test_the_dashboard_reads_a_range_a_shift_a_view_and_the_trend(): void
    {
        // Yesterday 21:00 (night) QC02 received d and judged it; today 10:00 (day) QC01 a and QC02 b.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 21:00:00', 'Asia/Dhaka'));
        $d = $this->finished('L', 1, 10, 10, 0, 0, '2026-11-05');
        $this->receive($d, $this->qc2)->assertCreated();
        $this->inspect($d, $this->qc2, 8, 1, 1, 'SEWING')->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
        $a = $this->finished('S', 1, 36, 30, 2, 4);
        $b = $this->finished('M', 1, 20, 20, 0, 0);
        $e = $this->finished('M', 21, 32, 12, 0, 0);   // out of finishing, never received: ready for quality
        $this->receive($a, $this->qc1)->assertCreated();
        $this->receive($b, $this->qc2)->assertCreated();
        $this->inspect($a, $this->qc1, 27, 3, 0, 'FABRIC')->assertCreated();
        $this->out($a, $this->qc1)->assertCreated();

        $today = $this->board()->assertOk()->json('data');
        $this->assertSame(['2026-11-06', '2026-11-06', 'line', 7, null], [$today['from'], $today['to'], $today['view'], $today['trend_days'], $today['shift']]);
        $s = $today['summary'];
        $this->assertEquals([50, 2, 30, 700, 27, 3, 0, 90.0, 10.0, 0.0, 20, 1, 1, 1, 1, 12, 2], [$s['in_qty'], $s['in_bundles'], $s['inspected'], $s['target'], $s['passed'], $s['reject'], $s['rework'], $s['pass_pct'], $s['reject_pct'], $s['rework_pct'],
            $s['pending_qty'], $s['pending_bundles'], $s['out_bundles'], $s['rework_open'], $s['ready_bundles'], $s['ready_qty'], $s['lines']]);
        $this->assertSame([[$e, 12, 'FIN01 — Finishing FIN01']], array_map(fn ($r) => [$r['bundle_no'], $r['qty'], $r['finishing_line']], $today['ready']));
        $this->assertSame([[$b, 20, 'IN_QUALITY']], array_map(fn ($r) => [$r['bundle_no'], $r['to_inspect'], $r['stage']], $today['pending']));
        $this->assertSame([[$b, 'IN_QUALITY'], [$d, 'REWORK_PENDING']], array_map(fn ($r) => [$r['bundle_no'], $r['stage']], $today['wip']));
        $this->assertSame([$d], array_column($today['rework'], 'bundle_no')); // yesterday's open rework shows on every range
        $this->assertSame(['QC03 — Quality QC03'], $today['unfiled']);
        $perf = collect($today['performance'])->keyBy('label');
        $this->assertEquals([400, 30, 30, 27, 3, 0, 90.0, 'Final Inspection · Quality · FLR-QC'], [$perf['QC01 — Quality QC01']['target'], $perf['QC01 — Quality QC01']['in_qty'], $perf['QC01 — Quality QC01']['inspected'], $perf['QC01 — Quality QC01']['passed'],
            $perf['QC01 — Quality QC01']['reject'], $perf['QC01 — Quality QC01']['pending'], $perf['QC01 — Quality QC01']['pass_pct'], $perf['QC01 — Quality QC01']['sub']]);
        $this->assertSame([20, 20, 0, null], [$perf['QC02 — Quality QC02']['in_qty'], $perf['QC02 — Quality QC02']['pending'], $perf['QC02 — Quality QC02']['inspected'], $perf['QC02 — Quality QC02']['pass_pct']]);

        // The trend runs over the last 7 days to To.
        $this->assertSame('2026-10-31', $today['trend'][0]['day']);
        $this->assertSame([['2026-11-05', 10, 10, 8, 1, 1], ['2026-11-06', 50, 30, 27, 3, 0]], array_map(fn ($r) => [$r['day'], $r['in_qty'], $r['inspected'], $r['passed'], $r['reject'], $r['rework']], array_slice($today['trend'], 5)));
        $this->assertCount(30, $this->board(['trend' => 30])->json('data.trend'));

        // Two days: a 2-day target and yesterday's inspection.
        $two = $this->board(['from' => '2026-11-05', 'to' => '2026-11-06'])->json('data');
        $this->assertEquals([60, 40, 1400, 35, 4, 1], [$two['summary']['in_qty'], $two['summary']['inspected'], $two['summary']['target'], $two['summary']['passed'], $two['summary']['reject'], $two['summary']['rework']]);
        $this->assertSame([$a, $d], array_column($two['inspections'], 'bundle_no'));

        // The day shift leaves out yesterday's 21:00 scans; QC02 (no shift plan) stays in view.
        $day = $this->board(['from' => '2026-11-05', 'to' => '2026-11-06', 'shift_id' => $this->dayShift])->json('data');
        $this->assertSame(['Day shift', 2, 2, 30], [$day['shift'], $day['summary']['lines'], $day['summary']['in_bundles'], $day['summary']['inspected']]);

        $this->assertSame([['Final Inspection', 2, 700, 30]], array_map(fn ($r) => [$r['label'], $r['lines'], $r['target'], $r['inspected']], $this->board(['view' => 'section'])->json('data.performance')));
        $this->assertSame('Dhaka Unit 1', $this->board(['view' => 'unit'])->json('data.performance.0.label'));
        $this->assertSame(1, $this->board(['line_id' => $this->qc2->id])->json('data.summary.lines'));
        $this->board(['from' => '2026-11-06', 'to' => '2026-11-05'])->assertUnprocessable()->assertJsonPath('errors.to.0', 'To must be on or after From.');
        $this->board(['trend' => 14])->assertUnprocessable();

        // Completed rework shows only in the range it was completed in, and comes back as pending inspection.
        $this->as()->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $today['rework'][0]['id'], 'status' => 'COMPLETED'])->assertOk();
        $now = $this->board()->json('data');
        $this->assertSame([[$d], 0, 21, 2], [array_column($now['rework'], 'bundle_no'), $now['summary']['rework_open'], $now['summary']['pending_qty'], $now['summary']['pending_bundles']]);
        $this->assertSame([], $this->board(['from' => '2026-11-05', 'to' => '2026-11-05'])->json('data.rework'));
    }

    public function test_who_may_scan_and_who_may_supervise(): void
    {
        $a = $this->finished('S', 1, 36, 36, 0, 0);
        $loc = ['code' => $a, 'section_id' => $this->qcSection, 'floor_id' => $this->qcFloor->id, 'line_id' => $this->qc1->id];
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/assembly/quality/ins', $loc)->assertCreated();
        $id = $this->withToken($chief)->postJson('/api/v1/assembly/quality/inspections', $loc + ['pass_qty' => 30, 'reject_qty' => 0, 'rework_qty' => 6, 'defect_type' => 'WASHING'])->assertCreated()->json('data.id');
        $this->withToken($chief)->getJson('/api/v1/assembly/quality/dashboard')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $id, 'status' => 'COMPLETED'])->assertForbidden();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->postJson('/api/v1/assembly/quality/rework', ['inspection_id' => $id, 'status' => 'COMPLETED'])->assertOk();
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->getJson('/api/v1/assembly/quality/options')->assertForbidden();
        $tech = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($tech)->postJson('/api/v1/assembly/quality/outs', $loc)->assertForbidden();
    }

    /** A printed card taken through sewing in full. */
    private function sewn(string $size, int $from, int $to, string $date = '2026-11-06'): string
    {
        $no = $this->as()->postJson('/api/v1/cutting/bundles', ['order_id' => $this->order, 'cut_date' => '2026-11-04', 'colour' => 'Black', 'cutting_no' => 1, 'size' => $size, 'serial_from' => $from, 'serial_to' => $to, 'parts_name' => 'Front Panel', 'batch_no' => 'B-1'])
            ->assertCreated()->json('data.bundle_no');
        $this->as()->postJson('/api/v1/cutting/bundles/print', ['bundles' => [$no]])->assertOk();
        $this->as()->postJson('/api/v1/assembly/sewing/ins', ['code' => $no, 'receive_date' => $date, 'section' => 'Sewing', 'floor_id' => $this->sew1->floor_id, 'line_id' => $this->sew1->id, 'short_qty' => 0])->assertCreated();
        $this->as()->postJson('/api/v1/assembly/sewing/outs', ['code' => $no, 'out_date' => $date, 'mode' => 'FULL'])->assertCreated();

        return $no;
    }

    /** Sewn in full, then received onto FIN01 and scanned off with good / reject / rework. */
    private function finished(string $size, int $from, int $to, int $good, int $reject, int $rework, string $date = '2026-11-06'): string
    {
        $no = $this->sewn($size, $from, $to, $date);
        $loc = ['code' => $no, 'section_id' => $this->fin1->section_id, 'floor_id' => $this->fin1->floor_id, 'line_id' => $this->fin1->id];
        $this->as()->postJson('/api/v1/assembly/finishing/ins', $loc)->assertCreated();
        $this->as()->postJson('/api/v1/assembly/finishing/outs', $loc + ['good_qty' => $good, 'reject_qty' => $reject, 'rework_qty' => $rework])->assertCreated();
        if ($rework > 0) {
            $this->as()->postJson('/api/v1/assembly/finishing/rework', ['bundle_no' => $no, 'status' => 'COMPLETED'])->assertOk();
        }

        return $no;
    }

    private function loc(string $no, ProductionLine $line): array
    {
        return ['code' => $no, 'section_id' => $line->section_id, 'floor_id' => $line->floor_id, 'line_id' => $line->id];
    }

    private function resolve(string $code, ProductionLine $line, string $kind): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/quality/resolve?'.http_build_query(['code' => $code, 'line_id' => $line->id, 'kind' => $kind]));
    }

    private function receive(string $no, ProductionLine $line): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/quality/ins', $this->loc($no, $line));
    }

    private function inspect(string $no, ProductionLine $line, int $pass, int $reject, int $rework, ?string $defect = null, ?string $remark = null): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/quality/inspections', $this->loc($no, $line) + ['pass_qty' => $pass, 'reject_qty' => $reject, 'rework_qty' => $rework, 'defect_type' => $defect, 'remark' => $remark]);
    }

    private function out(string $no, ProductionLine $line, ?string $remarks = null): TestResponse
    {
        return $this->as()->postJson('/api/v1/assembly/quality/outs', $this->loc($no, $line) + ['remarks' => $remarks]);
    }

    private function board(array $q = []): TestResponse
    {
        return $this->as()->getJson('/api/v1/assembly/quality/dashboard?'.http_build_query($q));
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
