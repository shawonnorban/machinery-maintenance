<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Tenancy\Models\Company;

/**
 * Library items for tests.
 *
 * Every procurement test needs an item department, a unit and an item or
 * two; repeating that in each class is how the fixtures drift apart.
 */
class ItemFixture
{
    public static function department(Company $company, string $name = 'General Store', string $code = 'GEN', ?string $defaultType = 'GENERAL'): ItemDepartment
    {
        return ItemDepartment::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            ['name' => $name, 'default_item_type' => $defaultType, 'active' => true],
        );
    }

    public static function uomId(Company $company, string $code = 'PCS'): string
    {
        $id = UnitOfMeasure::availableTo($company->id)->where('code', $code)->value('id');

        if ($id === null) {
            $id = UnitOfMeasure::create([
                'company_id' => null, 'code' => $code, 'name' => $code, 'active' => true,
            ])->id;
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function item(
        Company $company,
        string $code = 'GEN-BOLT-M8',
        string $name = 'Hex bolt M8 x 40',
        array $overrides = [],
    ): Item {
        $department = $overrides['item_department_id'] ?? self::department($company)->id;

        return Item::create(array_merge([
            'company_id' => $company->id,
            'item_department_id' => $department,
            'item_code' => $code,
            'name' => $name,
            'item_type' => 'GENERAL',
            'tracking_mode' => 'QUANTITY',
            'uom_id' => self::uomId($company),
            'minimum_stock' => '2',
            'reorder_level' => '5',
            'active' => true,
        ], $overrides));
    }
}
