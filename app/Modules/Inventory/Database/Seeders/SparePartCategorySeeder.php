<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Seeders;

use App\Modules\Inventory\Support\SparePartCategories;
use Illuminate\Support\Facades\DB;
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
    /**
     * Categories are one list per company (item_categories, user 2026-11-15):
     * the starter set goes into every company there is; a new company gets it
     * the first time its spare parts need categories.
     */
    public function run(): void
    {
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            SparePartCategories::ensure((string) $companyId);
        }
    }
}
