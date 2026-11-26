<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll of fabric received on a GRN line — its number, dye lot, GSM, width and quantity. Store & Material (AnnotechERP 09).
 */
class Roll extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_rolls';

    protected $fillable = ['company_id', 'grn_id', 'grn_line_id', 'stock_id', 'item_id', 'roll_no', 'dye_lot', 'gsm', 'width', 'uom', 'qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }
}
