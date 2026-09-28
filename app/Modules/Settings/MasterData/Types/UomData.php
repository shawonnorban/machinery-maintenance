<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Inventory\Models\UomConversion;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class UomData extends MasterDataType
{
    public function key(): string
    {
        return 'uoms';
    }

    public function model(): string
    {
        return UnitOfMeasure::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::code(16),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            Item::class => ['uom_id', 'purchase_uom_id'],
            UomConversion::class => ['from_uom_id', 'to_uom_id'],
        ];
    }
}
