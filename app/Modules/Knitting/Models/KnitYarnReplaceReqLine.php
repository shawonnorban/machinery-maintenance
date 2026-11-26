<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot going back to the spinner against a claim.
 */
class KnitYarnReplaceReqLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_replace_req_lines';

    protected $fillable = ['company_id', 'req_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
