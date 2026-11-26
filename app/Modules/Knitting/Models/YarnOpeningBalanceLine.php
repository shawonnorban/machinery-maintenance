<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One stock line of an opening balance.
 */
class YarnOpeningBalanceLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_ob_lines';

    protected $fillable = ['company_id', 'ob_id', 'sort_order', 'source', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'supplier_id', 'pack_qty', 'qty_per_pack', 'pack_uom', 'total_qty', 'cost_price', 'cones', 'rcv_date'];

    protected function casts(): array
    {
        return ['pack_qty' => 'float', 'qty_per_pack' => 'float', 'total_qty' => 'float', 'cost_price' => 'float', 'cones' => 'integer', 'rcv_date' => 'date'];
    }
}
