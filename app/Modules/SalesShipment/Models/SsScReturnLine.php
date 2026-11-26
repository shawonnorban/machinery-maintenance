<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A receipt line going back to the party. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sc_return_lines';

    protected $fillable = ['company_id', 'return_id', 'receipt_line_id', 'qty', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
