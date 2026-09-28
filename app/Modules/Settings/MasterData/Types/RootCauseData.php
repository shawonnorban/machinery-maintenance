<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class RootCauseData extends MasterDataType
{
    public function key(): string
    {
        return 'root-causes';
    }

    public function model(): string
    {
        return RootCause::class;
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Breakdown::class => 'root_cause_id'];
    }
}
