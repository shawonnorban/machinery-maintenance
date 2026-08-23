<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class FailureCodeData extends MasterDataType
{
    public function key(): string
    {
        return 'failure-codes';
    }

    public function model(): string
    {
        return FailureCode::class;
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function fields(): array
    {
        return [
            Field::reference('failure_category_id', 'failure-categories'),
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Breakdown::class => 'failure_code_id'];
    }
}
