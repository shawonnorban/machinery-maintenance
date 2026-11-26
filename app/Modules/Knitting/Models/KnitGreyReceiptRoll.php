<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll scanned into the grey store.
 */
class KnitGreyReceiptRoll extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_grey_receipt_rolls';

    protected $fillable = ['company_id', 'receipt_id', 'roll_id', 'bin_id', 'lot_id', 'weight', 'scanned_at', 'scanned_by'];

    protected function casts(): array
    {
        return ['weight' => 'float', 'scanned_at' => 'datetime'];
    }
}
