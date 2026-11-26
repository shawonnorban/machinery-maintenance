<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll or balance on a store-to-store grey transfer.
 */
class KnitGreyTransferLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_grey_transfer_lines';

    protected $fillable = ['company_id', 'transfer_id', 'sort_order', 'lot_id', 'roll_id', 'roll_no', 'fabric', 'colour_id', 'colour_name', 'rolls', 'qty', 'to_lot_id', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rolls' => 'integer', 'sort_order' => 'integer'];
    }
}
