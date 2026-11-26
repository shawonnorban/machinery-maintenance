<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item an issue asks for (a lot, given), how much the store has issued of it, and its rate on a sale. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemIssueLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_issue_lines';

    protected $fillable = ['company_id', 'issue_id', 'item_id', 'lot_no', 'required_qty', 'issued_qty', 'uom', 'rate_bdt', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['required_qty' => 'float', 'issued_qty' => 'float', 'rate_bdt' => 'float'];
    }
}
