<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\OtBook;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Salary Processing (AnnotechERP 24/06): a day's salary
 * calculated, reviewed, approved (kept on recalculation) and un-approved;
 * OT salary over a range with corrections; the festival bonus sheet from
 * preview to finalized; the monthly run — slips, leave deduction, OT,
 * bonus, stamp, compliance — through review, approval, finalize (locked),
 * re-open and paid; payroll adjustments raised on a finalized slip,
 * decided, and paid once with the next month; and who may.
 */
class HrSalaryProcessingTest extends TestCase
{
    use RefreshDatabase;

    private const H = '/api/v1/hr-payroll';

    private Company $delta;

    private User $owner;

    private string $factory;

    private string $dept;

    private array $types;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01')->id;
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn ($id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $this->dept = $row('departments', ['factory_id' => $this->factory, 'name' => 'Sewing', 'code' => 'SEW']);
        $section = $row('sections', ['department_id' => $this->dept, 'name' => 'Sewing A', 'code' => 'SA']);
        $line = $row('production_lines', ['department_id' => $this->dept, 'section_id' => $section, 'name' => 'Line 1', 'code' => 'L1']);
        $designation = $row('designations', ['department_id' => $this->dept, 'name' => 'Operator', 'code' => 'OP']);
        $this->types = collect($this->as()->getJson(self::H.'/profiles/options')->json('data.employee_types'))->pluck('value', 'label')->all();
        foreach ([['1001', 'Worker One', 'Worker'], ['1002', 'Worker Two', 'Worker'], ['1003', 'Staff One', 'Staff']] as [$card, $name, $type]) {
            $this->as()->postJson(self::H.'/profiles', ['card_no' => $card, 'factory_id' => $this->factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $this->types[$type], 'department_id' => $this->dept, 'designation_id' => $designation, 'joining_date' => '2026-01-01', 'gross_salary' => 21000, 'status' => 'ACTIVE'])->assertCreated();
        }
        DB::table('hr_employees')->where('company_id', $this->delta->id)->update(['grade_id' => DB::table('grades')->where('company_id', $this->delta->id)->where('code', '4')->value('id'), 'section_id' => $section]);
        DB::table('hr_employees')->where('company_id', $this->delta->id)->whereIn('card_no', ['1001', '1002'])->update(['line_id' => $line]);
        $this->as()->postJson('/api/v1/settings/devices', ['code' => 'GATE-01', 'name' => 'Main Gate', 'protocol' => 'ADMS_PUSH', 'serial_no' => 'SN-SP-1'])->assertCreated();
        // Tuesday 10 Nov: W1 07:55–20:10 (3 h OT), W2 08:00–19:30 (2 h), S1 08:05–16:00.
        $this->push("1001\t2026-11-10 07:55:00\t0\t1\n1001\t2026-11-10 20:10:00\t1\t1\n1002\t2026-11-10 08:00:00\t0\t1\n1002\t2026-11-10 19:30:00\t1\t1\n1003\t2026-11-10 08:05:00\t0\t1\n1003\t2026-11-10 16:00:00\t1\t1\n");
        // Only the punched day stays on the attendance, so the month is the test's to shape.
        DB::table('hr_attendance_days')->where('company_id', $this->delta->id)->where('date', '!=', '2026-11-10')->delete();
    }

    public function test_daily_and_ot_salary(): void
    {
        $options = $this->as()->getJson(self::H.'/salary/options')->assertOk()->json('data');
        $this->assertSame(['calculate' => true, 'approve' => true, 'finalize' => true], $options['can']);
        $this->assertSame(['EMP-000001', 'EMP-000002', 'EMP-000003'], array_column($options['employees'], 'value'));

        $view = $this->as()->getJson(self::H.'/salary/daily?date=2026-11-10')->assertOk()->json('data');
        $this->assertEquals([3, 0, 30], [$view['summary']['employees'], $view['summary']['calculated'], $view['days_in_month']]);
        $this->assertEquals(['PRESENT', 'NOT_CALCULATED', 0.0], [$view['rows'][0]['attendance'], $view['rows'][0]['status'], $view['rows'][0]['net']]);
        $this->as()->getJson(self::H.'/salary/daily?date=2026-11-12')->assertUnprocessable()->assertJsonValidationErrors(['date']);

        // A day with no attendance is flagged and pays nothing.
        $none = $this->as()->postJson(self::H.'/salary/daily/calculate', ['date' => '2026-11-09'])->assertOk()->json('data.view');
        $this->assertEquals(['NO_RECORD', 0.0, [__('hrpayroll.sp.flag.no_record')]], [$none['rows'][0]['attendance'], $none['rows'][0]['net'], $none['rows'][0]['flags']]);
        $this->assertSame(3, $none['summary']['flagged']);

        $calc = $this->as()->postJson(self::H.'/salary/daily/calculate', ['date' => '2026-11-10'])->assertOk()->json('data');
        $this->assertSame(__('hrpayroll.sp.done.daily_calculated', ['n' => 3, 'date' => '2026-11-10']), $calc['message']);
        $rows = collect($calc['view']['rows'])->keyBy('code');
        $rate = round((new OtBook($this->delta->id))->basic(DB::table('hr_employees')->where('card_no', '1001')->first()) / 208 * 2, 2);
        $w1 = $rows['EMP-000001'];
        $this->assertEquals([700.0, 3.0, $rate, round(3 * $rate, 2), round(700 + 3 * $rate, 2), 'CALCULATED', '07:55', '20:10'], [$w1['daily_salary'], $w1['ot_hours'], $w1['ot_rate'], $w1['ot_amount'], $w1['net'], $w1['status'], $w1['in_time'], $w1['out_time']]);
        $this->assertEquals([2.0, 0.0], [$rows['EMP-000002']['ot_hours'], $rows['EMP-000003']['ot_hours']]);

        // Review and approve the ticked row only; a recalculation keeps it; un-approve.
        $this->as()->postJson(self::H.'/salary/daily/move', ['date' => '2026-11-10', 'action' => 'approve'])->assertUnprocessable()->assertJsonPath('errors.action.0', __('hrpayroll.sp.v.none_to_approve'));
        $this->as()->postJson(self::H.'/salary/daily/move', ['date' => '2026-11-10', 'action' => 'review', 'employees' => ['EMP-000001']])->assertOk()->assertJsonPath('data.message', __('hrpayroll.sp.done.daily_reviewed', ['n' => 1]));
        $approved = $this->as()->postJson(self::H.'/salary/daily/move', ['date' => '2026-11-10', 'action' => 'approve'])->assertOk()->json('data.view');
        $this->assertEquals(['APPROVED', 'CALCULATED', 1], [$approved['rows'][0]['status'], $approved['rows'][1]['status'], $approved['summary']['approved']]);
        $this->assertNotNull($approved['rows'][0]['approved_by']);
        $again = $this->as()->postJson(self::H.'/salary/daily/calculate', ['date' => '2026-11-10'])->assertOk()->json('data.message');
        $this->assertStringContainsString(__('hrpayroll.sp.done.approved_kept', ['n' => 1]), $again);
        $this->as()->postJson(self::H.'/salary/daily/move', ['date' => '2026-11-10', 'action' => 'unapprove'])->assertOk()->assertJsonPath('data.view.rows.0.status', 'REVIEWED');

        // A department scope; a line scope keeps the workers on it.
        $this->assertCount(2, $this->as()->getJson(self::H.'/salary/daily?date=2026-11-10&mode=LINE&department='.$this->dept.'&line='.DB::table('production_lines')->where('code', 'L1')->value('id'))->json('data.rows'));
        $this->assertCount(1, $this->as()->getJson(self::H.'/salary/daily?date=2026-11-10&mode=INDIVIDUAL&employee=EMP-000003')->json('data.rows'));

        // OT Salary: the range's OT with the correction applied.
        $ot = $this->as()->getJson(self::H.'/salary/ot?from=2026-11-01&to=2026-11-11')->assertOk()->json('data');
        $this->assertEquals([2, 5.0, round(5 * $rate, 2), ['EMP-000001', 'EMP-000002']], [$ot['employees'], $ot['total_hours'], $ot['total_amount'], array_column($ot['rows'], 'code')]);
        $this->assertEquals(['17:00', '20:10', 'Day Shift', false], [$ot['rows'][0]['shift_end'], $ot['rows'][0]['out_time'], $ot['rows'][0]['shift'], $ot['rows'][0]['corrected']]);
        $this->as()->postJson(self::H.'/ot/correction', ['employee' => 'EMP-000001', 'date' => '2026-11-10', 'hours' => 4, 'rate' => 100, 'reason' => 'Gate log shows 21:10'])->assertOk();
        $ot = $this->as()->getJson(self::H.'/salary/ot?from=2026-11-10&to=2026-11-10&employee=EMP-000001')->json('data');
        $this->assertEquals([3.0, 4.0, 100.0, 400.0, true], [$ot['rows'][0]['original_hours'], $ot['rows'][0]['hours'], $ot['rows'][0]['rate'], $ot['rows'][0]['amount'], $ot['rows'][0]['corrected']]);
        $this->as()->getJson(self::H.'/salary/ot?from=2026-01-01&to=2026-11-10')->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->as()->postJson(self::H.'/salary/ot/recalculate', ['from' => '2026-11-10', 'to' => '2026-11-10'])->assertOk();

        // Who may: an auditor reads, a manager approves but does not calculate.
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->getJson(self::H.'/salary/daily?date=2026-11-10')->assertOk();
        $this->as($auditor)->postJson(self::H.'/salary/daily/calculate', ['date' => '2026-11-10'])->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->postJson(self::H.'/salary/daily/calculate', ['date' => '2026-11-10'])->assertForbidden();
        $this->as($manager)->postJson(self::H.'/salary/daily/move', ['date' => '2026-11-10', 'action' => 'approve'])->assertOk();
    }

    public function test_festival_bonus_and_the_monthly_run(): void
    {
        $nov = $this->period('APP-DALFY2026-27110001', '2026-11-01', '2026-11-30', '2026-12-07');
        // Worker Two was absent on 9 Nov; Staff One joined on 1 Oct.
        DB::table('hr_attendance_days')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'employee_id' => $this->emp('1002'), 'date' => '2026-11-09', 'status' => 'ABSENT', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_employees')->where('card_no', '1003')->update(['joining_date' => '2026-10-01']);

        // Festival Bonus: the month's festivals; a festival without a rule set is blocked.
        $set = DB::table('hr_bonus_rule_sets')->where('company_id', $this->delta->id)->value('id');
        $fest = $this->festival('Durga Puja', '2026-11-20', '2026-11', $set, 180);
        $bare = $this->festival('Founders Day', '2026-11-25', '2026-11', null, 0);
        $this->as()->getJson(self::H.'/salary/bonus?month=2026-10')->assertOk()->assertJsonPath('data.festivals', [])->assertJsonPath('data.sheet', null);
        $month = $this->as()->getJson(self::H.'/salary/bonus?month=2026-11')->assertOk()->json('data');
        $this->assertEquals([['Durga Puja', null], ['Founders Day', null]], array_map(fn ($f) => [$f['name'], $f['run_status']], $month['festivals']));
        $this->assertNull($month['sheet']);
        $this->assertSame([__('hrpayroll.sp.block.no_rule_set')], $this->as()->getJson(self::H."/salary/bonus?month=2026-11&festival={$bare}")->json('data.sheet.blockers'));
        $this->as()->postJson(self::H.'/salary/bonus/calculate', ['festival' => $bare])->assertUnprocessable();

        // The preview: workers half a Basic (10 months' service); Staff One under the minimum service.
        $sheet = $this->as()->getJson(self::H."/salary/bonus?month=2026-11&festival={$fest}")->json('data.sheet');
        $basic = (new OtBook($this->delta->id))->basic(DB::table('hr_employees')->where('card_no', '1001')->first());
        $this->assertEquals([['EMP-000001', 'BASIC', 0.5, round($basic * 0.5, 2), 323], ['EMP-000002', 'BASIC', 0.5, round($basic * 0.5, 2), 323]], array_map(fn ($e) => [$e['code'], $e['basis'], $e['pct'], $e['amount'], $e['service_days']], $sheet['eligible']));
        $this->assertEquals([['EMP-000003', __('hrpayroll.sp.why.min_service', ['days' => 50, 'min' => 180])]], array_map(fn ($e) => [$e['code'], $e['reason']], $sheet['not_eligible']));
        $this->assertEquals([null, []], [$sheet['run'], $sheet['blockers']]);
        $calc = $this->as()->postJson(self::H.'/salary/bonus/calculate', ['festival' => $fest])->assertOk()->json('data.view.sheet');
        $this->assertEquals(['DRAFT', 'APP-DALFY2026-27110001', round($basic, 2)], [$calc['run']['status'], $calc['run']['period_no'], $calc['total']]);
        $this->as()->postJson(self::H.'/salary/bonus/move', ['festival' => $fest, 'action' => 'approve'])->assertUnprocessable();
        foreach (['review', 'approve', 'reopen', 'review', 'approve', 'finalize'] as $step) {
            $this->as()->postJson(self::H.'/salary/bonus/move', ['festival' => $fest, 'action' => $step])->assertOk();
        }
        $this->assertSame('FINALIZED', DB::table('hr_bonus_runs')->value('status'));
        $this->as()->postJson(self::H.'/salary/bonus/calculate', ['festival' => $fest])->assertUnprocessable()->assertJsonPath('errors.festival.0', __('hrpayroll.sp.v.bonus_locked'));

        // Monthly Salary: nothing calculated yet; Calculate makes the slips.
        $options = $this->as()->getJson(self::H.'/salary/options')->json('data.periods');
        $this->assertSame([['value' => 'APP-DALFY2026-27110001', 'label' => 'APP-DALFY2026-27110001 · November 2026', 'closed' => false]], $options);
        $view = $this->as()->getJson(self::H.'/salary/monthly')->assertOk()->json('data');
        $this->assertEquals([null, [], 3], [$view['run'], $view['rows'], $view['not_calculated']]);
        $view = $this->as()->postJson(self::H.'/salary/monthly/calculate', ['period' => 'APP-DALFY2026-27110001'])->assertOk()->json('data.view');
        $rows = collect($view['rows'])->keyBy('code');
        $rate = round($basic / 208 * 2, 2);
        $w1 = $rows['EMP-000001'];
        $this->assertEquals([30, 1, 0, 30, 21000.0, 3.0, round(3 * $rate, 2), round($basic * 0.5, 2), 10.0, 'DRAFT', 'CASH'], [$w1['period_days'], $w1['present_days'], $w1['absent_days'], $w1['payable_days'], $w1['earned_gross'], $w1['ot_hours'], $w1['ot_amount'], $w1['bonus'], $w1['stamp_charge'], $w1['slip_status'], $w1['payment_method']]);
        $this->assertEquals(round(21000 + 3 * $rate + round($basic * 0.5, 2) - 10), $w1['net']);
        $w2 = $rows['EMP-000002'];
        $this->assertEquals([1, 29, 700.0, 20300.0], [$w2['absent_days'], $w2['payable_days'], $w2['leave_deduction'], $w2['earned_gross']]);
        $this->assertEquals([0.0, 0.0], [$rows['EMP-000003']['bonus'], $rows['EMP-000003']['ot_amount']]);
        $this->assertEquals(['CALCULATED', 3, 0, 3], [$view['run']['status'], $view['run']['slips'], $view['not_calculated'], $view['summary']['cash_count']]);

        // Compliance: a worker paid under the grade's minimum is warned.
        DB::table('hr_employees')->where('card_no', '1002')->update(['gross_a' => 12000]);
        $view = $this->as()->postJson(self::H.'/salary/monthly/calculate', ['period' => 'APP-DALFY2026-27110001', 'mode' => 'INDIVIDUAL', 'employee' => 'EMP-000002'])->assertOk()->json('data.view');
        $this->assertEquals([1, true, 12500.0, 'GRADE'], [$view['compliance_warnings'], $view['rows'][0]['below_minimum'], $view['rows'][0]['minimum_wage'], $view['rows'][0]['minimum_basis']]);

        // The workflow: review, approve, finalize (locked), re-open, finalize, paid.
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'approve'])->assertUnprocessable();
        $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'review'])->assertOk()->assertJsonPath('data.view.run.status', 'REVIEWED');
        $this->as($manager)->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'approve'])->assertOk();
        $this->as($manager)->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'finalize'])->assertForbidden();
        $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'finalize'])->assertOk()->assertJsonPath('data.view.rows.0.slip_status', 'PROCESSED');
        $this->as()->postJson(self::H.'/salary/monthly/calculate', ['period' => 'APP-DALFY2026-27110001'])->assertUnprocessable()->assertJsonPath('errors.period.0', __('hrpayroll.sp.v.locked'));
        $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'reopen'])->assertOk()->assertJsonPath('data.view.run.status', 'APPROVED');
        $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'finalize'])->assertOk();
        $paid = $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => 'pay'])->assertOk()->json('data.view');
        $this->assertEquals(['PAID', 'PAID', 0], [$paid['run']['status'], $paid['rows'][0]['slip_status'], $paid['adjustment_required']]);

        // A finalized salary blocks a bonus paid with it.
        $late = $this->festival('Late Fest', '2026-11-28', '2026-11', $set, 0);
        $this->assertSame([__('hrpayroll.sp.block.salary_finalized', ['period' => $nov->period_no])], $this->as()->getJson(self::H."/salary/bonus?month=2026-11&festival={$late}")->json('data.sheet.blockers'));
    }

    public function test_payroll_adjustment_on_a_finalized_slip(): void
    {
        $this->period('APP-DALFY2026-27110001', '2026-11-01', '2026-11-30', '2026-12-07');
        $this->as()->postJson(self::H.'/salary/monthly/calculate', ['period' => 'APP-DALFY2026-27110001'])->assertOk();
        $empty = $this->as()->getJson(self::H.'/salary/adjustments')->assertOk()->json('data');
        $this->assertEquals([null, [], 0], [$empty['period'], $empty['slips'], $empty['summary']['finalized']]);
        foreach (['review', 'approve', 'finalize'] as $step) {
            $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27110001', 'action' => $step])->assertOk();
        }
        $slipOt = (float) DB::table('hr_salary_slips')->where('employee_id', $this->emp('1001'))->value('ot_amount');

        // Nothing differs yet; an OT correction after finalizing flags the slip.
        $data = $this->as()->getJson(self::H.'/salary/adjustments')->json('data');
        $this->assertEquals(['APP-DALFY2026-27110001', 3, 0, []], [$data['period']['no'], $data['summary']['finalized'], $data['summary']['flagged'], $data['slips']]);
        $this->as()->postJson(self::H.'/ot/correction', ['employee' => 'EMP-000001', 'date' => '2026-11-10', 'hours' => 5, 'rate' => 100, 'reason' => 'Stayed for the shipment'])->assertOk();
        $data = $this->as()->getJson(self::H.'/salary/adjustments')->json('data');
        $this->assertEquals([1, 'EMP-000001', 5.0, 500.0], [$data['summary']['flagged'], $data['slips'][0]['code'], $data['slips'][0]['current_hours'], $data['slips'][0]['current_amount']]);
        $this->assertCount(3, $this->as()->getJson(self::H.'/salary/adjustments?show=all')->json('data.slips'));
        $monthly = $this->as()->getJson(self::H.'/salary/monthly?period=APP-DALFY2026-27110001')->json('data');
        $this->assertEquals([1, true], [$monthly['adjustment_required'], $monthly['rows'][0]['adjustment_required']]);

        // Raise: a reason is needed; the same figure is refused; the OT difference is raised.
        $base = ['period' => 'APP-DALFY2026-27110001', 'employee' => 'EMP-000001', 'kind' => 'OT', 'hours' => 5, 'rate' => 100];
        $this->as()->postJson(self::H.'/salary/adjustments', $base)->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->as()->postJson(self::H.'/salary/adjustments', ['hours' => 1, 'rate' => $slipOt, 'reason' => 'x'] + $base)->assertUnprocessable()->assertJsonValidationErrors(['amount']);
        $raised = $this->as()->postJson(self::H.'/salary/adjustments', ['reason' => 'OT corrected after finalizing'] + $base)->assertCreated()->json('data.view');
        $this->assertEquals([['PADJ-000001', 'OT', $slipOt, 500.0, round(500 - $slipOt, 2), 'PENDING']], array_map(fn ($a) => [$a['ref'], $a['kind'], $a['original'], $a['revised'], $a['amount'], $a['status']], $raised['adjustments']));
        $this->assertEquals([1, 1], [count($raised['pending']), $raised['summary']['flagged']]);
        $this->assertSame(__('hrpayroll.sp.why_adj.open', ['refs' => 'PADJ-000001']), $raised['slips'][0]['why']);
        // Another kind: an amount recovered from Staff One.
        $this->as()->postJson(self::H.'/salary/adjustments', ['period' => 'APP-DALFY2026-27110001', 'employee' => 'EMP-000003', 'kind' => 'OTHER', 'amount' => -250, 'reason' => 'Canteen advance'])->assertCreated();

        // Decide: a manager approves; rejecting needs a note.
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->postJson(self::H.'/salary/adjustments', ['reason' => 'x'] + $base)->assertForbidden();
        $this->as()->postJson(self::H.'/salary/adjustments/PADJ-000002/decide', ['decision' => 'REJECT'])->assertUnprocessable()->assertJsonValidationErrors(['note']);
        $this->as()->postJson(self::H.'/salary/adjustments/PADJ-000002/decide', ['decision' => 'REJECT', 'note' => 'Already recovered in cash'])->assertOk();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->postJson(self::H.'/salary/adjustments/PADJ-000001/decide', ['decision' => 'APPROVE'])->assertOk()->assertJsonPath('data.view.summary.approved', 1);
        $this->as()->postJson(self::H.'/salary/adjustments/PADJ-000001/decide', ['decision' => 'REJECT', 'note' => 'x'])->assertUnprocessable();

        // Paid once with December: the slip carries it; finalizing applies it.
        $this->period('APP-DALFY2026-27120001', '2026-12-01', '2026-12-31', '2027-01-07');
        $dec = collect($this->as()->postJson(self::H.'/salary/monthly/calculate', ['period' => 'APP-DALFY2026-27120001'])->assertOk()->json('data.view.rows'))->keyBy('code');
        $this->assertEquals([round(500 - $slipOt, 2), 0.0], [$dec['EMP-000001']['adjustment'], $dec['EMP-000003']['adjustment']]);
        $this->assertSame('APP-DALFY2026-27120001', $this->as()->getJson(self::H.'/salary/adjustments?period=APP-DALFY2026-27110001')->json('data.adjustments.1.paid_in'));
        foreach (['review', 'approve', 'finalize'] as $step) {
            $this->as()->postJson(self::H.'/salary/monthly/move', ['period' => 'APP-DALFY2026-27120001', 'action' => $step])->assertOk();
        }
        $this->assertSame('APPLIED', DB::table('hr_payroll_adjustments')->where('ref_no', 'PADJ-000001')->value('status'));
        $after = $this->as()->getJson(self::H.'/salary/adjustments?period=APP-DALFY2026-27110001')->json('data');
        $this->assertEquals([0, 1], [$after['summary']['flagged'], $after['summary']['applied']]);
    }

    private function period(string $no, string $start, string $end, string $pay): object
    {
        $year = DB::table('acc_fiscal_years')->where('company_id', $this->delta->id)->where('name', 'FY2026-27')->value('id');
        DB::table('hr_pay_periods')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'period_no' => $no, 'period_type' => 'MONTHLY', 'fiscal_year_id' => $year, 'month' => (int) substr($start, 5, 2),
            'start_date' => $start, 'end_date' => $end, 'pay_date' => $pay, 'status' => 'INITIATED', 'show_for_report' => true, 'closed' => false, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('hr_pay_periods')->where('period_no', $no)->first();
    }

    private function festival(string $name, string $date, string $month, ?string $set, int $min): string
    {
        $id = strtolower((string) Str::ulid());
        DB::table('hr_festivals')->insert(['id' => $id, 'company_id' => $this->delta->id, 'name' => $name, 'festival_date' => $date, 'year' => (int) substr($date, 0, 4), 'payment_month' => $month, 'bonus_rule_set_id' => $set, 'min_service' => $min, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function emp(string $card): string
    {
        return DB::table('hr_employees')->where('company_id', $this->delta->id)->where('card_no', $card)->value('id');
    }

    private function push(string $log): TestResponse
    {
        return $this->call('POST', '/iclock/cdata?SN=SN-SP-1&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $log)->assertOk();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
