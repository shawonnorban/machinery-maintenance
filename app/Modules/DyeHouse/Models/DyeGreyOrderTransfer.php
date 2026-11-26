<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Grey fabric moved between orders while it sits in the batch store (Order-Wise Grey Transfer from Batch Store). Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeGreyOrderTransfer extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['BULK_TO_BULK', 'BULK_TO_SAMPLE', 'SAMPLE_TO_BULK', 'BULK_TO_LEFTOVER'];

    public const STATUSES = ['10', '30', '50', '90', '99'];

    protected $table = 'dye_grey_order_transfers';

    protected $fillable = ['company_id', 'transfer_no', 'transfer_date', 'trans_type', 'buyer_id', 'from_order_id', 'to_order_id', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date'];
    }
}
