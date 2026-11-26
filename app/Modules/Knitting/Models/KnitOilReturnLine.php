<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One receipt line going back on a return challan.
 */
class KnitOilReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_oil_return_lines';

    protected $fillable = ['company_id', 'return_id', 'sort_order', 'receipt_line_id', 'item_id', 'uom', 'qty', 'rate', 'amount', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float'];
    }
}
