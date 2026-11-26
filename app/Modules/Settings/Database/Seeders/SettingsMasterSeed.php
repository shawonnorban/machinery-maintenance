<?php

declare(strict_types=1);

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Settings → General Settings sample masters (AnnotechERP 03/03): currencies against BDT and the label templates the stores print. */
final class SettingsMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->seed('currencies', [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.50, 'rate_date' => '2026-10-01'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'exchange_rate' => 129.80, 'rate_date' => '2026-10-01'],
            ['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'decimal_places' => 2, 'exchange_rate' => 152.40, 'rate_date' => '2026-10-01'],
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'decimal_places' => 2, 'exchange_rate' => 1, 'rate_date' => '2026-10-01'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2, 'exchange_rate' => 16.45, 'rate_date' => '2026-10-01'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2, 'exchange_rate' => 1.42, 'rate_date' => '2026-10-01'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'decimal_places' => 2, 'exchange_rate' => 15.30, 'rate_date' => '2026-10-01'],
        ]);

        $s->seed('label-templates', [
            ['code' => 'ROLL-QR', 'name' => 'Fabric roll sticker', 'usage' => 'ROLL', 'symbology' => 'QR', 'width_mm' => 100, 'height_mm' => 50, 'columns' => 1, 'printer_language' => 'ZPL'],
            ['code' => 'ITEM-128', 'name' => 'Store item label', 'usage' => 'ITEM', 'symbology' => 'CODE128', 'width_mm' => 50, 'height_mm' => 25, 'columns' => 2, 'printer_language' => 'PDF'],
            ['code' => 'BUNDLE-128', 'name' => 'Cutting bundle ticket', 'usage' => 'BUNDLE', 'symbology' => 'CODE128', 'width_mm' => 75, 'height_mm' => 40, 'columns' => 1, 'printer_language' => 'ZPL'],
            ['code' => 'CARTON-128', 'name' => 'Export carton label', 'usage' => 'CARTON', 'symbology' => 'CODE128', 'width_mm' => 100, 'height_mm' => 75, 'columns' => 1, 'printer_language' => 'PDF'],
            ['code' => 'ASSET-QR', 'name' => 'Machine asset tag', 'usage' => 'ASSET', 'symbology' => 'QR', 'width_mm' => 60, 'height_mm' => 40, 'columns' => 1, 'printer_language' => 'PDF'],
        ]);
    }
}
