<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item a party hands back, and what the store counted in.
 */
class KnitNeedleLoanReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_loan_return_lines';

    protected $fillable = ['company_id', 'return_id', 'sort_order', 'req_line_id', 'item_id', 'uom', 'return_qty', 'received_qty', 'rate', 'remarks'];

    protected function casts(): array
    {
        return ['return_qty' => 'float', 'received_qty' => 'float', 'rate' => 'float'];
    }
}
