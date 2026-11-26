<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade on a dyed yarn receipt.
 */
class KnitYdReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'program_line_id', 'lot_no', 'qty', 'uom', 'cones', 'lot_id', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'cones' => 'integer', 'sort_order' => 'integer'];
    }
}
