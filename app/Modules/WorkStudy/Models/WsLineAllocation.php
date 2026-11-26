<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An order allocated to a line: when it is planned to run, the pieces made against the allocation, whether it is on the day's target, and its status. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class WsLineAllocation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_line_allocations';

    protected $fillable = ['company_id', 'floor_id', 'planning_line_id', 'buyer_id', 'order_style', 'style', 'item', 'smv', 'input_date', 'plan_start', 'plan_end', 'allocation', 'production', 'in_target', 'status'];

    protected function casts(): array
    {
        return ['smv' => 'decimal:2', 'input_date' => 'date', 'plan_start' => 'date', 'plan_end' => 'date', 'allocation' => 'integer', 'production' => 'integer', 'in_target' => 'boolean'];
    }
}
