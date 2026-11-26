<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll's quality reading taken as its batch comes off a machine: weight, GSM, dia, shade and whether it is faulty. Finishing Line → QC Process (AnnotechERP 18/04).
 */
class FinQcEntry extends BaseModel
{
    use BelongsToTenant;

    public const SHADES = ['OK', 'OFF'];

    public const RESULTS = ['OK', 'FAULT'];

    protected $table = 'fin_qc_entries';

    protected $fillable = ['company_id', 'entry_date', 'receipt_id', 'machine_id', 'process_id', 'shift', 'roll_no', 'weight_kg', 'gsm', 'dia', 'shade', 'result', 'defect', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'weight_kg' => 'decimal:3', 'gsm' => 'decimal:2', 'dia' => 'decimal:2'];
    }
}
