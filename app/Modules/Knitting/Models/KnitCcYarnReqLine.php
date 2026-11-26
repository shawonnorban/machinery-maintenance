<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn an indent asks for.
 */
class KnitCcYarnReqLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_cc_yarn_req_lines';

    protected $fillable = ['company_id', 'req_id', 'sort_order', 'requirement_id', 'buyer_id', 'order_id', 'fabric_type_id', 'colour_id', 'composition_id', 'yarn_count_id', 'brand', 'lot_no', 'req_qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['req_qty' => 'float'];
    }
}
