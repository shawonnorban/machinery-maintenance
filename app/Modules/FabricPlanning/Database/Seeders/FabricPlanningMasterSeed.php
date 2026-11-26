<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Fabric Requirement → Master Data sample rows (AnnotechERP 13/01): batch lead rules by colour and volume, batch hours by colour group, and which dyeing machine each colour group runs on. */
final class FabricPlanningMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $rules = [
            ['SOLID', 'NORMAL', 'NO_WASH', 0, 45, 0, 5000, 18, 'SHIPMENT', 25, null],
            ['SOLID', 'NORMAL', 'NO_WASH', 46, 90, 5001, 50000, 25, 'SHIPMENT', 35, null],
            ['MELANGE', 'NORMAL', 'NO_WASH', 0, 60, 0, 10000, 15, 'PCD', null, 10],
            ['YARN_DYED', 'NORMAL', 'NO_WASH', 0, 90, 0, 20000, 30, 'PCD', null, 20],
            ['AOP', 'NORMAL', 'NO_WASH', 0, 75, 0, 20000, 28, 'SHIPMENT', 40, null],
            ['SOLID', 'PEACH', 'ENZYME', 0, 75, 0, 20000, 24, 'SHIPMENT', 35, null],
        ];
        $s->seed('fp-batch-lead-rules', array_map(fn (array $r) => [
            'colour_type' => $r[0], 'finish_type' => $r[1], 'wash_type' => $r[2], 'lead_from' => $r[3], 'lead_to' => $r[4], 'volume_from' => $r[5], 'volume_to' => $r[6],
            'production_lead_time' => $r[7], 'basis' => $r[8], 'days_before_shipment' => $r[9], 'days_before_pcd' => $r[10],
        ], $rules), ['colour_type', 'finish_type', 'wash_type', 'lead_from', 'volume_from']);

        $group = fn (ResourceSeeder $s, string $name) => $s->id('dye-colour-groups', ['name' => $name]);
        // One norm per fibre template, buyer category and dyeing method, as Brickland keeps them.
        $hours = [
            ['Medium', 'COTTON_100', 'A', 'EXHAUST', 9.5], ['Medium', 'COTTON_100', null, 'EXHAUST', 10], ['Dark', 'LYCRA_BLEND', null, 'EXHAUST', 12],
            ['PC/CVC', 'CVC_BLEND', null, 'EXHAUST', 13], ['Medium', null, null, 'COLD_PAD_BATCH', 24], ['Light', null, null, 'CONTINUOUS', 3], ['Light', null, null, 'PIGMENT', 4],
        ];
        $s->seed('fp-batch-hours', array_map(fn (array $h) => fn (ResourceSeeder $s) => [
            'colour_group_id' => $group($s, $h[0]), 'fiber_template' => $h[1], 'any_template' => $h[1] === null, 'buyer_category' => $h[2], 'any_category' => $h[2] === null, 'dyeing_method' => $h[3], 'batch_hours' => $h[4],
        ], $hours), ['fiber_template', 'buyer_category', 'dyeing_method']);

        $machine = fn (ResourceSeeder $s, string $no) => $s->id('dye-machines', ['machine_no' => $no]);
        $routes = [['White', 'DM-01'], ['Light', 'DM-02'], ['Medium', 'DM-04'], ['Dark', 'DM-03'], ['Extra Dark', 'DM-03'], ['Black/Royal Blue', 'DM-05'], ['PC/CVC', 'DM-03']];
        $s->seed('fp-machine-routes', array_map(fn (array $r) => fn (ResourceSeeder $s) => [
            'colour_group_id' => $group($s, $r[0]), 'dye_machine_id' => $machine($s, $r[1]), 'any_fabric_group' => true, 'any_template' => true, 'any_buyer' => true, 'any_gsm' => true,
        ], $routes), ['colour_group_id', 'dye_machine_id']);
    }
}
