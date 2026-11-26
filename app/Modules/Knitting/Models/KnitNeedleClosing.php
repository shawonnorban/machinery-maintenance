<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A needle store month struck: it takes no more movement.
 */
class KnitNeedleClosing extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_closings';

    protected $fillable = ['company_id', 'book', 'store_id', 'period', 'lines_struck', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }
}
