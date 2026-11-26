<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\GarmentPart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Garment Part Master: the parts a garment is cut from. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class GarmentPartResource extends MasterResource
{
    public function key(): string
    {
        return 'garment-parts';
    }

    public function model(): string
    {
        return GarmentPart::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'fabric_group', 'is_circular', 'is_process_group', 'is_body', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'is_circular', 'is_process_group', 'is_body'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'fabric_group' => ['nullable', Rule::in(GarmentPart::FABRIC_GROUPS)],
            'is_circular' => ['sometimes', 'boolean'],
            'is_process_group' => ['sometimes', 'boolean'],
            'is_body' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
