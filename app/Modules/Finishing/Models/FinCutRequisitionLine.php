<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot on a cutting requisition: what cutting asked for and what the store released. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinCutRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_cut_requisition_lines';

    protected $fillable = ['company_id', 'requisition_id', 'lot_id', 'description', 'lot_no', 'batch_no', 'req_qty', 'req_m', 'issue_qty', 'issue_m', 'rolls', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['req_qty' => 'decimal:3', 'issue_qty' => 'decimal:3', 'rolls' => 'integer', 'sort_order' => 'integer'];
    }
}
