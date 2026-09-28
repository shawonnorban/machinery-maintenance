<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field's value on one record. `fieldable_id` is a loose pair (plain
 * ULID column, no FK) alongside `fieldable_type` — the same reasoning
 * `file_attachments.attachable_id` already documents: the record a value
 * was entered against may not survive forever, and that must not cascade
 * the value away with it. `definition_id` is a real FK, and does cascade —
 * a value with no definition left to describe it is unreadable, not
 * historical.
 */
class CustomFieldValue extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'definition_id', 'fieldable_type', 'fieldable_id', 'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}
