<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll on a four-point inspection: its tag number, weight, defect points, grade and result. Finishing Line → QC Process (AnnotechERP 18/04).
 */
class FinInspectionRoll extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_inspection_rolls';

    protected $fillable = ['company_id', 'inspection_id', 'roll_no', 'weight_kg', 'points', 'grade', 'result', 'defect', 'sort_order'];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:3', 'points' => 'integer', 'sort_order' => 'integer'];
    }
}
