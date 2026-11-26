<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dye batch card (B-YYMM####): the order, shade and machine it runs for, its planned and card weight, and the plan and hours it is laid out on. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeBatch extends BaseModel
{
    use BelongsToTenant;

    public const SOURCES = ['PLAN', 'MANUAL', 'FINISHED', 'SPLIT', 'COLOUR', 'FROM_BULK'];

    public const STATUSES = ['OPEN', 'REPLACED', 'CLOSED'];

    protected $table = 'dye_batches';

    protected $fillable = ['company_id', 'batch_no', 'batch_date', 'scope', 'source', 'fp_batch_plan_id', 'parent_id', 'plan_id', 'buyer_id', 'order_id', 'order_no', 'style_ref', 'colour_id', 'colour_group_id', 'dyeing_method', 'dye_machine_id', 'lot_no', 'plan_qty', 'card_qty', 'store_id', 'manual_ref_no', 'manual_ref_date', 'planned_start', 'planned_end', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['batch_date' => 'date', 'manual_ref_date' => 'date', 'planned_start' => 'datetime', 'planned_end' => 'datetime', 'plan_qty' => 'decimal:2', 'card_qty' => 'decimal:2'];
    }
}
