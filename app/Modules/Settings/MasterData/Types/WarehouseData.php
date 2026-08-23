<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Factory;

/**
 * Where spare parts are kept (SRS 23).
 *
 * Three levels, because a factory store is not one room: a warehouse belongs
 * to a factory, a store to a warehouse, and a bin to a store. Stock is held in
 * bins, so until these exist a company cannot receive a single part — which is
 * what they were until now, since nothing but a seeder could create one.
 */
class WarehouseData extends MasterDataType
{
    public function key(): string
    {
        return 'warehouses';
    }

    public function model(): string
    {
        return Warehouse::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function sharedWithPlatform(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::belongsTo('factory_id', Factory::class),
            Field::text('name'),
            Field::code(32),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Store::class => 'warehouse_id'];
    }
}
