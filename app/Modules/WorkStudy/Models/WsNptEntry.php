<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Non-productive time booked against a line on a day: the cause, the hours it hit, the manpower and minutes lost. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class WsNptEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_npt_entries';

    protected $fillable = ['company_id', 'npt_date', 'floor_id', 'planning_line_id', 'order_style', 'reason_type', 'reason_details', 'responsible_dept', 'hour_slots', 'manpower', 'minutes', 'npt'];

    protected function casts(): array
    {
        return ['npt_date' => 'date', 'hour_slots' => 'array', 'manpower' => 'integer', 'minutes' => 'integer', 'npt' => 'integer'];
    }
}
