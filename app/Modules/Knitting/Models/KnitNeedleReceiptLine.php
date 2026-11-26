<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One needle on a receipt: what the paper claimed and what the store counted.
 */
class KnitNeedleReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'item_id', 'uom', 'challan_qty', 'received_qty', 'rate', 'amount', 'remarks'];

    protected function casts(): array
    {
        return ['challan_qty' => 'float', 'received_qty' => 'float', 'rate' => 'float', 'amount' => 'float'];
    }
}
