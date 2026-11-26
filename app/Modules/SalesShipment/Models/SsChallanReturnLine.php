<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A returned challan line. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsChallanReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_challan_return_lines';

    protected $fillable = ['company_id', 'return_id', 'challan_line_id', 'qty', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
