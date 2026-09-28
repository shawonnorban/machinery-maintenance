<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Modules\Tenancy\Models\ProductionLine;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the stoppage cost production (SRS 18, ERD Section 11).
 *
 * Recorded in pieces, not money. Converting output to currency needs a costing
 * rate this system does not own, and a fabricated figure would be quoted in a
 * meeting as fact.
 */
class ProductionImpact extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'production_impacts';

    protected $fillable = [
        'company_id', 'breakdown_id', 'production_line_id',
        'production_order_reference', 'estimated_loss', 'actual_loss',
        'unit', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_loss' => MoneyCast::class,
            'actual_loss' => MoneyCast::class,
        ];
    }

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(Breakdown::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }
}
