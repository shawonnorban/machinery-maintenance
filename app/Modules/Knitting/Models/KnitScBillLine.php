<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One charge on a sub-contract bill, booked to its programme.
 */
class KnitScBillLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_bill_lines';

    protected $fillable = ['company_id', 'bill_id', 'sort_order', 'sc_receipt_id', 'sc_program_id', 'yd_program_id', 'order_id', 'description', 'colour_id', 'colour_name', 'shade', 'challan_no', 'qty', 'uom', 'rate', 'amount', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float', 'sort_order' => 'integer'];
    }
}
