<?php

declare(strict_types=1);

namespace App\Modules\Costing\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One posted cost. Append-only after posting (ERD Section 14 rule 1).
 *
 * Never edited, never deleted. A cost figure that can be quietly changed is one
 * somebody will change to match a budget, and the first time that happens the
 * whole cost-per-machine report stops being evidence. A correction is a
 * REVERSAL row plus a new entry.
 */
class CostEntry extends BaseModel
{
    use BelongsToTenant;

    public const SOURCE_TYPES = [
        'LABOR', 'PARTS', 'EXTERNAL_SERVICE', 'VENDOR', 'TRANSPORT', 'MANUAL', 'REVERSAL',
    ];

    /**
     * Written by the system from the records underneath, so a work order's cost
     * cannot drift from its own labour entries and part lines
     * (ERD Section 14 rule 3).
     */
    public const DERIVED_SOURCE_TYPES = ['LABOR', 'PARTS'];

    protected $table = 'cost_entries';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'asset_id', 'work_order_id', 'breakdown_id', 'cost_category_id',
        'amount', 'currency', 'exchange_rate', 'base_amount', 'occurred_at',
        'description', 'source_type', 'source_reference_type', 'source_reference_id',
        'vendor_id', 'invoice_reference', 'posted_at', 'posted_by',
        'reverses_cost_entry_id', 'is_reversal',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'base_amount' => MoneyCast::class,
            'exchange_rate' => 'decimal:8',
            'occurred_at' => 'datetime',
            'posted_at' => 'datetime',
            'is_reversal' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(Breakdown::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class, 'cost_category_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_cost_entry_id');
    }

    public function isDerived(): bool
    {
        return in_array($this->source_type, self::DERIVED_SOURCE_TYPES, true);
    }
}
