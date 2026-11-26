<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A store's day of rolls received off the floor.
 */
class KnitGreyReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'CONFIRMED'];

    protected $table = 'knit_grey_receipts';

    protected $fillable = ['company_id', 'note_no', 'receive_date', 'store_id', 'status', 'confirmed_at', 'confirmed_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'confirmed_at' => 'datetime'];
    }
}
