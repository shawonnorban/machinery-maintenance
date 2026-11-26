<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item borrowed in on a loan receipt.
 */
class KnitNeedleLoanReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_loan_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'item_id', 'uom', 'receive_qty', 'rate', 'remarks'];

    protected function casts(): array
    {
        return ['receive_qty' => 'float', 'rate' => 'float'];
    }
}
