<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class FailureCategoryData extends MasterDataType
{
    public function key(): string
    {
        return 'failure-categories';
    }

    public function model(): string
    {
        return FailureCategory::class;
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            // The technician entering a breakdown at 2am reads the Bengali.
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            FailureCode::class => 'failure_category_id',
            Breakdown::class => 'failure_category_id',
        ];
    }
}
