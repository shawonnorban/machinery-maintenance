<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Actions;

use App\Modules\CustomField\Models\CustomFieldDefinition;
use App\Modules\CustomField\Models\CustomFieldValue;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validates and stores one record's custom field values in one pass —
 * called from inside the owning record's own save (e.g.
 * `AssetApiController::store()`), inside the same transaction, so a bad
 * custom field value rolls back the whole save rather than leaving the
 * record saved with half its custom data missing.
 *
 * A submitted key with no matching *active* definition is ignored rather
 * than rejected — a field deactivated after the form was loaded, or a
 * stale client, must not block saving the record itself.
 */
class SaveCustomFieldValues
{
    /**
     * @param  array<string, mixed>  $submitted  Keyed by the definition's own `code`.
     */
    public function handle(string $fieldableType, string $fieldableId, array $submitted): void
    {
        $definitions = CustomFieldDefinition::where('fieldable_type', $fieldableType)
            ->where('active', true)
            ->get();

        $errors = [];
        $toSave = [];

        foreach ($definitions as $definition) {
            $raw = $submitted[$definition->code] ?? null;
            $blank = $raw === null || $raw === '';

            if ($definition->required && $blank) {
                $errors["custom_fields.{$definition->code}"] = __('custom_field.required_field', ['field' => $definition->label]);

                continue;
            }

            if ($blank) {
                CustomFieldValue::where('definition_id', $definition->id)
                    ->where('fieldable_type', $fieldableType)
                    ->where('fieldable_id', $fieldableId)
                    ->delete();

                continue;
            }

            try {
                $toSave[$definition->id] = $this->castValue($definition, $raw);
            } catch (Throwable) {
                $errors["custom_fields.{$definition->code}"] = __('custom_field.invalid_field', ['field' => $definition->label]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($toSave as $definitionId => $value) {
            CustomFieldValue::updateOrCreate(
                ['definition_id' => $definitionId, 'fieldable_type' => $fieldableType, 'fieldable_id' => $fieldableId],
                ['value_json' => $value],
            );
        }
    }

    /**
     * @throws Throwable when the raw value cannot be cast to its declared type
     */
    private function castValue(CustomFieldDefinition $definition, mixed $raw): string|float|bool
    {
        return match ($definition->field_type) {
            'NUMBER' => is_numeric($raw) ? (float) $raw : throw new \UnexpectedValueException('Not numeric'),
            'DATE' => CarbonImmutable::parse((string) $raw)->toDateString(),
            'BOOLEAN' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? throw new \UnexpectedValueException('Not boolean'),
            'SELECT' => in_array((string) $raw, $definition->options_json ?? [], true)
                ? (string) $raw
                : throw new \UnexpectedValueException('Not one of the defined options'),
            default => (string) $raw, // TEXT
        };
    }
}
