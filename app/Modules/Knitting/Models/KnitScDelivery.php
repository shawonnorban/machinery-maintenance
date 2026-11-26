<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Grey, collar / cuff and the party's unused yarn going out to an in-house party on one challan.
 */
class KnitScDelivery extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'ISSUED', 'CANCELLED'];

    protected $table = 'knit_sc_deliveries';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'party_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'driver', 'receiver', 'receiver_mobile', 'note', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
