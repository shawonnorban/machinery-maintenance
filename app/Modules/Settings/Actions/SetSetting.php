<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingsResolver;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Writes a setting at one scope, validating against the catalog first.
 *
 * An unknown key, a value of the wrong type, or a value outside the allowed
 * set is rejected. Configuration that silently accepts anything is how a
 * typo becomes a wrong availability figure six months later (ADR-054).
 */
class SetSetting
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $resolver,
    ) {}

    public function handle(
        string $key,
        mixed $value,
        ?string $factoryId = null,
        ?string $lineId = null,
        ?string $userId = null,
    ): Setting {
        $definition = $this->resolver->definition($key);

        $level = match (true) {
            $lineId !== null => 'LINE',
            $factoryId !== null => 'FACTORY',
            default => 'COMPANY',
        };

        if (! $definition->allowsLevel($level)) {
            throw ValidationException::withMessages([
                'key' => "Setting [{$key}] cannot be defined at {$level} level. Allowed: "
                    .implode(', ', $definition->scope_levels).'.',
            ]);
        }

        $value = $this->castAndValidate($definition->value_type, $definition->allowed_values, $key, $value);

        $setting = Setting::updateOrCreate(
            [
                'company_id' => $this->context->companyId(),
                'factory_id' => $factoryId,
                'production_line_id' => $lineId,
                'key' => $key,
            ],
            [
                'value' => $value,
                'value_type' => $definition->value_type,
                'updated_by' => $userId,
            ],
        );

        $this->resolver->flush();

        return $setting;
    }

    private function castAndValidate(string $type, ?array $allowed, string $key, mixed $value): mixed
    {
        $cast = match ($type) {
            'BOOL' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'INT' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'DECIMAL' => is_numeric($value) ? number_format((float) $value, 4, '.', '') : null,
            'LIST' => is_array($value) ? array_values($value) : (is_string($value) ? array_filter(explode(',', $value)) : null),
            default => is_scalar($value) ? (string) $value : null,
        };

        if ($cast === null) {
            throw ValidationException::withMessages([
                'value' => "Setting [{$key}] expects a {$type} value.",
            ]);
        }

        if ($allowed !== null && $allowed !== []) {
            $check = is_array($cast) ? $cast : [$cast];

            foreach ($check as $item) {
                if (! in_array($item, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'value' => "Setting [{$key}] only accepts: ".implode(', ', $allowed).'.',
                    ]);
                }
            }
        }

        return $cast;
    }
}
