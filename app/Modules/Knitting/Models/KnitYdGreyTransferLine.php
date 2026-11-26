<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade of greige on a grey yarn transfer challan.
 */
class KnitYdGreyTransferLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_grey_transfer_lines';

    protected $fillable = ['company_id', 'transfer_id', 'sort_order', 'program_line_id', 'lot_id', 'colour_id', 'colour_name', 'lot_no', 'pack_qty', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'pack_qty' => 'integer', 'sort_order' => 'integer'];
    }
}
