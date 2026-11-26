<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sample drawn out of a bulk batch onto a card of its own (Sample Batch from Bulk Lot): what is drawn comes off the bulk card in the same transaction. Dye House → Batch (Sample) (AnnotechERP 17/04).
 */
class DyeSampleTransfer extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sample_transfers';

    protected $fillable = ['company_id', 'transfer_no', 'transfer_date', 'batch_id', 'new_batch_id', 'new_batch_no', 'lot_no', 'batch_date', 'batch_qty', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'batch_date' => 'date', 'batch_qty' => 'decimal:2'];
    }
}
