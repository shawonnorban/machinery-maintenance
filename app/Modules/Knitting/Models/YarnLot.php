<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A yarn stock line: one store's yarn of one item, count, colour, brand and lot, from one supplier, for one buyer or buffer. Knit Production → Yarn Store (AnnotechERP 15/03).
 */
class YarnLot extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_lots';

    protected $fillable = ['company_id', 'lot_key', 'store_id', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'supplier_id', 'buyer_id', 'order_id', 'is_buffer', 'source', 'uom', 'rate', 'first_date'];

    protected function casts(): array
    {
        return ['is_buffer' => 'boolean', 'rate' => 'float', 'first_date' => 'date'];
    }
}
