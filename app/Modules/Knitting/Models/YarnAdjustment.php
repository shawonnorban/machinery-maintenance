<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A physical-count correction booked against the yarn ledger.
 */
class YarnAdjustment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_adjustments';

    protected $fillable = ['company_id', 'adj_no', 'adj_date', 'store_id', 'reason', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['adj_date' => 'date'];
    }
}
