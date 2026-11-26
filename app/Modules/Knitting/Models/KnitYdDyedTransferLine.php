<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One receive line forwarded on a dyed yarn transfer.
 */
class KnitYdDyedTransferLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_dyed_transfer_lines';

    protected $fillable = ['company_id', 'transfer_id', 'sort_order', 'receipt_line_id', 'knit_prog_id', 'collar_prog_id', 'pack_qty', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'pack_qty' => 'integer', 'sort_order' => 'integer'];
    }
}
