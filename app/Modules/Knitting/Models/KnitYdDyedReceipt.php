<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dyed yarn back from the dye house, against the grey challan it answers.
 */
class KnitYdDyedReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'ISSUED', 'FINALIZED', 'CANCELLED'];

    protected $table = 'knit_yd_dyed_receipts';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'gate_pass_no', 'party_id', 'grey_transfer_id', 'program_id', 'buyer_id', 'order_id', 'store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
