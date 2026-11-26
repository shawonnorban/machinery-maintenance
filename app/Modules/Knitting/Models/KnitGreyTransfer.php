<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Grey fabric moved from one store to another, the order unchanged.
 */
class KnitGreyTransfer extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['STORE', 'COLLAR_CUFF', 'TO_BATCH', 'ORDER', 'REUSE', 'TO_LEFTOVER', 'BATCH_TO_LEFTOVER'];

    public const CC_TYPES = ['COLLAR', 'CUFF', 'COLLAR_CUFF'];

    public const TYPES = ['DEV_TO_DEV', 'BULK_TO_BULK', 'SAMPLE_TO_SAMPLE', 'PROJECTION_TO_BULK'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RECEIVED', 'CANCELLED'];

    protected $table = 'knit_grey_transfers';

    protected $fillable = ['company_id', 'transfer_no', 'kind', 'transfer_date', 'trans_type', 'cc_type', 'trans_ref', 'buyer_id', 'order_id', 'to_order_id', 'from_store_id', 'to_store_id', 'batch_no', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date'];
    }
}
