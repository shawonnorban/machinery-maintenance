<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A programme line: an order size going out, and the rate the party is paid. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsScProgrammeLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sc_programme_lines';

    protected $fillable = ['company_id', 'programme_id', 'size_id', 'qty', 'rate'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float'];
    }
}
