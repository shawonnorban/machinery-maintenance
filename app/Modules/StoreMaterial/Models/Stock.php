<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A stock bucket: one material held for a buyer, PO, style and order (all blank: general stock), in the material's unit. Store & Material (AnnotechERP 09).
 */
class Stock extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_stocks';

    protected $fillable = ['company_id', 'item_id', 'order_id', 'buyer', 'po_number', 'style', 'order_no', 'uom'];

    protected function casts(): array
    {
        return [];
    }
}
