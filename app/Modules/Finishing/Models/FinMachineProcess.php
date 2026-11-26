<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A process a finishing machine is allowed to run, for a dia, and the stage a batch enters and leaves at (Machine-Process Mapping). Finishing Line → Master Data (AnnotechERP 18/01).
 */
class FinMachineProcess extends BaseModel
{
    use BelongsToTenant;

    public const DIAS = ['OPEN', 'TUBE', 'BOTH'];

    protected $table = 'fin_machine_processes';

    protected $fillable = ['company_id', 'dia_type', 'machine_id', 'process_id', 'sequence', 'batch_status', 'next_status', 'is_default', 'active'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'is_default' => 'boolean', 'active' => 'boolean'];
    }
}
