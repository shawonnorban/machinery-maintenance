<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Out-House Process Bill (DF-SC-BL-): an outside dye house's bill, and its revision trail. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScBill extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAITING_STORE', 'APPROVED', 'COMPLETED', 'CANCELLED'];

    protected $table = 'dye_sc_bills';

    protected $fillable = ['company_id', 'bill_no', 'bill_date', 'supplier_id', 'amount', 'ref_bill_id', 'revision_no', 'revision_descr', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'amount' => 'float', 'revision_no' => 'integer'];
    }
}
