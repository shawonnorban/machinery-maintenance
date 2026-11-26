<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One machine and the oil it drew on the shift, against its running hours.
 */
class KnitOilConsumptionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_oil_consumption_lines';

    protected $fillable = ['company_id', 'consumption_id', 'sort_order', 'machine_id', 'item_id', 'uom', 'qty', 'running_hrs', 'cost_price', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'running_hrs' => 'float', 'cost_price' => 'float'];
    }
}
