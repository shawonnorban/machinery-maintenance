<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Finance & Ledger → Master Data sample rows (AnnotechERP 23/01): the earlier fiscal year, cost centre groups and centres, and the company's bank accounts. The chart of accounts and payment modes ship as defaults. */
final class AccountingMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->ensure('acc-accounts');
        $s->ensure('acc-payment-modes');
        $s->ensure('acc-fiscal-years');
        $s->seed('acc-fiscal-years', [
            ['name' => 'FY2025-26', 'fy_start' => '2025-07-01', 'fy_end' => '2026-06-30', 'cal_start' => '2025-01-01', 'cal_end' => '2025-12-31', 'closed' => false],
        ], ['name']);

        $s->seed('acc-cost-center-groups', array_map(fn (array $g) => ['name' => $g[0], 'display_order' => $g[1]], [
            ['Production', 10], ['Production Support', 20], ['Administration', 30], ['Sales & Marketing', 40],
        ]));

        $group = fn (ResourceSeeder $s, string $name) => $s->id('acc-cost-center-groups', ['name' => $name]);
        $dhaka = $this->factoryId('DHK');
        $gazipur = $this->factoryId('GAZ');
        $centres = [
            ['Knitting', 'Production', $gazipur, 'Knitting Department'], ['Dyeing & Finishing', 'Production', $gazipur, 'Dyeing Department'],
            ['Cutting', 'Production', $dhaka, 'Cutting Department'], ['Sewing', 'Production', $dhaka, 'Sewing / Production Department'],
            ['Finishing & Packing', 'Production', $dhaka, 'Finishing Department'], ['Maintenance & Utility', 'Production Support', $gazipur, 'Maintenance Department'],
            ['Quality Assurance', 'Production Support', $dhaka, 'Quality Assurance / Quality Control'], ['Head Office Admin', 'Administration', $dhaka, 'HR & Administration'],
            ['Accounts & Finance', 'Administration', $dhaka, 'Accounts & Finance'], ['Merchandising', 'Sales & Marketing', $dhaka, 'Merchandising Department'],
            ['Commercial', 'Sales & Marketing', $dhaka, 'Commercial Department'],
        ];
        $s->seed('acc-cost-centers', array_map(fn (array $c) => fn (ResourceSeeder $s) => ['name' => $c[0], 'group_id' => $group($s, $c[1]), 'factory_id' => $c[2], 'dept_section' => $c[3]], $centres));

        $s->ensure('tx-banks');
        $bank = fn (ResourceSeeder $s, string $code) => $s->id('tx-banks', ['code' => $code]);
        $accounts = [
            ['BNK-BRAC', '1501201234567001', 'Delta Apparels Ltd — Operating', 'CURRENT', 'BDT'],
            ['DBBL', '1171100023456', 'Delta Apparels Ltd — ERQ', 'FC', 'USD'],
            ['EBL', '1041060345678', 'Delta Apparels Ltd — Cash Credit', 'CC', 'BDT'],
            ['BNK-ASIA', '00233004567', 'Delta Apparels Ltd — Payroll', 'SND', 'BDT'],
        ];
        $s->seed('acc-bank-accounts', array_map(fn (array $a) => fn (ResourceSeeder $s) => [
            'bank_id' => $bank($s, $a[0]), 'account_no' => $a[1], 'account_name' => $a[2], 'account_type' => $a[3], 'currency' => $a[4],
        ], $accounts), ['account_no']);
    }
}
