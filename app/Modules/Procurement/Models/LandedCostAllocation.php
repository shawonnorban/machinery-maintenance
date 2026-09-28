<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one receipt line's share of the charges came to, and how it was
 * worked out (Procurement spec §11).
 *
 * Written at posting time and kept, rather than recomputed on demand. The
 * distribution depends on the quantities and rates as they were on the day
 * — a line later corrected, or stock since issued, would silently change
 * the answer, and an auditor asking "how did this become the cost" needs
 * the arithmetic that was actually used.
 */
class LandedCostAllocation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'landed_cost_allocations';

    protected $fillable = [
        'company_id', 'landed_cost_voucher_id', 'goods_receipt_id',
        'goods_receipt_line_id', 'item_id', 'basis_value', 'quantity',
        'share_amount', 'unit_uplift', 'previous_unit_cost', 'new_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'basis_value' => MoneyCast::class,
            'quantity' => MoneyCast::class,
            'share_amount' => MoneyCast::class,
            'unit_uplift' => MoneyCast::class,
            'previous_unit_cost' => MoneyCast::class,
            'new_unit_cost' => MoneyCast::class,
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
