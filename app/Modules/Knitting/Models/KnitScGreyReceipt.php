<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Grey, collar and cuff in from an outside knitter, against the party's challan.
 */
class KnitScGreyReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'RECEIVED', 'QC_PASSED', 'QC_HOLD', 'QC_REJECTED', 'CANCELLED'];

    public const TYPES = ['FABRIC', 'COLLAR', 'CUFF'];

    protected $table = 'knit_sc_grey_receipts';

    protected $fillable = ['company_id', 'rcv_no', 'rcv_date', 'party_id', 'store_id', 'challan_no', 'challan_date', 'order_id', 'gate_pass_no', 'vehicle_no', 'note', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['rcv_date' => 'date', 'challan_date' => 'date'];
    }
}
