<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch placed on a scheduling board, in running order. Fabric Requirement → Batch Schedule Board (AnnotechERP 13/02).
 */
class FpScheduleBatch extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_schedule_batches';

    protected $fillable = ['company_id', 'schedule_plan_id', 'batch_plan_id', 'dye_machine_id', 'planned_start', 'planned_end', 'sequence'];

    protected function casts(): array
    {
        return ['planned_start' => 'date', 'planned_end' => 'date', 'sequence' => 'integer'];
    }
}
