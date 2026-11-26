<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One stock line leaving on a party challan.
 */
class YarnPartyChallanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_party_challan_lines';

    protected $fillable = ['company_id', 'challan_id', 'sort_order', 'lot_id', 'qty', 'rate', 'cones', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'cones' => 'integer'];
    }
}
