<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line moving from one style to the next: the minutes lost, the feeding time, and whether it is finalised for the efficiency report. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class WsChangeover extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_changeovers';

    protected $fillable = ['company_id', 'floor_id', 'planning_line_id', 'changeover_date', 'new_buyer_id', 'new_order', 'new_gmt_item', 'prev_buyer_id', 'prev_order', 'prev_gmt_item', 'prev_output_on', 'sco_minutes', 'ft_hours', 'remarks', 'finalized', 'created_by'];

    protected function casts(): array
    {
        return ['changeover_date' => 'date', 'prev_output_on' => 'date', 'sco_minutes' => 'integer', 'ft_hours' => 'decimal:2', 'finalized' => 'boolean'];
    }
}
