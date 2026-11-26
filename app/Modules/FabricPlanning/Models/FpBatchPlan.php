<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An order's fabric gathered into batches, placed in a week and on a dye machine. Fabric Requirement → Fabric Batch Plan Entry (AnnotechERP 13/02).
 */
class FpBatchPlan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PLANNED', 'SCHEDULED', 'RELEASED', 'CANCELLED'];

    public const COLOUR_TYPES = ['SOLID', 'MELANGE', 'YARN_DYED', 'AOP'];

    protected $table = 'fp_batch_plans';

    protected $fillable = [
        'company_id', 'bp_no', 'week_start', 'buyer_id', 'style_job', 'order_no', 'order_cat', 'colour_type', 'colour_id', 'colour_group_id', 'dyeing_method', 'dye_machine_id',
        'shipment_date', 'booking_confirm_date', 'production_hours', 'pending_qty', 'batches', 'bp_qty', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'shipment_date' => 'date', 'booking_confirm_date' => 'date', 'production_hours' => 'float', 'pending_qty' => 'float', 'batches' => 'integer', 'bp_qty' => 'float'];
    }
}
