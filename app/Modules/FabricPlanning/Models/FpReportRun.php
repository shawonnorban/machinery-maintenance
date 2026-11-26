<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A report run: the unit, buyer and week it covered, and how many rows it gave. Fabric Requirement → Reports (AnnotechERP 13/03).
 */
class FpReportRun extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_report_runs';

    protected $fillable = ['company_id', 'report_id', 'factory_id', 'buyer_id', 'week_start', 'export_excel', 'rows', 'created_by'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'export_excel' => 'boolean', 'rows' => 'integer'];
    }
}
