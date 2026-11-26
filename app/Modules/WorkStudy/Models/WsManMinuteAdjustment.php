<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The operators and ASOs a line carried on a day but did not sew its order with, for one reason, in the general shift and each overtime hour. Work Study → Floor Operations (AnnotechERP 14/07).
 */
class WsManMinuteAdjustment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_man_minute_adjustments';

    protected $fillable = ['company_id', 'adjust_date', 'floor_id', 'planning_line_id', 'reason', 'used_op', 'used_aso', 'ot_slots', 'ot_op', 'ot_aso', 'created_by'];

    protected function casts(): array
    {
        return ['adjust_date' => 'date', 'ot_slots' => 'array', 'used_op' => 'integer', 'used_aso' => 'integer', 'ot_op' => 'integer', 'ot_aso' => 'integer'];
    }
}
