<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Resources;

use App\Modules\ColourLab\Models\LabEquipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Lab Equipment: what a certificate may cover (a spectrophotometer and its
 * calibration), added from the certificate form's ✚ (Brickland's "New
 * Equipment"). Withdrawn, never deleted. Colour Lab (AnnotechERP 16/01).
 */
class EquipmentResource extends ColourLabResource
{
    public function key(): string
    {
        return 'lab-equipment';
    }

    public function model(): string
    {
        return LabEquipment::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'manufacturer', 'serial_no', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return LabEquipment::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['code.required' => __('colourlab.v.code'), 'name.required' => __('colourlab.v.equipment_name'), 'code.unique' => __('colourlab.v.code_taken')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', Rule::unique('lab_equipment', 'code')->where('company_id', $this->company())->ignore($row?->getKey())],
            'name' => [$required, 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
