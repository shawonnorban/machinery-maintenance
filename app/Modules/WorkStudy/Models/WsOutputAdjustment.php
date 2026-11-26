<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line's sewing output for one hour of a day corrected: the figure recorded beside the one it is corrected to. Work Study → Floor Operations (AnnotechERP 14/07).
 */
class WsOutputAdjustment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_output_adjustments';

    protected $fillable = ['company_id', 'adjust_date', 'floor_id', 'planning_line_id', 'hour', 'buyer_id', 'order_no', 'item_name', 'recorded_qty', 'adjusted_qty', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['adjust_date' => 'date', 'hour' => 'integer', 'recorded_qty' => 'integer', 'adjusted_qty' => 'integer'];
    }
}
