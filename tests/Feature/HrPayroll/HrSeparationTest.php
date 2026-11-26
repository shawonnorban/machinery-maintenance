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
 * HR & Payroll → Separation (AnnotechERP 24/08): resignations and
 * terminations sent, rejected, reopened, withdrawn and approved (off the
 * roster after the last day, at once when it has passed); lefty cases
 * reported, investigated, confirmed, reinstated and closed with their
 * history; final settlements worked out from the records, edited,
 * recalculated, approved (locked) and paid; and who may.
 */
class HrSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const H = '/api/v1/hr-payroll';

    private Company $delta;

    private User $owner;

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
        $section = $row('sections', ['department_id' => $dept, 'name' => 'Sewing A', 'code' => 'SA']);
        $designation = $row('designations', ['department_id' => $dept, 'name' => 'Operator', 'code' => 'OP']);
        $types = collect($this->as()->getJson(self::H.'/profiles/options')->json('data.employee_types'))->pluck('value', 'label')->all();
        foreach ([['1001', 'Worker One', 'Worker'], ['1002', 'Worker Two', 'Worker'], ['1003', 'Staff One', 'Staff']] as [$card, $name, $type]) {
            $this->as()->postJson(self::H.'/profiles', ['card_no' => $card, 'factory_id' => $factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $types[$type], 'department_id' => $dept, 'section_id' => $section, 'designation_id' => $designation, 'joining_date' => '2026-01-01', 'gross_salary' => 21000, 'status' => 'ACTIVE'])->assertCreated();
        }
    }

    public function test_resignation_and_release(): void
    {
        $this->assertSame(['EMP-000001', 'EMP-000002'], array_column($this->as()->getJson(self::H.'/separation/employees?q=worker')->assertOk()->json('data'), 'code'));
        $this->as()->postJson(self::H.'/separations', [])->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.st.v.employee'))->assertJsonValidationErrors(['application_date', 'last_working_date', 'reason']);
        $this->as()->postJson(self::H.'/separations', ['employee' => 'EMP-000001', 'type' => 'RESIGNATION', 'application_date' => '2026-11-10', 'last_working_date' => '2026-11-01', 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.last_working_date.0', __('hrpayroll.st.v.last_before_application'));

        // Sent, refused twice, rejected, reopened and approved for a later day.
        $sent = $this->as()->postJson(self::H.'/separations', ['employee' => 'EMP-000001', 'type' => 'RESIGNATION', 'application_date' => '2026-11-01', 'last_working_date' => '2026-11-20', 'reason' => 'Moving to Chattogram'])->assertCreated()->json('data');
        $this->assertSame(__('hrpayroll.st.done.sent', ['ref' => 'SEP-2026-00001']), $sent['message']);
        $this->assertEquals(['SEP-2026-00001', 'RESIGNATION', 19, 'PENDING', false], [$sent['rows'][0]['ref'], $sent['rows'][0]['type'], $sent['rows'][0]['notice_days'], $sent['rows'][0]['status'], $sent['rows'][0]['applied']]);
        $this->as()->postJson(self::H.'/separations', ['employee' => 'EMP-000001', 'type' => 'RELEASE', 'application_date' => '2026-11-02', 'last_working_date' => '2026-11-30', 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.st.v.open_separation', ['ref' => 'SEP-2026-00001']));
        $this->as()->postJson(self::H.'/separations/SEP-2026-00001/decide', ['decision' => 'REJECT', 'note' => 'Notice too short'])->assertOk()->assertJsonPath('data.rows.0.status', 'REJECTED');
        $this->as()->postJson(self::H.'/separations/SEP-2026-00001/decide', ['decision' => 'APPROVE'])->assertUnprocessable();
        $this->as()->postJson(self::H.'/separations/SEP-2026-00001/reopen')->assertOk()->assertJsonPath('data.rows.0.status', 'PENDING');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->postJson(self::H.'/separations/SEP-2026-00001/decide', ['decision' => 'APPROVE', 'note' => 'Agreed'])->assertOk()
            ->assertJsonPath('data.message', __('hrpayroll.st.done.leaves_after', ['ref' => 'SEP-2026-00001', 'date' => '20-11-2026']));
        $this->assertSame('ACTIVE', DB::table('hr_employees')->where('card_no', '1001')->value('status'));

        // A pending one is withdrawn; one whose last day has passed takes the employee off at once.
        $this->as()->postJson(self::H.'/separations', ['employee' => 'EMP-000002', 'type' => 'RELEASE', 'application_date' => '2026-11-05', 'last_working_date' => '2026-11-30', 'notice_days' => 30, 'reason' => 'Contract ends'])->assertCreated()->assertJsonPath('data.rows.0.notice_days', 30);
        $this->as()->deleteJson(self::H.'/separations/SEP-2026-00002')->assertOk();
        $this->as()->postJson(self::H.'/separations', ['employee' => 'EMP-000003', 'type' => 'TERMINATION', 'application_date' => '2026-11-05', 'last_working_date' => '2026-11-05', 'reason' => 'Gross misconduct'])->assertCreated();
        $this->as()->postJson(self::H.'/separations/SEP-2026-00002/decide', ['decision' => 'APPROVE'])->assertOk()->assertJsonPath('data.message', __('hrpayroll.st.done.off_roster', ['ref' => 'SEP-2026-00002', 'date' => '05-11-2026']));
        $s3 = DB::table('hr_employees')->where('card_no', '1003')->first();
        $this->assertEquals(['SEPARATED', '2026-11-05', false], [$s3->status, substr((string) $s3->separation_date, 0, 10), (bool) $s3->active]);
        $this->assertSame(0, DB::table('hr_employee_assignments')->where('employee_id', $s3->id)->whereNull('effective_to')->count());
        $this->as()->getJson(self::H.'/separation/employees?q=1003')->assertOk()->assertJsonPath('data', []);

        // Settle from the row: a draft worked out from the records; only once.
        $this->as()->postJson(self::H.'/separations/SEP-2026-00002/settlement')->assertCreated()->assertJsonPath('data.message', __('hrpayroll.st.done.settlement_created', ['ref' => 'FS-000001']));
        $row = collect($this->as()->getJson(self::H.'/separations')->json('data.rows'))->firstWhere('ref', 'SEP-2026-00002');
        $this->assertEquals(['FS-000001', 'DRAFT', true], [$row['settlement_ref'], $row['settlement_status'], $row['applied']]);
        $this->as()->postJson(self::H.'/separations/SEP-2026-00002/settlement')->assertUnprocessable()->assertJsonPath('errors.decision.0', __('hrpayroll.st.v.settled'));
        $fs = DB::table('hr_settlements')->where('ref_no', 'FS-000001')->first();
        $this->assertEquals(['TERMINATION', '2026-11-05', 3500.0, round(3500 + (float) $fs->leave_encashment, 2)], [$fs->settlement_type, substr((string) $fs->action_date, 0, 10), (float) $fs->salary_due, (float) $fs->payable]);

        // On the day after the last working day the resignation takes effect.
        $this->travelTo(CarbonImmutable::parse('2026-11-21 09:00:00', 'Asia/Dhaka'));
        $rows = collect($this->as()->getJson(self::H.'/separations')->json('data.rows'))->keyBy('ref');
        $this->assertTrue($rows['SEP-2026-00001']['applied']);
        $this->assertSame('SEPARATED', DB::table('hr_employees')->where('card_no', '1001')->value('status'));

        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->getJson(self::H.'/separations')->assertOk()->assertJsonPath('data.can_request', false);
        $this->as($auditor)->postJson(self::H.'/separations', ['employee' => 'EMP-000002', 'type' => 'RESIGNATION', 'application_date' => '2026-11-21', 'last_working_date' => '2026-11-30', 'reason' => 'x'])->assertForbidden();
    }

    public function test_lefty_cases(): void
    {
        $w2 = DB::table('hr_employees')->where('card_no', '1002')->value('id');
        DB::table('hr_attendance_days')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'employee_id' => $w2, 'date' => '2026-11-03', 'status' => 'PRESENT', 'created_at' => now(), 'updated_at' => now()]);
        $found = $this->as()->getJson(self::H.'/lefty/candidates?q=1002')->assertOk()->json('data.0');
        $this->assertEquals(['EMP-000002', '2026-11-03', 8, null, 'Delta Apparels Ltd'], [$found['code'], $found['last_attendance_date'], $found['absent_days'], $found['open_record'], $found['company']]);

        $this->as()->postJson(self::H.'/lefty', ['employee' => 'EMP-000002'])->assertUnprocessable()->assertJsonValidationErrors(['last_working_date', 'reported_date', 'reason']);
        $base = ['employee' => 'EMP-000002', 'last_working_date' => '2026-11-03', 'reported_date' => '2026-11-11', 'reason' => 'Absent since 4 Nov, phone off', 'document_ref' => 'LL-1102'];
        $this->as()->postJson(self::H.'/lefty', $base)->assertCreated()->assertJsonPath('data.rows.0.ref', 'LF-2026-0001')->assertJsonPath('data.rows.0.actions', ['INVESTIGATE', 'CONFIRM', 'REJECT']);
        $this->as()->postJson(self::H.'/lefty', $base)->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.st.v.open_lefty', ['ref' => 'LF-2026-0001']));
        $this->assertSame('LF-2026-0001', $this->as()->getJson(self::H.'/lefty/candidates?q=1002')->json('data.0.open_record'));

        // Investigate, confirm (off the roster), reinstate (a reason needed), close.
        $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'INVESTIGATE'])->assertOk()->assertJsonPath('data.rows.0.status', 'UNDER_INVESTIGATION');
        $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'REJECT'])->assertUnprocessable()->assertJsonPath('errors.note.0', __('hrpayroll.st.v.note'));
        $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'REINSTATE', 'note' => 'x'])->assertUnprocessable()->assertJsonPath('errors.action.0', __('hrpayroll.st.v.lefty_step'));
        $c = $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'CONFIRM', 'note' => 'Letters unanswered'])->assertOk()->json('data.rows.0');
        $this->assertEquals(['CONFIRMED', ['REINSTATE', 'CLOSE'], false], [$c['status'], $c['actions'], $c['payroll_eligible']]);
        $e = DB::table('hr_employees')->where('id', $w2)->first();
        $this->assertEquals(['SEPARATED', '2026-11-03'], [$e->status, substr((string) $e->separation_date, 0, 10)]);
        $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'REINSTATE'])->assertUnprocessable();
        $r = $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'REINSTATE', 'note' => 'Returned with a medical certificate'])->assertOk()->json('data.rows.0');
        $this->assertEquals(['REINSTATED', ['CLOSE'], true], [$r['status'], $r['actions'], $r['payroll_eligible']]);
        $this->assertEquals(['ACTIVE', null], array_values((array) DB::table('hr_employees')->where('id', $w2)->first(['status', 'separation_date'])));
        $closed = $this->as()->postJson(self::H.'/lefty/LF-2026-0001/move', ['action' => 'CLOSE'])->assertOk()->json('data.rows.0');
        $this->assertSame([], $closed['actions']);
        $this->assertEquals([['', 'PENDING_REVIEW'], ['PENDING_REVIEW', 'UNDER_INVESTIGATION'], ['UNDER_INVESTIGATION', 'CONFIRMED'], ['CONFIRMED', 'REINSTATED'], ['REINSTATED', 'CLOSED']], array_map(fn ($h) => [$h['from'], $h['to']], $closed['history']));
        $this->assertSame('Returned with a medical certificate', $closed['history'][3]['note']);

        // A confirmed lefty settles as long absenteeism.
        $this->as()->postJson(self::H.'/lefty', ['employee' => 'EMP-000001', 'last_working_date' => '2026-11-05', 'reported_date' => '2026-11-11', 'reason' => 'Not seen since 5 Nov'])->assertCreated();
        $this->as()->postJson(self::H.'/lefty/LF-2026-0002/move', ['action' => 'CONFIRM'])->assertOk();
        $preview = $this->as()->getJson(self::H.'/settlements/preview?employee=EMP-000001')->assertOk()->json('data');
        $this->assertEquals(['DISABLE_FOR_LONG_ABSENTEEISM', '2026-11-05', __('hrpayroll.st.lefty_line', ['ref' => 'LF-2026-0002', 'date' => '05-11-2026'])], [$preview['settlement_type'], $preview['action_date'], $preview['separation']]);

        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->assertSame(['report' => false, 'confirm' => false, 'reverse' => false], $this->as($auditor)->getJson(self::H.'/lefty')->assertOk()->json('data.can'));
        $this->as($auditor)->postJson(self::H.'/lefty', $base)->assertForbidden();
    }

    public function test_final_settlement(): void
    {
        $this->as()->postJson(self::H.'/separations', ['employee' => 'EMP-000003', 'type' => 'RESIGNATION', 'application_date' => '2026-10-20', 'last_working_date' => '2026-11-06', 'reason' => 'Higher studies'])->assertCreated();
        $this->as()->postJson(self::H.'/separations/SEP-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        $list = $this->as()->getJson(self::H.'/settlements')->assertOk()->json('data');
        $this->assertSame([], $list['rows']);
        $this->assertSame(__('hrpayroll.st.to_settle', ['who' => 'EMP-000003 — Staff One']), $list['employees'][0]['label']);

        // Worked out: 1–6 Nov less an absent day at 21000 ÷ 30; one approved, unpaid adjustment.
        $s3 = DB::table('hr_employees')->where('card_no', '1003')->value('id');
        DB::table('hr_attendance_days')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'employee_id' => $s3, 'date' => '2026-11-04', 'status' => 'ABSENT', 'created_at' => now(), 'updated_at' => now()]);
        $p = $this->as()->getJson(self::H.'/settlements/preview?employee=EMP-000003')->assertOk()->json('data');
        $this->assertEquals(['RESIGN_FROM_JOB', '2026-11-06', 3500.0, 0.0, 0.0, 0.0], [$p['settlement_type'], $p['action_date'], $p['salary_due'], $p['ot_due'], $p['bonus_due'], $p['loan_recovery']]);
        $this->assertStringContainsString(__('hrpayroll.st.note.no_paid'), $p['notes']);
        $this->assertSame(__('hrpayroll.st.sep_line', ['ref' => 'SEP-2026-00001', 'type' => 'resignation', 'date' => '06-11-2026']), $p['separation']);

        $this->as()->postJson(self::H.'/settlements', ['employee' => 'EMP-000003'])->assertUnprocessable()->assertJsonValidationErrors(['settlement_type', 'action_date']);
        $body = ['employee' => 'EMP-000003', 'settlement_type' => 'RESIGN_FROM_JOB', 'action_date' => '2026-11-06', 'salary_due' => $p['salary_due'], 'ot_due' => 0, 'bonus_due' => 0, 'leave_encashment' => $p['leave_encashment'], 'deduction' => 500, 'loan_recovery' => 0, 'other_adjustment' => 0, 'remarks' => 'ID card returned'];
        $saved = $this->as()->postJson(self::H.'/settlements', $body)->assertCreated()->json('data');
        $row = $saved['rows'][0];
        $this->assertEquals(['FS-000001', 'SEP-2026-00001', 'DRAFT', '0y 10m 5d', round(3500 + $p['leave_encashment'], 2), 500.0, round(3000 + $p['leave_encashment'], 2)], [$row['ref'], $row['separation'], $row['status'], $row['service_length'], $row['dues'], $row['recoveries'], $row['payable']]);
        $this->assertSame('EMP-000003 — Staff One', collect($saved['employees'])->firstWhere('value', 'EMP-000003')['label']);
        $this->as()->postJson(self::H.'/settlements', $body)->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.st.v.draft_exists'));
        $this->assertEquals(round(2900 + $p['leave_encashment'], 2), $this->as()->putJson(self::H.'/settlements/FS-000001', ['other_adjustment' => -100] + $body)->assertOk()->json('data.rows.0.payable'));

        // Calculate puts the worked-out figures back; approve locks; paid.
        $this->as()->postJson(self::H.'/settlements/FS-000001/move', ['action' => 'calculate'])->assertOk()->assertJsonPath('data.rows.0.deduction', 0);
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as()->postJson(self::H.'/settlements/FS-000001/move', ['action' => 'paid'])->assertUnprocessable();
        $this->as($manager)->postJson(self::H.'/settlements/FS-000001/move', ['action' => 'approve'])->assertOk()->assertJsonPath('data.rows.0.status', 'APPROVED');
        $this->as()->putJson(self::H.'/settlements/FS-000001', $body)->assertUnprocessable()->assertJsonPath('errors.status.0', __('hrpayroll.st.v.locked'));
        $this->as()->deleteJson(self::H.'/settlements/FS-000001')->assertUnprocessable();
        $paid = $this->as($manager)->postJson(self::H.'/settlements/FS-000001/move', ['action' => 'paid'])->assertOk()->json('data.rows.0');
        $this->assertSame('PAID', $paid['status']);
        $this->assertNotNull($paid['paid_at']);

        // A draft for anyone else can be deleted.
        $this->as()->postJson(self::H.'/settlements', ['employee' => 'EMP-000001', 'settlement_type' => 'DEATH', 'action_date' => '2026-11-10', 'salary_due' => 1000])->assertCreated();
        $this->as()->deleteJson(self::H.'/settlements/FS-000002')->assertOk();

        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->getJson(self::H.'/settlements')->assertOk()->assertJsonPath('data.can_manage', false);
        $this->as($auditor)->postJson(self::H.'/settlements', $body)->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
