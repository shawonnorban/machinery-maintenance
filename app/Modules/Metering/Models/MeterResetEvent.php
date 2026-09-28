<?php

declare(strict_types=1);

namespace App\Modules\Metering\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A meter replacement or rollover. Separate and auditable, because it is the
 * one legitimate reason a cumulative reading may go down (ADR-013).
 */
class MeterResetEvent extends BaseModel
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'meter_id', 'old_value', 'new_value',
        'reason', 'reset_at', 'reset_by',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => MoneyCast::class,
            'new_value' => MoneyCast::class,
            'reset_at' => 'datetime',
        ];
    }
}
