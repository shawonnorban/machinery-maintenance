<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A received chalan line: good and rejected quantity. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sc_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'delivery_line_id', 'qty', 'rejected_qty', 'rate', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rejected_qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}
