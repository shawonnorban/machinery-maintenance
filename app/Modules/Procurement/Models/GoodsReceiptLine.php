<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One item on a goods receipt (Procurement spec §9). */
class GoodsReceiptLine extends BaseModel
{
    use BelongsToTenant;

    public const INSPECTION_STATUSES = ['PENDING', 'PASSED', 'FAILED', 'PARTIAL'];

    protected $table = 'goods_receipt_lines';

    protected $fillable = [
        'company_id', 'goods_receipt_id', 'item_id', 'bin_id', 'item_code', 'item_name', 'po_line_id',
        'received_qty', 'accepted_qty', 'uom_id', 'lot_no', 'lot_manufactured_date', 'lot_expiry_date',
        'supplier_lot_no', 'rate', 'currency', 'inspection_status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_qty' => MoneyCast::class,
            'accepted_qty' => MoneyCast::class,
            'rate' => MoneyCast::class,
            // Read off the drum at the gate; they become the lot's own
            // record (Inventory spec §12).
            'lot_manufactured_date' => 'date',
            'lot_expiry_date' => 'date',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    /** What goes on the shelf: the accepted quantity when inspection set one, else all of it. */
    public function postableQty(): string
    {
        return (string) ($this->accepted_qty ?? $this->received_qty);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** The physical rolls this line produced (Inventory spec §3), for ROLL-tracked items. */
    public function rolls(): HasMany
    {
        return $this->hasMany(FabricRoll::class, 'goods_receipt_line_id');
    }
}
