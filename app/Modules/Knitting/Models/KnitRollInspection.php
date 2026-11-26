<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The four-point inspection sheet of one grey roll.
 */
class KnitRollInspection extends BaseModel
{
    use BelongsToTenant;

    /** Draft until the roll is passed, held or rejected from the register. */
    public const STATUSES = ['DRAFT', 'PASSED', 'HOLD', 'REJECTED'];

    protected $table = 'knit_roll_inspections';

    protected $fillable = ['company_id', 'qci_no', 'roll_id', 'inspected_on', 'shift_id', 'qc_by', 'deduct_weight', 'grade_override', 'total_points', 'grade_point', 'grade', 'comments', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['inspected_on' => 'date', 'deduct_weight' => 'float', 'grade_point' => 'float', 'total_points' => 'integer'];
    }
}
