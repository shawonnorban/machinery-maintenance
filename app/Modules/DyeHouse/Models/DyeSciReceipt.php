<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * In-House Sub-Contract Fabric Receipt: a party's fabric received in against an in-house programme, on their own challan. Dye House → S/C In-House (AnnotechERP 17/10).
 */
class DyeSciReceipt extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sci_receipts';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'programme_id', 'supplier_id', 'store_id', 'qty_kg', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date', 'qty_kg' => 'float'];
    }
}
