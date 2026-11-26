<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Yarn going out of the store to a sub-contract party.
 */
class YarnPartyChallan extends BaseModel
{
    use BelongsToTenant;

    /** Draft → Issued (the yarn has left) → Accepted or Rejected (it came back); Cancelled stays on file. */
    public const STATUSES = ['DRAFT', 'ISSUED', 'ACCEPTED', 'REJECTED', 'CANCELLED'];

    protected $table = 'knit_yarn_party_challans';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'party_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
