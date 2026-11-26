<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Chemical Opening Balance: what a store held of one lot on the day it opened its books, counted in packs and loose, and the rate it was valued at. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemOpening extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_openings';

    protected $fillable = ['company_id', 'party_id', 'store_id', 'ob_date', 'category_id', 'usd_rate', 'item_id', 'source', 'auto_lot', 'lot_no', 'exp_date', 'pack_qty', 'pack_uom', 'qty_per_pack', 'loose_qty', 'total_qty', 'uom', 'rate_bdt', 'lot_id', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'exp_date' => 'date', 'auto_lot' => 'boolean', 'usd_rate' => 'float', 'pack_qty' => 'float', 'qty_per_pack' => 'float', 'loose_qty' => 'float', 'total_qty' => 'float', 'rate_bdt' => 'float'];
    }
}
