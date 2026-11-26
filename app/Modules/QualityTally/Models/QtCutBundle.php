<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bundle card the cutting manager gave as input (scanned from Cutting Floor or typed): a part of a size and serial run, checked on a cutting QC table. Quality Tally (AnnotechERP 36).
 */
class QtCutBundle extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_cut_bundles';

    protected $fillable = ['company_id', 'barcode', 'bundle_no', 'job_id', 'line_id', 'garment_type', 'panel_id', 'size', 'serial_from', 'serial_to', 'qty', 'cutting_no', 'batch_no', 'input_date', 'source', 'source_id', 'created_by'];

    protected function casts(): array
    {
        return ['serial_from' => 'integer', 'serial_to' => 'integer', 'qty' => 'integer', 'input_date' => 'date'];
    }
}
