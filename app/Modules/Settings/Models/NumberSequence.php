<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One counter per company, factory, document type and period (ERD Section 25).
 * Allocation is handled by NumberSequenceGenerator under a row lock.
 */
class NumberSequence extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'document_type', 'format',
        'period_key', 'reset_policy', 'current_value', 'padding',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'padding' => 'integer',
        ];
    }
}
