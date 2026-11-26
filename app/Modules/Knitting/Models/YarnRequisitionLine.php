<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn line of a purchase indent: item, count, colour, brand, unit, local and import quantities.
 */
class YarnRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_requisition_lines';

    protected $fillable = ['company_id', 'requisition_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'uom', 'qty_local', 'qty_import', 'knit_start_date', 'ship_date', 'booking_price', 'remarks'];

    protected function casts(): array
    {
        return ['qty_local' => 'float', 'qty_import' => 'float', 'booking_price' => 'float', 'knit_start_date' => 'date', 'ship_date' => 'date'];
    }
}
