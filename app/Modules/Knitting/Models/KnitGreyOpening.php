<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A grey fabric opening line, open until finalised.
 */
class KnitGreyOpening extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_grey_openings';

    protected $fillable = ['company_id', 'opening_no', 'kind', 'opening_date', 'store_id', 'buyer_id', 'order_id', 'party_id', 'fabric', 'colour_id', 'colour_name', 'finish_dia', 'gsm', 'qty', 'rate', 'remarks', 'finalized', 'lot_id', 'created_by'];

    protected function casts(): array
    {
        return ['opening_date' => 'date', 'qty' => 'float', 'rate' => 'float', 'gsm' => 'float', 'finalized' => 'boolean'];
    }
}
