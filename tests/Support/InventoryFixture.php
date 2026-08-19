<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;

/**
 * Stock locations and parts for tests.
 *
 * Balances live per bin, so every inventory test needs the full
 * factory - warehouse - store - bin chain. Repeating it in six test classes is
 * how they drift apart.
 */
class InventoryFixture
{
    public static function bin(
        Company $company,
        Factory $factory,
        string $code = 'DHK-A1',
        bool $inTransit = false,
    ): Bin {
        $warehouse = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => $factory->code.'-WH'],
            ['factory_id' => $factory->id, 'name' => $factory->name.' main store'],
        );

        $store = Store::firstOrCreate(
            ['company_id' => $company->id, 'code' => $factory->code.'-ST'],
            ['warehouse_id' => $warehouse->id, 'name' => 'Spare parts store'],
        );

        return Bin::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'store_id' => $store->id,
                'name' => $code,
                'is_in_transit' => $inTransit,
            ],
        );
    }

    public static function part(
        Company $company,
        string $partNumber = 'JK-DDL9000-HOOK',
        string $name = 'Rotary hook, Juki DDL-9000C',
        array $overrides = [],
    ): SparePart {
        return SparePart::create(array_merge([
            'company_id' => $company->id,
            'part_number' => $partNumber,
            'name' => $name,
            'unit' => 'PCS',
            'minimum_stock' => '2',
            'reorder_level' => '5',
            'currency' => 'BDT',
            'active' => true,
        ], $overrides));
    }
}
