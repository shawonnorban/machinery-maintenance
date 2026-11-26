<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch's four-point fabric inspection: the lab report from its cut sample, who inspected, the verdict and its rolls; a draft until posted, which records the verdict and moves the batch on the floor board. Finishing Line → QC Process (AnnotechERP 18/04).
 */
class FinInspection extends BaseModel
{
    use BelongsToTenant;

    public const VERDICTS = ['PASS', 'HOLD', 'REJECT'];

    protected $table = 'fin_inspections';

    protected $fillable = ['company_id', 'ref_no', 'receipt_id', 'insp_date', 'shift', 'actual_dia', 'shrinkage_l', 'shrinkage_w', 'spirality', 'inspector_id', 'shift_incharge_id', 'qc_incharge_id', 'verdict', 'shade_note', 'qc_comment', 'roll_count', 'total_kg', 'total_points', 'status', 'posted_at', 'posted_by', 'created_by'];

    protected function casts(): array
    {
        return ['insp_date' => 'date', 'actual_dia' => 'decimal:2', 'shrinkage_l' => 'decimal:2', 'shrinkage_w' => 'decimal:2', 'spirality' => 'decimal:2', 'roll_count' => 'integer', 'total_kg' => 'decimal:3', 'total_points' => 'integer', 'posted_at' => 'datetime'];
    }

    public function rolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinInspectionRoll::class, 'inspection_id')->orderBy('sort_order');
    }
}
