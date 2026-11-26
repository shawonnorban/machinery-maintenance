<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An invoiced shipment line: description, quantity, unit price, cartons and weights. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsInvoiceLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_invoice_lines';

    protected $fillable = ['company_id', 'invoice_id', 'shipment_line_id', 'description', 'qty', 'unit_price', 'carton_qty', 'net_weight', 'gross_weight', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'unit_price' => 'float', 'carton_qty' => 'integer', 'net_weight' => 'float', 'gross_weight' => 'float', 'sort_order' => 'integer'];
    }
}
