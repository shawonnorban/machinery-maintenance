<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One item going back to the supplier, and the receipt line it came in on (Trims & Accessories, AnnotechERP 06/06). */
class AccReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_acc_return_lines';

    protected $fillable = ['company_id', 'return_id', 'mrr_line_id', 'item_id', 'description', 'uom', 'qty', 'reason', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'sort_order' => 'integer'];
    }
}
