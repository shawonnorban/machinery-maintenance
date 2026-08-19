<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Seeders;

use App\Modules\Inventory\Models\SparePartCategory;
use Illuminate\Database\Seeder;

/**
 * Spare part categories for a garment factory (Seed Catalog 7).
 *
 * Platform rows: null company_id, visible to every tenant, extensible per
 * company. Seeded because a store with uncategorised parts cannot answer
 * "what are we spending on electricals", which is the first question a factory
 * manager asks of a maintenance budget.
 */
class SparePartCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['SEWING_PARTS', 'Sewing machine parts', 'সেলাই মেশিনের পার্টস'],
            ['CUTTING_PARTS', 'Cutting machine parts', 'কাটিং মেশিনের পার্টস'],
            ['ELECTRICAL', 'Electrical', 'বৈদ্যুতিক'],
            ['MECHANICAL', 'Mechanical', 'যান্ত্রিক'],
            ['BEARINGS', 'Bearings and bushes', 'বেয়ারিং ও বুশ'],
            ['BELTS_CHAINS', 'Belts and chains', 'বেল্ট ও চেইন'],
            ['PNEUMATIC', 'Pneumatic', 'নিউম্যাটিক'],
            ['HYDRAULIC', 'Hydraulic', 'হাইড্রলিক'],
            ['LUBRICANTS', 'Lubricants and oils', 'তেল ও লুব্রিক্যান্ট'],
            ['FASTENERS', 'Fasteners', 'নাট-বোল্ট'],
            ['STEAM_UTILITY', 'Steam and utility', 'স্টিম ও ইউটিলিটি'],
            ['SAFETY', 'Safety equipment', 'নিরাপত্তা সরঞ্জাম'],
            ['CONSUMABLES', 'Consumables', 'ভোগ্য সামগ্রী'],
        ] as [$code, $name, $nameBn]) {
            SparePartCategory::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'name_bn' => $nameBn, 'active' => true],
            );
        }
    }
}
