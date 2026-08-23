<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class StoreData extends MasterDataType
{
    public function key(): string
    {
        return 'stores';
    }

    public function model(): string
    {
        return Store::class;
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
            Field::reference('warehouse_id', 'warehouses'),
            Field::text('name'),
            Field::code(32),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Bin::class => 'store_id'];
    }
}
