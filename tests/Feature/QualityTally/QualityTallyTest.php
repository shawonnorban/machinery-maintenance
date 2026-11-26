<?php

declare(strict_types=1);

namespace Tests\Feature\QualityTally;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Actions\SyncMenus;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Support\LicensedModules;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Quality Tally (AnnotechERP 36): hidden until the platform switches it on;
 * jobs and line targets in, taps out — RFT / Defect / Rectify / Reject with
 * annotech-rmg's formulas (checked = RFT + defect, pass capped at checked,
 * DHU from defect tags); only an assigned QC taps; an offline replay counts
 * once; undo; the tally sheet and dashboard agree.
 */
class QualityTallyTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $qc;

    private string $sewing;

    private string $sewing2;

    private string $cutting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        LicensedModules::forget();
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->qc = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'qc@delta.test');

        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001'])->id;
        $department = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $department, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Production', 'code' => 'PRD', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['sewing' => 'LINE01', 'sewing2' => 'LINE02'] as $prop => $code) {
            $this->{$prop} = (string) Str::ulid();
            DB::table('planning_lines')->insert(['id' => $this->{$prop}, 'company_id' => $this->delta->id, 'floor_id' => $floor, 'code' => $code, 'name' => $code, 'display_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->cutting = (string) Str::ulid();
        DB::table('production_lines')->insert(['id' => $this->cutting, 'company_id' => $this->delta->id, 'name' => 'Cutting 1', 'code' => 'C1', 'department_id' => $department, 'line_type' => 'CUTTING', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_module_is_hidden_until_the_platform_switches_it_on(): void
    {
        $this->api($this->owner)->getJson('/api/v1/quality-tally/lookups')->assertForbidden();
        $this->assertNotContains('QUALITY_TALLY', $this->moduleCodes());
        $this->assertContains('/quality-tally/sewing-tally', $this->api($this->owner)->getJson('/api/v1/access/locked-modules')->assertOk()->json('data'));

        $staff = PlatformFixture::staff();
        $row = fn ($res) => collect($res->assertOk()->json('data'))->firstWhere('code', 'QUALITY_TALLY');
        $this->assertFalse($row($this->withToken(PlatformFixture::token($staff))->getJson('/api/v1/platform/tenants/'.$this->delta->id.'/modules'))['enabled']);
        $this->assertTrue($row($this->withToken(PlatformFixture::token($staff))->putJson('/api/v1/platform/tenants/'.$this->delta->id.'/modules/QUALITY_TALLY', ['enabled' => true]))['enabled']);
        // A tenant token never finds the platform's switch (404, as for every platform route).
        $this->api($this->owner)->putJson('/api/v1/platform/tenants/'.$this->delta->id.'/modules/QUALITY_TALLY', ['enabled' => false])->assertNotFound();

        $this->api($this->owner)->getJson('/api/v1/quality-tally/lookups')->assertOk();
        $this->assertContains('QUALITY_TALLY', $this->moduleCodes());
        $this->assertNotContains('/quality-tally/sewing-tally', $this->api($this->owner)->getJson('/api/v1/access/locked-modules')->assertOk()->json('data'));

        $this->withToken(PlatformFixture::token($staff))->putJson('/api/v1/platform/tenants/'.$this->delta->id.'/modules/QUALITY_TALLY', ['enabled' => false])->assertOk();
        $this->api($this->owner)->getJson('/api/v1/quality-tally/lookups')->assertForbidden();
    }

    public function test_taps_follow_the_formulas_and_only_an_assigned_qc_taps(): void
    {
        $this->enable();
        [$job, $defects] = $this->setUpFloor();
        $tap = fn (string $action, array $extra = []) => $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'action' => $action] + $extra);

        // The owner is not a QC: no tapping, whatever the role.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'action' => 'RFT'])
            ->assertUnprocessable()->assertJsonValidationErrors('line_id');
        // A line not given to this QC.
        $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing2, 'job_id' => $job, 'action' => 'RFT'])
            ->assertUnprocessable()->assertJsonValidationErrors('line_id');
        // A defect tap needs a defect of the line's section.
        $tap('DEFECT')->assertUnprocessable()->assertJsonValidationErrors('defects');
        $tap('DEFECT', ['defects' => [$defects['CUTTING']]])->assertUnprocessable()->assertJsonValidationErrors('defects');
        $tap('RFT', ['defects' => [$defects['SEWING'][0]]])->assertUnprocessable()->assertJsonValidationErrors('defects');

        foreach (range(1, 8) as $i) {
            $tap('RFT')->assertCreated();
        }
        $tap('DEFECT', ['defects' => [$defects['SEWING'][0], $defects['SEWING'][1]]])->assertCreated();
        $tap('DEFECT', ['defects' => [$defects['SEWING'][0]]])->assertCreated();
        $tap('RECTIFY')->assertCreated();
        $summary = $tap('REJECT')->assertCreated()->json('data.summary');

        // checked = 8 RFT + 2 defect = 10; pass = min(8 + 1, 10) = 9; DHU = 3 tags / 10 = 30 %.
        $this->assertSame([8, 2, 1, 1, 10, 9, 3], [$summary['rft'], $summary['defect'], $summary['rectify'], $summary['reject'], $summary['checked'], $summary['pass'], $summary['defect_tags']]);
        $this->assertEquals(30.0, $summary['dhu']);
        $this->assertEquals(80.0, $summary['rft_pct']);
        $this->assertSame(100, $summary['target']);
        $this->assertEquals(9.0, $summary['achievement']);
        $this->assertSame(13, $summary['hourly_target']); // 100 ÷ 8 h, rounded
        $this->assertSame(12, $summary['mine']);
        // Each hour's DHU counts that hour's defect tags too.
        $this->assertEquals(30.0, $summary['hourly'][0]['dhu']);

        // Rectify can never push pass above checked.
        foreach (range(1, 5) as $i) {
            $tap('RECTIFY')->assertCreated();
        }
        $this->assertSame(10, $tap('RECTIFY')->json('data.summary.pass'));

        // A closed job takes no more taps.
        $this->api($this->owner)->patchJson("/api/v1/quality-tally/jobs/{$job}", ['job_no' => 'J-100', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => '', 'color' => 'Navy', 'order_qty' => 1000, 'status' => 'CLOSED'])->assertOk();
        $tap('RFT')->assertUnprocessable()->assertJsonValidationErrors('job_id');
    }

    public function test_an_offline_replay_counts_once_and_undo_takes_back_the_last_tap(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        $entry = fn (string $uuid, string $action = 'RFT', ?string $at = null) => ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'action' => $action, 'client_uuid' => $uuid, 'occurred_at' => $at];
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();
        $earlier = QtBook::now()->subMinutes(30)->toIso8601String();

        $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', $entry($a, 'RFT', $earlier))->assertCreated();
        // The same tap again (the tablet retried): 200, not counted twice.
        $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', $entry($a, 'RFT', $earlier))->assertOk()->assertJsonPath('data.duplicate', true);

        $res = $this->api($this->qc)->postJson('/api/v1/quality-tally/tally/bulk', ['entries' => [
            $entry($a), $entry($b, 'REJECT'), $entry((string) Str::uuid(), 'DEFECT'), // repeat · new · refused (no defects)
            $entry((string) Str::uuid(), 'RFT', QtBook::now()->subDays(10)->toIso8601String()), // too old
        ]])->assertOk();
        $this->assertSame([1, 1, 2], [$res->json('data.recorded'), $res->json('data.duplicate'), count($res->json('data.rejected'))]);
        $this->assertSame(2, DB::table('qt_entries')->where('section', 'SEWING')->count());
        $this->assertTrue((bool) DB::table('qt_entries')->where('client_uuid', $a)->value('synced_late'));

        $undo = $this->api($this->qc)->postJson('/api/v1/quality-tally/tally/undo', ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job])->assertOk();
        $this->assertSame('REJECT', $undo->json('data.undone.action'));
        $this->assertSame(1, DB::table('qt_entries')->where('section', 'SEWING')->count());
    }

    public function test_the_sheet_dashboard_and_jobs_agree(): void
    {
        $this->enable();
        [$job, $defects] = $this->setUpFloor();
        $tap = fn (string $section, string $line, string $action, array $tags = [], array $extra = []) => $this->api($this->qc)
            ->postJson('/api/v1/quality-tally/tally', ['section' => $section, 'line_id' => $line, 'job_id' => $job, 'action' => $action, 'defects' => $tags] + $extra)->assertCreated();
        foreach (range(1, 3) as $i) {
            $tap('SEWING', $this->sewing, 'RFT');
        }
        $tap('SEWING', $this->sewing, 'DEFECT', [$defects['SEWING'][0]]);
        // A cutting tap is one panel of a bundle the cutting manager gave.
        $panel = ['panel_id' => DB::table('qt_panels')->where('garment_type', 'T-Shirt')->value('id'), 'cut_bundle_id' => $this->giveBundle($job, 'B-1')->assertCreated()->json('data.id')];
        $tap('CUTTING', $this->cutting, 'RFT', [], $panel);
        $tap('CUTTING', $this->cutting, 'RFT', [], $panel);

        $sheet = $this->api($this->owner)->getJson('/api/v1/quality-tally/sheet?'.http_build_query(['section' => 'SEWING', 'line_id' => $this->sewing]))->assertOk()->json('data');
        $this->assertSame([4, 3, 1], [$sheet['totals']['checked'], $sheet['totals']['pass'], $sheet['totals']['defect_tags']]);
        $this->assertSame(1, $sheet['defects'][0]['total']);
        $this->assertSame(3, array_sum(array_column($sheet['hours'], 'pass')));
        // 3 pass × 12 SMV ÷ (20 × 8 h × 60) = 0.38 %.
        $this->assertEquals(0.38, $sheet['efficiency']);

        $board = $this->api($this->owner)->getJson('/api/v1/quality-tally/dashboard')->assertOk()->json('data');
        $this->assertSame(3, $board['sections']['SEWING']['pass']);
        $this->assertSame(2, $board['sections']['CUTTING']['pass']);
        $this->assertEquals(25.0, $board['sections']['SEWING']['dhu']);
        // Across sections the same garment is counted twice: no target, pass or achievement total.
        $this->assertTrue($board['totals']['mixed']);
        $this->assertNull($board['totals']['target']);
        $this->assertNull($board['totals']['pass']);
        $this->assertSame(6, $board['totals']['checked']);
        $this->assertEquals(25.0, collect($board['hourly'])->firstWhere('section', 'SEWING')['dhu']);

        // A job's pass is per section: the same garment passes cutting and sewing.
        $jobs = $this->api($this->owner)->getJson('/api/v1/quality-tally/jobs')->assertOk()->json('data');
        $this->assertSame(['CUTTING' => 82, 'SEWING' => 3, 'QUALITY' => 0], $jobs[0]['pass']);
        // A tapped job cannot be deleted.
        $this->api($this->owner)->deleteJson("/api/v1/quality-tally/jobs/{$job}")->assertUnprocessable();

        $history = $this->api($this->owner)->getJson('/api/v1/quality-tally/history?section=SEWING')->assertOk()->json('data');
        $this->assertSame(4, $history['total']);
    }

    public function test_night_shift_attendance_efficiency_and_the_planned_day(): void
    {
        $this->enable();
        [$job, $defects] = $this->setUpFloor();
        $day = (string) Str::ulid();
        $night = (string) Str::ulid();
        DB::table('hr_shifts')->insert([
            ['id' => $day, 'company_id' => $this->delta->id, 'code' => 'D', 'name' => 'Day', 'start_time' => '08:00', 'end_time' => '17:00', 'break_min' => 60, 'cross_day' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $night, 'company_id' => $this->delta->id, 'code' => 'N', 'name' => 'Night', 'start_time' => '20:00', 'end_time' => '04:00', 'break_min' => 0, 'cross_day' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $tap = fn (string $action, array $extra = []) => $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'action' => $action] + $extra);

        foreach ([1, 2] as $back) {
            $this->target($job, $this->sewing, QtBook::now()->subDays($back)->toDateString());
        }
        // 02:00 on the night shift belongs to the day the shift started.
        // Yesterday, so the test means the same at any hour it runs.
        $base = QtBook::now()->subDay()->startOfDay();
        $twoAm = $base->addHours(2);
        $tap('RFT', ['shift_id' => $night, 'occurred_at' => $twoAm->toIso8601String()])->assertCreated();
        $this->assertSame($twoAm->subDay()->toDateString(), DB::table('qt_entries')->where('shift_id', $night)->value('production_date'));
        // An unknown shift is refused.
        $tap('RFT', ['shift_id' => (string) Str::ulid()])->assertUnprocessable()->assertJsonValidationErrors('shift_id');

        // Day shift: 3 passes yesterday, then attendance 25 people + 60 min overtime.
        foreach (range(1, 3) as $i) {
            $tap('RFT', ['shift_id' => $day, 'occurred_at' => $base->addHours(10)->toIso8601String()])->assertCreated();
        }
        $this->api($this->owner)->postJson('/api/v1/quality-tally/attendance', ['date' => $base->toDateString(), 'section' => 'SEWING', 'rows' => [
            ['line_id' => $this->sewing, 'shift_id' => $day, 'attendance' => 25, 'overtime_minutes' => 60],
            ['line_id' => $this->sewing2, 'shift_id' => $day, 'attendance' => 0, 'overtime_minutes' => null],
        ]])->assertOk();
        $this->assertSame(1, DB::table('qt_line_attendances')->count());
        $sheet = $this->api($this->owner)->getJson('/api/v1/quality-tally/sheet?'.http_build_query(['date' => $base->toDateString(), 'section' => 'SEWING', 'line_id' => $this->sewing]))->assertOk()->json('data');
        // 3 × 12 SMV = 36 earned ÷ 25 × (480 + 60) = 13,500 man-minutes = 0.27 %.
        $this->assertSame(13500, $sheet['man_minutes']);
        $this->assertEquals(0.27, $sheet['efficiency']);
        $this->assertTrue($sheet['attendance_recorded']);

        // A job carries a size and a due date; the same job no in another size is another job.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/jobs', ['job_no' => 'J-100', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => '', 'color' => 'Navy', 'size' => 'XL', 'order_qty' => 500, 'due_date' => QtBook::now()->addDays(5)->toDateString()])
            ->assertCreated();
        $xl = collect($this->api($this->owner)->getJson('/api/v1/quality-tally/jobs')->json('data'))->firstWhere('size', 'XL');
        $this->assertSame(5, $xl['days_left']);

        // Plan yesterday in one go; nothing was passed then, so today shows the whole target as the shortfall.
        $yesterday = QtBook::now()->subDay()->toDateString();
        $this->api($this->owner)->postJson('/api/v1/quality-tally/targets/bulk', ['target_date' => $yesterday, 'section' => 'SEWING', 'rows' => [
            ['line_id' => $this->sewing2, 'job_id' => $job, 'working_hours' => 8, 'manpower' => 20, 'target_qty' => 400],
        ]])->assertOk()->assertJsonPath('data.saved', 1);
        $short = collect($this->api($this->owner)->getJson('/api/v1/quality-tally/targets/shortfall?'.http_build_query(['date' => QtBook::today(), 'section' => 'SEWING']))->assertOk()->json('data'));
        $this->assertSame(400, $short->firstWhere('line_id', $this->sewing2)['shortfall']);
        // A row with no target removes it.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/targets/bulk', ['target_date' => $yesterday, 'section' => 'SEWING', 'rows' => [
            ['line_id' => $this->sewing2, 'job_id' => $job, 'working_hours' => 8, 'target_qty' => 0],
        ]])->assertOk()->assertJsonPath('data.removed', 1);
    }

    public function test_analytics_and_the_reports_read_the_same_taps(): void
    {
        $this->enable();
        [$job, $defects] = $this->setUpFloor();
        $tap = fn (string $action, array $tags = []) => $this->api($this->qc)
            ->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'action' => $action, 'defects' => $tags])->assertCreated();
        foreach (range(1, 4) as $i) {
            $tap('RFT');
        }
        $tap('DEFECT', [$defects['SEWING'][0]]);

        $an = $this->api($this->owner)->getJson('/api/v1/quality-tally/analytics?'.http_build_query(['section' => 'SEWING', 'from' => QtBook::now()->subDays(6)->toDateString(), 'to' => QtBook::today()]))
            ->assertOk()->json('data');
        $this->assertCount(7, $an['days']);
        $this->assertSame([5, 4, 100], [$an['totals']['checked'], $an['totals']['pass'], $an['totals']['target']]);
        $this->assertEquals(20.0, $an['totals']['dhu']);
        $this->assertSame(1, $an['top_defects'][0]['count']);
        // A range wider than 90 days is cut to 90.
        $wide = $this->api($this->owner)->getJson('/api/v1/quality-tally/analytics?'.http_build_query(['from' => '2020-01-01', 'to' => QtBook::today()]))->assertOk()->json('data');
        $this->assertCount(90, $wide['days']);

        // The Reporting runner produces the module's report (a small one at once).
        $job1 = $this->api($this->owner)->postJson('/api/v1/report-jobs', ['report' => 'qt_defect_analysis', 'format' => 'csv', 'from' => QtBook::today(), 'to' => QtBook::today(), 'section' => 'SEWING'])
            ->assertOk()->json('data');
        $this->assertTrue($job1['is_downloadable']);
        $this->assertSame(1, $job1['row_count']);
        $daily = $this->api($this->owner)->postJson('/api/v1/report-jobs', ['report' => 'qt_daily_summary', 'format' => 'csv', 'from' => QtBook::today(), 'to' => QtBook::today()])
            ->assertOk()->json('data');
        $this->assertSame(1, $daily['row_count']);
    }

    public function test_cutting_is_tapped_panel_by_panel_and_the_inspection_is_read_from_the_taps(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        $front = DB::table('qt_panels')->where('garment_type', 'T-Shirt')->where('name', 'Front Part')->value('id');
        $back = DB::table('qt_panels')->where('garment_type', 'T-Shirt')->where('name', 'Back Part')->value('id');
        $hood = DB::table('qt_panels')->where('garment_type', 'Hoody')->where('name', 'Hood Top')->value('id');
        // The cut panel form's own codes are the cutting standard (B … Y1).
        $code = fn (string $c) => DB::table('qt_defect_types')->where('section', 'CUTTING')->where('code', $c)->value('id');
        [$c3, $f, $o] = [$code('C3'), $code('F'), $code('O')];
        $this->assertNotNull($c3);

        $bundle = $this->giveBundle($job, 'B-44', ['cutting_no' => '22', 'batch_no' => '8822', 'size' => 'M', 'serial_from' => 1, 'serial_to' => 3, 'qty' => 3])->assertCreated()->json('data.id');
        $tap = fn (string $action, ?string $panel, array $tags = [], array $extra = []) => $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', array_merge(
            ['section' => 'CUTTING', 'line_id' => $this->cutting, 'job_id' => $job, 'action' => $action, 'cut_bundle_id' => $bundle, 'panel_id' => $panel, 'defects' => $tags], $extra,
        ));

        // No bundle, no panel, a Hoody panel on a T-Shirt bundle: refused.
        $tap('RFT', $back, [], ['cut_bundle_id' => null])->assertUnprocessable()->assertJsonValidationErrors('cut_bundle_id');
        $tap('RFT', null)->assertUnprocessable()->assertJsonValidationErrors('panel_id');
        $tap('RFT', $hood)->assertUnprocessable()->assertJsonValidationErrors('panel_id');

        // Back: 3 RFT, and no fourth (the bundle holds three). Front: 2 defects (one with two codes), one put right, one rejected.
        foreach (range(1, 3) as $i) {
            $tap('RFT', $back)->assertCreated();
        }
        $tap('RFT', $back)->assertUnprocessable()->assertJsonValidationErrors('panel_id');
        $tap('DEFECT', $front, [$c3])->assertCreated();
        $tap('DEFECT', $front, [$f, $o])->assertCreated();
        $tap('RECTIFY', $front)->assertCreated();
        $summary = $tap('REJECT', $front)->assertCreated()->json('data.summary');
        $this->assertSame([5, 1, 1], [$summary['cutting']['lot_checked'], $summary['cutting']['lot_reject'], $summary['cutting']['bundles']]);
        $this->assertEquals(['checked' => 2, 'pass' => 1, 'defect' => 2, 'rectify' => 1, 'reject' => 1], $summary['cutting']['panels'][$front]);

        // The inspection is the taps, lot by lot.
        $list = $this->api($this->owner)->getJson('/api/v1/quality-tally/panel-inspections')->assertOk()->json('data.rows');
        $this->assertCount(1, $list);
        $this->assertSame([5, 1, 1], [$list[0]['checked'], $list[0]['reject'], $list[0]['bundles']]);
        $sheet = $this->api($this->owner)->getJson('/api/v1/quality-tally/panel-inspections/lot?'.http_build_query($list[0]['key']))->assertOk()->json('data');
        $rows = collect($sheet['rows'])->keyBy('panel');
        $this->assertSame([3, 0], [$rows['Back Part']['checked'], $rows['Back Part']['reject']]);
        $this->assertSame([2, 1, 1], [$rows['Front Part']['checked'], $rows['Front Part']['rectify'], $rows['Front Part']['reject']]);
        $this->assertSame([1, 1, 1], [$rows['Front Part']['defects'][$c3], $rows['Front Part']['defects'][$f], $rows['Front Part']['defects'][$o]]);
        $this->assertEquals(20.0, $sheet['totals']['reject_pct']);

        // The dashboard's cutting figures come from the same taps.
        $board = $this->api($this->owner)->getJson('/api/v1/quality-tally/dashboard')->assertOk()->json('data');
        $this->assertSame([5, 1, 1], [$board['panels']['checked'], $board['panels']['reject'], $board['panels']['reports']]);
        // A panel tapped on cannot be deleted.
        $this->api($this->owner)->deleteJson('/api/v1/quality-tally/panels/'.$back)->assertUnprocessable();
    }

    public function test_a_job_runs_from_cutting_to_quality_and_the_target_is_the_input(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        DB::table('qt_entries')->delete();
        DB::table('qt_cut_bundles')->delete();
        $panels = DB::table('qt_panels')->where('garment_type', 'T-Shirt')->where('active', true)->orderBy('sort_order')->get(['id', 'name', 'per_piece']);
        $this->assertSame(['Back Part' => 1, 'Front Part' => 1, 'Sleeve' => 2], $panels->pluck('per_piece', 'name')->all());
        [$back, $front, $sleeve] = $panels->pluck('id')->all();
        $tap = fn (string $section, string $line, string $action, array $extra = []) => $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', ['section' => $section, 'line_id' => $line, 'job_id' => $job, 'action' => $action] + $extra);
        $pending = fn (string $section) => $this->api($this->owner)->getJson('/api/v1/quality-tally/targets/pending?section='.$section)->assertOk()->json('data.rows.0');

        // Nothing has passed cutting yet: sewing's QC has nothing to check, though the line has its target.
        $tap('SEWING', $this->sewing, 'RFT')->assertUnprocessable()->assertJsonValidationErrors('job_id');

        // A card is given once; a job is one garment.
        $bundle = $this->giveBundle($job, 'B-1', ['size' => 'M', 'qty' => 2])->assertCreated()->json('data.id');
        $this->giveBundle($job, 'B-1')->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->giveBundle($job, 'B-2', ['garment_type' => 'Hoody'])->assertUnprocessable()->assertJsonValidationErrors('garment_type');
        $this->assertSame('INPUT', $this->api($this->qc)->getJson('/api/v1/quality-tally/cut-bundles/scan?code=b1')->assertOk()->json('data.found'));
        $this->assertNull($this->api($this->qc)->getJson('/api/v1/quality-tally/cut-bundles/scan?code=ZZ9')->assertOk()->json('data.found'));

        // Two pieces' worth of every panel, but one sleeve short: a piece needs both, so one complete piece.
        foreach ([[$back, 2], [$front, 2], [$sleeve, 3]] as [$panel, $times]) {
            foreach (range(1, $times) as $n) {
                $tap('CUTTING', $this->cutting, 'RFT', ['cut_bundle_id' => $bundle, 'panel_id' => $panel])->assertCreated();
            }
        }
        $flow = fn () => $this->api($this->owner)->getJson('/api/v1/quality-tally/flow')->assertOk()->json('data.0');
        $this->assertSame(1, $flow()['cutting']['pieces']);
        $tap('CUTTING', $this->cutting, 'RFT', ['cut_bundle_id' => $bundle, 'panel_id' => $sleeve])->assertCreated();
        $this->assertSame([2, 2], [$flow()['cutting']['pieces'], $flow()['sewing']['waiting']]);
        // The bundle of two holds four sleeves, and no fifth.
        $tap('CUTTING', $this->cutting, 'RFT', ['cut_bundle_id' => $bundle, 'panel_id' => $sleeve])->assertUnprocessable()->assertJsonValidationErrors('panel_id');
        // A checked bundle is not taken back.
        $this->api($this->owner)->deleteJson("/api/v1/quality-tally/cut-bundles/{$bundle}")->assertUnprocessable();

        // The sewing manager sees the order: 1,000 ordered, none passed, 2 ready, 100 planned today on one line.
        $row = $pending('SEWING');
        $this->assertSame([1000, 0, 1000, 2, 100, 2], [$row['order_qty'], $row['done'], $row['left'], $row['ready'], $row['planned'], $row['suggest']]);
        // Cutting's is in pieces (complete), its target in panels: 998 left × 4 panels (two sleeves).
        $row = $pending('CUTTING');
        $this->assertSame([2, 998, null, 4, 3992], [$row['done'], $row['left'], $row['ready'], $row['panels'], $row['suggest']]);

        // Sewing checks what is ready, then no more.
        $this->assertSame(1, $tap('SEWING', $this->sewing, 'RFT')->assertCreated()->json('data.summary.ready'));
        $tap('SEWING', $this->sewing, 'RFT')->assertCreated();
        $tap('SEWING', $this->sewing, 'RFT')->assertUnprocessable()->assertJsonValidationErrors('job_id');
        // A line with no target that day is not tapped on at all.
        DB::table('qt_line_targets')->where('section', 'SEWING')->delete();
        DB::table('qt_entries')->where('section', 'SEWING')->limit(1)->delete();
        $this->assertStringContainsString('target', $tap('SEWING', $this->sewing, 'RFT')->assertUnprocessable()->json('errors.job_id.0'));
    }

    public function test_each_section_has_its_own_manager_and_qc_who_see_their_own_menu_only(): void
    {
        $this->enable();
        app(SyncMenus::class)->handle();
        [$job] = $this->setUpFloor();
        $manager = TenantFixture::user($this->delta, 'QT_CUTTING_MANAGER', 'cut@delta.test');
        $qc = TenantFixture::user($this->delta, 'QT_SEWING_QC', 'sew@delta.test');
        $head = TenantFixture::user($this->delta, 'QT_SEWING_MANAGER', 'sewm@delta.test');
        $menus = fn (User $u) => collect($this->api($u)->getJson('/api/v1/access/navigation')->assertOk()->json('data'))->where('code', 'QUALITY_TALLY')
            ->flatMap(fn ($m) => collect($m['entries'])->flatMap(fn ($e) => $e['type'] === 'folder' ? collect($e['items'])->pluck('code') : [$e['code']]))->values()->all();

        $this->assertSame(['QUALITY_TALLY.CUTTING_DASHBOARD', 'QUALITY_TALLY.CUTTING_INPUT', 'QUALITY_TALLY.CUTTING_TARGET', 'QUALITY_TALLY.CUTTING_ATTENDANCE', 'QUALITY_TALLY.CUT_PANEL', 'QUALITY_TALLY.CUTTING_SHEET', 'QUALITY_TALLY.CUTTING_HISTORY', 'QUALITY_TALLY.JOBS'], $menus($manager));
        $this->assertSame(['QUALITY_TALLY.SEWING_DASHBOARD', 'QUALITY_TALLY.SEWING_TALLY', 'QUALITY_TALLY.SEWING_SHEET', 'QUALITY_TALLY.SEWING_HISTORY'], $menus($qc));

        // Reading: their own section, never another's, never all at once.
        foreach (['dashboard', 'targets', 'history', 'attendance', 'targets/pending'] as $path) {
            $this->api($manager)->getJson("/api/v1/quality-tally/{$path}?section=CUTTING")->assertOk();
            $this->api($manager)->getJson("/api/v1/quality-tally/{$path}?section=SEWING")->assertForbidden();
        }
        $this->api($manager)->getJson('/api/v1/quality-tally/dashboard')->assertForbidden();
        $this->api($manager)->getJson('/api/v1/quality-tally/flow')->assertForbidden();
        $this->api($qc)->getJson('/api/v1/quality-tally/cut-bundles')->assertForbidden();
        $lookups = $this->api($manager)->getJson('/api/v1/quality-tally/lookups')->assertOk()->json('data');
        $this->assertSame([['CUTTING'], ['CUTTING'], ['CUTTING']], [$lookups['managed'], $lookups['sections'], array_keys($lookups['lines'])]);

        // The managers keep the jobs; QC Assignment stays with the owner, the factory admin and management.
        $newJob = fn (User $u, string $no) => $this->api($u)->postJson('/api/v1/quality-tally/jobs', ['job_no' => $no, 'buyer_name' => 'H&M', 'style' => 'Tee', 'po_no' => '', 'color' => 'Red', 'order_qty' => 10]);
        $newJob($manager, 'J-201')->assertCreated();
        $newJob($head, 'J-202')->assertCreated();
        $newJob($qc, 'J-203')->assertForbidden();
        $this->api($manager)->getJson('/api/v1/quality-tally/inspectors')->assertForbidden();
        $this->api($manager)->postJson('/api/v1/quality-tally/inspectors', ['user_id' => $qc->id, 'hr_employee_id' => null, 'lines' => []])->assertForbidden();

        // Writing: the manager their own section; the QC nothing but taps.
        $target = fn (User $u, string $section, string $line) => $this->api($u)->postJson('/api/v1/quality-tally/targets', ['target_date' => QtBook::today(), 'section' => $section, 'line_id' => $line, 'job_id' => $job, 'working_hours' => 8, 'target_qty' => 50]);
        $target($manager, 'CUTTING', $this->cutting)->assertCreated();
        $target($manager, 'SEWING', $this->sewing2)->assertForbidden();
        $target($qc, 'SEWING', $this->sewing2)->assertForbidden();
        $mine = $target($head, 'SEWING', $this->sewing2)->assertCreated()->json('data.id');
        // Another section's target cannot be taken over by calling it one's own.
        $this->api($manager)->patchJson("/api/v1/quality-tally/targets/{$mine}", ['target_date' => QtBook::today(), 'section' => 'CUTTING', 'line_id' => $this->cutting, 'job_id' => $job, 'working_hours' => 8, 'target_qty' => 1])->assertForbidden();
        $this->giveBundle($job, 'B-9', [], $manager)->assertCreated();
        $this->giveBundle($job, 'B-10', [], $head)->assertForbidden();

        // A sewing QC, given a sewing and a cutting line, taps in sewing only.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/inspectors', ['user_id' => $qc->id, 'hr_employee_id' => null, 'lines' => [
            ['section' => 'SEWING', 'line_id' => $this->sewing2], ['section' => 'CUTTING', 'line_id' => $this->cutting],
        ]])->assertCreated();
        $this->assertSame(['SEWING'], array_values(array_unique(array_column($this->api($qc)->getJson('/api/v1/quality-tally/tally/context')->assertOk()->json('data.lines'), 'section'))));
        $this->api($qc)->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing2, 'job_id' => $job, 'action' => 'RFT'])->assertCreated();
        $this->api($qc)->postJson('/api/v1/quality-tally/tally', ['section' => 'CUTTING', 'line_id' => $this->cutting, 'job_id' => $job, 'action' => 'RFT'])->assertForbidden();
        $this->api($manager)->postJson('/api/v1/quality-tally/tally', ['section' => 'CUTTING', 'line_id' => $this->cutting, 'job_id' => $job, 'action' => 'RFT'])->assertForbidden();
    }

    public function test_a_running_job_starts_from_its_opening_pieces(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        $save = fn (array $opening) => $this->api($this->owner)->patchJson("/api/v1/quality-tally/jobs/{$job}", ['job_no' => 'J-100', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => '', 'color' => 'Navy', 'order_qty' => 1000] + $opening);
        // No section can have passed more than the one before it.
        $save(['opening_cut' => 100, 'opening_sewing' => 101])->assertUnprocessable()->assertJsonValidationErrors('opening_sewing');
        $save(['opening_cut' => 100, 'opening_sewing' => 60, 'opening_quality' => 50])->assertOk();

        // 100 opening + 20 pieces out of the bundles; sewing had passed 60 of them, quality 50 of those.
        $flow = $this->api($this->owner)->getJson('/api/v1/quality-tally/flow')->assertOk()->json('data.0');
        $this->assertSame([120, 60, 60, 10, 50], [$flow['cutting']['pieces'], $flow['sewing']['waiting'], $flow['sewing']['pass'], $flow['quality']['waiting'], $flow['quality']['pass']]);
        $this->assertEquals(5.0, $flow['done_pct']);
        // Closing the job (no opening sent) keeps them.
        $save(['status' => 'CLOSED'])->assertOk();
        $this->assertSame(100, (int) DB::table('qt_jobs')->where('id', $job)->value('opening_cut'));
    }

    public function test_hr_gives_the_qc_their_shift(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        $shift = fn (string $code, string $start, string $end) => \App\Modules\HrPayroll\Support\FactoryCalendarSetup::shift($this->delta->id, $code, $code, $start, $end, 60);
        [$day, $night] = [$shift('DAY', '08:00', '17:00'), $shift('NIGHT', '20:00', '05:00')];
        $employee = (string) Str::ulid();
        $shifts = QtBook::shifts($this->delta->id);
        $at = fn (string $t) => \Carbon\CarbonImmutable::parse($t, QtBook::TZ);
        $of = function (string $t, ?string $who) use ($at, $shifts): array {
            $s = QtBook::shiftOf($who, $at($t), $shifts);

            return [$s?->code, QtBook::productionDate($at($t), $s)];
        };

        // Nobody's: the clock decides, and between shifts it is the overtime of the one that ended.
        $this->assertSame([['DAY', '2026-11-25'], ['DAY', '2026-11-25'], ['NIGHT', '2026-11-25'], ['NIGHT', '2026-11-24'], ['NIGHT', '2026-11-24']],
            [$of('2026-11-25 10:00', null), $of('2026-11-25 18:30', null), $of('2026-11-25 21:00', null), $of('2026-11-25 02:00', null), $of('2026-11-25 06:30', null)]);

        // On HR's roster: day until the 24th, night from the 25th.
        DB::statement('alter table hr_shift_rosters drop constraint hr_shift_rosters_employee_id_foreign');
        foreach ([[$day, '2026-11-01', '2026-11-24'], [$night, '2026-11-25', null]] as [$id, $from, $to]) {
            DB::table('hr_shift_rosters')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'employee_id' => $employee, 'shift_id' => $id, 'from_date' => $from, 'to_date' => $to, 'weekly_off' => '["FRI"]', 'mode' => 'INDIVIDUAL', 'batch' => 'RS-1', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame([['DAY', '2026-11-24'], ['NIGHT', '2026-11-25'], ['NIGHT', '2026-11-25'], ['NIGHT', '2026-11-25']],
            [$of('2026-11-24 21:00', $employee), $of('2026-11-25 18:30', $employee), $of('2026-11-26 02:00', $employee), $of('2026-11-26 07:30', $employee)]);

        // A tap carries no shift: the server reads it, and the tablet is not given a list to pick from.
        $this->assertArrayNotHasKey('shifts', $this->api($this->qc)->getJson('/api/v1/quality-tally/tally/context')->assertOk()->json('data'));
        $id = $this->api($this->qc)->postJson('/api/v1/quality-tally/tally', ['section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'action' => 'REJECT'])->assertCreated()->json('data.id');
        $this->assertContains(DB::table('qt_entries')->where('id', $id)->value('shift_id'), [$day, $night]);
    }

    public function test_a_bundle_needs_no_table_and_the_first_to_check_it_takes_it(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        $other = (string) Str::ulid();
        DB::table('production_lines')->insert(['id' => $other, 'company_id' => $this->delta->id, 'name' => 'Cutting 2', 'code' => 'C2', 'department_id' => DB::table('departments')->value('id'), 'line_type' => 'CUTTING', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $qc2 = TenantFixture::user($this->delta, 'QT_CUTTING_QC', 'cut2@delta.test');
        $this->api($this->owner)->postJson('/api/v1/quality-tally/inspectors', ['user_id' => $qc2->id, 'hr_employee_id' => null, 'lines' => [['section' => 'CUTTING', 'line_id' => $other]]])->assertCreated();
        $panel = DB::table('qt_panels')->where('garment_type', 'T-Shirt')->where('name', 'Back Part')->value('id');
        $bundle = $this->giveBundle($job, 'B-77', ['line_id' => null, 'qty' => 5])->assertCreated()->json('data.id');
        $tap = fn (User $u, string $line) => $this->api($u)->postJson('/api/v1/quality-tally/tally', ['section' => 'CUTTING', 'line_id' => $line, 'job_id' => $job, 'action' => 'RFT', 'cut_bundle_id' => $bundle, 'panel_id' => $panel]);
        $listed = fn (User $u, string $line) => in_array($bundle, array_column($this->api($u)->getJson("/api/v1/quality-tally/cut-bundles?line_id={$line}")->assertOk()->json('data.rows'), 'id'), true);

        // Every table sees it until one checks it; then it is that table's.
        $this->assertTrue($listed($this->qc, $this->cutting) && $listed($qc2, $other));
        $tap($this->qc, $this->cutting)->assertCreated();
        $this->assertSame($this->cutting, DB::table('qt_cut_bundles')->where('id', $bundle)->value('line_id'));
        $this->assertFalse($listed($qc2, $other));
        $tap($qc2, $other)->assertUnprocessable()->assertJsonValidationErrors('cut_bundle_id');
        // Its only tap taken back: any table's again.
        $this->api($this->qc)->postJson('/api/v1/quality-tally/tally/undo', ['section' => 'CUTTING', 'line_id' => $this->cutting, 'job_id' => $job])->assertOk();
        $this->assertNull(DB::table('qt_cut_bundles')->where('id', $bundle)->value('line_id'));
        $tap($qc2, $other)->assertCreated();
        // The form's jobs name the garment they are already cut as.
        $this->assertSame('T-Shirt', collect($this->api($this->owner)->getJson('/api/v1/quality-tally/panel-inspections/form')->assertOk()->json('data.jobs'))->firstWhere('value', $job)['garment_type']);
    }

    public function test_a_scanned_card_finds_its_job(): void
    {
        $this->enable();
        [$job] = $this->setUpFloor();
        $order = (string) Str::ulid();
        $other = (string) Str::ulid();
        DB::statement('alter table cut_bundles drop constraint cut_bundles_order_id_foreign');
        $card = function (string $no, string $order, string $style, string $colour, string $po) {
            DB::table('cut_bundles')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'bundle_no' => $no, 'cut_date' => QtBook::today(), 'order_id' => $order, 'po_no' => $po, 'style' => $style, 'colour' => $colour,
                'cutting_no' => 1, 'size' => 'M', 'serial_from' => 1, 'serial_to' => 30, 'qty' => 30, 'parts_name' => 'Front Panel', 'batch_no' => 'B-1', 'created_at' => now(), 'updated_at' => now()]);
        };
        $scan = fn (string $no) => $this->api($this->owner)->getJson('/api/v1/quality-tally/cut-bundles/scan?code='.$no)->assertOk()->json('data.card');

        // Case and spaces do not matter; the job carries no PO, so the card's PO does not contradict it.
        $card('BND-1', $order, 'BASIC  tee', 'navy', 'PO-1');
        $found = $scan('BND1');
        $this->assertSame([$job, 'STYLE', 'T-Shirt'], [$found['job_id'], $found['match'], $found['garment_type']]);
        $this->assertSame('Front Part', DB::table('qt_panels')->where('id', $found['panel_id'])->value('name'));
        // A second job of that style and colour with the card's PO is the surer one.
        $po = $this->api($this->owner)->postJson('/api/v1/quality-tally/jobs', ['job_no' => 'J-101', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => 'PO-1', 'color' => 'Navy', 'order_qty' => 100])->assertCreated()->json('data.id');
        $this->assertSame([$po, 'PO'], [$scan('BND1')['job_id'], $scan('BND1')['match']]);
        // Two equally good jobs are not guessed between: the manager picks …
        $this->api($this->owner)->postJson('/api/v1/quality-tally/jobs', ['job_no' => 'J-102', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => 'PO-1', 'color' => 'Navy', 'order_qty' => 100])->assertCreated();
        $found = $scan('BND1');
        $this->assertSame([null, null, 3], [$found['job_id'], $found['match'], count($found['job_choices'])]);
        // … and the next card of that order and colour follows; another order's does not.
        $this->giveBundle($po, 'BND-1', ['barcode' => 'BND1', 'source_id' => $found['source_id']])->assertCreated();
        $card('BND-2', $order, 'Basic Tee', 'navy', 'PO-1');
        $card('BND-3', $other, 'Basic Tee', 'navy', 'PO-1');
        $this->assertSame([$po, 'LOT'], [$scan('BND2')['job_id'], $scan('BND2')['match']]);
        $this->assertNull($scan('BND3')['job_id']);
        // A style with no job at all.
        $card('BND-4', $other, 'Polo', 'Red', 'PO-9');
        $this->assertSame([null, []], [$scan('BND4')['job_id'], $scan('BND4')['job_choices']]);
    }

    private function target(string $job, string $line, string $date): void
    {
        DB::table('qt_line_targets')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'target_date' => $date, 'section' => 'SEWING', 'line_id' => $line, 'job_id' => $job, 'working_hours' => 8, 'target_qty' => 100, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function giveBundle(string $job, string $no, array $extra = [], ?User $by = null): \Illuminate\Testing\TestResponse
    {
        return $this->api($by ?? $this->owner)->postJson('/api/v1/quality-tally/cut-bundles', $extra + ['bundle_no' => $no, 'job_id' => $job, 'line_id' => $this->cutting, 'garment_type' => 'T-Shirt', 'size' => null, 'qty' => 30]);
    }

    /** @return array{0: string, 1: array{SEWING: list<string>, CUTTING: string}} */
    private function setUpFloor(): array
    {
        $this->api($this->owner)->postJson('/api/v1/quality-tally/defect-types/standard')->assertOk();
        $sewing = DB::table('qt_defect_types')->where('section', 'SEWING')->orderBy('sort_order')->limit(2)->pluck('id')->all();
        $cutting = DB::table('qt_defect_types')->where('section', 'CUTTING')->value('id');

        $job = $this->api($this->owner)->postJson('/api/v1/quality-tally/jobs', ['job_no' => 'J-100', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => '', 'color' => 'Navy', 'order_qty' => 1000, 'smv' => 12])
            ->assertCreated()->json('data.id');
        // The same job no, PO and colour twice is refused.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/jobs', ['job_no' => 'J-100', 'buyer_name' => 'H&M', 'style' => 'Basic Tee', 'po_no' => '', 'color' => 'Navy', 'order_qty' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('job_no');

        $this->api($this->owner)->postJson('/api/v1/quality-tally/targets', ['target_date' => QtBook::today(), 'section' => 'SEWING', 'line_id' => $this->sewing, 'job_id' => $job, 'working_hours' => 8, 'manpower' => 20, 'target_qty' => 100])
            ->assertCreated();
        // A sewing line is not a cutting line.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/targets', ['target_date' => QtBook::today(), 'section' => 'CUTTING', 'line_id' => $this->sewing, 'job_id' => $job, 'working_hours' => 8, 'target_qty' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors('line_id');

        $this->api($this->owner)->postJson('/api/v1/quality-tally/inspectors', ['user_id' => $this->qc->id, 'hr_employee_id' => null, 'lines' => [
            ['section' => 'SEWING', 'line_id' => $this->sewing], ['section' => 'CUTTING', 'line_id' => $this->cutting],
        ]])->assertCreated();

        // Twenty complete pieces are out of cutting (a month ago), so sewing has something to check.
        $this->api($this->owner)->postJson('/api/v1/quality-tally/panels/standard')->assertOk()->assertJsonPath('data.added', 22);
        $bundle = $this->giveBundle($job, 'B-0', ['qty' => 20])->assertCreated()->json('data.id');
        $old = QtBook::now()->subDays(30);
        // A paired part (Sleeve) twice a piece.
        foreach (DB::table('qt_panels')->where('garment_type', 'T-Shirt')->pluck('per_piece', 'id') as $panel => $per) {
            foreach (range(1, 20 * $per) as $n) {
                DB::table('qt_entries')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'section' => 'CUTTING', 'line_id' => $this->cutting, 'job_id' => $job, 'user_id' => $this->qc->id, 'action' => 'RFT', 'cut_bundle_id' => $bundle, 'panel_id' => $panel,
                    'occurred_at' => $old, 'production_date' => $old->toDateString(), 'hour' => 10, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        return [$job, ['SEWING' => $sewing, 'CUTTING' => $cutting]];
    }

    private function enable(): void
    {
        DB::table('company_modules')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'module_code' => 'QUALITY_TALLY', 'created_at' => now(), 'updated_at' => now()]);
        LicensedModules::forget();
    }

    /** @return list<string> */
    private function moduleCodes(): array
    {
        return array_column($this->api($this->owner)->getJson('/api/v1/access/navigation')->assertOk()->json('data'), 'code');
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
