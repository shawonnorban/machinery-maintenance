<?php

declare(strict_types=1);

namespace App\Modules\Costing\Database\Seeders;

use App\Modules\Costing\Models\CostCategory;
use Illuminate\Database\Seeder;

/**
 * Cost categories (SRS 23).
 *
 * PARTS is not optional: the system posts a derived entry against it for
 * every part a work order keeps, and a tenant without it would silently record
 * no cost at all. The rest are the buckets a mill's maintenance spend actually
 * falls into.
 *
 * There is no labour category. Technicians are salaried employees, so their
 * hours are not money leaving the business and are never posted as cost.
 */
class CostCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
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
