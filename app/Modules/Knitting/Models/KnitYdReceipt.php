<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dyed yarn back from the dye house against a Y/D programme.
 */
class KnitYdReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'CONFIRMED', 'CANCELLED'];

    protected $table = 'knit_yd_receipts';

    protected $fillable = ['company_id', 'receive_no', 'receive_date', 'program_id', 'supplier_id', 'working_ref', 'challan_no', 'challan_date', 'store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'challan_date' => 'date'];
    }
}
