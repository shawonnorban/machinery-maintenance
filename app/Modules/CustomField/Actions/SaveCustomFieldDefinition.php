<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Actions;

use App\Modules\CustomField\Models\CustomFieldDefinition;
use Illuminate\Validation\ValidationException;

/**
 * Defines or edits one custom field. `code` is write-once: it is what a
 * submitted value is keyed by (`custom_fields[{code}]`), and letting it
 * change after values exist would orphan every one of them silently.
 */
class SaveCustomFieldDefinition
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $companyId, string $fieldableType, array $data): CustomFieldDefinition
    {
        $this->assertValid($data);

        if (CustomFieldDefinition::where('fieldable_type', $fieldableType)->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => __('custom_field.code_taken')]);
        }

        return CustomFieldDefinition::create([
            'company_id' => $companyId,
            'fieldable_type' => $fieldableType,
            'code' => $data['code'],
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options_json' => $data['field_type'] === 'SELECT' ? array_values($data['options'] ?? []) : null,
            'required' => $data['required'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => $data['active'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomFieldDefinition $definition, array $data): CustomFieldDefinition
    {
        $this->assertValid($data);

        $definition->forceFill([
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options_json' => $data['field_type'] === 'SELECT' ? array_values($data['options'] ?? []) : null,
            'required' => $data['required'] ?? false,
            'sort_order' => $data['sort_order'] ?? $definition->sort_order,
        ])->save();

        return $definition->fresh();
    }

    public function setActive(CustomFieldDefinition $definition, bool $active): CustomFieldDefinition
    {
        $definition->forceFill(['active' => $active])->save();

        return $definition->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValid(array $data): void
    {
        if (! in_array($data['field_type'] ?? null, CustomFieldDefinition::FIELD_TYPES, true)) {
            throw ValidationException::withMessages(['field_type' => __('custom_field.field_type_invalid')]);
        }

        if ($data['field_type'] === 'SELECT' && count($data['options'] ?? []) === 0) {
            throw ValidationException::withMessages(['options' => __('custom_field.options_required')]);
        }
    }
}
