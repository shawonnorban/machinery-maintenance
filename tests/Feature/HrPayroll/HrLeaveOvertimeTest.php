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
 * HR & Payroll → Leave & Overtime (AnnotechERP 24/05): leave keyed and
 * worked into the attendance (daily leave, an early departure), balances;
 * OT eligibility (limits, grade switches, Worker OT for staff) and the OT it
 * counts, OT corrections with their history; OT requests sent, returned,
 * resubmitted and approved with someone unticked, the approved window capping
 * the OT paid; and who may.
 */
class HrLeaveOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private const H = '/api/v1/hr-payroll';

    private Company $delta;

    private User $owner;

    private string $factory;

    private string $dept;

    private string $section;

    private string $line;

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
        $this->section = $row('sections', ['department_id' => $this->dept, 'name' => 'Sewing A', 'code' => 'SA']);
        $this->line = $row('production_lines', ['department_id' => $this->dept, 'section_id' => $this->section, 'name' => 'Line 1', 'code' => 'L1']);
        $designation = $row('designations', ['department_id' => $this->dept, 'name' => 'Operator', 'code' => 'OP']);
        $this->types = collect($this->as()->getJson(self::H.'/profiles/options')->json('data.employee_types'))->pluck('value', 'label')->all();
        foreach ([['1001', 'Worker One', 'Worker'], ['1002', 'Worker Two', 'Worker'], ['1003', 'Staff One', 'Staff']] as [$card, $name, $type]) {
            $this->as()->postJson(self::H.'/profiles', ['card_no' => $card, 'factory_id' => $this->factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $this->types[$type], 'department_id' => $this->dept, 'designation_id' => $designation, 'joining_date' => '2026-01-01', 'gross_salary' => 20000, 'status' => 'ACTIVE'])->assertCreated();
        }
        DB::table('hr_employees')->where('company_id', $this->delta->id)->update(['grade_id' => DB::table('grades')->where('company_id', $this->delta->id)->where('code', '4')->value('id'), 'section_id' => $this->section]);
        DB::table('hr_employees')->where('company_id', $this->delta->id)->whereIn('card_no', ['1001', '1002'])->update(['line_id' => $this->line]);
        $this->as()->postJson('/api/v1/settings/devices', ['code' => 'GATE-01', 'name' => 'Main Gate', 'protocol' => 'ADMS_PUSH', 'serial_no' => 'SN-LO-1'])->assertCreated();
        // Tuesday 10 Nov: W1 07:55–20:10 (3 h past 17:00), W2 08:00–19:30, S1 08:05–16:00 (an hour early).
        $this->push("1001\t2026-11-10 07:55:00\t0\t1\n1001\t2026-11-10 20:10:00\t1\t1\n1002\t2026-11-10 08:00:00\t0\t1\n1002\t2026-11-10 19:30:00\t1\t1\n1003\t2026-11-10 08:05:00\t0\t1\n1003\t2026-11-10 16:00:00\t1\t1\n");
    }

    public function test_leave_is_keyed_worked_into_attendance_and_balanced(): void
    {
        $options = $this->as()->getJson(self::H.'/leave/options')->assertOk()->json('data');
        $this->assertCount(8, $options['leave_types']);
        $year = $options['fiscal_year'];
        $this->assertSame('FY2026-27', collect($options['fiscal_years'])->firstWhere('value', $year)['label']);
        $type = collect($options['leave_types'])->pluck('value', 'label');

        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'DAILY', 'status' => 'APPROVED'])->assertUnprocessable()->assertJsonValidationErrors(['employee', 'leave_type_id', 'from_date', 'to_date']);
        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'DAILY', 'employee' => 'EMP-000001', 'leave_type_id' => $type['Casual Leave (CL)'], 'from_date' => '2026-11-09', 'to_date' => '2026-11-10', 'status' => 'APPROVED', 'reason' => 'Fever'])
            ->assertCreated()->assertJsonPath('data.ref_no', 'LV-000001');
        // The days are Leave even with the punches; the joining date is the day after.
        $days = collect($this->as()->getJson(self::H.'/attendance/daily?from=2026-11-09&to=2026-11-10')->json('data.rows'))->where('name', 'Worker One')->pluck('status', 'date')->all();
        $this->assertEquals(['2026-11-09' => 'LEAVE', '2026-11-10' => 'LEAVE'], $days);
        $this->assertSame('2026-11-11', substr((string) DB::table('hr_leave_applications')->where('ref_no', 'LV-000001')->value('joining_date'), 0, 10));

        // Overlap, a leave type for women only, a year it is not in.
        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'DAILY', 'employee' => 'EMP-000001', 'leave_type_id' => $type['Sick Leave (SL)'], 'from_date' => '2026-11-10', 'to_date' => '2026-11-12', 'status' => 'PENDING'])
            ->assertUnprocessable()->assertJsonPath('errors.from_date.0', __('hrpayroll.lo.v.overlap', ['ref' => 'LV-000001']));
        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'DAILY', 'employee' => 'EMP-000002', 'leave_type_id' => $type['Maternity Leave (ML)'], 'from_date' => '2026-11-12', 'to_date' => '2026-11-12', 'status' => 'APPROVED'])
            ->assertUnprocessable()->assertJsonValidationErrors(['leave_type_id']);
        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'DAILY', 'employee' => 'EMP-000002', 'leave_type_id' => $type['Sick Leave (SL)'], 'from_date' => '2027-01-02', 'to_date' => '2027-01-03', 'status' => 'APPROVED'])
            ->assertUnprocessable()->assertJsonValidationErrors(['from_date']);

        // An early departure excuses Staff One's early out.
        $this->assertSame(60, collect($this->as()->getJson(self::H.'/attendance/daily?from=2026-11-10&to=2026-11-10')->json('data.rows'))->firstWhere('name', 'Staff One')['early']);
        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'EARLY_DEPARTURE', 'employee' => 'EMP-000003', 'leave_type_id' => $type['Sick Leave (SL)'], 'early_date' => '2026-11-10', 'early_time' => '16:00', 'hours' => 1, 'purpose' => 'Doctor', 'status' => 'APPROVED'])
            ->assertCreated()->assertJsonPath('data.ref_no', 'LV-000002');
        $s1 = collect($this->as()->getJson(self::H.'/attendance/daily?from=2026-11-10&to=2026-11-10')->json('data.rows'))->firstWhere('name', 'Staff One');
        $this->assertEquals([0, false, 'PRESENT'], [$s1['early'], $s1['is_early_out'], $s1['status']]);

        // Balances: Casual 10 less 2; Sick 14 less an hour.
        $bal = collect($this->as()->getJson(self::H.'/leave/balances?employee=EMP-000001')->assertOk()->json('data.rows'))->keyBy('code');
        $this->assertEquals([10.0, 2.0, 8.0, 64.0], [$bal['CL']['entitled_day'], $bal['CL']['taken_day'], $bal['CL']['balance_day'], $bal['CL']['balance_hour']]);
        $this->assertArrayNotHasKey('ML', $bal->all());
        $this->assertNull($bal['LWP']['balance_day']);
        $sl = collect($this->as()->getJson(self::H.'/leave/balances?employee=EMP-000003')->json('data.rows'))->firstWhere('code', 'SL');
        $this->assertEquals([1.0, 13.88], [$sl['taken_hour'], $sl['balance_day']]);

        // A type that checks its balance refuses more than is left.
        DB::table('hr_leave_types')->where('id', $type['Casual Leave (CL)'])->update(['balance_check' => true]);
        $this->as()->postJson(self::H.'/leave', ['fiscal_year_id' => $year, 'mode' => 'DAILY', 'employee' => 'EMP-000001', 'leave_type_id' => $type['Casual Leave (CL)'], 'from_date' => '2026-12-01', 'to_date' => '2026-12-09', 'status' => 'APPROVED'])
            ->assertUnprocessable()->assertJsonPath('errors.to_date.0', __('hrpayroll.lo.v.balance', ['left' => 8]));

        // Records; a deleted leave gives the days back to the punches.
        $this->assertSame(['LV-000002', 'LV-000001'], array_column($this->as()->getJson(self::H.'/leave')->assertOk()->json('data'), 'ref_no'));
        $this->as()->deleteJson(self::H.'/leave/LV-000001')->assertOk();
        $this->assertSame('PRESENT', collect($this->as()->getJson(self::H.'/attendance/daily?from=2026-11-10&to=2026-11-10')->json('data.rows'))->firstWhere('name', 'Worker One')['status']);

        // Who may: a factory manager reads leave but does not key it; a storekeeper sees nothing.
        $fm = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->as($fm)->getJson(self::H.'/leave')->assertOk();
        $this->as($fm)->postJson(self::H.'/leave', [])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson(self::H.'/leave')->assertForbidden();
    }

    public function test_ot_eligibility_limits_and_corrections(): void
    {
        $data = $this->as()->getJson(self::H.'/ot/eligibility')->assertOk()->json('data');
        $rows = collect($data['rows'])->keyBy('code');
        $this->assertEquals(['GENERAL', 'Graded worker · OT-STD · Basic ÷ 208 × 2'], [$rows['EMP-000001']['ot'], $rows['EMP-000001']['general_reason']]);
        $this->assertEquals(['NONE', 'Staff', 'Staff — no general OT', 'No Worker OT rule', true], [$rows['EMP-000003']['ot'], $rows['EMP-000003']['category'], $rows['EMP-000003']['general_reason'], $rows['EMP-000003']['worker_rule'], $rows['EMP-000003']['worker_ot_allowed']]);
        $this->assertEquals(['employees' => 3, 'general_ot' => 2, 'staff' => 1, 'worker_ot' => 0], $data['summary']);

        // W1 stayed 3 h 10 past the shift: 3 h of OT at Basic ÷ 208 × 2.
        $day = collect($this->as()->getJson(self::H.'/ot/correction?date=2026-11-10')->assertOk()->json('data.rows'))->keyBy('code');
        $rate = round((new OtBook($this->delta->id))->basic(DB::table('hr_employees')->where('card_no', '1001')->first()) / 208 * 2, 2);
        $this->assertEquals([3.0, $rate, round(3 * $rate, 2), '20:10', '17:00', 'GENERAL'], [$day['EMP-000001']['original_hours'], $day['EMP-000001']['original_rate'], $day['EMP-000001']['original_amount'], $day['EMP-000001']['out_time'], $day['EMP-000001']['shift_end'], $day['EMP-000001']['ot_type']]);
        $this->assertArrayNotHasKey('EMP-000003', $day->all());

        // Limits: rounding in whole hours; OT from 30 minutes after the shift end, capped at 2 h.
        $limits = ['start_after_min' => 30, 'grace_min' => 0, 'general_rounding_min' => 90, 'worker_rounding_min' => 60, 'break_min' => 0, 'break_after_hours' => 0, 'min_minutes' => 0, 'max_hours_day' => 2];
        $this->as()->postJson(self::H.'/ot/limits', $limits)->assertUnprocessable()->assertJsonValidationErrors(['general_rounding_min']);
        $this->as()->postJson(self::H.'/ot/limits', ['general_rounding_min' => 60] + $limits)->assertOk();
        $this->assertEquals(2.0, collect($this->as()->getJson(self::H.'/ot/correction?date=2026-11-10')->json('data.rows'))->firstWhere('code', 'EMP-000001')['original_hours']);
        // W2 left 19:30: 2 h past 17:30, but a 45-minute minimum with 15 minutes' grace rounds 1 h 50 up to 2 h, and no cap.
        $this->as()->postJson(self::H.'/ot/limits', ['general_rounding_min' => 60, 'start_after_min' => 40, 'grace_min' => 15, 'max_hours_day' => 0, 'min_minutes' => 45] + $limits)->assertOk();
        $this->assertEquals(2.0, collect($this->as()->getJson(self::H.'/ot/correction?date=2026-11-10')->json('data.rows'))->firstWhere('code', 'EMP-000002')['original_hours']);
        $this->as()->postJson(self::H.'/ot/limits', ['general_rounding_min' => 60, 'start_after_min' => 0, 'max_hours_day' => 0] + $limits)->assertOk();
        $this->assertSame(3, DB::table('hr_ot_audit')->where('subject', 'LIMITS')->count());

        // Grade 4 switched off (with a reason): no General OT; back on.
        $this->as()->postJson(self::H.'/ot/grade', ['grade' => '4', 'general_ot' => false])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->as()->postJson(self::H.'/ot/grade', ['grade' => '4', 'general_ot' => false, 'reason' => 'OT policy 2026'])->assertOk();
        $this->assertSame('Grade 4 — General OT switched off', collect($this->as()->getJson(self::H.'/ot/eligibility')->json('data.rows'))->firstWhere('code', 'EMP-000001')['general_reason']);
        $this->assertSame([], $this->as()->getJson(self::H.'/ot/correction?date=2026-11-10')->json('data.rows'));
        $this->as()->postJson(self::H.'/ot/grade', ['grade' => '4', 'general_ot' => true, 'reason' => 'Restored'])->assertOk();

        // Worker OT for staff, by a rule written for the Staff type; never for a worker.
        DB::table('hr_ot_rules')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'code' => 'OT-STAFF', 'name' => 'Staff OT', 'wage_basis' => 'GROSS', 'divisor' => 208, 'multiplier' => 1, 'effective_from' => '2026-01-01', 'employment_type_id' => $this->types['Staff'], 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->as()->postJson(self::H.'/ot/employee', ['employee' => 'EMP-000001', 'worker_ot' => true])->assertUnprocessable()->assertJsonValidationErrors(['worker_ot']);
        $this->as()->postJson(self::H.'/ot/employee', ['employee' => 'EMP-000003', 'worker_ot' => true, 'note' => 'Shipment week'])->assertOk();
        $s1 = collect($this->as()->getJson(self::H.'/ot/eligibility')->json('data.rows'))->firstWhere('code', 'EMP-000003');
        $this->assertEquals(['WORKER', 'OT-STAFF · Gross ÷ 208 × 1', 'Shipment week'], [$s1['ot'], $s1['worker_rule'], $s1['note']]);

        // A correction: a reason first, then saved, its history; reset (with a reason) back to the attendance.
        $this->as()->postJson(self::H.'/ot/correction', ['employee' => 'EMP-000001', 'date' => '2026-11-10', 'hours' => 4, 'rate' => 100])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $saved = $this->as()->postJson(self::H.'/ot/correction', ['employee' => 'EMP-000001', 'date' => '2026-11-10', 'hours' => 4, 'rate' => 100, 'reason' => 'Gate log shows 21:10'])->assertOk()->json('data.row');
        $this->assertEquals([4.0, 100.0, 400.0, 'Gate log shows 21:10', true], [$saved['corrected_hours'], $saved['corrected_rate'], $saved['corrected_amount'], $saved['reason'], $saved['has_history']]);
        $summary = $this->as()->getJson(self::H.'/ot/correction?date=2026-11-10')->json('data.summary');
        $this->assertEquals([1, 5.0, 6.0], [$summary['corrected'], $summary['original_hours'], $summary['effective_hours']]);
        $this->as()->postJson(self::H.'/ot/correction/reset', ['employee' => 'EMP-000001', 'date' => '2026-11-10'])->assertUnprocessable();
        $this->assertNull($this->as()->postJson(self::H.'/ot/correction/reset', ['employee' => 'EMP-000001', 'date' => '2026-11-10', 'reason' => 'Typed on the wrong day'])->assertOk()->json('data.row.corrected_hours'));
        $this->assertSame(['RESET', 'SAVE'], array_column($this->as()->getJson(self::H.'/ot/correction/history?employee=EMP-000001&date=2026-11-10')->assertOk()->json('data'), 'action'));

        // A factory manager reads but does not change OT.
        $fm = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->as($fm)->getJson(self::H.'/ot/correction')->assertOk();
        $this->as($fm)->postJson(self::H.'/ot/limits', $limits)->assertForbidden();
    }

    public function test_ot_requests_are_sent_returned_approved_and_cap_the_ot(): void
    {
        $fm = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $options = $this->as()->getJson(self::H.'/ot/options')->assertOk()->json('data');
        $this->assertTrue($options['can_approve']);
        $this->assertContains($fm->id, array_column($options['approvers'], 'value'));
        $general = collect($options['shifts'])->firstWhere('start', '08:00')['value'];

        $line = $this->as()->getJson(self::H."/ot/line-employees?line={$this->line}&date=2026-11-10")->assertOk()->json('data');
        $this->assertEquals([2, $general, ['EMP-000001', 'EMP-000002']], [$line['total'], $line['shift_id'], array_column($line['employees'], 'code')]);

        $body = ['date' => '2026-11-10', 'department_id' => $this->dept, 'section_id' => $this->section, 'line_id' => $this->line, 'shift_id' => $general, 'ot_start' => '17:00', 'ot_end' => '19:00',
            'reason' => 'Shipment due', 'employees' => ['EMP-000001', 'EMP-000002'], 'approval' => 'ROUTED'];
        $this->as()->postJson(self::H.'/ot/requests', $body)->assertUnprocessable()->assertJsonValidationErrors(['approver_id']);
        $this->as()->postJson(self::H.'/ot/requests', ['employees' => ['EMP-000003'], 'approver_id' => $fm->id] + $body)->assertUnprocessable()->assertJsonValidationErrors(['employees']);
        $made = $this->as()->postJson(self::H.'/ot/requests', ['approver_id' => $fm->id] + $body)->assertCreated()->json('data.row');
        $this->assertEquals(['OTR-000001', 'PENDING', 2, 4.0, 'fm@delta.test'], [$made['request_no'], $made['status'], $made['employee_count'], $made['requested_hours'], DB::table('users')->where('id', $made['approver_id'])->value('email')]);
        $this->assertTrue(DB::table('notifications')->where('user_id', $fm->id)->where('event_type', 'HR_OT_REQUESTED')->exists());
        $this->as()->postJson(self::H.'/ot/requests', ['approver_id' => $fm->id] + $body)->assertUnprocessable()->assertJsonPath('errors.employees.0', __('hrpayroll.lo.v.already', ['code' => 'EMP-000001', 'no' => 'OTR-000001']));

        // The approver returns it with a note; the requester hears; it is edited and sent again.
        $box = $this->as($fm)->getJson(self::H.'/ot/approvals')->assertOk()->json('data');
        $this->assertSame(['OTR-000001'], array_column($box['inbox'], 'request_no'));
        $this->as($fm)->postJson(self::H.'/ot/requests/OTR-000001/decide', ['decision' => 'RETURN'])->assertUnprocessable()->assertJsonValidationErrors(['note']);
        $this->as($fm)->postJson(self::H.'/ot/requests/OTR-000001/decide', ['decision' => 'RETURN', 'note' => 'Two hours is enough — end at 19:00 and say which order'])->assertOk();
        $this->assertTrue(DB::table('notifications')->where('user_id', $this->owner->id)->where('event_type', 'HR_OT_DECIDED')->exists());
        $this->as()->putJson(self::H.'/ot/requests/OTR-000001', ['approver_id' => $fm->id, 'reason' => 'Shipment due — PO 4471'] + $body)->assertOk();
        $this->as()->putJson(self::H.'/ot/requests/OTR-000001', ['approver_id' => $fm->id] + $body)->assertUnprocessable()->assertJsonValidationErrors(['status']);

        // Approved without Worker Two: Worker One's 3 h is capped to the 2 h window.
        $this->as($fm)->postJson(self::H.'/ot/requests/OTR-000001/decide', ['decision' => 'APPROVE', 'removed' => ['EMP-000001', 'EMP-000002']])->assertUnprocessable()->assertJsonValidationErrors(['removed']);
        $this->as($fm)->postJson(self::H.'/ot/requests/OTR-000001/decide', ['decision' => 'APPROVE', 'removed' => ['EMP-000002']])->assertOk()->assertJsonPath('data.message', __('hrpayroll.lo.done.decided.APPROVED', ['no' => 'OTR-000001', 'n' => 1]));
        $this->as($fm)->postJson(self::H.'/ot/requests/OTR-000001/decide', ['decision' => 'REJECT', 'note' => 'x'])->assertUnprocessable();
        $req = $this->as()->getJson(self::H.'/ot/requests')->assertOk()->json('data.0');
        $people = collect($req['employees'])->keyBy('code');
        $this->assertEquals(['APPROVED', 1, 2.0, 3.17, 2.0, 0.0, true], [$req['status'], $req['employee_count'], $people['EMP-000001']['approved_hours'], $people['EMP-000001']['actual_hours'], $people['EMP-000001']['payable_hours'], $people['EMP-000002']['approved_hours'], $people['EMP-000002']['removed']]);
        $this->assertEquals(2.0, collect($this->as()->getJson(self::H.'/ot/correction?date=2026-11-10')->json('data.rows'))->firstWhere('code', 'EMP-000001')['original_hours']);
        $this->assertSame(['OTR-000001'], array_column($this->as($fm)->getJson(self::H.'/ot/approvals')->json('data.decided'), 'request_no'));

        // Approved directly by whoever holds OT Approval; not by an auditor, who cannot request at all.
        $direct = $this->as()->postJson(self::H.'/ot/requests', ['date' => '2026-11-11', 'ot_start' => '17:00', 'ot_end' => '01:00', 'approval' => 'DIRECT'] + $body)->assertCreated()->json('data.row');
        $this->assertEquals(['OTR-000002', 'APPROVED', 8.0], [$direct['request_no'], $direct['status'], $direct['hours']]);
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->getJson(self::H.'/ot/requests')->assertOk();
        $this->as($auditor)->postJson(self::H.'/ot/requests', $body)->assertForbidden();
        $this->as($auditor)->postJson(self::H.'/ot/requests/OTR-000002/decide', ['decision' => 'APPROVE'])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson(self::H.'/ot/approvals')->assertForbidden();
    }

    private function push(string $log): TestResponse
    {
        return $this->call('POST', '/iclock/cdata?SN=SN-LO-1&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $log)->assertOk();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
