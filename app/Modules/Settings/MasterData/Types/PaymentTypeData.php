<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Finance\Models\PaymentType;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Vendor\Models\Vendor;

class PaymentTypeData extends MasterDataType
{
    public function key(): string
    {
        return 'payment-types';
    }

    public function model(): string
    {
        return PaymentType::class;
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
        return [Vendor::class => 'default_payment_type_id'];
    }
}
