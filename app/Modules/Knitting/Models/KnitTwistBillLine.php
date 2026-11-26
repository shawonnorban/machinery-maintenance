<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One finalised receive challan priced, or an agreed adjustment.
 */
class KnitTwistBillLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_twist_bill_lines';

    protected $fillable = ['company_id', 'bill_id', 'sort_order', 'receive_id', 'particulars', 'composition_id', 'yarn_count_id', 'qty', 'rate', 'amount', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float'];
    }
}
