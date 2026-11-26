<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot corrected, up or down.
 */
class YarnAdjustmentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_adjustment_lines';

    protected $fillable = ['company_id', 'adjustment_id', 'lot_id', 'qty', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
