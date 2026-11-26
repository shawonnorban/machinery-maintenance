<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An approved sub-contract programme, and the yarn held against it, handed from one outside knitter to another.
 */
class KnitYarnProgTransfer extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'ISSUED', 'FINALIZED', 'CANCELLED'];

    protected $table = 'knit_yarn_prog_transfers';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'sc_program_id', 'from_party_id', 'to_party_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'note', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
