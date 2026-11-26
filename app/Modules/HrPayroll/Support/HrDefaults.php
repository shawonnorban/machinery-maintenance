<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\HrPayroll\Models\HrDesignationGroup;
use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrGazette;
use App\Modules\HrPayroll\Models\HrLeaveType;
use App\Modules\HrPayroll\Models\HrPayElement;
use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\Tenancy\Models\Grade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The HR & Payroll lists a company starts with, as Brickland ships them
 * (AnnotechERP 24/01): two employment types, forty-five employee categories,
 * seven pay grades, eight pay elements, the day and night shifts, eight leave types, the
 * standard OT rule, six bonus rules, four wage gazettes with their structure
 * lines, a grade wage and a policy, three holidays and the six payroll
 * settings. Made the first time a list is opened, and only into an empty
 * list; insertOrIgnore because a page opens several requests at once.
 */
final class HrDefaults
{
    /** [name, manpower category, order, leaf] — all at level 1 */
    public const GROUPS = [
        ['Operetions Head', 'STAFF', 1, false],
        ['Merchandiser', 'STAFF', 5, false],
        ['Chairman', 'STAFF', 10, true],
        ['Director', 'STAFF', 20, true],
        ['GM', 'STAFF', 30, true],
        ['DGM', 'STAFF', 40, true],
        ['AGM', 'STAFF', 50, true],
        ['Manager', 'STAFF', 60, true],
        ['Deputy Mgr', 'STAFF', 70, true],
        ['Asst. Manager', 'STAFF', 80, true],
        ['Sr. Executive', 'STAFF', 90, true],
        ['Executive', 'STAFF', 100, true],
        ['Staff', null, 100, true],
        ['Worker', null, 101, true],
        ['Jr. Executive', 'STAFF', 110, true],
        ['Asst. Executive', 'STAFF', 120, true],
        ['Asst. Eng', 'STAFF', 130, true],
        ['Incharge', 'SUPERVISOR', 140, true],
        ['LineChief', 'SUPERVISOR', 150, true],
        ['Controller', 'SUPERVISOR', 160, true],
        ['Mechanic', 'STAFF', 170, true],
        ['Electrician', 'STAFF', 180, true],
        ['Sample Man', 'STAFF', 190, true],
        ['Office Asst.', 'STAFF', 200, true],
        ['Driver', 'STAFF', 210, true],
        ['Guard', 'STAFF', 220, true],
        ['Labour', 'HELPER', 230, true],
        ['Operator', 'OPERATOR', 240, true],
        ['Packer', 'OPERATOR', 250, true],
        ['Poly', 'OPERATOR', 260, true],
        ['Printer', 'OPERATOR', 270, true],
        ['Folder', 'OPERATOR', 280, true],
        ['Utility', 'HELPER', 290, true],
        ['Iron', 'OPERATOR', 300, true],
        ['QI', 'QUALITY', 310, true],
        ['Cutter', 'OPERATOR', 320, true],
        ['Others', 'STAFF', 330, true],
        ['Cleaner', 'HELPER', 340, true],
        ['Helper/Asst.', 'HELPER', 350, true],
        ['Input Man', 'OPERATOR', 360, true],
        ['Asst. InputMan', 'OPERATOR', 370, true],
        ['Sucker', 'OPERATOR', 380, true],
        ['Hang Tag', 'OPERATOR', 390, true],
        ['Spot/Wash', 'OPERATOR', 400, true],
        ['Needle Dtr', 'OPERATOR', 410, true],
    ];

    /** code => name */
    public const PAY_ELEMENTS = ['BASIC' => 'Basic Pay', 'CONV' => 'Conveyance Allowance', 'FOOD' => 'Food Allowance', 'GROSS' => 'Gross Pay', 'HRENT' => 'House Rent Allowance', 'MED' => 'Medical Allowance', 'OTREG' => 'OT-Regular', 'SHIFT' => 'Shift Allowance'];

    /**
     * The two shifts every module works on (user, 2026-11-25; Brickland ships four): [code, name, start, end,
     * break start, regular hours, cross day] — the hours past them are overtime; break 60, grace 15, OT allowed and approved
     */
    public const SHIFTS = [['DAY', 'Day Shift', '08:00', '17:00', '13:00', 8, false], ['NIGHT', 'Night Shift', '20:00', '05:00', '01:00', 8, true]];

    /** The shift of an employee on no roster, unless the DEFAULT_SHIFT setting names another. */
    public const DEFAULT_SHIFT = 'DAY';

    /** [code, name, paid, group, max days, applicable for, encashable] */
    public const LEAVE_TYPES = [
        ['CL', 'Casual Leave', true, 'LEAVE', 10, 'COMMON', false], ['CPL', 'CPL Leave', true, 'LEAVE', 5, 'COMMON', false], ['EL', 'Earn Leave', true, 'LEAVE', 18, 'COMMON', true],
        ['LWP', 'Leave Without Pay', false, 'ABSENT', null, 'COMMON', false], ['ML', 'Maternity Leave', true, 'LEAVE', 112, 'FEMALE', false], ['OL', 'Off Day Leave', true, 'PRESENT', null, 'COMMON', false],
        ['SL', 'Sick Leave', true, 'LEAVE', 14, 'COMMON', false], ['SPL', 'Special Leave', true, 'LEAVE', 7, 'COMMON', false],
    ];

    /** [service low, high, salary low, high, basis, %, employment type] */
    public const BONUS_RULES = [
        [6, 11, 1, 24999, 'BASIC', 0.5, 'Staff'], [6, 11, 25000, 999999, 'GROSS', 0.3, 'Staff'], [12, 99999, 1, 24999, 'BASIC', 1, 'Staff'],
        [12, 99999, 25000, 999999, 'GROSS', 0.5, 'Staff'], [6, 11, 1, 999999, 'BASIC', 0.5, 'Worker'], [12, 99999, 1, 999999, 'BASIC', 1, 'Worker'],
    ];

    /** [name, effective date, year] */
    public const GAZETTES = [['RMG Minimum Wage 2023 (Non-EPZ)', '2023-12-01', 2023], ['Gejet for 2023', '2023-12-01', 2023], ['Gejet for 2018', '2018-12-01', 2018], ['Gejet for 2013', '2013-12-01', 2013]];

    /** gazette => [category => [element => amount]] */
    public const GAZETTE_LINES = [
        'RMG Minimum Wage 2023 (Non-EPZ)' => ['Worker' => ['BASIC' => 1.5, 'HRENT' => 50, 'MED' => 750, 'FOOD' => 1250, 'CONV' => 450]],
        'Gejet for 2013' => ['Worker' => ['BASIC' => 1.4, 'HRENT' => 40, 'MED' => 250, 'FOOD' => 650, 'CONV' => 200], 'Staff' => ['BASIC' => 1.4, 'HRENT' => 40, 'MED' => 250, 'FOOD' => 650, 'CONV' => 200]],
    ];

    /** [code, name, type, start date, recurring] — filed under FY2026-27, not yet on the calendar */
    public const HOLIDAYS = [['HOL-JANMASHTAMI', 'Janmashtami', 'GOVT', '2026-08-26', false], ['HOL-VICTORY', 'Victory Day', 'GOVT', '2026-12-16', true], ['HOL-WEEKLY-FRI', 'Weekly Off (Friday)', 'WEEKLY_OFF', null, false]];

    /** key => [default, rule] */
    public const SETTINGS = [
        'SALARY_DAY_BASIS' => ['30', 'int:1:31'],
        'LATE_DEDUCTION_MODE' => ['LATE_DAYS', 'in:NONE,LATE_DAYS,PER_MINUTE'],
        'LATE_DAYS_PER_DEDUCTION' => ['3', 'int:1:31'],
        'LATE_DEDUCTION_BASIS' => ['BASIC', 'in:BASIC,GROSS'],
        'DEFAULT_GRACE_MINUTES' => ['15', 'int:0:240'],
        'ROUND_NET_TO' => ['1', 'int:1:1000'],
    ];

    public static function ensure(string $companyId): void
    {
        $now = now();
        $row = fn (array $values) => ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'created_at' => $now, 'updated_at' => $now] + $values;
        if (! HrEmploymentType::query()->exists()) {
            DB::table('hr_employment_types')->insertOrIgnore([$row(['name' => 'Staff', 'name_bn' => 'Staff', 'short_name' => 'STF', 'level' => 1, 'is_leaf' => true, 'active' => true]), $row(['name' => 'Worker', 'name_bn' => 'Worker', 'short_name' => 'WKR', 'level' => 1, 'is_leaf' => true, 'active' => true])]);
        }
        if (! HrDesignationGroup::query()->exists()) {
            DB::table('hr_designation_groups')->insertOrIgnore(array_map(fn (array $g) => $row(['name' => $g[0], 'name_bn' => $g[0], 'short_name' => $g[0], 'level' => 1, 'sort_order' => $g[2], 'manpower_category' => $g[1], 'is_leaf' => $g[3], 'active' => true]), self::GROUPS));
        }
        if (! Grade::query()->exists()) {
            DB::table('grades')->insertOrIgnore(array_map(fn (int $n) => $row(['name' => "Grade {$n}", 'code' => (string) $n, 'code_bn' => "Grade-{$n}", 'sort_order' => $n, 'status' => 'ACTIVE']), range(1, 7)));
        }
        if (! HrPayElement::query()->exists()) {
            DB::table('hr_pay_elements')->insertOrIgnore(array_map(fn (string $code) => $row(['code' => $code, 'name' => self::PAY_ELEMENTS[$code], 'active' => true]), array_keys(self::PAY_ELEMENTS)));
        }
        if (! HrShift::query()->exists()) {
            DB::table('hr_shifts')->insertOrIgnore(array_map(fn (array $s) => $row(['code' => $s[0], 'name' => $s[1], 'start_time' => $s[2], 'end_time' => $s[3], 'break_start' => $s[4], 'break_min' => 60, 'working_hours' => $s[5], 'grace_min' => 15, 'cross_day' => $s[6], 'ot_allowed' => true, 'ot_approval' => true, 'active' => true]), self::SHIFTS));
        }
        if (! HrLeaveType::query()->exists()) {
            DB::table('hr_leave_types')->insertOrIgnore(array_map(fn (array $l) => $row(['code' => $l[0], 'name' => $l[1], 'paid' => $l[2], 'leave_group' => $l[3], 'max_days' => $l[4], 'applicable_for' => $l[5], 'encashable' => $l[6], 'active' => true]), self::LEAVE_TYPES));
        }
        foreach (['hr_bonus_rule_sets' => ['Bonus Rule'], 'hr_shift_teams' => ['Team A', 'Team B']] as $table => $names) {
            if (! DB::table($table)->where('company_id', $companyId)->exists()) {
                DB::table($table)->insertOrIgnore(array_map(fn (string $n) => $row(['name' => $n, 'active' => true]), $names));
            }
        }
        $types = DB::table('hr_employment_types')->where('company_id', $companyId)->pluck('id', 'name');
        $elements = DB::table('hr_pay_elements')->where('company_id', $companyId)->pluck('id', 'code');
        if (! DB::table('hr_ot_rules')->where('company_id', $companyId)->exists()) {
            DB::table('hr_ot_rules')->insertOrIgnore([$row(['code' => 'OT-STD', 'name' => 'Standard OT (Basic ÷ 208 × 2)', 'wage_basis' => 'BASIC', 'divisor' => 208, 'multiplier' => 2, 'effective_from' => '2023-12-01', 'legal_ref' => 'Bangladesh Labour Act 2006, s.108 — twice the ordinary rate of basic wage', 'active' => true])]);
        }
        if (! DB::table('hr_bonus_rules')->where('company_id', $companyId)->exists()) {
            $set = DB::table('hr_bonus_rule_sets')->where('company_id', $companyId)->where('name', 'Bonus Rule')->value('id');
            DB::table('hr_bonus_rules')->insertOrIgnore(array_map(fn (array $b) => $row(['rule_set_id' => $set, 'service_low' => $b[0], 'service_high' => $b[1], 'salary_low' => $b[2], 'salary_high' => $b[3], 'basis' => $b[4], 'pct' => $b[5], 'employment_type_id' => $types[$b[6]] ?? null, 'active' => true]), self::BONUS_RULES));
        }
        if (! HrGazette::query()->exists()) {
            DB::table('hr_gazettes')->insertOrIgnore(array_map(fn (array $g) => $row(['name' => $g[0], 'effective_date' => $g[1], 'year' => $g[2], 'active' => true]), self::GAZETTES));
            $gazettes = DB::table('hr_gazettes')->where('company_id', $companyId)->pluck('id', 'name');
            $lines = [];
            foreach (self::GAZETTE_LINES as $gazette => $categories) {
                foreach ($categories as $category => $amounts) {
                    foreach ($amounts as $code => $amount) {
                        $lines[] = $row(['gazette_id' => $gazettes[$gazette], 'employment_type_id' => $types[$category], 'pay_element_id' => $elements[$code], 'amount' => $amount, 'active' => true]);
                    }
                }
            }
            DB::table('hr_gazette_lines')->insertOrIgnore($lines);
            DB::table('hr_gazette_policies')->insertOrIgnore([$row(['gazette_id' => $gazettes['Gejet for 2013'], 'code' => 'EPP-2013', 'name' => 'Employee pay policy 2013', 'active' => true])]);
            $grade4 = DB::table('grades')->where('company_id', $companyId)->where('code', '4')->value('id');
            if ($grade4) {
                DB::table('hr_gazette_grade_wages')->insertOrIgnore([$row(['gazette_id' => $gazettes['RMG Minimum Wage 2023 (Non-EPZ)'], 'employment_type_id' => $types['Worker'], 'grade_id' => $grade4, 'basic' => 6700, 'house_rent_pct' => 50, 'medical' => 750, 'conveyance' => 450, 'food' => 1250, 'active' => true])]);
            }
        }
        if (! DB::table('hr_holidays')->where('company_id', $companyId)->exists()) {
            AccountingDefaults::fiscalYears($companyId);
            $year = DB::table('acc_fiscal_years')->where('company_id', $companyId)->where('name', 'FY2026-27')->value('id');
            if ($year) {
                DB::table('hr_holidays')->insertOrIgnore(array_map(fn (array $h) => $row(['fiscal_year_id' => $year, 'code' => $h[0], 'name' => $h[1], 'type' => $h[2], 'start_date' => $h[3], 'days' => 1, 'recurring' => $h[4], 'calendar_days' => 0, 'active' => true]), self::HOLIDAYS));
            }
        }
        if (! DB::table('hr_payroll_settings')->where('company_id', $companyId)->exists()) {
            DB::table('hr_payroll_settings')->insertOrIgnore(array_map(fn (string $key) => $row(['key' => $key, 'value' => self::SETTINGS[$key][0]]), array_keys(self::SETTINGS)));
        }
    }
}
