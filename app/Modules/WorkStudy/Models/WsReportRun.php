<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Work Study report run: which report, for what, and how many rows it found. Work Study → Reports (AnnotechERP 14/02).
 */
class WsReportRun extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_report_runs';

    protected $fillable = ['company_id', 'report_key', 'factory_id', 'buyer_id', 'floor_id', 'order_no', 'production_date', 'export_excel', 'rows_count', 'created_by'];

    protected function casts(): array
    {
        return ['production_date' => 'date', 'export_excel' => 'boolean', 'rows_count' => 'integer'];
    }
}
