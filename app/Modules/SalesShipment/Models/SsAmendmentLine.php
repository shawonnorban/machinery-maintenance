<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A size-wise revision: the current and revised quantity and rate. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsAmendmentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_amendment_lines';

    protected $fillable = ['company_id', 'amendment_id', 'size_id', 'prev_qty', 'revised_qty', 'prev_rate', 'revised_rate'];

    protected function casts(): array
    {
        return ['prev_qty' => 'float', 'revised_qty' => 'float', 'prev_rate' => 'float', 'revised_rate' => 'float'];
    }
}
