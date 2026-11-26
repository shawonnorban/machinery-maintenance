<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Check Roll Quality Inspection (CRI-): one roll of a dyed batch received from dyeing, the faults ticked on it and the department answerable; nothing ticked passes it, anything ticked holds it. Dye House → Dyeing Q.C (AnnotechERP 17/08).
 */
class DyeCheckRollInspection extends BaseModel
{
    use BelongsToTenant;

    /** Brickland's Mark Defects, in its order. */
    public const DEFECTS = ['CREASE_MARK', 'SOFTENAR_SPOT', 'SHADE_NOT_OK', 'UNEVEN_DYEING', 'RUNNING_SHADE', 'WRINKLE_MARK', 'COLOR_SPOT', 'PATCHES', 'DIA_MARK', 'SHADE_NOT_OK_YD_MELL', 'PPP_CONTAMINATION', 'THICK_AND_THIN', 'ROLL_TO_ROLL_SHADE_VARIATION', 'BAD_SMELL', 'SINKER_MARK', 'DEAD_COTTON', 'NEPS', 'HAIRINESS', 'SLUB', 'YARN_CONTRA'];

    protected $table = 'dye_check_roll_inspections';

    protected $fillable = ['company_id', 'inspection_no', 'inspection_date', 'batch_id', 'roll_no', 'defects', 'department_id', 'result', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['inspection_date' => 'date', 'defects' => 'array'];
    }
}
