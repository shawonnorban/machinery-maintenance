<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One fabric roll, collar / cuff or yarn line on a delivery challan.
 */
class KnitScDeliveryLine extends BaseModel
{
    use BelongsToTenant;

    public const SECTIONS = ['FABRIC', 'COLLAR_CUFF', 'YARN'];

    protected $table = 'knit_sc_delivery_lines';

    protected $fillable = ['company_id', 'delivery_id', 'sort_order', 'section', 'part', 'lot_id', 'yarn_lot_id', 'description', 'colour_id', 'colour_name', 'shade', 'roll_no', 'lot_no', 'finish_dia', 'mc_gauge', 'r_qty', 'bags', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'r_qty' => 'integer', 'bags' => 'integer', 'sort_order' => 'integer'];
    }
}
