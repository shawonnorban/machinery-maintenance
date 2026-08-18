<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A configured value at one exact scope (ERD 24). Resolution across scopes is
 * SettingsResolver's job, not this model's.
 */
class Setting extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'production_line_id',
        'key', 'value', 'value_type', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public function getValueAttribute(mixed $value): mixed
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) && array_key_exists('v', $decoded) ? $decoded['v'] : $decoded;
    }

    public function setValueAttribute(mixed $value): void
    {
        $this->attributes['value'] = json_encode(['v' => $value], JSON_UNESCAPED_UNICODE);
    }

    public function level(): string
    {
        return match (true) {
            $this->production_line_id !== null => 'LINE',
            $this->factory_id !== null => 'FACTORY',
            default => 'COMPANY',
        };
    }
}
