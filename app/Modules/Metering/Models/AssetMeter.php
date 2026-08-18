<?php

declare(strict_types=1);

namespace App\Modules\Metering\Models;

use App\Modules\Asset\Models\Asset;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One meter fitted to one asset. current_value is denormalised from the
 * latest reading so due-date evaluation does not scan reading history on
 * every scheduler tick.
 */
class AssetMeter extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'asset_id', 'meter_type_id',
        'current_value', 'last_reading_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => MoneyCast::class,
            'last_reading_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MeterType::class, 'meter_type_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'meter_id')->orderByDesc('reading_at');
    }
}
