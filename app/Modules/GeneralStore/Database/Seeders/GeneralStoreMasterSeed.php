<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** General Store → Master Data sample rows (AnnotechERP 10/01): suppliers and the everyday items a factory store carries. Categories, units, departments and stores ship as defaults. */
final class GeneralStoreMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        foreach (['store-categories', 'store-units', 'store-departments', 'store-locations'] as $key) {
            $s->ensure($key);
        }

        $s->seed('store-suppliers', array_map(fn (array $v) => ['code' => $v[0], 'name' => $v[1], 'contact_person' => $v[2], 'mobile' => $v[3], 'email' => $v[4], 'address' => $v[5]], [
            ['GS-SUP-01', 'Rahman Stationery House', 'Abdur Rahman', '01711-345678', 'rahman.stationery@example.com', '45 Nilkhet Road, Dhaka 1205'],
            ['GS-SUP-02', 'Bismillah Electric Store', 'Shafiqul Islam', '01819-456789', 'bismillah.electric@example.com', 'Nawabpur Road, Dhaka 1100'],
            ['GS-SUP-03', 'Safety First BD Ltd', 'Nazmul Huda', '01912-567890', 'sales@safetyfirstbd.example', 'Tongi I/A, Gazipur'],
            ['GS-SUP-04', 'Clean Pro Supplies', 'Farzana Yasmin', '01611-678901', 'orders@cleanpro.example', 'Mirpur 10, Dhaka 1216'],
            ['GS-SUP-05', 'Tech Point Computers', 'Rakib Hasan', '01712-789012', 'techpoint@example.com', 'IDB Bhaban, Agargaon, Dhaka 1207'],
        ]));

        $category = fn (ResourceSeeder $s, string $code) => $s->id('store-categories', ['code' => $code]);
        $unit = fn (ResourceSeeder $s, string $code) => $s->id('store-units', ['code' => $code]);
        $items = [
            ['GS-STA-001', 'A4 Paper 80 GSM', 'STAT', 'REAM', 'Double A', 20, 40, 520, 'R-01'],
            ['GS-STA-002', 'Ball Pen Blue', 'STAT', 'PCS', 'Matador', 100, 200, 6, 'R-01'],
            ['GS-STA-003', 'Box File', 'OFFICE', 'PCS', null, 20, 30, 120, 'R-02'],
            ['GS-ELC-001', 'LED Tube Light 20W', 'ELEC', 'PCS', 'Transtec', 30, 50, 280, 'R-05'],
            ['GS-ELC-002', 'Electric Cable 2.5 rm', 'ELEC', 'ROLL', 'BRB', 5, 10, 4800, 'R-05'],
            ['GS-ELC-003', 'Circuit Breaker 32A', 'ELEC', 'PCS', 'Schneider', 10, 15, 650, 'R-05'],
            ['GS-SAF-001', 'Ear Plug (pair)', 'SAFE', 'PAIR', '3M', 200, 400, 25, 'R-07'],
            ['GS-SAF-002', 'Cotton Face Mask', 'SAFE', 'PCS', null, 500, 1000, 10, 'R-07'],
            ['GS-SAF-003', 'Metal Mesh Glove', 'SAFE', 'PCS', 'Niroflex', 10, 20, 3200, 'R-07'],
            ['GS-CLN-001', 'Floor Cleaner 5 L', 'CLEAN', 'LTR', 'Savlon', 20, 40, 95, 'R-08'],
            ['GS-CLN-002', 'Broom (soft)', 'CLEAN', 'PCS', null, 20, 30, 120, 'R-08'],
            ['GS-HRD-001', 'Machine Oil 1 L', 'MAINT', 'LTR', 'Mobil', 20, 40, 450, 'R-04'],
            ['GS-HRD-002', 'Allen Key Set', 'HARD', 'SET', 'Stanley', 2, 5, 850, 'R-04'],
            ['GS-PCK-001', 'Packing Tape 2"', 'PACK', 'ROLL', null, 50, 100, 65, 'R-09'],
            ['GS-IT-001', 'Printer Toner 85A', 'IT', 'PCS', 'HP', 5, 10, 2900, 'R-03'],
        ];
        $s->seed('store-items', array_map(fn (array $i) => fn (ResourceSeeder $s) => [
            'item_code' => $i[0], 'name' => $i[1], 'gs_category_id' => $category($s, $i[2]), 'gs_unit_id' => $unit($s, $i[3]), 'brand' => $i[4],
            'minimum_stock' => $i[5], 'reorder_level' => $i[6], 'default_price' => $i[7], 'rack_location' => $i[8],
        ], $items), ['item_code']);
    }
}
