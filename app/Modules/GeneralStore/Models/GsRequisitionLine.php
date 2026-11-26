<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item on a requisition: what was asked for and what the Store Manager approved. General Store → Requisition (AnnotechERP 10/04–10/05).
 */
class GsRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gs_requisition_lines';

    protected $fillable = ['company_id', 'gs_requisition_id', 'gs_item_id', 'requested_qty', 'approved_qty', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['requested_qty' => 'decimal:4', 'approved_qty' => 'decimal:4'];
    }
}
