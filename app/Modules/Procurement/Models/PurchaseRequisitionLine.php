<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item on a purchase requisition (Procurement spec §5). */
class PurchaseRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'purchase_requisition_lines';

    protected $fillable = [
        'company_id', 'purchase_requisition_id', 'item_id', 'item_code', 'item_name', 'uom_id',
        'requested_qty', 'ordered_qty', 'required_date', 'estimated_rate',
        'current_stock_qty', 'already_ordered_qty', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_qty' => MoneyCast::class,
            'ordered_qty' => MoneyCast::class,
            'estimated_rate' => MoneyCast::class,
            'current_stock_qty' => MoneyCast::class,
            'already_ordered_qty' => MoneyCast::class,
            'required_date' => 'date',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** What has not yet been placed on a purchase order. */
    public function pendingQty(): string
    {
        $pending = bcsub((string) $this->requested_qty, (string) $this->ordered_qty, 4);

        return bccomp($pending, '0', 4) > 0 ? $pending : '0.0000';
    }

    public function estimatedAmount(): string
    {
        return bcmul((string) $this->requested_qty, (string) ($this->estimated_rate ?? '0'), 4);
    }
}
