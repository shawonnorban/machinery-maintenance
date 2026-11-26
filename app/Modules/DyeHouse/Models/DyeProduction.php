<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch's output on a day (Sample Batch Production; Bulk Batch Production, 17/07): the requisition it came in on, the weight that went on, machine, model/combo, lab dip and liquor ratio — one per batch per day. Dye House → Batch (Sample) (AnnotechERP 17/04).
 */
class DyeProduction extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_productions';

    protected $fillable = ['company_id', 'batch_id', 'production_date', 'requisition_id', 'weight_kg', 'dye_machine_id', 'model_combo', 'lab_dip_ref', 'liquor_ratio', 'bypass_check_roll', 'scouring', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['production_date' => 'date', 'weight_kg' => 'decimal:3', 'bypass_check_roll' => 'boolean', 'scouring' => 'boolean'];
    }
}
