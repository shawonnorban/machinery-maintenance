<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A packing list (PL-…): a shipment packed carton run by carton run; drafted, finalized or cancelled. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsPackingList extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'FINAL', 'CANCELLED'];

    protected $table = 'ss_packing_lists';

    protected $fillable = ['company_id', 'packing_no', 'shipment_id', 'packing_date', 'invoice_id', 'remarks', 'state', 'created_by'];

    protected function casts(): array
    {
        return ['packing_date' => 'date'];
    }
}
