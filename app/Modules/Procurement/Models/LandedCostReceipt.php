<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which goods receipt a landed cost voucher applies to.
 *
 * A join row rather than a column on the receipt, because one container's
 * freight bill does not respect GRN boundaries: the same charge is spread
 * over every receipt it actually paid to bring in.
 */
class LandedCostReceipt extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'landed_cost_receipts';

    protected $fillable = ['company_id', 'landed_cost_voucher_id', 'goods_receipt_id'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
