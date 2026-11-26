<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Knit Production → Master Data sample rows (AnnotechERP 15/01): the circular and flat-bed machines, their capacity plans, operator postings and label printers. Yarn counts and the machine reference lists ship as defaults. */
final class KnittingMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->ensure('knit-yarn-counts');
        $s->ensure('knit-machines');
        $origin = fn (ResourceSeeder $s, string $code) => $s->id('origin-countries', ['code' => $code]);
        $floor = $this->floorId('Building A', 'Ground Floor');
        $machines = [
            ['KM-01', 'Single Jersey', 'Mayer & Cie', 'Relanit 3.2 HS', '276', '30"', '24GG', 450, 96, 28],
            ['KM-02', 'Single Jersey', 'Mayer & Cie', 'Relanit 3.2 HS', '276', '34"', '24GG', 520, 108, 26],
            ['KM-03', 'Single Jersey', 'Terrot', 'S296-2', '276', '36"', '28GG', 480, 108, 25],
            ['KM-04', 'Rib', 'Fukuhara', 'V-LEC 6', '392', '30"', '18GG', 380, 60, 22],
            ['KM-05', 'Interlock', 'Pailung', 'PL-KD3.0', '158', '34"', '24GG', 420, 68, 24],
            ['KM-06', 'Fleece / Terry', 'Orizio', 'JFL', '380', '30"', '20GG', 400, 90, 20],
            ['KM-07', 'Fleece / Terry', 'Wellknit', 'WK-F3', '158', '32"', '20GG', 410, 96, 20],
            ['KM-08', 'Flat Bed (Collar/Cuff)', 'Fukuhara', 'FCK-2', '392', '26"', '14GG', 60, 4, 30],
        ];
        $s->seed('knit-machines', array_map(fn (array $m) => fn (ResourceSeeder $s) => [
            'machine_no' => $m[0], 'machine_type_id' => $this->ref('MC_TYPE', $m[1]), 'brand_id' => $this->ref('BRAND', $m[2]), 'model' => $m[3], 'origin_country_id' => $origin($s, $m[4]),
            'serial_no' => $m[0].'-'.$m[4], 'dia_id' => $this->ref('DIA', $m[5]), 'gauge_id' => $this->ref('GG', $m[6]), 'supported_gauge_ids' => [$this->ref('GG', $m[6])], 'capacity_kg' => $m[7], 'feeders' => $m[8], 'rpm' => $m[9], 'efficiency' => 85,
            'floor_id' => $floor, 'mfg_year' => 2020,
        ], $machines), ['machine_no']);

        $machine = fn (ResourceSeeder $s, string $no) => $s->id('knit-machines', ['machine_no' => $no]);
        $day = $this->shiftId('Day Shift');
        $night = $this->shiftId('Night Shift');
        $plans = [];
        foreach ($machines as [$no, , , , , , , $capacity]) {
            $plans[] = fn (ResourceSeeder $s) => ['machine_id' => $machine($s, $no), 'shift_id' => $day, 'effective_from' => '2026-07-01', 'plan_hours' => 12, 'running_days' => 26, 'efficiency' => 85, 'capacity_per_hour' => round($capacity / 24, 2)];
        }
        $s->seed('knit-capacity-plans', $plans, ['machine_id', 'effective_from']);

        $postings = [['KM-01', 'Jashim Uddin', $day], ['KM-02', 'Tanvir Rahman', $day], ['KM-03', 'Kamrul Hasan', $night], ['KM-04', 'Delwar Hossain', $night]];
        $s->seed('knit-operator-postings', array_map(fn (array $p, int $i) => fn (ResourceSeeder $s) => [
            'machine_id' => $machine($s, $p[0]), 'user_id' => $this->userId($p[1], $i), 'shift_id' => $p[2], 'effective_at' => '2026-07-01',
        ], $postings, array_keys($postings)), ['machine_id', 'user_id']);

        $s->seed('knit-label-printers', [
            ['name' => 'Knitting Floor Printer 1', 'address' => '192.168.10.41', 'auto_scale' => false, 'display_order' => 10],
            ['name' => 'Knitting Floor Printer 2', 'address' => '192.168.10.42', 'auto_scale' => true, 'display_order' => 20],
            ['name' => 'Grey Store Printer', 'address' => '192.168.10.45', 'auto_scale' => false, 'display_order' => 30],
        ]);
    }

    /** A row of the knitting reference lists (machine type, brand, dia, gauge) by its list and name. */
    private function ref(string $list, string $name): string
    {
        return DB::table('knit_ref_entries')->where('company_id', $this->companyId())->where('list', $list)->where('name', $name)->value('id')
            ?? throw new RuntimeException("Seed reference not found: knit {$list} {$name}");
    }
}
