<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The shade, hand-feel and GSM verdict on a finished batch (Sample Finishing Quality Check), one per batch per day. Dye House → Batch (Sample) (AnnotechERP 17/04).
 */
class DyeFinishingQc extends BaseModel
{
    use BelongsToTenant;

    public const RESULTS = ['PENDING', 'PASSED', 'FAILED', 'REWORK'];

    protected $table = 'dye_finishing_qcs';

    protected $fillable = ['company_id', 'scope', 'batch_id', 'qc_date', 'result', 'model_combo', 'gsm', 'width_inch', 'checked_by', 'shade_note', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['qc_date' => 'date', 'gsm' => 'decimal:2', 'width_inch' => 'decimal:2'];
    }
}
