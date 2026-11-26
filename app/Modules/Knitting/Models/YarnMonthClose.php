<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A month whose yarn movement is struck.
 */
class YarnMonthClose extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_month_closes';

    protected $fillable = ['company_id', 'period', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }
}
