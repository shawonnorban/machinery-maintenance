<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\SettingDefinition;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Resolves an effective setting value.
 *
 * Resolution order, most specific wins (SRS 53.1):
 *   production line -> factory -> company -> platform default
 *
 * The level that defined the value is resolvable too, because an
 * administrator asking "why is it behaving this way" needs to see where the
 * value came from, not only what it is.
 */
class SettingsResolver
{
    /** @var array<string, array<string, mixed>> company id => key => resolved */
    private array $cache = [];

    /** @var array<string, SettingDefinition>|null */
    private ?array $definitions = null;

    public function __construct(private readonly TenantContext $context) {}

    public function get(string $key, ?string $factoryId = null, ?string $lineId = null): mixed
    {
        return $this->resolve($key, $factoryId, $lineId)['value'];
    }

    public function bool(string $key, ?string $factoryId = null, ?string $lineId = null): bool
    {
        return (bool) $this->get($key, $factoryId, $lineId);
    }

    public function int(string $key, ?string $factoryId = null, ?string $lineId = null): int
    {
        return (int) $this->get($key, $factoryId, $lineId);
    }

    public function string(string $key, ?string $factoryId = null, ?string $lineId = null): string
    {
        return (string) $this->get($key, $factoryId, $lineId);
    }

    /**
     * @return list<string>
     */
    public function list(string $key, ?string $factoryId = null, ?string $lineId = null): array
    {
        $value = $this->get($key, $factoryId, $lineId);

        if (is_array($value)) {
            return array_values($value);
        }

        return $value === null || $value === '' ? [] : explode(',', (string) $value);
    }

    /**
     * The value plus the level that defined it: PLATFORM, COMPANY, FACTORY or LINE.
     *
     * @return array{value: mixed, level: string, setting_id: string|null}
     */
    public function resolve(string $key, ?string $factoryId = null, ?string $lineId = null): array
    {
        $definition = $this->definition($key);

        $companyId = $this->context->companyIdOrNull();

        if ($companyId === null) {
            return ['value' => $definition->default_value, 'level' => 'PLATFORM', 'setting_id' => null];
        }

        $cacheKey = $companyId.'|'.$key.'|'.($factoryId ?? '-').'|'.($lineId ?? '-');

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $rows = $this->companyRows($companyId, $key);

        // Most specific first. A line override beats a factory override, which
        // beats the company value, which beats the platform default.
        $candidates = [];

        if ($lineId !== null) {
            $candidates[] = ['LINE', fn (Setting $s) => $s->production_line_id === $lineId];
        }

        if ($factoryId !== null) {
            $candidates[] = ['FACTORY', fn (Setting $s) => $s->production_line_id === null && $s->factory_id === $factoryId];
        }

        $candidates[] = ['COMPANY', fn (Setting $s) => $s->production_line_id === null && $s->factory_id === null];

        foreach ($candidates as [$level, $matches]) {
            foreach ($rows as $row) {
                if ($matches($row)) {
                    return $this->cache[$cacheKey] = [
                        'value' => $this->cast($row->value, $row->value_type),
                        'level' => $level,
                        'setting_id' => $row->id,
                    ];
                }
            }
        }

        return $this->cache[$cacheKey] = [
            'value' => $definition->default_value,
            'level' => 'PLATFORM',
            'setting_id' => null,
        ];
    }

    /**
     * Every effective setting for a scope, for the settings screen (API 19.2).
     *
     * @return array<string, array{value: mixed, level: string, setting_id: string|null}>
     */
    public function all(?string $factoryId = null, ?string $lineId = null): array
    {
        $result = [];

        foreach ($this->allDefinitions() as $key => $definition) {
            $result[$key] = $this->resolve($key, $factoryId, $lineId);
        }

        return $result;
    }

    public function definition(string $key): SettingDefinition
    {
        $definitions = $this->allDefinitions();

        if (! isset($definitions[$key])) {
            // An unknown key is rejected, never silently stored (ADR-054).
            throw new InvalidArgumentException(
                "Unknown setting key [{$key}]. Add it to setting_definitions first.",
            );
        }

        return $definitions[$key];
    }

    public function flush(): void
    {
        $this->cache = [];
        $this->definitions = null;
    }

    /**
     * @return array<string, SettingDefinition>
     */
    private function allDefinitions(): array
    {
        return $this->definitions ??= SettingDefinition::all()->keyBy('key')->all();
    }

    /**
     * @return Collection<int, Setting>
     */
    private function companyRows(string $companyId, string $key)
    {
        return Setting::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->get();
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'BOOL' => (bool) $value,
            'INT' => (int) $value,
            'DECIMAL' => (string) $value,
            'LIST' => is_array($value) ? array_values($value) : [],
            default => $value,
        };
    }
}
