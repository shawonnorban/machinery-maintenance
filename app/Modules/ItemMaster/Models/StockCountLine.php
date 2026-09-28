<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shelf on a count sheet (Inventory spec §12).
 *
 * `counted_qty` is null until somebody has actually been to that shelf.
 * That is not the same as a counted zero — an empty bin is a finding, an
 * unvisited bin is an unfinished job — and a sheet that cannot tell the
 * two apart is not a count.
 */
class StockCountLine extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'MATCHED', 'VARIANCE', 'RECOUNT', 'ADJUSTED'];

    protected $table = 'stock_count_lines';

    protected $fillable = [
        'company_id', 'stock_count_id', 'item_id', 'item_code', 'item_name', 'uom_id',
        'bin_id', 'lot_no', 'system_qty', 'counted_qty', 'variance_qty', 'unit_cost',
        'variance_value', 'status', 'is_unexpected', 'counted_by', 'counted_at',
        'notes', 'item_stock_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => MoneyCast::class,
            'variance_value' => MoneyCast::class,
            'is_unexpected' => 'boolean',
            'counted_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ItemStockTransaction::class, 'item_stock_transaction_id');
    }

    /** Nobody has been to this shelf yet. */
    public function isUncounted(): bool
    {
        return $this->counted_qty === null;
    }

    /** More on the shelf than the system thought. */
    public function isOver(): bool
    {
        return bccomp((string) $this->variance_qty, '0', 4) === 1;
    }

    /** What the adjustment has to move, signed. */
    public function adjustmentQty(): string
    {
        return (string) $this->variance_qty;
    }
}
