<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Finishing Line → Master Data sample rows (AnnotechERP 18/01): the finishing machines, the processes each runs in sequence, and their daily targets. Process types ship as defaults. */
final class FinishingMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->ensure('fin-process-types');
        // Slitting opens tubular fabric before the open-width line; the defaults leave it out.
        $group = DB::table('fin_process_groups')->where('company_id', $this->companyId())->where('code', 'DYEING_FINISHING')->value('id');
        $s->seed('fin-process-types', [fn () => [
            'code' => 'DF-SL', 'name' => 'Slitting', 'short_name' => 'SL', 'group_id' => $group ?? throw new RuntimeException('Seed reference not found: process group DYEING_FINISHING'),
            'subgroup_id' => DB::table('fin_process_subgroups')->where('company_id', $this->companyId())->where('code', 'DF-DRY')->value('id'),
            'wip_stage' => 'DRYING', 'rate' => 4, 'loss_pct' => 0, 'display_order' => 90,
        ]]);
        $list = fn (ResourceSeeder $s, string $name) => $s->id('dye-machine-lists', ['name' => $name]);
        $origin = fn (ResourceSeeder $s, string $code) => $s->id('origin-countries', ['code' => $code]);
        $floor = $this->floorId('Building B', '1st Floor');
        $machines = [
            ['STENTER', 'ST-01', 'Stenter Machine 1', 'Power-Frame', '276', 'Brückner', 6000, 'RUNNING', 210],
            ['STENTER', 'ST-02', 'Stenter Machine 2', 'Power-Frame', '276', 'Brückner', 6000, 'IDLE', 210],
            ['COMPACTOR', 'CP-01', 'Open Compactor', 'Tubetex OC', '840', 'Tubetex', 5000, 'RUNNING', 120],
            ['COMPACTOR', 'CP-02', 'Tube Compactor', 'Tubetex TC', '840', 'Tubetex', 4500, 'IDLE', 120],
            ['DRYER', 'DR-01', 'Relax Dryer', 'Corino RD', '380', 'Corino', 5500, 'RUNNING', 150],
            ['SLITTING', 'SL-01', 'Slitting Machine', 'Corino SL', '380', 'Corino', 7000, 'IDLE', null],
            ['HYDRO_EXTRACTOR', 'HE-01', 'Hydro Extractor', 'Fongs HE', '156', 'Fongs', 3000, 'RUNNING', null],
            ['BRUSH', 'BR-01', 'Brushing Machine', 'Lafer GSR', '380', 'Lafer', 2500, 'IDLE', null],
        ];
        $s->seed('fin-machines', array_map(fn (array $m) => fn (ResourceSeeder $s) => [
            'machine_type' => $m[0], 'machine_no' => $m[1], 'name' => $m[2], 'model' => $m[3], 'origin_country_id' => $origin($s, $m[4]), 'brand_id' => $list($s, $m[5]),
            'capacity' => $m[6], 'location_id' => $list($s, 'Finishing Bay'), 'mou' => 'KG', 'efficiency' => 80, 'status' => $m[7], 'temperature' => $m[8], 'floor_id' => $floor, 'year' => 2020,
        ], $machines), ['machine_no']);

        $machine = fn (ResourceSeeder $s, string $no) => $s->id('fin-machines', ['machine_no' => $no]);
        $process = fn (ResourceSeeder $s, string $code) => $s->id('fin-process-types', ['code' => $code]);
        $steps = [
            ['ST-01', 'OPEN', 'DF-HS', 1, 'DRYING', 'HEAT_SET'], ['ST-01', 'OPEN', 'DF-ST', 2, 'HEAT_SET', 'STENTERING'],
            ['ST-02', 'OPEN', 'DF-ST', 1, 'DRYING', 'STENTERING'],
            ['CP-01', 'OPEN', 'DF-CMP', 1, 'STENTERING', 'COMPACTING'], ['CP-02', 'TUBE', 'DF-CMP', 1, 'DRYING', 'COMPACTING'],
            ['DR-01', 'BOTH', 'DF-DRY', 1, 'DEWATERING', 'DRYING'], ['SL-01', 'TUBE', 'DF-SL', 1, 'DEWATERING', 'DRYING'],
            ['HE-01', 'TUBE', 'DF-DW', 1, 'DYEING_RUNNING', 'DEWATERING'], ['BR-01', 'OPEN', 'DF-BR', 1, 'STENTERING', 'BRUSHING'],
        ];
        $s->seed('fin-machine-processes', array_map(fn (array $p) => fn (ResourceSeeder $s) => [
            'machine_id' => $machine($s, $p[0]), 'dia_type' => $p[1], 'process_id' => $process($s, $p[2]), 'sequence' => $p[3], 'batch_status' => $p[4], 'next_status' => $p[5], 'is_default' => $p[3] === 1,
        ], $steps), ['machine_id', 'process_id', 'dia_type']);

        $s->seed('fin-machine-targets', array_map(fn (array $m) => fn (ResourceSeeder $s) => [
            'machine_id' => $machine($s, $m[1]), 'dia_type' => $m[0] === 'COMPACTOR' && $m[1] === 'CP-02' ? 'TUBE' : 'BOTH', 'effective_from' => '2026-07-01', 'target_qty' => round($m[6] * 0.8), 'shift_hours' => 12,
        ], $machines), ['machine_id', 'dia_type', 'effective_from']);
    }
}
