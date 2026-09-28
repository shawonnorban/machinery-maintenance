<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class HsCodeData extends MasterDataType
{
    public function key(): string
    {
        return 'hs-codes';
    }

    public function model(): string
    {
        return HsCode::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    /** The tariff code is the name people know it by. */
    public function displayColumn(): string
    {
        return 'code';
    }

    public function fields(): array
    {
        return [
            Field::code(16),
            Field::text('description'),
            Field::number('duty_rate'),
            Field::number('vat_rate'),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Item::class => 'hs_code_id'];
    }
}
