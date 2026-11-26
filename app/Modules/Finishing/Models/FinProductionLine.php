<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One batch through one machine on one process within a production sheet: what went in and what came off, the rolls, finished GSM and dia, load and unload times. Finishing Line → DF Production (AnnotechERP 18/02).
 */
class FinProductionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_production_lines';

    protected $fillable = ['company_id', 'sheet_id', 'receipt_id', 'machine_id', 'process_id', 'input_kg', 'output_kg', 'rolls', 'fin_gsm', 'fin_dia', 'load_time', 'unload_time', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['input_kg' => 'decimal:3', 'output_kg' => 'decimal:3', 'rolls' => 'integer', 'fin_gsm' => 'decimal:2', 'fin_dia' => 'decimal:2', 'load_time' => 'datetime', 'unload_time' => 'datetime', 'sort_order' => 'integer'];
    }
}
