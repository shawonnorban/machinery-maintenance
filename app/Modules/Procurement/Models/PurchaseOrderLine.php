<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item on a purchase order (Procurement spec §7). */
class PurchaseOrderLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'pr_line_id', 'item_id', 'item_code', 'item_name', 'uom_id',
        'ordered_qty', 'rate', 'rate_contract_id', 'amount', 'received_qty', 'required_date',
        'technical_spec_json', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_qty' => MoneyCast::class,
            'rate' => MoneyCast::class,
            'amount' => MoneyCast::class,
            'received_qty' => MoneyCast::class,
            'required_date' => 'date',
            'technical_spec_json' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function requisitionLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionLine::class, 'pr_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** What the supplier still owes on this line. */
    public function pendingQty(): string
    {
        $pending = bcsub((string) $this->ordered_qty, (string) $this->received_qty, 4);

        return bccomp($pending, '0', 4) > 0 ? $pending : '0.0000';
    }
}
