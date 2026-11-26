<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One machine and needle broken on a shift.
 */
class KnitNeedleBrokenLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_broken_lines';

    protected $fillable = ['company_id', 'broken_id', 'sort_order', 'machine_id', 'item_id', 'uom', 'qty', 'cost_price', 'amount', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'cost_price' => 'float', 'amount' => 'float'];
    }
}
