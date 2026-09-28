<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One company-defined field on one entity type — the schema, not a value.
 * `fieldable_type` is the same loose discriminator string
 * `file_attachments` already uses (`'asset'`, `'work_order'`, ...), so a
 * company's custom fields and its attachments agree on what an entity type
 * is called.
 */
class CustomFieldDefinition extends BaseModel
{
    use BelongsToTenant;

    public const FIELD_TYPES = ['TEXT', 'NUMBER', 'DATE', 'BOOLEAN', 'SELECT'];

    protected $fillable = [
        'company_id', 'fieldable_type', 'code', 'label', 'field_type',
        'options_json', 'required', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'required' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiSchema(): array
    {
        return [
            'id' => $this->id,
            'fieldable_type' => $this->fieldable_type,
            'code' => $this->code,
            'label' => $this->label,
            'field_type' => $this->field_type,
            'options' => $this->options_json ?? [],
            'required' => $this->required,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
        ];
    }
}
