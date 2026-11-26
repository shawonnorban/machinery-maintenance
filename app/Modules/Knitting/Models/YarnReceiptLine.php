<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One stock line taken in on an MRR: what was counted beside what the challan claimed.
 */
class YarnReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'po_line_id', 'lot_id', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'pack_qty', 'qty_per_pack', 'pack_uom', 'recv_qty', 'rate', 'challan_qty', 'cones', 'remarks'];

    protected function casts(): array
    {
        return ['pack_qty' => 'float', 'qty_per_pack' => 'float', 'recv_qty' => 'float', 'rate' => 'float', 'challan_qty' => 'float', 'cones' => 'integer'];
    }
}
