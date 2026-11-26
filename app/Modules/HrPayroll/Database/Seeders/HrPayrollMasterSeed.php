<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;
use Illuminate\Support\Facades\DB;

/**
 * HR & Payroll → Master Data sample rows (AnnotechERP 24/01): more
 * employment types, overtime rules, the year's festivals and government
 * holidays, and the monthly pay periods of FY2026-27. Designation groups,
 * grades, pay elements, shifts, leave types, gazettes and bonus rules ship as
 * defaults.
 */
final class HrPayrollMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        foreach (['hr-designation-groups', 'hr-grades', 'hr-pay-elements', 'hr-shifts', 'hr-leave-types', 'hr-bonus-rules', 'hr-gazette-lines'] as $key) {
            $s->ensure($key);
        }
        $s->seed('hr-employment-types', [
            ['name' => 'Contractual', 'name_bn' => 'চুক্তিভিত্তিক', 'short_name' => 'CTR', 'is_leaf' => true],
            ['name' => 'Apprentice', 'name_bn' => 'শিক্ষানবিশ', 'short_name' => 'APR', 'is_leaf' => true],
            ['name' => 'Casual', 'name_bn' => 'অস্থায়ী', 'short_name' => 'CSL', 'is_leaf' => true],
        ], ['name']);

        $type = fn (ResourceSeeder $s, string $name) => $s->id('hr-employment-types', ['name' => $name]);
        $s->seed('hr-ot-rules', [
            fn (ResourceSeeder $s) => ['code' => 'OT-WKR', 'name' => 'Worker overtime (Labour Act §108)', 'effective_from' => '2026-01-01', 'wage_basis' => 'BASIC', 'divisor' => 208, 'multiplier' => 2, 'employment_type_id' => $type($s, 'Worker'), 'legal_ref' => 'Bangladesh Labour Act 2006, section 108'],
            fn (ResourceSeeder $s) => ['code' => 'OT-CTR', 'name' => 'Contractual overtime', 'effective_from' => '2026-01-01', 'wage_basis' => 'BASIC', 'divisor' => 208, 'multiplier' => 2, 'employment_type_id' => $type($s, 'Contractual')],
            fn (ResourceSeeder $s) => ['code' => 'OT-HOL', 'name' => 'Holiday duty', 'effective_from' => '2026-01-01', 'wage_basis' => 'GROSS', 'divisor' => 30, 'multiplier' => 1, 'remarks' => 'One day gross for duty on a festival holiday'],
        ]);

        $ruleSet = DB::table('hr_bonus_rule_sets')->where('company_id', $this->companyId())->value('id');
        $s->seed('hr-festivals', [
            ['name' => 'Eid-ul-Fitr', 'festival_date' => '2027-03-10', 'year' => 2027, 'payment_month' => '2027-02', 'bonus_rule_set_id' => $ruleSet, 'bonus_type' => 'EID_FITR', 'min_service' => 3, 'add_holiday' => true],
            ['name' => 'Eid-ul-Adha', 'festival_date' => '2027-05-17', 'year' => 2027, 'payment_month' => '2027-05', 'bonus_rule_set_id' => $ruleSet, 'bonus_type' => 'EID_ADHA', 'min_service' => 3, 'add_holiday' => true],
            ['name' => 'Eid-ul-Fitr', 'festival_date' => '2026-03-20', 'year' => 2026, 'payment_month' => '2026-03', 'bonus_rule_set_id' => $ruleSet, 'bonus_type' => 'EID_FITR', 'min_service' => 3, 'add_holiday' => true],
        ], ['name', 'year']);

        $s->ensure('acc-fiscal-years');
        $fy = $s->id('acc-fiscal-years', ['name' => 'FY2026-27']);
        $holidays = [
            ['HOL-VIC', 'Victory Day', 'GOVT', '2026-12-16', 1],
            ['HOL-XMS', 'Christmas Day', 'GOVT', '2026-12-25', 1],
            ['HOL-SHD', 'Shaheed Day & International Mother Language Day', 'GOVT', '2027-02-21', 1],
            ['HOL-EIDF', 'Eid-ul-Fitr', 'GOVT', '2027-03-09', 5],
            ['HOL-IND', 'Independence Day', 'GOVT', '2027-03-26', 1],
            ['HOL-PB', 'Pohela Boishakh', 'GOVT', '2027-04-14', 1],
            ['HOL-MAY', 'May Day', 'GOVT', '2027-05-01', 1],
            ['HOL-EIDA', 'Eid-ul-Adha', 'GOVT', '2027-05-16', 5],
            ['HOL-ANN', 'Company Foundation Day', 'COMPANY', '2027-01-10', 1],
        ];
        $s->seed('hr-holidays', array_map(fn (array $h) => ['code' => $h[0], 'name' => $h[1], 'type' => $h[2], 'fiscal_year_id' => $fy, 'start_date' => $h[3], 'days' => $h[4]], $holidays));

        $periods = [];
        foreach ([7, 8, 9, 10, 11, 12] as $month) {
            $start = sprintf('2026-%02d-01', $month);
            $end = date('Y-m-t', strtotime($start));
            $pay = date('Y-m-07', strtotime($start.' +1 month'));
            $periods[] = ['period_type' => 'MONTHLY', 'fiscal_year_id' => $fy, 'month' => $month, 'start_date' => $start, 'end_date' => $end, 'pay_date' => $pay, 'status' => $month < 10 ? 'CLOSED' : 'INITIATED', 'closed' => $month < 10];
        }
        $s->seed('hr-pay-periods', $periods, ['fiscal_year_id', 'month', 'period_type']);
    }
}
