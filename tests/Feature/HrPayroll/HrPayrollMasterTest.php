<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\HrPayroll\Support\HolidayCalendar;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Master Data (AnnotechERP 24/01): the lists a company starts
 * with as Brickland ships them; the type and category trees, grades shared
 * with Company Management, pay elements; shift, leave, OT and bonus rules;
 * wage gazettes with their policies, structure lines and grade wages;
 * festivals and the holiday calendar; pay periods with their numbering and
 * life, allowance and deduction entry and upload, payroll settings and the
 * shared fiscal year register — and who may change them.
 */
class HrPayrollMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_the_lists_open_with_bricklands_defaults(): void
    {
        $this->as()->getJson('/api/v1/erp/hr-employment-types')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.short_name', 'STF');
        $groups = collect($this->as()->getJson('/api/v1/erp/hr-designation-groups')->json('data'));
        $this->assertCount(45, $groups);
        $this->assertSame(['Operetions Head', 'Merchandiser'], $groups->where('is_leaf', false)->pluck('name')->values()->all());
        $this->assertNull($groups->firstWhere('name', 'Worker')['manpower_category']);
        $this->assertSame('QUALITY', $groups->firstWhere('name', 'QI')['manpower_category']);
        $this->as()->getJson('/api/v1/erp/hr-grades')->assertJsonCount(7, 'data')->assertJsonPath('data.3.code_bn', 'Grade-4');
        $elements = collect($this->as()->getJson('/api/v1/erp/hr-pay-elements')->json('data'));
        $this->assertSame(['BASIC', 'CONV', 'FOOD', 'GROSS', 'HRENT', 'MED', 'OTREG', 'SHIFT'], $elements->pluck('code')->all());
        $this->assertSame(3, $elements->firstWhere('code', 'BASIC')['in_structures']);
        $shifts = collect($this->as()->getJson('/api/v1/erp/hr-shifts')->json('data'));
        $this->assertTrue($shifts->firstWhere('code', 'NIGHT')['cross_day']);
        $this->assertFalse($shifts->firstWhere('code', 'DAY')['cross_day']);
        // Two shifts in the whole system: Day 08:00–17:00 (break 13:00) and Night 20:00–05:00 (break 01:00).
        $this->assertEquals([['DAY', '08:00', '17:00', '13:00', 60], ['NIGHT', '20:00', '05:00', '01:00', 60]], $shifts->map(fn ($s) => [$s['code'], $s['start_time'], $s['end_time'], $s['break_start'], $s['break_min']])->all());
        $leaves = collect($this->as()->getJson('/api/v1/erp/hr-leave-types')->json('data'));
        $this->assertCount(8, $leaves);
        $this->assertSame(['FEMALE', 112], [$leaves->firstWhere('code', 'ML')['applicable_for'], $leaves->firstWhere('code', 'ML')['max_days']]);
        $this->as()->getJson('/api/v1/erp/hr-ot-rules')->assertJsonPath('data.0.formula', 'Basic ÷ 208 × 2');
        $bonus = collect($this->as()->getJson('/api/v1/erp/hr-bonus-rules')->json('data'));
        $this->assertSame([4, 2], [$bonus->where('employment_type', 'Staff')->count(), $bonus->where('employment_type', 'Worker')->count()]);
        $lines = collect($this->as()->getJson('/api/v1/erp/hr-gazette-lines')->json('data'));
        $this->assertCount(15, $lines);
        $this->assertSame(['Gross ÷ 1.5', '50% of Basic', '৳750'], $lines->take(3)->pluck('means')->all());
        $this->as()->getJson('/api/v1/erp/hr-gazette-grade-wages')->assertJsonPath('data.0.gross_a', 12500)->assertJsonPath('data.0.house_rent', 3350);
        $this->as()->getJson('/api/v1/erp/hr-holidays')->assertJsonCount(3, 'data')->assertJsonPath('data.0.code', 'HOL-JANMASHTAMI');
        $this->as()->getJson('/api/v1/hr-payroll/settings')->assertJsonCount(6, 'data')->assertJsonPath('data.1.value', 'LATE_DAYS');
        $this->as()->getJson('/api/v1/erp/hr-fiscal-years')->assertJsonCount(2, 'data');
        $look = $this->as()->getJson('/api/v1/hr-payroll/lookups')->assertOk()->json('data');
        $this->assertSame('Delta Apparels Ltd (DAL)', $look['company']['label']);
        $this->assertSame(5, collect($look['gazettes'])->firstWhere('label', 'RMG Minimum Wage 2023 (Non-EPZ)')['lines']);
    }

    public function test_trees_grades_and_pay_elements(): void
    {
        $staff = collect($this->as()->getJson('/api/v1/erp/hr-employment-types')->json('data'))->firstWhere('name', 'Staff');
        $this->as()->postJson('/api/v1/erp/hr-employment-types', ['name_bn' => 'x', 'short_name' => 'x'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Type Name [E] is required.');
        $probation = $this->as()->postJson('/api/v1/erp/hr-employment-types', ['parent_id' => $staff['id'], 'name' => 'Probation', 'name_bn' => 'Probation', 'short_name' => 'PRB'])->assertCreated()->assertJsonPath('data.level', 2)->json('data');
        $this->as()->getJson("/api/v1/erp/hr-employment-types/{$staff['id']}")->assertJsonPath('data.is_leaf', false);
        $this->as()->patchJson("/api/v1/erp/hr-employment-types/{$staff['id']}", ['parent_id' => $probation['id']])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->as()->postJson('/api/v1/erp/hr-employment-types', ['name' => 'staff', 'name_bn' => 'x', 'short_name' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $tree = $this->as()->getJson('/api/v1/hr-payroll/type-tree')->json('data');
        $this->assertSame(['Staff', '— Probation', 'Worker'], array_column($tree, 'label'));
        $this->as()->getJson("/api/v1/hr-payroll/type-tree/{$staff['id']}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.short_name', 'PRB');
        // The types under a node are corrected in the grid and saved as a whole.
        $this->as()->putJson("/api/v1/hr-payroll/type-tree/{$staff['id']}", ['rows' => [['id' => $probation['id'], 'name' => 'Worker', 'name_bn' => 'x', 'short_name' => 'PRB']]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.name');
        $this->as()->putJson("/api/v1/hr-payroll/type-tree/{$staff['id']}", ['rows' => [['id' => $probation['id'], 'name' => 'Probation Staff', 'name_bn' => 'শিক্ষানবিশ', 'short_name' => 'PRB', 'active' => true]]])
            ->assertOk()->assertJsonPath('data.0.name', 'Probation Staff')->assertJsonPath('data.0.leaf', 'Yes');
        // A node becomes a leaf again when its last child goes.
        $worker = collect($this->as()->getJson('/api/v1/erp/hr-employment-types')->json('data'))->firstWhere('name', 'Worker');
        $this->as()->patchJson("/api/v1/erp/hr-employment-types/{$probation['id']}", ['parent_id' => $worker['id']])->assertOk();
        $this->as()->getJson("/api/v1/erp/hr-employment-types/{$staff['id']}")->assertJsonPath('data.is_leaf', true);
        $this->as()->getJson("/api/v1/erp/hr-employment-types/{$worker['id']}")->assertJsonPath('data.is_leaf', false);
        $this->as()->deleteJson("/api/v1/erp/hr-employment-types/{$probation['id']}")->assertStatus(405);

        $this->as()->postJson('/api/v1/erp/hr-designation-groups', ['name_bn' => 'x', 'short_name' => 'x', 'sort_order' => 1])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Group Name [E] is required.');
        $this->as()->postJson('/api/v1/erp/hr-designation-groups', ['name' => 'Trainee', 'name_bn' => 'Trainee', 'short_name' => 'TRN', 'sort_order' => 420, 'manpower_category' => 'ROBOT'])->assertUnprocessable()->assertJsonValidationErrors('manpower_category');
        $this->as()->postJson('/api/v1/erp/hr-designation-groups', ['name' => 'Trainee', 'name_bn' => 'Trainee', 'short_name' => 'TRN', 'sort_order' => 420, 'manpower_category' => 'HELPER'])->assertCreated()->assertJsonPath('data.level', 1);

        // Pay Grade is Company Management's grade register, seen from HR.
        $this->as()->postJson('/api/v1/erp/hr-grades', ['code_bn' => 'গ্রেড-৮'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Grade# [E] is required.');
        $eight = $this->as()->postJson('/api/v1/erp/hr-grades', ['code' => '8', 'code_bn' => 'গ্রেড-৮'])->assertCreated()->assertJsonPath('data.name', 'Grade 8')->assertJsonPath('data.active', true)->json('data');
        $this->as()->postJson('/api/v1/erp/hr-grades', ['code' => '8', 'code_bn' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->patchJson("/api/v1/erp/hr-grades/{$eight['id']}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->assertSame('INACTIVE', DB::table('grades')->where('id', $eight['id'])->value('status'));

        $this->as()->postJson('/api/v1/erp/hr-pay-elements', ['name' => 'Night Allowance'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Element Code is required.');
        $this->as()->postJson('/api/v1/erp/hr-pay-elements', ['code' => 'night', 'name' => 'Night Allowance'])->assertCreated()->assertJsonPath('data.code', 'NIGHT')->assertJsonPath('data.in_structures', 0);
        $this->as()->postJson('/api/v1/erp/hr-pay-elements', ['code' => 'Basic', 'name' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_shift_leave_ot_and_bonus_rules(): void
    {
        $this->as()->postJson('/api/v1/erp/hr-shifts', ['name' => 'Late'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Shift Code is required.');
        $this->as()->postJson('/api/v1/erp/hr-shifts', ['code' => 'ST-X', 'name' => 'Late', 'start_time' => '8am', 'end_time' => '17:00'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->as()->postJson('/api/v1/erp/hr-shifts', ['code' => 'st-late', 'name' => 'Late Night', 'start_time' => '20:00', 'end_time' => '04:30', 'break_min' => 30])
            ->assertCreated()->assertJsonPath('data.code', 'ST-LATE')->assertJsonPath('data.cross_day', true)->assertJsonPath('data.working_hours', '8.00');

        $this->as()->postJson('/api/v1/erp/hr-leave-types', ['name' => 'Paternity'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Leave Type Code is required.');
        $this->as()->postJson('/api/v1/erp/hr-leave-types', ['code' => 'pl', 'name' => 'Paternity Leave', 'leave_group' => 'HOLIDAY', 'applicable_for' => 'MALE'])->assertUnprocessable()->assertJsonValidationErrors('leave_group');
        $this->as()->postJson('/api/v1/erp/hr-leave-types', ['code' => 'pl', 'name' => 'Paternity Leave', 'leave_group' => 'LEAVE', 'applicable_for' => 'MALE', 'max_days' => 5, 'color' => '#3987e5'])->assertCreated()->assertJsonPath('data.code', 'PL');
        $this->as()->postJson('/api/v1/erp/hr-leave-types', ['code' => 'cl', 'name' => 'Again', 'leave_group' => 'LEAVE', 'applicable_for' => 'COMMON'])->assertUnprocessable()->assertJsonValidationErrors('code');

        $worker = collect($this->as()->getJson('/api/v1/erp/hr-employment-types')->json('data'))->firstWhere('name', 'Worker')['id'];
        $this->as()->postJson('/api/v1/erp/hr-ot-rules', ['code' => 'OT-W'])->assertUnprocessable()->assertJsonPath('errors.effective_from.0', 'Effective From is required.');
        $this->as()->postJson('/api/v1/erp/hr-ot-rules', ['code' => 'OT-W', 'name' => 'Worker OT', 'wage_basis' => 'GROSS', 'divisor' => 208, 'multiplier' => 2, 'effective_from' => '2026-01-01', 'effective_to' => '2025-12-31'])->assertUnprocessable()->assertJsonValidationErrors('effective_to');
        $this->as()->postJson('/api/v1/erp/hr-ot-rules', ['code' => 'ot-w', 'name' => 'Worker OT', 'wage_basis' => 'GROSS', 'divisor' => 208, 'multiplier' => 1.5, 'effective_from' => '2026-01-01', 'employment_type_id' => $worker])
            ->assertCreated()->assertJsonPath('data.formula', 'Gross ÷ 208 × 1.5')->assertJsonPath('data.category', 'Worker');

        $set = $this->as()->getJson('/api/v1/hr-payroll/lookups')->json('data.rule_sets.0.value');
        $rule = ['rule_set_id' => $set, 'service_low' => 24, 'service_high' => 12, 'salary_low' => 1, 'salary_high' => 0, 'basis' => 'BASIC', 'pct' => 1, 'employment_type_id' => $worker];
        $this->as()->postJson('/api/v1/erp/hr-bonus-rules', ['rule_set_id' => $set])->assertUnprocessable()->assertJsonPath('errors.service_low.0', 'Service Length Low is required.');
        $this->as()->postJson('/api/v1/erp/hr-bonus-rules', $rule)->assertUnprocessable()->assertJsonValidationErrors('service_high');
        $this->as()->postJson('/api/v1/erp/hr-bonus-rules', [...$rule, 'service_high' => 0, 'pct' => null])->assertUnprocessable()->assertJsonValidationErrors('pct');
        $this->as()->postJson('/api/v1/erp/hr-bonus-rules', [...$rule, 'service_high' => 0])->assertCreated()->assertJsonPath('data.scope', '')->assertJsonPath('data.rule_set', 'Bonus Rule');
    }

    public function test_gazettes_their_policies_lines_and_grade_wages(): void
    {
        $look = $this->as()->getJson('/api/v1/hr-payroll/lookups')->json('data');
        $types = collect($look['types'])->pluck('value', 'label');
        $elements = collect($look['elements'])->pluck('value', 'code');
        $gazette2013 = collect($look['gazettes'])->firstWhere('label', 'Gejet for 2013')['value'];
        $loaded = $this->as()->getJson("/api/v1/hr-payroll/gazettes/{$gazette2013}")->assertOk()->json('data');
        $this->assertSame([['code' => 'EPP-2013', 'name' => 'Employee pay policy 2013', 'active' => true]], $loaded['policies']);
        $this->assertSame(['Worker', 'Staff'], array_column($loaded['structure'], 'category'));
        $this->assertSame(['Gross ÷ 1.4', '40% of Basic', '৳200', null], [$loaded['structure'][0]['basic'], $loaded['structure'][0]['house_rent'], $loaded['structure'][0]['transport'], $loaded['structure'][0]['gross_min']]);

        $this->as()->postJson('/api/v1/hr-payroll/gazettes', ['policies' => []])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Gazette Name is required.');
        $this->as()->postJson('/api/v1/hr-payroll/gazettes', ['name' => 'RMG 2026', 'effective_date' => '2026-12-01', 'policies' => [['code' => 'P1', 'name' => 'A'], ['code' => 'p1', 'name' => 'B']]])->assertUnprocessable()->assertJsonValidationErrors('policies');
        $new = $this->as()->postJson('/api/v1/hr-payroll/gazettes', ['name' => 'RMG 2026', 'number' => 'S.R.O. 1/2026', 'year' => 2026, 'effective_date' => '2026-12-01', 'policies' => [['code' => 'p1', 'name' => 'Pay policy 2026']]])
            ->assertOk()->assertJsonPath('data.policies.0.code', 'P1')->json('data');
        $this->as()->postJson('/api/v1/hr-payroll/gazettes', ['name' => 'rmg 2026', 'effective_date' => '2026-12-01', 'policies' => []])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as()->putJson("/api/v1/hr-payroll/gazettes/{$new['id']}", ['name' => 'RMG 2026', 'number' => 'S.R.O. 1/2026', 'year' => 2026, 'effective_date' => '2026-12-01', 'policies' => [['code' => 'P2', 'name' => 'Replaced']]])->assertOk()->assertJsonCount(1, 'data.policies')->assertJsonPath('data.policies.0.code', 'P2');

        $line = ['gazette_id' => $new['id'], 'employment_type_id' => $types['Worker'], 'pay_element_id' => $elements['GROSS'], 'amount' => 12500];
        $this->as()->postJson('/api/v1/erp/hr-gazette-lines', [...$line, 'amount' => null])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Amount is required.');
        $this->as()->postJson('/api/v1/erp/hr-gazette-lines', $line)->assertCreated()->assertJsonPath('data.means', '৳12,500')->assertJsonPath('data.number_year', 'S.R.O. 1/2026 / 2026');
        $this->as()->postJson('/api/v1/erp/hr-gazette-lines', $line)->assertUnprocessable()->assertJsonValidationErrors('pay_element_id');
        foreach (['BASIC' => 1.5, 'MED' => 750, 'FOOD' => 1250, 'CONV' => 450] as $code => $amount) {
            $this->as()->postJson('/api/v1/erp/hr-gazette-lines', [...$line, 'pay_element_id' => $elements[$code], 'amount' => $amount])->assertCreated();
        }
        $this->as()->getJson("/api/v1/hr-payroll/gazettes/{$new['id']}")->assertJsonPath('data.structure.0.gross_min', '৳12,500')->assertJsonPath('data.structure.0.basic_at_min', '৳6,700.00');
        $this->as()->deleteJson("/api/v1/hr-payroll/gazettes/{$new['id']}")->assertUnprocessable()->assertJsonValidationErrors('gazette');

        $grade = collect($look['grades'])->firstWhere('label', 'Grade 1')['value'];
        $wage = ['gazette_id' => $new['id'], 'employment_type_id' => $types['Worker'], 'grade_id' => $grade, 'basic' => 8000, 'house_rent_pct' => 50, 'medical' => 750, 'conveyance' => 450, 'food' => 1250, 'other' => 100];
        $this->as()->postJson('/api/v1/erp/hr-gazette-grade-wages', [...$wage, 'gazette_id' => null])->assertUnprocessable()->assertJsonPath('errors.gazette_id.0', 'Gazette is required.');
        $this->as()->postJson('/api/v1/erp/hr-gazette-grade-wages', $wage)->assertCreated()->assertJsonPath('data.gross_a', 14550)->assertJsonPath('data.year', 2026);
        $this->as()->postJson('/api/v1/erp/hr-gazette-grade-wages', $wage)->assertUnprocessable()->assertJsonValidationErrors('grade_id');

        $empty = $this->as()->postJson('/api/v1/hr-payroll/gazettes', ['name' => 'Draft', 'effective_date' => '2027-01-01', 'policies' => []])->json('data.id');
        $this->as()->deleteJson("/api/v1/hr-payroll/gazettes/{$empty}")->assertOk();
    }

    public function test_festivals_put_their_holiday_on_the_calendar(): void
    {
        $set = $this->as()->getJson('/api/v1/hr-payroll/lookups')->json('data.rule_sets.0.value');
        $festival = ['name' => 'Eid-ul-Adha', 'festival_date' => '2027-05-17', 'year' => 2027, 'payment_month' => '2027-05', 'bonus_rule_set_id' => $set, 'bonus_type' => 'EID_ADHA', 'add_holiday' => true];
        $this->as()->postJson('/api/v1/erp/hr-festivals', ['festival_date' => '2027-05-17'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Festival Name is required.');
        $this->as()->postJson('/api/v1/erp/hr-festivals', [...$festival, 'year' => 2026])->assertUnprocessable()->assertJsonValidationErrors('year');
        $this->as()->postJson('/api/v1/erp/hr-festivals', [...$festival, 'festival_date' => '2030-05-17', 'year' => 2030])->assertUnprocessable()->assertJsonValidationErrors('add_holiday');
        $saved = $this->as()->postJson('/api/v1/erp/hr-festivals', $festival)->assertCreated()->json('data');
        $this->as()->getJson("/api/v1/erp/hr-festivals/{$saved['id']}")->assertJsonPath('data.holiday', 'HOL-EID-UL-ADHA-2027')->assertJsonPath('data.bonus_rule_set', 'Bonus Rule');
        $holiday = collect($this->as()->getJson('/api/v1/erp/hr-holidays')->json('data'))->firstWhere('code', 'HOL-EID-UL-ADHA-2027');
        $this->assertSame([1, 'GOVT', 'FY2026-27'], [$holiday['calendar_days'], $holiday['type'], $holiday['fiscal_year']]);
        // HR & Payroll's holidays are the factory calendar itself.
        $this->assertTrue(HolidayCalendar::isHoliday($this->delta->id, '2027-05-17'));
        $this->as()->postJson('/api/v1/erp/hr-festivals', [...$festival, 'name' => 'eid-ul-adha', 'add_holiday' => false])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as()->deleteJson("/api/v1/erp/hr-holidays/{$holiday['id']}")->assertUnprocessable();

        $year = collect($this->as()->getJson('/api/v1/erp/hr-fiscal-years')->json('data'))->firstWhere('name', 'FY2026-27')['id'];
        $this->as()->postJson('/api/v1/erp/hr-holidays', ['code' => 'hol-x', 'name' => 'Too late', 'type' => 'COMPANY', 'fiscal_year_id' => $year, 'start_date' => '2028-01-01'])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $three = $this->as()->postJson('/api/v1/erp/hr-holidays', ['code' => 'hol-may', 'name' => 'May Day', 'type' => 'GOVT', 'fiscal_year_id' => $year, 'start_date' => '2027-05-01', 'days' => 3])
            ->assertCreated()->assertJsonPath('data.code', 'HOL-MAY')->assertJsonPath('data.calendar_days', 3)->assertJsonPath('data.end_date', '2027-05-03')->json('data');
        $this->as()->deleteJson("/api/v1/erp/hr-holidays/{$three['id']}")->assertUnprocessable()->assertJsonValidationErrors('id');
        // Put on calendar: a seeded holiday files its days; one without a date cannot.
        $janmashtami = collect($this->as()->getJson('/api/v1/erp/hr-holidays')->json('data'))->firstWhere('code', 'HOL-JANMASHTAMI');
        $this->as()->postJson("/api/v1/hr-payroll/holidays/{$janmashtami['id']}/calendar")->assertOk()->assertJsonPath('data.calendar_days', 1);
        $weekly = collect($this->as()->getJson('/api/v1/erp/hr-holidays')->json('data'))->firstWhere('code', 'HOL-WEEKLY-FRI');
        $this->as()->postJson("/api/v1/hr-payroll/holidays/{$weekly['id']}/calendar")->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->as()->patchJson("/api/v1/erp/hr-festivals/{$saved['id']}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->as()->deleteJson("/api/v1/erp/hr-festivals/{$saved['id']}")->assertNoContent();
        $this->as()->deleteJson("/api/v1/erp/hr-holidays/{$weekly['id']}")->assertNoContent();
    }

    public function test_pay_periods_entries_uploads_settings_and_fiscal_years(): void
    {
        $look = $this->as()->getJson('/api/v1/hr-payroll/lookups')->json('data');
        $year = collect($look['fiscal_years'])->firstWhere('label', 'FY2026-27')['value'];
        $elements = collect($look['elements'])->pluck('value', 'code');
        $period = ['period_type' => 'MONTHLY', 'fiscal_year_id' => $year, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'pay_date' => '2026-10-05', 'status' => 'INITIATED', 'pay_element_id' => $elements['BASIC']];
        $this->as()->postJson('/api/v1/erp/hr-pay-periods', ['fiscal_year_id' => $year])->assertUnprocessable()->assertJsonPath('errors.period_type.0', 'Period Type is required.');
        $this->as()->postJson('/api/v1/erp/hr-pay-periods', [...$period, 'end_date' => '2026-08-31'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $sept = $this->as()->postJson('/api/v1/erp/hr-pay-periods', $period)->assertCreated()->assertJsonPath('data.period_no', 'APP-DALFY2026-27090001')->json('data');
        $this->as()->postJson('/api/v1/erp/hr-pay-periods', [...$period, 'period_no' => 'TYPED'])->assertCreated()->assertJsonPath('data.period_no', 'APP-DALFY2026-27090002');

        $ayesha = TenantFixture::user($this->delta, 'VIEWER', 'ayesha@delta.test');
        $ayesha->forceFill(['employee_code' => 'E-0001'])->save();
        $karim = TenantFixture::user($this->delta, 'VIEWER', 'karim@delta.test');
        $karim->forceFill(['employee_code' => 'E-0002'])->save();
        $entry = ['pay_period_id' => $sept['id'], 'pay_element_id' => $elements['FOOD'], 'rows' => [['user_id' => $ayesha->id, 'amount' => 500]]];
        $this->as()->postJson('/api/v1/hr-payroll/entries/allowances', [...$entry, 'pay_period_id' => null])->assertUnprocessable()->assertJsonPath('errors.pay_period_id.0', 'Pay Period is required.');
        $this->as()->postJson('/api/v1/hr-payroll/entries/allowances', $entry)->assertOk()->assertJsonPath('data.saved', 1);
        // Typed in again, an allowance replaces the amount; without that it is refused as already entered.
        $this->as()->postJson('/api/v1/hr-payroll/entries/allowances', [...$entry, 'rows' => [['user_id' => $ayesha->id, 'amount' => 650]]])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.employee_code' => 'This element is already entered for this employee in this period.']);
        $this->as()->postJson('/api/v1/hr-payroll/entries/allowances', [...$entry, 'replace' => true, 'rows' => [['user_id' => $ayesha->id, 'amount' => 650]]])->assertOk();
        $this->as()->getJson('/api/v1/erp/hr-allowances')->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', '650.00')->assertJsonPath('data.0.employee_code', 'E-0001');
        $this->as()->postJson('/api/v1/hr-payroll/entries/allowances', [...$entry, 'rows' => [['employee_code' => 'E-9999', 'amount' => 1]]])->assertUnprocessable()->assertJsonPath('errors', ['rows.0.employee_code' => ['No employee has this code.']]);
        $this->as()->postJson('/api/v1/hr-payroll/entries/deductions', [...$entry, 'pay_element_id' => $elements['MED'], 'rows' => [['employee_code' => 'e-0002', 'amount' => 100]]])->assertOk();
        $this->as()->postJson('/api/v1/hr-payroll/entries/deductions', [...$entry, 'pay_element_id' => $elements['MED'], 'rows' => [['employee_code' => 'E-0002', 'amount' => 100]]])->assertUnprocessable();
        $this->as()->getJson("/api/v1/erp/hr-pay-periods/{$sept['id']}")->assertJsonPath('data.total_pays', 1)->assertJsonPath('data.total_payable', 550);

        // A file is read and checked before anything is stored: an allowance file carries Emp Code and Amount;
        // a deduction file Emp Code, Ded Type Id, Pay Period Id and Amount, the chosen period standing in for a blank one.
        $csv = UploadedFile::fake()->createWithContent('allowance.csv', "Emp Code,Amount\nE-0001,1500\nE-0404,10\nE-0002,abc\nE-0001,20\n");
        $upload = ['kind' => 'allowances', 'format' => 'csv', 'pay_period_id' => $sept['id'], 'pay_element_id' => $elements['MED']];
        $rows = $this->as()->post('/api/v1/hr-payroll/uploads', [...$upload, 'file' => $csv], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame([null, 'No employee has this code.', 'The amount must be a number above zero.', 'This employee appears twice.'], array_column($rows, 'error'));
        $this->assertSame([2, 3, 4, 5], array_column($rows, 'row'));
        $food = UploadedFile::fake()->createWithContent('food.csv', "Emp Code,Amount\nE-0001,10\n");
        $this->as()->post('/api/v1/hr-payroll/uploads', [...$upload, 'pay_element_id' => $elements['FOOD'], 'file' => $food], ['Accept' => 'application/json'])->assertJsonPath('data.0.error', 'This element is already entered for this employee in this period.');
        $json = UploadedFile::fake()->createWithContent('deduction.json', json_encode([
            ['emp_code' => 'E-0001', 'ded_type_id' => 'conv', 'amount' => 75],
            ['emp_code' => 'E-0002', 'ded_type_id' => 'XX', 'amount' => 5],
            ['emp_code' => 'E-0002', 'ded_type_id' => 'MED', 'pay_period_id' => 'NOPE', 'amount' => 5],
            ['emp_code' => 'E-0002', 'ded_type_id' => 'MED', 'pay_period_id' => 'APP-DALFY2026-27090001', 'amount' => 5],
        ]));
        $rows = $this->as()->post('/api/v1/hr-payroll/uploads', ['kind' => 'deductions', 'format' => 'json', 'pay_period_id' => $sept['id'], 'file' => $json], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame([['CONV', 'APP-DALFY2026-27090001', null], ['XX', 'APP-DALFY2026-27090001', 'No pay element has this code.'], ['MED', 'NOPE', 'No open pay period has this number.'], ['MED', 'APP-DALFY2026-27090001', 'This head is already deducted from this employee for this period.']], array_map(fn ($r) => [$r['pay_element'], $r['pay_period'], $r['error']], $rows));
        $this->as()->post('/api/v1/hr-payroll/uploads', ['kind' => 'allowances', 'format' => 'xlsx', 'file' => UploadedFile::fake()->createWithContent('a.csv', "x\n")], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->as()->postJson('/api/v1/hr-payroll/entries/deductions', ['pay_period_id' => $sept['id'], 'rows' => [['employee_code' => 'E-0001', 'pay_element' => 'CONV', 'amount' => 75]]])->assertOk();
        $this->as()->getJson('/api/v1/erp/hr-deductions')->assertJsonCount(2, 'data');

        // Pay Period Actions: one step at a time; a closed period takes no more entries.
        $company = $this->as()->getJson('/api/v1/hr-payroll/period-companies')->assertJsonPath('data.0.count', 2)->json('data.0.key');
        $this->as()->putJson("/api/v1/hr-payroll/period-companies/{$company}", ['rows' => [['id' => $sept['id'], 'action' => 'ACTIVATE']]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.action');
        foreach (['SUBMIT' => 'SUBMITTED', 'SEND_BACK' => 'INITIATED', 'SUBMIT ' => 'SUBMITTED', 'ACTIVATE' => 'ACTIVATED', 'CLOSE' => 'CLOSED', 'REOPEN' => 'ACTIVATED', 'CLOSE ' => 'CLOSED'] as $move => $status) {
            $rows = $this->as()->putJson("/api/v1/hr-payroll/period-companies/{$company}", ['rows' => [['id' => $sept['id'], 'action' => trim($move), 'show_for_report' => false, 'remarks' => 'September payroll']]])->assertOk()->json('data');
            $this->assertSame($status, collect($rows)->firstWhere('id', $sept['id'])['status']);
        }
        $closed = collect($rows)->firstWhere('id', $sept['id']);
        $this->assertSame(['Pay Period Closed', 'YES', false, 'September payroll'], [$closed['status_label'], $closed['closed_label'], $closed['show_for_report'], $closed['remarks']]);
        $this->as()->getJson("/api/v1/erp/hr-pay-periods/{$sept['id']}")->assertJsonPath('data.closed', true);
        $this->as()->postJson('/api/v1/hr-payroll/entries/allowances', $entry)->assertUnprocessable()->assertJsonValidationErrors('pay_period_id');

        // Payroll Settings, and the fiscal year register shared with Finance & Ledger.
        $this->as()->putJson('/api/v1/hr-payroll/settings', ['values' => ['SALARY_DAY_BASIS' => '45']])->assertUnprocessable()->assertJsonValidationErrors('values.SALARY_DAY_BASIS');
        $this->as()->putJson('/api/v1/hr-payroll/settings', ['values' => ['LATE_DEDUCTION_MODE' => 'per_minute', 'ROUND_NET_TO' => '10']])->assertOk()->assertJsonPath('data.1.value', 'PER_MINUTE')->assertJsonPath('data.5.value', '10');
        $years = $this->as()->getJson('/api/v1/hr-payroll/fiscal-registers/FY')->assertJsonCount(2, 'data')->json('data');
        $this->as()->putJson('/api/v1/hr-payroll/fiscal-registers/FY', ['rows' => [...$years, ['name' => 'FY2028-29', 'fy_start' => '2028-01-01', 'fy_end' => '2028-12-31', 'cal_start' => '2028-01-01', 'cal_end' => '2028-12-31']]])->assertUnprocessable();
        $years = $this->as()->putJson('/api/v1/hr-payroll/fiscal-registers/FY', ['rows' => [...$years, ['name' => 'FY2028-29', 'fy_start' => '2028-07-01', 'fy_end' => '2029-06-30', 'cal_start' => '2028-01-01', 'cal_end' => '2028-12-31', 'active' => false]]])->assertOk()->assertJsonCount(3, 'data')->json('data');
        $this->assertSame([false, 0], [$years[0]['active'], $years[0]['pay_periods']]);
        // A year left out of the grid is deleted — unless pay periods or holidays are filed under it.
        $this->as()->putJson('/api/v1/hr-payroll/fiscal-registers/FY', ['rows' => array_values(array_filter($years, fn ($y) => $y['name'] !== 'FY2026-27'))])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->putJson('/api/v1/hr-payroll/fiscal-registers/FY', ['rows' => array_values(array_filter($years, fn ($y) => $y['name'] !== 'FY2028-29'))])->assertOk()->assertJsonCount(2, 'data');
        $this->as()->getJson('/api/v1/erp/acc-fiscal-years')->assertJsonCount(2, 'data');
    }

    public function test_who_may_see_and_change_the_masters(): void
    {
        $token = fn (string $role, string $email) => app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
        $auditor = $token('AUDITOR', 'auditor@delta.test');
        $this->withToken($auditor)->getJson('/api/v1/erp/hr-shifts')->assertOk();
        $this->withToken($auditor)->postJson('/api/v1/erp/hr-pay-elements', ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->withToken($auditor)->putJson('/api/v1/hr-payroll/settings', ['values' => []])->assertForbidden();

        $manager = $token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->getJson('/api/v1/hr-payroll/lookups')->assertOk();
        $this->withToken($manager)->postJson('/api/v1/hr-payroll/gazettes', ['name' => 'X', 'effective_date' => '2026-01-01', 'policies' => []])->assertForbidden();

        $admin = $token('FACTORY_ADMIN', 'admin@delta.test');
        $this->withToken($admin)->postJson('/api/v1/erp/hr-pay-elements', ['code' => 'BONUS', 'name' => 'Festival Bonus'])->assertCreated();

        $viewer = $token('VIEWER', 'viewer@delta.test');
        $this->withToken($viewer)->getJson('/api/v1/erp/hr-shifts')->assertOk();
        $this->withToken($viewer)->postJson('/api/v1/hr-payroll/entries/allowances', ['rows' => []])->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
