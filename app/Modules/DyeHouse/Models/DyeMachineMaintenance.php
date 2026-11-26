<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dyeing machine's downtime log (Dye Machine Maintenance): when it was reported, stopped and ran again, the defect, who reported and who serviced it. Dye House → Requisition (AnnotechERP 17/05).
 */
class DyeMachineMaintenance extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['REPORTED', 'IN_PROGRESS', 'RESOLVED', 'CANCELLED'];

    protected $table = 'dye_machine_maintenance';

    protected $fillable = ['company_id', 'dye_machine_id', 'log_date', 'report_time', 'problem_time', 'resolved_time', 'defect_type', 'report_by', 'service_by', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['log_date' => 'date', 'report_time' => 'datetime', 'problem_time' => 'datetime', 'resolved_time' => 'datetime'];
    }
}
