<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Dashboard and Reports (AnnotechERP 24/02): the 47-report
 * catalog; employee, manpower, attendance and OT reports worked out from the
 * roll and the attendance days (categories by designation group, absent with
 * no punch, late minutes, missing punches, corrected OT); the salary reports'
 * empty states; the Excel sheet; the dashboard's tiles and Today's
 * Operations; and who may.
 */
class HrReportTest extends TestCase
{
    use RefreshDatabase;

    private const H = '/api/v1/hr-payroll';

    private Company $delta;

    private User $owner;

    /** @var array<string, string> code => id */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01')->id;
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn ($id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $dept = $row('departments', ['factory_id' => $factory, 'name' => 'Sewing', 'code' => 'SEW']);
        $a = $row('sections', ['department_id' => $dept, 'name' => 'Sewing A', 'code' => 'SA']);
        $b = $row('sections', ['department_id' => $dept, 'name' => 'Sewing B', 'code' => 'SB']);
        $operator = $row('designations', ['department_id' => $dept, 'name' => 'Operator', 'code' => 'OP']);
        $helper = $row('designations', ['department_id' => $dept, 'name' => 'Helper', 'code' => 'HP']);
        $chief = $row('designations', ['department_id' => $dept, 'name' => 'Line Chief', 'code' => 'LC']);
        $types = collect($this->as()->getJson(self::H.'/profiles/options')->json('data.employee_types'))->pluck('value', 'label')->all();
        foreach ([['1001', 'Worker One', $a, $operator], ['1002', 'Worker Two', $a, $operator], ['1003', 'Worker Three', $b, $helper], ['1004', 'Chief Four', $b, $chief]] as [$card, $name, $section, $designation]) {
            $this->as()->postJson(self::H.'/profiles', ['card_no' => $card, 'factory_id' => $factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $types['Worker'], 'department_id' => $dept, 'section_id' => $section, 'designation_id' => $designation, 'joining_date' => '2026-01-01', 'gross_salary' => 21000, 'status' => 'ACTIVE'])->assertCreated();
        }
        $this->ids = DB::table('hr_employees')->pluck('id', 'employee_code')->all();
        DB::table('hr_employees')->where('id', $this->ids['EMP-000004'])->update(['status' => 'SEPARATED', 'separation_date' => '2026-10-31']);
        // 10 Nov: one late, one absent, one with no OUT punch.
        $day = fn (string $code, string $status, ?string $in, ?string $out, array $extra = []) => DB::table('hr_attendance_days')->insert(array_merge([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'employee_id' => $this->ids[$code], 'date' => '2026-11-10', 'status' => $status,
            'in_at' => $in ? CarbonImmutable::parse("2026-11-10 {$in}", 'Asia/Dhaka')->utc() : null, 'out_at' => $out ? CarbonImmutable::parse("2026-11-10 {$out}", 'Asia/Dhaka')->utc() : null,
            'worked_minutes' => 0, 'late_minutes' => 0, 'early_out_minutes' => 0, 'ot_minutes' => 0, 'is_late' => false, 'is_early_out' => false, 'corrected' => false, 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
        $day('EMP-000001', 'PRESENT', '09:12', '18:00', ['worked_minutes' => 528, 'late_minutes' => 12, 'is_late' => true]);
        $day('EMP-000002', 'ABSENT', null, null);
        $day('EMP-000003', 'MISSING_PUNCH', '09:00', null);
        DB::table('hr_ot_corrections')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'employee_id' => $this->ids['EMP-000001'], 'date' => '2026-11-10', 'hours' => 2, 'rate' => 100,
            'reason' => 'Line target', 'updated_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_catalog_and_employee_and_manpower_reports(): void
    {
        $c = $this->as()->getJson(self::H.'/reports')->assertOk()->json('data');
        $this->assertCount(47, $c['reports']);
        $this->assertSame(['employee', 'manpower', 'attendance', 'ot', 'salary', 'hr'], array_column($c['categories'], 'key'));
        $this->assertSame([1, 'employee-master', 'Employee Master'], [$c['reports'][0]['no'], $c['reports'][0]['key'], $c['reports'][0]['name']]);
        $this->assertSame(['period', 'employee'], collect($c['reports'])->firstWhere('key', 'payslip')['required']);
        $this->assertSame(['2026-11-11', '2026-11-01', '2026-11'], [$c['defaults']['date'], $c['defaults']['from'], $c['defaults']['month']]);
        $this->assertSame(['Sewing A', 'Sewing B'], array_column($c['options']['sections'], 'label'));
        $this->assertTrue($c['canExport']);
        $this->assertSame('Delta Apparels Ltd', $c['letterhead']['company']);

        $run = fn (string $key, array $f = []) => $this->as()->postJson(self::H."/reports/{$key}", $f)->assertOk()->json('data');
        $this->as()->postJson(self::H.'/reports/nope')->assertNotFound();
        $this->as()->postJson(self::H.'/reports/joining', ['from' => '11-01-2026'])->assertUnprocessable();

        $list = $run('employee-list');
        $this->assertSame(['EMP-000001', 'EMP-000002', 'EMP-000003', 'EMP-000004'], array_column($list['rows'], 'code'));
        $this->assertSame(['code', 'name', 'card', 'designation', 'department', 'section', 'line', 'joining', 'mobile', 'status'], array_column($list['columns'], 'key'));
        $this->assertSame(['EMP-000004'], array_column($run('employee-list', ['status' => 'SEPARATED'])['rows'], 'code'));
        $this->assertSame(['EMP-000003'], array_column($run('employee-list', ['employee' => '1003'])['rows'], 'code'));
        $this->assertSame([3, 0, 0, 1], array_column($run('employee-status')['tiles'], 'value'));
        $this->assertCount(32, $run('employee-master')['columns']);
        $assign = $run('employee-assignment');
        $this->assertSame([3, 3, 0], array_column($assign['tiles'], 'value'));
        $joined = $run('joining', ['from' => '2026-01-01', 'to' => '2026-01-31']);
        $this->assertSame([4, 84000], [$joined['foot']['code'], (int) $joined['foot']['gross_a']]);
        $this->assertSame([], $run('joining')['rows']);
        $this->assertSame([0, 0, 0, 0, 0], array_column($run('employee-movement')['tiles'], 'value'));

        // 10 Nov: two present (one late, one without an OUT punch), one absent; the separated one is off the count.
        $total = $run('total-manpower', ['date' => '2026-11-10']);
        $this->assertSame([3, 2, 1, 0], array_column($total['tiles'], 'value'));
        $measures = collect($total['rows'])->pluck('count', 'measure')->all();
        $this->assertSame([3, 2, 1, 0, 1, 1], [$measures['Total Active Employees'], $measures['Operator'], $measures['Helper'], $measures['Supervisor'], $measures['Late'], $measures['Missing Punch']]);
        $design = $run('designation-manpower', ['date' => '2026-11-10']);
        $this->assertSame(['Operator', 2, 1, 1], [$design['rows'][0]['category'], $design['rows'][0]['total'], $design['rows'][0]['present'], $design['rows'][0]['absent']]);
        $this->assertEquals([3, 2, 1, 66.7], [$design['foot']['total'], $design['foot']['present'], $design['foot']['absent'], $design['foot']['pct']]);
        $sections = $run('section-manpower', ['date' => '2026-11-10']);
        $this->assertSame([['Sewing A', 2, 2, 1], ['Sewing B', 1, 0, 1]], array_map(fn ($r) => [$r['section'], $r['total'], $r['operator'], $r['present']], $sections['rows']));
        $this->assertSame('Not assigned', $run('line-manpower', ['date' => '2026-11-10'])['rows'][0]['line']);
    }

    public function test_attendance_and_ot_reports(): void
    {
        $run = fn (string $key, array $f = []) => $this->as()->postJson(self::H."/reports/{$key}", $f)->assertOk()->json('data');
        $daily = $run('daily-attendance', ['date' => '2026-11-10']);
        $this->assertSame([3, 2, 1, 0, 1, 1], array_column($daily['tiles'], 'value'));
        $late = $run('daily-attendance', ['date' => '2026-11-10', 'status' => 'LATE'])['rows'];
        $this->assertSame([['EMP-000001', '09:12 AM', '06:00 PM', 8.8, 12, 'Late']], array_map(fn ($r) => [$r['code'], $r['in'], $r['out'], $r['worked'], $r['late_min'], $r['status']], $late));
        $this->assertCount(2, $run('present', ['date' => '2026-11-10'])['rows']);
        $absent = $run('absent', ['date' => '2026-11-10']);
        $this->assertSame([1, 1, 0], array_column($absent['tiles'], 'value'));
        $this->assertSame([3, 0, 3], array_column($run('absent', ['date' => '2026-11-11'])['tiles'], 'value'));
        $lateReport = $run('late', ['from' => '2026-11-01', 'to' => '2026-11-11']);
        $this->assertSame([1, 1, 12], array_column($lateReport['tiles'], 'value'));
        $this->assertEquals(12, $lateReport['foot']['late_min']);
        $missing = $run('missing-punch', ['from' => '2026-11-01', 'to' => '2026-11-11'])['rows'];
        $this->assertSame([['EMP-000003', 'OUT']], array_map(fn ($r) => [$r['code'], $r['missing']], $missing));
        $month = $run('monthly-attendance', ['month' => '2026-11']);
        $this->assertSame(['EMP-000001', 1, 0, 1, 12], [$month['rows'][0]['code'], $month['rows'][0]['present'], $month['rows'][0]['absent'], $month['rows'][0]['late_days'], $month['rows'][0]['late_min']]);
        $this->assertSame('No shift set', collect($run('shift-attendance', ['date' => '2026-11-10'])['rows'])->last()['shift']);

        // The corrected OT is what is paid.
        $ot = $run('daily-ot', ['date' => '2026-11-10']);
        $this->assertSame([['EMP-000001', 2, 100, 200, 'Corrected']], array_map(fn ($r) => [$r['code'], (int) $r['hours'], (int) $r['rate'], (int) $r['amount'], $r['correction']], $ot['rows']));
        $tiles = collect($ot['tiles'])->pluck('value', 'label')->all();
        $this->assertEquals([1, 2, 200, 1], [$tiles['OT Employees'], $tiles['OT Hours'], $tiles['OT Salary (৳)'], $tiles['Corrected Days']]);
        $this->assertEquals(200, $run('employee-ot', ['from' => '2026-11-01', 'to' => '2026-11-11'])['foot']['amount']);
        $this->assertSame('Sewing A', $run('section-ot', ['from' => '2026-11-01', 'to' => '2026-11-11'])['rows'][0]['section']);
        $mo = $run('monthly-ot', ['month' => '2026-11'])['rows'][0];
        $this->assertEquals([1, 2, 200, 2, 0], [$mo['corrections'], $mo['hours'], $mo['amount'], $mo['general_hours'], $mo['worker_hours']]);
        $this->assertSame([], $run('daily-ot', ['date' => '2026-11-10', 'otType' => 'WORKER'])['rows']);

        // Salary reports before any payroll, the payslip without an employee, Gross B without Complex.
        $this->assertSame([[], __('hrpayroll.rp.e.no_payroll')], [$run('monthly-salary')['rows'], $run('monthly-salary')['empty']]);
        $this->assertSame(__('hrpayroll.rp.e.employee_required'), $run('payslip')['empty']);
        $this->assertSame(__('hrpayroll.rp.e.no_complex'), $run('gross-b-salary')['empty']);

        // Excel: the title, the header and the rows.
        $sheet = $this->as()->get(self::H.'/reports/late/xlsx?from=2026-11-01&to=2026-11-11');
        $sheet->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $sheet->headers->get('Content-Type'));
        $this->assertStringContainsString('Late-Report.xlsx', (string) $sheet->headers->get('Content-Disposition'));
    }

    public function test_dashboard_and_who_may(): void
    {
        $d = $this->as()->getJson(self::H.'/dashboard?date=2026-11-10')->assertOk()->json('data');
        $k = $d['kpis'];
        $this->assertSame([4, 3, 0, 1, 2, 1, 1, 1], [$k['total'], $k['active'], $k['inactive'], $k['resigned'], $k['present'], $k['absent'], $k['late'], $k['missingPunch']]);
        $this->assertEquals(2, $k['approvedOtHours']);
        $this->assertNull($k['netPayable']);
        $this->assertSame([['label' => 'Sewing', 'value' => 3]], $d['byDepartment']);
        $this->assertCount(30, $d['attendanceDays']);
        $this->assertSame(['date' => '2026-11-10', 'present' => 2, 'absent' => 1, 'late' => 1, 'leave' => 0], $d['attendanceDays'][9]);
        $this->assertCount(4, $d['recentEmployees']);
        $op = $d['operations'];
        $this->assertSame(['Operator', 2, 1, 1], array_values($op['designation'][0]));
        // On time / late / missing punch / leave / absent / not punched yet.
        $this->assertSame([0, 1, 1, 0, 1, 0], array_column($op['summary'], 'value'));
        $this->assertSame([['line' => 'No line', 'total' => 3, 'present' => 2, 'absent' => 1, 'otEmployees' => 1, 'otHours' => 2]], $op['lines']);
        $this->assertSame([['line' => 'No line', 'employees' => 1, 'hours' => 2, 'amount' => 200]], array_map(fn ($x) => array_map(fn ($v) => is_float($v) ? (int) $v : $v, $x), $op['otLines']));
        // This Month: every employee-day to the 10th, unrecorded days as not punched yet.
        $m = $this->as()->getJson(self::H.'/dashboard?date=2026-11-10&period=MONTH')->assertOk()->json('data.operations');
        $this->assertSame([30, 27], [$m['total'], collect($m['summary'])->firstWhere('key', 'NOT_RECORDED')['value']]);
        $this->as()->getJson(self::H.'/dashboard?period=YEAR')->assertUnprocessable();

        // Who may: the auditor reads and cannot export; a storekeeper sees neither screen.
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $this->as($auditor)->getJson(self::H.'/dashboard')->assertOk();
        $this->as($auditor)->getJson(self::H.'/reports')->assertOk()->assertJsonPath('data.canExport', false);
        $this->as($auditor)->postJson(self::H.'/reports/employee-list')->assertOk();
        $this->as($auditor)->get(self::H.'/reports/employee-list/xlsx')->assertForbidden();
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($keeper)->getJson(self::H.'/dashboard')->assertForbidden();
        $this->as($keeper)->getJson(self::H.'/reports')->assertForbidden();
        $this->as($keeper)->postJson(self::H.'/reports/employee-list')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
