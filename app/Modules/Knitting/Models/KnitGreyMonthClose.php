<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A month whose grey fabric movement is struck.
 */
class KnitGreyMonthClose extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_grey_month_closes';

    protected $fillable = ['company_id', 'period', 'remarks', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }
}
