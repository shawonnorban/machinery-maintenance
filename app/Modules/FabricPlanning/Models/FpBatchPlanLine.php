<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A fabric line of a batch plan: grey required against what is planned. Fabric Requirement → Fabric Batch Plan Entry (AnnotechERP 13/02).
 */
class FpBatchPlanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_batch_plan_lines';

    protected $fillable = ['company_id', 'batch_plan_id', 'fabric', 'grey_req', 'bp_plus', 'bp_minus', 'bp_qty', 'sort_order'];

    protected function casts(): array
    {
        return ['grey_req' => 'float', 'bp_plus' => 'float', 'bp_minus' => 'float', 'bp_qty' => 'float', 'sort_order' => 'integer'];
    }
}
