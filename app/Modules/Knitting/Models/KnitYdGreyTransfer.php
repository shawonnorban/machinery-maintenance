<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Greige yarn going to a dye house, against a Y/D programme.
 */
class KnitYdGreyTransfer extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'ISSUED', 'FINALIZED', 'CANCELLED'];

    protected $table = 'knit_yd_grey_transfers';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'gate_pass_no', 'vehicle_no', 'party_id', 'program_id', 'store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
