<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A flat-knit part a job needs: in-house and sub-contract pieces asked apart.
 */
class KnitCcRequirement extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['BULK', 'SAMPLE', 'DEVELOP'];

    public const ITEMS = ['COLLAR', 'CUFF', 'PLACKET', 'NECK_RIB', 'TAPE'];

    public const STATUSES = ['OPEN', 'CLOSED'];

    protected $table = 'knit_cc_requirements';

    protected $fillable = ['company_id', 'req_no', 'req_type', 'category', 'buyer_id', 'order_id', 'work_style_no', 'ship_month', 'item', 'description', 'colour_id', 'size_desc', 'gauge', 'req_in_house', 'req_sc', 'uom', 'remarks', 'status', 'closed_at', 'created_by'];

    protected function casts(): array
    {
        return ['req_in_house' => 'float', 'req_sc' => 'float', 'closed_at' => 'datetime'];
    }
}
