<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a dye machine can take in a week. Fabric Requirement → Dye Machine Capacity Board (AnnotechERP 13/02).
 */
class FpMachineCapacity extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_machine_capacities';

    protected $fillable = ['company_id', 'dye_machine_id', 'week_start', 'capacity_kg', 'working_days', 'holidays', 'max_load_pct', 'capacity_hours', 'avg_batch_qty'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'capacity_kg' => 'float', 'working_days' => 'integer', 'holidays' => 'integer', 'max_load_pct' => 'float', 'capacity_hours' => 'float', 'avg_batch_qty' => 'float'];
    }
}
