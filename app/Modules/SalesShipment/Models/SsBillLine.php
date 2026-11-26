<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A billed challan line: quantity, rate and its BDT rate. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsBillLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_bill_lines';

    protected $fillable = ['company_id', 'bill_id', 'challan_line_id', 'qty', 'rate', 'exchange_rate', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'exchange_rate' => 'float', 'sort_order' => 'integer'];
    }
}
