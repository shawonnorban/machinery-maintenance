<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One sample line's target on a day sheet: for which sample, in what hours, with what manpower — and what it came to. */
class SampleDaySheetLine extends BaseModel
{
    use BelongsToTenant;

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT', 'CRITICAL'];

    protected $table = 'proto_day_sheet_lines';

    protected $fillable = ['company_id', 'day_sheet_id', 'planning_line_id', 'sample_id', 'sample_type_id', 'order_id', 'target', 'wh', 'manpower', 'target_hrs', 'actual', 'actual_hrs', 'start_time', 'end_time', 'team', 'priority', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['target' => 'integer', 'wh' => 'decimal:2', 'manpower' => 'integer', 'target_hrs' => 'decimal:2', 'actual' => 'integer', 'actual_hrs' => 'decimal:2', 'sort_order' => 'integer'];
    }
}
