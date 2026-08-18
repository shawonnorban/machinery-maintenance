<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The seeded catalog of settable keys (ERD 24). Platform-wide, not tenant
 * owned: the same keys exist for every company.
 */
class SettingDefinition extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key', 'value_type', 'allowed_values', 'default_value',
        'scope_levels', 'name', 'description', 'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'allowed_values' => 'array',
            'default_value' => 'array',
            'scope_levels' => 'array',
            'is_sensitive' => 'boolean',
        ];
    }

    /**
     * default_value is stored wrapped so JSON can carry scalars and lists
     * uniformly. This unwraps it.
     */
    public function getDefaultValueAttribute(mixed $value): mixed
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) && array_key_exists('v', $decoded) ? $decoded['v'] : $decoded;
    }

    public function allowsLevel(string $level): bool
    {
        return in_array($level, $this->scope_levels, true);
    }
}
