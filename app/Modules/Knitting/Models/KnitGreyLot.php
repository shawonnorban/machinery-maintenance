<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A grey fabric stock line: one store, order, shade, fabric and roll.
 */
class KnitGreyLot extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['IN_HOUSE', 'COLLAR_CUFF', 'SC_IN_HOUSE'];

    protected $table = 'knit_grey_lots';

    protected $fillable = ['company_id', 'lot_key', 'store_id', 'kind', 'prod_type', 'buyer_id', 'order_id', 'party_id', 'roll_id', 'roll_no', 'fabric_type_id', 'fabric', 'colour_id', 'colour_name', 'finish_dia', 'gsm', 'part', 'batch_no', 'bin_id', 'rate', 'first_date'];

    protected function casts(): array
    {
        return ['first_date' => 'date', 'gsm' => 'float', 'rate' => 'float'];
    }
}
