<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a line produced on one day, by overtime tier, with the man-minutes of overtime behind it. Work Study → Costing (AnnotechERP 14/05).
 */
class WsLineProduction extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_line_productions';

    protected $fillable = ['company_id', 'floor_id', 'prod_date', 'planning_line_id', 'buyer_id', 'order_no', 'item_name', 'op', 'aso', 'wh', 'target', 'prod_g', 'prod_aot', 'prod_eot', 'prod_2ot', 'prod_5ot', 'manot_aot', 'manot_eot', 'manot_2ot', 'manot_5ot'];

    protected function casts(): array
    {
        return ['prod_date' => 'date', 'op' => 'integer', 'aso' => 'integer', 'wh' => 'decimal:2', 'target' => 'integer', 'prod_g' => 'integer', 'prod_aot' => 'integer', 'prod_eot' => 'integer', 'prod_2ot' => 'integer', 'prod_5ot' => 'integer', 'manot_aot' => 'decimal:2', 'manot_eot' => 'decimal:2', 'manot_2ot' => 'decimal:2', 'manot_5ot' => 'decimal:2'];
    }
}
