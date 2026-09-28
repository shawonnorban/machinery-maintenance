<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Finance\Models\Bank;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Vendor\Models\Vendor;

class BankData extends MasterDataType
{
    public function key(): string
    {
        return 'banks';
    }

    public function model(): string
    {
        return Bank::class;
    }

    public function group(): string
    {
        return 'finance';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::code(32),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Vendor::class => 'bank_id'];
    }
}
