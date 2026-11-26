<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One material received on a GRN, into its bucket and the Fabric or the Trims & Accessories warehouse. Store & Material (AnnotechERP 09).
 */
class GrnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_grn_lines';

    protected $fillable = ['company_id', 'grn_id', 'requisition_line_id', 'stock_id', 'item_id', 'kind', 'uom', 'qty', 'unit_price', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_price' => 'decimal:4'];
    }
}
