<?php

declare(strict_types=1);

namespace App\Modules\Metering\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only (ERD rule 19, ADR-013). A wrong reading is corrected by a
 * compensating reading, never by an update: due dates were already computed
 * from the original.
 */
class MeterReading extends BaseModel
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const SOURCES = ['MANUAL', 'IMPORT', 'API', 'IOT'];

    /**
     * Millisecond precision on the wire as well as in the column. Without it
     * Eloquent writes second-precision strings and the uniqueness guard
     * rejects two readings posted in the same second.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'asset_id', 'meter_id', 'value', 'previous_value', 'delta',
        'reading_at', 'source', 'source_reference', 'is_reset_baseline',
        'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => MoneyCast::class,
            'previous_value' => MoneyCast::class,
            'delta' => MoneyCast::class,
            'reading_at' => 'datetime',
            'is_reset_baseline' => 'boolean',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(AssetMeter::class, 'meter_id');
    }
}
