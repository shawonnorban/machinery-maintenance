<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One Production Master Data row: a day's cutting, sewing or finishing figures of a line, as the Daily Production Report columns (30/01). Production Tracking (AnnotechERP 30).
 */
class PtEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pt_entries';

    protected $fillable = ['company_id', 'section', 'entry_date', 'line_id', 'buyer_name', 'style', 'po_no', 'item', 'order_qty', 'target', 'production', 'bundles_ready', 'bundles_issued', 'reject', 'rework', 'smv', 'manpower', 'available_hours', 'plan_hours', 'run_day', 'total_produced', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'order_qty' => 'integer', 'target' => 'integer', 'production' => 'integer', 'bundles_ready' => 'integer', 'bundles_issued' => 'integer', 'reject' => 'integer', 'rework' => 'integer', 'smv' => 'decimal:3', 'manpower' => 'integer', 'available_hours' => 'decimal:2', 'plan_hours' => 'decimal:2', 'run_day' => 'integer', 'total_produced' => 'integer'];
    }
}
