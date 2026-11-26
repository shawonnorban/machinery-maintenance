<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch card reassigned to another shade before it runs (Batch Colour Replacement Transfer): the new card takes its own number, and the old one is replaced. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeColourTransfer extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_colour_transfers';

    protected $fillable = ['company_id', 'transfer_no', 'batch_id', 'new_batch_id', 'transfer_date', 'new_batch_no', 'lot_no', 'batch_date', 'batch_qty', 'colour_id', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'batch_date' => 'date', 'batch_qty' => 'decimal:2'];
    }
}
