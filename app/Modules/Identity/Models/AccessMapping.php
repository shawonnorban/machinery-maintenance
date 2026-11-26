<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One row of an Access Control mapping (AnnotechERP 02/02): a subject, a
 * target and the rights between them. `MappingRegistry` defines each kind.
 */
class AccessMapping extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'access_mappings';

    protected $fillable = ['company_id', 'kind', 'subject_id', 'target_id', 'flags', 'amount', 'text', 'date_from', 'date_to', 'sort_order', 'is_default'];

    protected function casts(): array
    {
        return ['flags' => 'array', 'amount' => 'decimal:2', 'date_from' => 'date', 'date_to' => 'date', 'sort_order' => 'integer', 'is_default' => 'boolean'];
    }

    public function holds(string $flag): bool
    {
        return (bool) ($this->flags[$flag] ?? false);
    }
}
