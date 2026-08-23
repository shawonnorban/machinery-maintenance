<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class MeterTypeData extends MasterDataType
{
    public function key(): string
    {
        return 'meter-types';
    }

    public function model(): string
    {
        return MeterType::class;
    }

    public function group(): string
    {
        return 'maintenance';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::code(),
            Field::text('unit', max: 32),
            // A cumulative meter only ever goes up, so a lower reading is a
            // replaced counter rather than a typo. Usage-based maintenance
            // schedules are computed differently for the two.
            Field::boolean('is_cumulative'),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            AssetMeter::class => 'meter_type_id',
            Asset::class => 'default_meter_type_id',
        ];
    }
}
