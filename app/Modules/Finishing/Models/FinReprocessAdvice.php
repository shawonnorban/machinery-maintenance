<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The re-process the floor is advised to run on what a posted inspection rejected: the process, the machine, the rejected rolls and kilos. Finishing Line → QC Process (AnnotechERP 18/04).
 */
class FinReprocessAdvice extends BaseModel
{
    use BelongsToTenant;

    public const REASONS = ['SHADE', 'GSM', 'WIDTH', 'DEFECT', 'OTHER'];

    protected $table = 'fin_reprocess_advices';

    protected $fillable = ['company_id', 'advice_no', 'advice_date', 'inspection_id', 'receipt_id', 'process_id', 'machine_id', 'rejected_rolls', 'rejected_kg', 'reason', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['advice_date' => 'date', 'rejected_rolls' => 'integer', 'rejected_kg' => 'decimal:3'];
    }
}
