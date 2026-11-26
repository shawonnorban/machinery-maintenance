<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A month locked against sales papers: when, by whom, how many orders, and whether over exceptions. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsMonthClose extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_month_closes';

    protected $fillable = ['company_id', 'period', 'orders_count', 'remarks', 'exceptions', 'overridden_by', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['orders_count' => 'integer', 'exceptions' => 'array', 'closed_at' => 'datetime'];
    }
}
