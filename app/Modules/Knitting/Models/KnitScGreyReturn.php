<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Grey going back to a party, out-house or in-house.
 */
class KnitScGreyReturn extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['OUT_HOUSE', 'IN_HOUSE'];

    public const STATUSES = ['DRAFT', 'ISSUED', 'RECEIVED_BACK', 'CANCELLED'];

    protected $table = 'knit_sc_grey_returns';

    protected $fillable = ['company_id', 'challan_no', 'kind', 'challan_date', 'party_id', 'store_id', 'order_id', 'receipt_id', 'sc_receipt_id', 'reason', 'gate_pass_no', 'vehicle_no', 'driver', 'note', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
