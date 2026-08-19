<?php

declare(strict_types=1);

namespace App\Modules\Costing\Database\Seeders;

use App\Modules\Costing\Models\CostCategory;
use Illuminate\Database\Seeder;

/**
 * Cost categories (SRS 23).
 *
 * LABOR and PARTS are not optional: the system posts derived entries against
 * them from every work order, and a tenant without them would silently record
 * no cost at all. The rest are the buckets a garment factory's maintenance
 * spend actually falls into.
 */
class CostCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['LABOR', 'Labour', 'শ্রম', 'MAINTENANCE'],
            ['PARTS', 'Spare parts', 'স্পেয়ার পার্টস', 'MAINTENANCE'],
            ['EXTERNAL_SERVICE', 'External service', 'বাইরের সার্ভিস', 'MAINTENANCE'],
            ['VENDOR', 'Vendor charges', 'ভেন্ডর চার্জ', 'MAINTENANCE'],
            ['TRANSPORT', 'Transportation', 'পরিবহন', 'MAINTENANCE'],
            ['EMERGENCY', 'Emergency callout', 'জরুরি কলআউট', 'REPAIR'],
            ['ACQUISITION', 'Machine purchase', 'মেশিন ক্রয়', 'ACQUISITION'],
            ['INSTALLATION', 'Installation and commissioning', 'ইনস্টলেশন ও কমিশনিং', 'INSTALLATION'],
            ['UPGRADE', 'Upgrade and modification', 'আপগ্রেড ও পরিবর্তন', 'UPGRADE'],
            ['OTHER', 'Other', 'অন্যান্য', 'OTHER'],
        ] as [$code, $name, $nameBn, $bucket]) {
            CostCategory::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'name_bn' => $nameBn,
                    'lifecycle_bucket' => $bucket,
                    'active' => true,
                ],
            );
        }
    }
}
