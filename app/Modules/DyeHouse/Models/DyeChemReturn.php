<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dye & Chemical Receipt Return: drums going back to the supplier against a receipt, on a return challan (RTN-), off stock once finalised. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemReturn extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['SUPPLIER', 'DAMAGE', 'EXCESS', 'WRONG_ITEM'];

    public const STATUSES = ['DRAFT', 'FINALIZED', 'CANCELLED'];

    protected $table = 'dye_chem_returns';

    protected $fillable = ['company_id', 'return_no', 'return_date', 'receipt_id', 'return_type', 'party_id', 'supplier_id', 'store_id', 'remarks', 'status', 'finalized_at', 'finalized_by', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'finalized_at' => 'datetime'];
    }
}
