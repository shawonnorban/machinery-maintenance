<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A finishing machine's output target per shift, for a process (or any) and dia, from a day (Machine-Wise Target Setup). Finishing Line → Master Data (AnnotechERP 18/01).
 */
class FinMachineTarget extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_machine_targets';

    protected $fillable = ['company_id', 'machine_id', 'process_id', 'dia_type', 'effective_from', 'target_qty', 'shift_hours', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'target_qty' => 'decimal:2', 'shift_hours' => 'decimal:2', 'active' => 'boolean'];
    }
}
