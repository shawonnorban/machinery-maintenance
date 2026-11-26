<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A lot of one dye or chemical in one store — its number, expiry and the rate it came in at; its stock is its moves added up. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemLot extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_lots';

    protected $fillable = ['company_id', 'store_id', 'item_id', 'lot_no', 'exp_date', 'uom', 'rate_bdt', 'source'];

    protected function casts(): array
    {
        return ['exp_date' => 'date', 'rate_bdt' => 'float'];
    }
}
