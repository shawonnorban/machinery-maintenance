<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A job tallied: buyer, style, PO, colour, order quantity and SMV. Quality Tally (AnnotechERP 36).
 */
class QtJob extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_jobs';

    protected $fillable = ['company_id', 'job_no', 'buyer_name', 'style', 'po_no', 'color', 'size', 'order_qty', 'opening_cut', 'opening_sewing', 'opening_quality', 'smv', 'due_date', 'status', 'remarks', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['order_qty' => 'integer', 'opening_cut' => 'integer', 'opening_sewing' => 'integer', 'opening_quality' => 'integer', 'smv' => 'decimal:3', 'due_date' => 'date'];
    }
}
