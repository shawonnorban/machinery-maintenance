<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Attendance & Shift (AnnotechERP 24/04): days worked out from
 * a push terminal's punches — present, late, early out, OT for workers only,
 * missing punch, absent, holiday, weekly off — then a missing punch corrected
 * and approved, the month chart edited, the shift roster in its four modes,
 * the month summary, a punch file imported, the exports, and who may.
 */
class HrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const A = '/api/v1/hr-payroll/attendance';

    private Company $delta;

    private User $owner;

    private string $factory;

    private string $dept;

    private string $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01')->id;
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        // Wednesday morning in Dhaka: Tuesday's day is over.
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn ($id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $this->dept = $row('departments', ['factory_id' => $this->factory, 'name' => 'Sewing', 'code' => 'SEW']);
        $this->line = $row('production_lines', ['department_id' => $this->dept, 'name' => 'Line 1', 'code' => 'L1']);
        $designation = $row('designations', ['department_id' => $this->dept, 'name' => 'Operator', 'code' => 'OP']);
        $types = collect($this->as()->getJson('/api/v1/hr-payroll/profiles/options')->json('data.employee_types'))->pluck('value', 'label');
        foreach ([['1001', 'Worker One', 'Worker'], ['1002', 'Worker Two', 'Worker'], ['1003', 'Staff One', 'Staff']] as [$card, $name, $type]) {
            $this->as()->postJson('/api/v1/hr-payroll/profiles', ['card_no' => $card, 'factory_id' => $this->factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $types[$type], 'department_id' => $this->dept, 'designation_id' => $designation, 'joining_date' => '2026-11-01', 'gross_salary' => 20000, 'status' => 'ACTIVE'])->assertCreated();
        }
        // A graded worker earns General OT (24/05); staff do not, whatever the grade.
        DB::table('hr_employees')->where('company_id', $this->delta->id)->update(['grade_id' => DB::table('grades')->where('company_id', $this->delta->id)->where('code', '4')->value('id')]);
        FactoryCalendarSetup::holiday($this->delta->id, '2026-11-08', 'Test Holiday');
        $this->as()->postJson('/api/v1/settings/devices', ['code' => 'GATE-01', 'name' => 'Main Gate', 'protocol' => 'ADMS_PUSH', 'serial_no' => 'SN-ATT-1'])->assertCreated();
    }

    public function test_days_are_worked_out_from_the_punches_and_corrected(): void
    {
        // Tuesday 10 Nov: W1 early in, late out (OT); W2 late, no OUT; S1 (staff) leaves early, no OT.
        $this->push("1001\t2026-11-10 07:55:00\t0\t1\n1001\t2026-11-10 20:10:00\t1\t1\n1002\t2026-11-10 08:40:00\t0\t1\n1003\t2026-11-10 08:05:00\t0\t1\n1003\t2026-11-10 16:00:00\t1\t1\n9999\t2026-11-10 08:00:00\t0\t1\n");
        // The terminal posting the punches already updated the days.
        $this->assertSame('PRESENT', DB::table('hr_attendance_days as a')->join('hr_employees as e', 'e.id', '=', 'a.employee_id')->where('e.card_no', '1001')->whereDate('a.date', '2026-11-10')->value('a.status'));

        $day = $this->as()->getJson(self::A.'/daily?from=2026-11-10&to=2026-11-10')->assertOk()->json('data');
        $this->assertEquals(['PRESENT' => 2, 'ABSENT' => 0, 'LATE' => 1, 'EARLY_OUT' => 1, 'LEAVE' => 0, 'MISSING_PUNCH' => 1, 'HOLIDAY' => 0, 'WEEKLY_OFF' => 0], $day['counts']);
        $rows = collect($day['rows'])->keyBy('name');
        $this->assertEquals(['07:55', '20:10', 11.25, 0, 3.0, 'Day Shift 08:00 AM–05:00 PM'], [$rows['Worker One']['in'], $rows['Worker One']['out'], $rows['Worker One']['worked'], $rows['Worker One']['late'], $rows['Worker One']['ot'], $rows['Worker One']['shift']]);
        $this->assertEquals(['MISSING_PUNCH', 40, true, null], [$rows['Worker Two']['status'], $rows['Worker Two']['late'], $rows['Worker Two']['is_late'], $rows['Worker Two']['out']]);
        $this->assertEquals([60, true, 0.0, 6.92], [$rows['Staff One']['early'], $rows['Staff One']['is_early_out'], $rows['Staff One']['ot'], $rows['Staff One']['worked']]);

        // The week: Friday off, Sunday's holiday, Monday absent.
        $week = collect($this->as()->getJson(self::A.'/daily?from=2026-11-06&to=2026-11-09&status=')->json('data.rows'))->where('name', 'Worker One')->pluck('status', 'date')->all();
        $this->assertEquals(['2026-11-06' => 'WEEKLY_OFF', '2026-11-07' => 'ABSENT', '2026-11-08' => 'HOLIDAY', '2026-11-09' => 'ABSENT'], $week);
        $this->as()->getJson(self::A.'/daily?from=2026-11-10&to=2026-11-01')->assertUnprocessable();

        // Missing punch: W2's day, corrected with the right OUT and approved.
        $missing = $this->as()->getJson(self::A.'/missing?from=2026-11-10&to=2026-11-10')->assertOk()->json('data');
        $this->assertSame(['Worker Two'], array_column($missing['rows'], 'name'));
        $this->as()->postJson(self::A.'/corrections', ['employee' => 'EMP-000002', 'date' => '2026-11-10'])->assertUnprocessable()->assertJsonValidationErrors(['correct_in', 'reason']);
        $this->as()->postJson(self::A.'/corrections', ['employee' => 'EMP-000002', 'date' => '2026-11-10', 'correct_out' => '17:30', 'reason' => 'Forgot to punch out'])->assertCreated();
        $this->as()->postJson(self::A.'/corrections', ['employee' => 'EMP-000002', 'date' => '2026-11-10', 'correct_out' => '17:35', 'reason' => 'Again'])->assertUnprocessable();
        $missing = $this->as()->getJson(self::A.'/missing?from=2026-11-10&to=2026-11-10')->json('data');
        $this->assertEquals([true, 1, '08:40 AM – —', 'PENDING'], [$missing['rows'][0]['pending'], $missing['pending'], $missing['corrections'][0]['old'], $missing['corrections'][0]['status']]);
        $this->as()->postJson(self::A.'/corrections/'.$missing['corrections'][0]['id'].'/decide', ['decision' => 'APPROVE'])->assertOk();
        $this->as()->postJson(self::A.'/sync')->assertOk();
        $w2 = collect($this->as()->getJson(self::A.'/daily?from=2026-11-10&to=2026-11-10')->json('data.rows'))->firstWhere('name', 'Worker Two');
        $this->assertEquals(['PRESENT', '17:30', 40, 7.83, true], [$w2['status'], $w2['out'], $w2['late'], $w2['worked'], $w2['corrected']]);

        // The month chart: Monday entered as leave, then deleted back to the punches.
        $month = $this->as()->getJson(self::A.'/month?employee=EMP-000001&year=2026&month=11')->assertOk()->json('data');
        $this->assertCount(30, $month['rows']);
        $this->assertEquals(['present' => 1, 'absent' => 8, 'late' => 0, 'missing' => 0, 'leave' => 0, 'off' => 2, 'ot' => 3.0], $month['totals']);
        $this->as()->postJson(self::A.'/day', ['employee' => 'EMP-000001', 'date' => '2026-11-12', 'status' => 'PRESENT'])->assertUnprocessable();
        $this->as()->postJson(self::A.'/day', ['employee' => 'EMP-000001', 'date' => '2026-11-09', 'status' => 'LEAVE', 'remarks' => 'Sick'])->assertOk();
        $this->as()->postJson(self::A.'/sync')->assertOk();
        $this->assertSame('LEAVE', collect($this->as()->getJson(self::A.'/month?employee=EMP-000001&year=2026&month=11')->json('data.rows'))->firstWhere('date', '2026-11-09')['status']);
        $this->as()->postJson(self::A.'/day/delete', ['employee' => 'EMP-000001', 'date' => '2026-11-09'])->assertOk();
        $this->assertSame('ABSENT', collect($this->as()->getJson(self::A.'/month?employee=EMP-000001&year=2026&month=11')->json('data.rows'))->firstWhere('date', '2026-11-09')['status']);

        // The month summary: marks a day and OT, totals per worker; Excel and PDF.
        $summary = $this->as()->getJson(self::A.'/summary?year=2026&month=11&department='.$this->dept)->assertOk()->json('data');
        $w1 = collect($summary['rows'])->firstWhere('name', 'Worker One');
        $this->assertEquals(['P', 3.0, 'W', 'H', 'A'], [$w1['cells'][9]['mark'], $w1['cells'][9]['ot'], $w1['cells'][5]['mark'], $w1['cells'][7]['mark'], $w1['cells'][8]['mark']]);
        $this->assertSame('L', collect($summary['rows'])->firstWhere('name', 'Worker Two')['cells'][9]['mark']);
        $this->as()->getJson(self::A.'/summary?year=2026&month=11')->assertUnprocessable()->assertJsonPath('errors.department.0', __('hrpayroll.att.v.department'));
        $this->assertStringStartsWith('application/pdf', $this->as()->get(self::A.'/summary/pdf?year=2026&month=11&department='.$this->dept)->assertOk()->headers->get('Content-Type'));
        $this->assertStringStartsWith('application/pdf', $this->as()->get(self::A.'/daily/pdf?from=2026-11-10&to=2026-11-10')->assertOk()->headers->get('Content-Type'));
        $this->assertStringContainsString('spreadsheetml', (string) $this->as()->get(self::A.'/daily/xlsx?from=2026-11-10&to=2026-11-10')->assertOk()->headers->get('Content-Type'));

        // Biometric Sync: the day's raw punches, one nobody holds.
        $sync = $this->as()->getJson(self::A.'/sync?date=2026-11-10')->assertOk()->json('data');
        $this->assertEquals([6, 1, 1], [$sync['tiles']['punches'], $sync['unmapped'], count($this->as()->getJson(self::A.'/sync?date=2026-11-10&unmapped=1')->json('data.punches'))]);
        $this->assertSame(6, $sync['devices'][0]['punches']);

        // Import file: a USB attendance log — one punch already in, one new.
        $file = UploadedFile::fake()->createWithContent('attlog.dat', "  1001\t2026-11-10 07:55:00\t1\t0\t0\t0\n  1001\t2026-11-11 07:58:00\t1\t0\t0\t0\n");
        $this->as()->post(self::A.'/import', ['device' => 'GATE-01', 'upload' => $file], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.message', __('hrpayroll.att.done.imported', ['read' => 2, 'saved' => 1, 'days' => 1]));
        $this->as()->post(self::A.'/import', ['device' => 'GATE-01', 'upload' => UploadedFile::fake()->createWithContent('x.txt', "nothing here\n")], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame('PRESENT', collect($this->as()->getJson(self::A.'/daily?range=today')->json('data.rows'))->firstWhere('name', 'Worker One')['status']);

        // Who may: a factory manager reads and approves but does not correct; a storekeeper sees nothing.
        $fm = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->as($fm)->getJson(self::A.'/daily?range=today')->assertOk();
        $this->as($fm)->postJson(self::A.'/day', ['employee' => 'EMP-000001', 'date' => '2026-11-09', 'status' => 'LEAVE'])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson(self::A.'/daily')->assertForbidden();
    }

    public function test_the_shift_roster_decides_the_day(): void
    {
        $this->push("1003\t2026-11-10 08:05:00\t0\t1\n1003\t2026-11-10 16:00:00\t1\t1\n");
        $this->as()->getJson('/api/v1/hr-payroll/roster/options')->assertOk();
        \App\Modules\HrPayroll\Support\FactoryCalendarSetup::shift($this->delta->id, 'ST-MOR', 'Morning', '06:00', '14:00', 60);
        \App\Modules\HrPayroll\Support\FactoryCalendarSetup::shift($this->delta->id, 'ST-EVE', 'Evening', '14:00', '22:00', 60);
        $shifts = collect($this->as()->getJson('/api/v1/hr-payroll/roster/options')->assertOk()->json('data.shifts'))->pluck('value', 'code');

        // Individual: S1 on the morning shift from Tuesday — late now, and Saturday off.
        $this->as()->postJson('/api/v1/hr-payroll/roster', ['mode' => 'INDIVIDUAL'])->assertUnprocessable()->assertJsonValidationErrors(['employee', 'shift', 'from']);
        $this->as()->postJson('/api/v1/hr-payroll/roster', ['mode' => 'INDIVIDUAL', 'employee' => 'EMP-000003', 'shift' => $shifts['ST-MOR'], 'from' => '2026-11-10', 'weekly_off' => ['SAT']])->assertCreated()->assertJsonPath('data.batch', 'RS-000001');
        $s1 = collect($this->as()->getJson(self::A.'/daily?from=2026-11-10&to=2026-11-10')->json('data.rows'))->firstWhere('name', 'Staff One');
        $this->assertEquals(['Morning 06:00 AM–02:00 PM', 125, true, 0, 0.0], [$s1['shift'], $s1['late'], $s1['is_late'], $s1['early'], $s1['ot']]);

        // Weekly rotation: both workers, morning then evening, over two weeks — one assignment a week.
        $this->as()->postJson('/api/v1/hr-payroll/roster', ['mode' => 'ROTATION', 'employees' => ['EMP-000001', 'EMP-000002'], 'pattern' => [$shifts['ST-MOR']], 'weeks' => 2, 'from' => '2026-11-16'])->assertUnprocessable()->assertJsonValidationErrors(['pattern']);
        $this->as()->postJson('/api/v1/hr-payroll/roster', ['mode' => 'ROTATION', 'employees' => ['EMP-000001', 'EMP-000002'], 'pattern' => [$shifts['ST-MOR'], $shifts['ST-EVE']], 'weeks' => 2, 'from' => '2026-11-16'])->assertCreated()->assertJsonPath('data.batch', 'RS-000002');
        $roster = collect($this->as()->getJson('/api/v1/hr-payroll/roster?employee=EMP-000001')->json('data'));
        $this->assertEquals([['2026-11-16', '2026-11-22', 'Morning 06:00 AM–02:00 PM'], ['2026-11-23', '2026-11-29', 'Evening 02:00 PM–10:00 PM']], $roster->sortBy('from')->map(fn ($r) => [$r['from'], $r['to'], $r['shift']])->values()->all());
        $this->assertSame(['FRI'], $roster->first()['weekly_off']);

        // Line-wise: everybody posted to the line; an empty line is refused.
        DB::table('hr_employees')->where('card_no', '1001')->update(['line_id' => $this->line]);
        $this->as()->postJson('/api/v1/hr-payroll/roster', ['mode' => 'LINE', 'line' => $this->line, 'shift' => $shifts['DAY'], 'from' => '2026-12-01'])->assertCreated()->assertJsonPath('data.message', __('hrpayroll.att.done.assigned', ['n' => 1, 'batch' => 'RS-000003']));

        // Removing S1's assignment puts Tuesday back on the general shift.
        $id = collect($this->as()->getJson('/api/v1/hr-payroll/roster?employee=EMP-000003')->json('data'))->first()['id'];
        $this->as()->deleteJson("/api/v1/hr-payroll/roster/{$id}")->assertOk();
        $s1 = collect($this->as()->getJson(self::A.'/daily?from=2026-11-10&to=2026-11-10')->json('data.rows'))->firstWhere('name', 'Staff One');
        $this->assertEquals(['Day Shift 08:00 AM–05:00 PM', 0, 60], [$s1['shift'], $s1['late'], $s1['early']]);
    }

    public function test_a_night_is_eight_regular_hours_and_the_rest_is_overtime(): void
    {
        $shifts = collect($this->as()->getJson('/api/v1/hr-payroll/roster/options')->assertOk()->json('data.shifts'))->pluck('value', 'code');
        foreach (['EMP-000001', 'EMP-000002'] as $code) {
            $this->as()->postJson('/api/v1/hr-payroll/roster', ['mode' => 'INDIVIDUAL', 'employee' => $code, 'shift' => $shifts['NIGHT'], 'from' => '2026-11-10', 'weekly_off' => ['FRI']])->assertCreated();
        }
        // 20:00, 8 hours and the hour's break: the regular night ends at 05:00. W1 stays to 09:10, W2 leaves at 04:30.
        $this->push("1001\t2026-11-10 19:55:00\t0\t1\n1002\t2026-11-10 20:00:00\t0\t1\n1002\t2026-11-11 04:30:00\t1\t1\n1001\t2026-11-11 09:10:00\t1\t1\n");
        $this->as()->postJson(self::A.'/sync')->assertOk();
        $rows = collect($this->as()->getJson(self::A.'/daily?from=2026-11-10&to=2026-11-10')->assertOk()->json('data.rows'))->keyBy('name');
        $this->assertEquals(['Night Shift 08:00 PM–05:00 AM', 4.0, 0, false], [$rows['Worker One']['shift'], $rows['Worker One']['ot'], $rows['Worker One']['early'], $rows['Worker One']['is_early_out']]);
        $this->assertEquals([0.0, 30, true], [$rows['Worker Two']['ot'], $rows['Worker Two']['early'], $rows['Worker Two']['is_early_out']]);
    }

    private function push(string $log): TestResponse
    {
        return $this->call('POST', '/iclock/cdata?SN=SN-ATT-1&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $log)->assertOk();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
