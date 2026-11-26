<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dyed yarn going back to a dye house to be run again.
 */
class KnitYdReprocess extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['IN_HOUSE', 'SUB_CONTRACT'];

    public const STATUSES = ['DRAFT', 'ISSUED', 'ACCEPTED', 'REJECTED', 'CANCELLED'];

    protected $table = 'knit_yd_reprocesses';

    protected $fillable = ['company_id', 'ref_no', 'kind', 'ref_date', 'supplier_id', 'party_id', 'bill_no', 'challan_no', 'challan_date', 'program_id', 'store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['ref_date' => 'date', 'challan_date' => 'date'];
    }
}
