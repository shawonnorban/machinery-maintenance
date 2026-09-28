<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class CostCategoryData extends MasterDataType
{
    public function key(): string
    {
        return 'cost-categories';
    }

    public function model(): string
    {
        return CostCategory::class;
    }

    public function group(): string
    {
        return 'cost';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            // The bucket is what lets a machine's total cost of ownership be
            // assembled without anyone deciding, per report, whether a vendor
            // invoice counts as maintenance.
            Field::enum('lifecycle_bucket', CostCategory::LIFECYCLE_BUCKETS),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [CostEntry::class => 'cost_category_id'];
    }
}
