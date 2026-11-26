<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Gate Pass → Master Data sample rows (AnnotechERP 11/01): the outside places goods go to and the items that pass the gate. Categories, location types and purposes ship as defaults. */
final class GatePassMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        foreach (['gatepass-categories', 'gatepass-location-types', 'gatepass-purposes'] as $key) {
            $s->ensure($key);
        }

        $type = fn (ResourceSeeder $s, string $code) => $s->id('gatepass-location-types', ['code' => $code]);
        $locations = [
            ['LOC-001', 'Ananta Embroidery Ltd', 'LT-01', 'Shamim Ahmed', '01711-112233', 'Kashimpur, Gazipur'],
            ['LOC-002', 'Colour Print Solution', 'LT-02', 'Rafiq Mia', '01819-223344', 'Konabari, Gazipur'],
            ['LOC-003', 'Smart Wash Ltd', 'LT-03', 'Kamal Hossain', '01912-334455', 'Ashulia, Savar, Dhaka'],
            ['LOC-004', 'SGS Bangladesh Laboratory', 'LT-06', 'Dr. Sharmin Akter', '01712-445566', 'Banani, Dhaka 1213'],
            ['LOC-005', 'LPP Bangladesh Liaison Office', 'LT-07', 'Shahriar Kabir', '01713-556677', 'Gulshan 2, Dhaka 1212'],
            ['LOC-006', 'Avery Dennison EPZ Unit', 'LT-08', 'Mostafa Kamal', '01714-667788', 'Dhaka EPZ, Savar'],
            ['LOC-007', 'Rupa Knit Composite (Subcontract)', 'LT-09', 'Anwar Hossain', '01715-778899', 'Fatullah, Narayanganj'],
        ];
        $s->seed('gatepass-locations', array_map(fn (array $l) => fn (ResourceSeeder $s) => [
            'code' => $l[0], 'name' => $l[1], 'location_type_id' => $type($s, $l[2]), 'contact_person' => $l[3], 'mobile' => $l[4], 'address' => $l[5],
        ], $locations));

        $s->seed('gatepass-items', array_map(fn (array $i) => ['code' => $i[0], 'name' => $i[1], 'item_category' => $i[2], 'unit' => $i[3]], [
            ['GPI-001', 'Knit T-Shirt (finished)', 'FINISHED_GARMENTS', 'PCS'], ['GPI-002', 'Polo Shirt (finished)', 'FINISHED_GARMENTS', 'PCS'],
            ['GPI-003', 'Cut Panels for Print', 'FABRIC', 'PCS'], ['GPI-004', 'Finished Fabric Roll', 'FABRIC', 'ROLL'],
            ['GPI-005', 'Grey Fabric', 'FABRIC', 'KG'], ['GPI-006', 'Sewing Thread', 'TRIMS', 'BOX'],
            ['GPI-007', 'Sample Garments', 'SAMPLE_GARMENTS', 'PCS'], ['GPI-008', 'Export Carton (empty)', 'PACKING_MATERIAL', 'NOS'],
            ['GPI-009', 'Sewing Machine for Repair', 'MACHINERY', 'NOS'], ['GPI-010', 'Shipping Documents', 'DOCUMENTS', 'SET'],
        ]));
    }
}
