<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A return challan sending a receipt's oil back to its supplier.
 */
class KnitOilReturn extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['RETURNABLE', 'NON_RETURNABLE'];

    public const STATUSES = ['DRAFT', 'ISSUED', 'FINALIZED', 'CANCELLED'];

    protected $table = 'knit_oil_returns';

    protected $fillable = ['company_id', 'challan_no', 'receipt_id', 'challan_date', 'challan_type', 'store_id', 'supplier_id', 'gate_pass_no', 'vehicle_no', 'reason', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
