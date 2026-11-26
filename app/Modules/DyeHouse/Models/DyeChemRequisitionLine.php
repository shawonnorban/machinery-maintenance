<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One dye or chemical a requisition asks for, in its unit, and how much the store has released of it. Dye House → Requisition (AnnotechERP 17/05).
 */
class DyeChemRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_requisition_lines';

    protected $fillable = ['company_id', 'requisition_id', 'item_id', 'uom', 'required_qty', 'issued_qty', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['required_qty' => 'decimal:3', 'issued_qty' => 'decimal:3'];
    }
}
