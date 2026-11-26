<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Fabric released from a cancelled or short batch into left-over stock (Batch Transfer to Leftover). Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeLeftoverTransfer extends BaseModel
{
    use BelongsToTenant;

    public const REASONS = ['BATCH_END', 'ORDER_CANCELLED', 'SHORT_BATCH', 'EXCESS_KNITTING', 'QUALITY_HOLD'];

    public const STATUSES = ['DRAFT', 'REQUESTED', 'APPROVED', 'TRANSFERRED', 'CANCELLED'];

    protected $table = 'dye_leftover_transfers';

    protected $fillable = ['company_id', 'transfer_no', 'transfer_date', 'reason', 'source', 'buyer_id', 'order_id', 'colour_id', 'qty', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'qty' => 'decimal:2'];
    }
}
