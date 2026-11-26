<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A store's month for one lot on the opening grid: its adjustment and reason.
 */
class YarnPeriodLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_period_lines';

    protected $fillable = ['company_id', 'store_id', 'period', 'lot_id', 'adjustment', 'reason', 'finalized'];

    protected function casts(): array
    {
        return ['adjustment' => 'float', 'finalized' => 'boolean'];
    }
}
