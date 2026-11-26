<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Resources;

use App\Modules\ColourLab\Models\LabCertType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Certification Type: the kinds of certificate the lab holds and who issues
 * them, added from the certificate form's ✚ (Brickland's "New Certification
 * Type"). A type is withdrawn, never deleted. Colour Lab (AnnotechERP 16/01).
 */
class CertTypeResource extends ColourLabResource
{
    public function key(): string
    {
        return 'lab-cert-types';
    }

    public function model(): string
    {
        return LabCertType::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'issuing_body', 'active'];
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
        return LabCertType::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['code.required' => __('colourlab.v.code'), 'name.required' => __('colourlab.v.type_name'), 'code.unique' => __('colourlab.v.code_taken')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', Rule::unique('lab_cert_types', 'code')->where('company_id', $this->company())->ignore($row?->getKey())],
            'name' => [$required, 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
