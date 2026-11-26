<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One oil on a receipt: what the supplier's paper claimed, what was measured in, and the part accepted on condition.
 */
class KnitOilReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_oil_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'item_id', 'uom', 'challan_qty', 'received_qty', 'cond_qty', 'rate', 'amount', 'remarks'];

    protected function casts(): array
    {
        return ['challan_qty' => 'float', 'received_qty' => 'float', 'cond_qty' => 'float', 'rate' => 'float', 'amount' => 'float'];
    }
}
