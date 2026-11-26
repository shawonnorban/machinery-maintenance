<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An S/C programme (SC-…): a sales order's work put out to a sub-contractor. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsScProgramme extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sc_programmes';

    protected $fillable = ['company_id', 'sc_no', 'order_id', 'party_id', 'sc_date', 'target_date', 'process', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['sc_date' => 'date', 'target_date' => 'date'];
    }
}
