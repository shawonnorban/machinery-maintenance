<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade of a sub-contract grey requisition: the kilos asked now, and issued. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_requisition_lines';

    protected $fillable = ['company_id', 'requisition_id', 'shade_id', 'req_kg', 'issued_kg', 'sort_order'];

    protected function casts(): array
    {
        return ['req_kg' => 'float', 'issued_kg' => 'float'];
    }
}
