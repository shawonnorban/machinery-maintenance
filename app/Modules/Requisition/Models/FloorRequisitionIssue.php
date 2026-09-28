<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hand-over across the store counter (Floor/Store Requisition spec §6).
 *
 * A requisition line is rarely one movement: part is issued now and the
 * rest when the purchase arrives, and each part may come from a different
 * bin, lot or roll. This row is that movement — what left, where from, at
 * what price, and the ledger transaction it produced. Without it, "which
 * roll went to line 4" is a question the ledger can only answer by
 * guessing, and a return has no price to post back at.
 */
class FloorRequisitionIssue extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'floor_requisition_issues';

    protected $fillable = [
        'company_id', 'floor_requisition_line_id', 'bin_id', 'lot_no', 'fabric_roll_id',
        'quantity', 'unit_cost', 'currency', 'item_stock_transaction_id',
        'returned_qty', 'returned_at', 'return_transaction_id', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'returned_qty' => MoneyCast::class,
            'returned_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(FloorRequisitionLine::class, 'floor_requisition_line_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ItemStockTransaction::class, 'item_stock_transaction_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Still out on the floor, for a returnable item. */
    public function outstandingQty(): string
    {
        return bcsub((string) $this->quantity, (string) $this->returned_qty, 4);
    }
}
