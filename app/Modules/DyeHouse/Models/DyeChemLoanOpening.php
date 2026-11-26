<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Chemical Loan Opening Balance: what one supplier had lent us, and we them, of one item on opening day. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemLoanOpening extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_loan_openings';

    protected $fillable = ['company_id', 'ob_date', 'supplier_id', 'category_id', 'item_id', 'uom', 'loan_received', 'loan_issued', 'is_final', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'loan_received' => 'float', 'loan_issued' => 'float', 'is_final' => 'boolean'];
    }
}
