<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Inventory\Models\UomConversion;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class UomConversionData extends MasterDataType
{
    public function key(): string
    {
        return 'uom-conversions';
    }

    public function model(): string
    {
        return UomConversion::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    /** No name on a conversion: the pair of units is what identifies it. */
    public function displayColumn(): string
    {
        return 'factor';
    }

    public function supportsActive(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            // Units are General Store's one shared list (`units_of_measure`), not a master-data type.
            Field::belongsTo('from_uom_id', UnitOfMeasure::class),
            Field::belongsTo('to_uom_id', UnitOfMeasure::class),
            Field::number('factor'),
        ];
    }

    public function validateRow(array $data, ?Model $existing): void
    {
        if (($data['from_uom_id'] ?? null) === ($data['to_uom_id'] ?? null)) {
            throw ValidationException::withMessages([
                'to_uom_id' => __('masterdata.same_unit_conversion'),
            ]);
        }
    }
}
