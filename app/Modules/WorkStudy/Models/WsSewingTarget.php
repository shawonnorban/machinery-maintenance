<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An order a line runs on a day: the manpower, hours and efficiency its target is worked out from, and what it made. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class WsSewingTarget extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_sewing_targets';

    protected $fillable = ['company_id', 'target_date', 'floor_id', 'planning_line_id', 'buyer_id', 'order_style', 'gmt_item', 'ship_date', 'fob', 'smv', 'is_car', 'day_run', 'lo_effi', 't_effi', 'wh', 'wh_from', 'wh_to', 'used_op', 'used_aso', 'layout_op', 'layout_aso', 'wip_rfi', 'plan', 'target_gen', 'target_ot', 'prod_gen', 'prod_ot', 'input_min', 'prod_min', 'ttl_value', 'is_merged', 'is_single', 'is_off', 'sort_order', 'created_by'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'ship_date' => 'date', 'fob' => 'decimal:4', 'smv' => 'decimal:2', 'is_car' => 'boolean', 'day_run' => 'integer', 'lo_effi' => 'decimal:2', 't_effi' => 'decimal:2', 'wh' => 'decimal:2', 'wh_from' => 'decimal:2', 'wh_to' => 'decimal:2', 'used_op' => 'integer', 'used_aso' => 'integer', 'layout_op' => 'integer', 'layout_aso' => 'integer', 'wip_rfi' => 'integer', 'plan' => 'integer', 'target_gen' => 'integer', 'target_ot' => 'integer', 'prod_gen' => 'integer', 'prod_ot' => 'integer', 'input_min' => 'integer', 'prod_min' => 'integer', 'ttl_value' => 'decimal:2', 'is_merged' => 'boolean', 'is_single' => 'boolean', 'is_off' => 'boolean', 'sort_order' => 'integer'];
    }
}
