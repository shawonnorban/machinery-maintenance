<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One fabric line going back on a return challan, against the MRR batch it came in on (Trims & Accessories, AnnotechERP 06/06). */
class FabricReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_return_lines';

    protected $fillable = ['company_id', 'return_id', 'mrr_line_id', 'description', 'uom', 'qty', 'reason', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'sort_order' => 'integer'];
    }
}
