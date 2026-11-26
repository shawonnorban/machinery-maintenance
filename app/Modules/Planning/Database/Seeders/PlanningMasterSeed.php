<?php

declare(strict_types=1);

namespace App\Modules\Planning\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Capacity Planning → Master Data sample rows (AnnotechERP 12/01): eight sewing lines with their machines, manpower and shifts, efficiency standards and style SMVs. Lead times ship as defaults. */
final class PlanningMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->ensure('planning-lead-times');
        $ground = $this->floorId('Building A', 'Ground Floor');
        $first = $this->floorId('Building A', '1st Floor');
        $lines = [];
        foreach (range(1, 8) as $n) {
            $lines[] = [
                'floor_id' => $n <= 4 ? $ground : $first, 'code' => sprintf('LINE%02d', $n), 'name' => sprintf('Line %02d', $n),
                'std_manpower' => $n % 2 ? 48 : 42, 'std_machines' => $n % 2 ? 40 : 34, 'std_hours' => 10, 'std_efficiency' => $n % 2 ? 65 : 60,
                'best_fit' => $n <= 4 ? 'Basic knit tops' : ($n <= 6 ? 'Polo and fleece' : 'Bottoms'), 'display_order' => $n * 10,
            ];
        }
        $s->seed('planning-lines', $lines);

        $line = fn (ResourceSeeder $s, string $code) => $s->id('planning-lines', ['code' => $code]);
        // A line's shifts are HR & Payroll's Day and Night.
        $s->ensure('hr-shifts');
        $hrShift = fn (ResourceSeeder $s, string $code) => $s->id('hr-shifts', ['code' => $code]);
        $machines = [];
        $manpower = [];
        $shifts = [];
        foreach (range(1, 8) as $n) {
            $code = sprintf('LINE%02d', $n);
            $big = $n % 2 === 1;
            foreach ([['Single Needle Lockstitch', 'SNLS', 'SEWING', $big ? 16 : 14], ['Overlock 4-thread', 'OL-4T', 'SEWING', $big ? 12 : 10], ['Flatlock', 'FL', 'SEWING', $big ? 8 : 6], ['Button Attach', 'BA', 'SPECIAL', 2], ['Thread Sucker', 'TS', 'FINISHING', 2]] as [$type, $nameCode, $category, $installed]) {
                $machines[] = fn (ResourceSeeder $s) => ['planning_line_id' => $line($s, $code), 'machine_type' => $type, 'name_code' => $nameCode, 'category' => $category, 'installed' => $installed, 'working' => $installed - ($category === 'SEWING' ? 1 : 0), 'standby' => $category === 'SEWING' ? 1 : 0];
            }
            foreach ([['OPERATOR', $big ? 34 : 30], ['HELPER', $big ? 10 : 8], ['IRONMAN', 2], ['QC', 2], ['SUPERVISOR', 1]] as [$role, $required]) {
                $manpower[] = fn (ResourceSeeder $s) => ['planning_line_id' => $line($s, $code), 'role' => $role, 'required' => $required];
            }
            $shifts[] = fn (ResourceSeeder $s) => ['planning_line_id' => $line($s, $code), 'hr_shift_id' => $hrShift($s, 'DAY'), 'manpower' => $big ? 48 : 42, 'target' => $big ? 1200 : 1000];
        }
        $s->seed('planning-line-machines', $machines, ['planning_line_id', 'machine_type']);
        $s->seed('planning-line-manpower', $manpower, ['planning_line_id', 'role']);
        $s->seed('planning-line-shifts', $shifts, ['planning_line_id', 'hr_shift_id']);

        $buyer = fn (ResourceSeeder $s, string $code) => $s->id('buyers', ['code' => $code]);
        $s->seed('planning-efficiencies', [
            fn (ResourceSeeder $s) => ['applies_to' => 'UNIT', 'factory_id' => $this->factoryId('DHK'), 'efficiency' => 62, 'effective_from' => '2026-01-01', 'remarks' => 'Unit average'],
            fn (ResourceSeeder $s) => ['applies_to' => 'BUYER', 'buyer_id' => $buyer($s, 'LPP'), 'efficiency' => 65, 'effective_from' => '2026-01-01'],
            fn (ResourceSeeder $s) => ['applies_to' => 'BUYER', 'buyer_id' => $buyer($s, 'HM'), 'efficiency' => 60, 'effective_from' => '2026-01-01'],
            fn (ResourceSeeder $s) => ['applies_to' => 'BUYER', 'buyer_id' => $buyer($s, 'PRM'), 'efficiency' => 68, 'effective_from' => '2026-01-01'],
            fn (ResourceSeeder $s) => ['applies_to' => 'LINE', 'planning_line_id' => $line($s, 'LINE01'), 'efficiency' => 70, 'effective_from' => '2026-01-01', 'remarks' => 'Most skilled line'],
            fn (ResourceSeeder $s) => ['applies_to' => 'STYLE', 'style_no' => 'POLO-201', 'efficiency' => 55, 'effective_from' => '2026-01-01'],
        ], ['applies_to', 'factory_id', 'buyer_id', 'planning_line_id', 'style_no']);

        $s->seed('planning-style-smvs', array_map(fn (array $v) => ['style_no' => $v[0], 'smv' => $v[1], 'sam' => $v[2], 'remarks' => $v[3]], [
            ['TEE-101', 8.2, 8.0, 'Basic crew neck tee'], ['TEE-102', 9.6, 9.4, 'V-neck tee with rib'], ['POLO-201', 14.6, 14.2, 'Pique polo, 3-button placket'],
            ['HD-301', 22.4, 21.8, 'Pullover hoodie with kangaroo pocket'], ['SWT-302', 16.8, 16.5, 'Crew neck sweatshirt'], ['JOG-401', 18.9, 18.4, 'Fleece jogger with cuffs'],
            ['TNK-501', 6.4, 6.2, 'Ladies tank top'], ['KID-601', 7.8, 7.5, 'Kids printed tee'],
        ]), ['style_no']);
    }
}
