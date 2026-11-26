<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A correction to a posted production line — quantity, machine or process — with what it read before, so it stays auditable. Finishing Line → DF Production (AnnotechERP 18/02).
 */
class FinProductionAmendment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_production_amendments';

    protected $fillable = ['company_id', 'amend_no', 'amend_date', 'line_id', 'old_input_kg', 'new_input_kg', 'old_output_kg', 'new_output_kg', 'old_machine_id', 'new_machine_id', 'old_process_id', 'new_process_id', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['amend_date' => 'date', 'old_input_kg' => 'decimal:3', 'new_input_kg' => 'decimal:3', 'old_output_kg' => 'decimal:3', 'new_output_kg' => 'decimal:3'];
    }
}
