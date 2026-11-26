<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The oil a store opens a period with, one item and date, priced in its currency.
 */
class KnitOilOpening extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_oil_openings';

    protected $fillable = ['company_id', 'store_id', 'ob_date', 'item_id', 'uom', 'qty', 'unit_price', 'currency_id', 'conv_rate', 'cost_price', 'remarks', 'finalized', 'finalized_at', 'finalized_by', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'qty' => 'float', 'unit_price' => 'float', 'conv_rate' => 'float', 'cost_price' => 'float', 'finalized' => 'boolean', 'finalized_at' => 'datetime'];
    }
}
