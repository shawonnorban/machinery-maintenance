<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A reconciled chalan line: the figures read off the chalan, the short agreed and the balance. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScReconciliationLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sc_reconciliation_lines';

    protected $fillable = ['company_id', 'reconciliation_id', 'delivery_line_id', 'delivered', 'received', 'rejected', 'returned', 'short_qty', 'balance'];

    protected function casts(): array
    {
        return ['delivered' => 'float', 'received' => 'float', 'rejected' => 'float', 'returned' => 'float', 'short_qty' => 'float', 'balance' => 'float'];
    }
}
