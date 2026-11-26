<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Work Study → Master Data sample rows (AnnotechERP 14/01): garment items and their parts, learning curves and the machine profile of each line. */
final class WorkStudyMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->ensure('ws-garment-items');
        $s->seed('ws-garment-items', array_map(fn (array $i) => ['item_group' => $i[0], 'name' => $i[1]], [
            ['Knit Tops', 'Crew Neck Sweatshirt'], ['Knit Tops', 'Ladies Tank Top'], ['Bottoms', 'Fleece Jogger'], ['Kids', 'Kids T-Shirt'],
        ]), ['name']);

        $item = fn (ResourceSeeder $s, string $name) => $s->id('ws-garment-items', ['name' => $name]);
        $part = fn (ResourceSeeder $s, string $code) => $s->id('garment-parts', ['code' => $code]);
        $parts = [
            'Basic T-Shirt' => ['BDY', 'SLV', 'NRB'], 'Polo Shirt' => ['BDY', 'SLV', 'CLR', 'PLK', 'CUF'], 'Pullover Hoodie' => ['BDY', 'SLV', 'HOD', 'LIN', 'PKT', 'CUF', 'WBD'],
            'Crew Neck Sweatshirt' => ['BDY', 'SLV', 'NRB', 'CUF', 'WBD'], 'Ladies Tank Top' => ['BDY', 'NRB'], 'Fleece Jogger' => ['BDY', 'PKT', 'WBD', 'CUF'], 'Kids T-Shirt' => ['BDY', 'SLV', 'NRB'],
        ];
        $rows = [];
        foreach ($parts as $name => $codes) {
            foreach ($codes as $i => $code) {
                $rows[] = fn (ResourceSeeder $s) => ['garment_item_id' => $item($s, $name), 'garment_part_id' => $part($s, $code), 'component_sl' => $i + 1, 'included' => true];
            }
        }
        $s->seed('ws-item-parts', $rows, ['garment_item_id', 'garment_part_id']);

        $points = [];
        foreach (['SINGLE_JERSEY' => 0, 'PIQUE' => -5, 'FLEECE' => -8] as $class => $shift) {
            foreach (['BASIC' => 0, 'CRITICAL' => -10] as $type => $hard) {
                foreach ([1 => 35, 2 => 45, 3 => 55, 4 => 62, 5 => 68, 7 => 72] as $day => $eff) {
                    $points[] = ['fabric_class' => $class, 'prod_type' => $type, 'day' => $day, 'efficiency' => max(10, $eff + $shift + $hard)];
                }
            }
        }
        $s->seed('ws-learning-points', $points, ['fabric_class', 'prod_type', 'day']);

        $line = fn (ResourceSeeder $s, string $code) => $s->id('planning-lines', ['code' => $code]);
        $profiles = [];
        foreach (['LINE01', 'LINE02', 'LINE03', 'LINE04'] as $code) {
            foreach ([['SNLS', 16, 2, 16], ['OVERLOCK', 12, 1, 12], ['FLATLOCK', 8, 1, 8], ['BUTTON_ATTACH', 1, 0, 1], ['BUTTON_HOLE', 1, 0, 1], ['BARTACK', 1, 0, 1]] as [$type, $ops, $aso, $mcs]) {
                $profiles[] = fn (ResourceSeeder $s) => ['planning_line_id' => $line($s, $code), 'machine_type' => $type, 'operators' => $ops, 'aso' => $aso, 'machines' => $mcs];
            }
        }
        $s->seed('ws-line-profiles', $profiles, ['planning_line_id', 'machine_type']);
    }
}
