<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A subcontractor's pre-treatment bill and its revisions (Sub-Contract Pre-Treatment Bill). Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyePtBill extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAITING_STORE', 'APPROVED', 'COMPLETED', 'CANCELLED'];

    protected $table = 'dye_pt_bills';

    protected $fillable = ['company_id', 'supplier_id', 'bill_no', 'bill_date', 'amount', 'ref_bill_no', 'revision_no', 'revision_descr', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'amount' => 'decimal:2', 'revision_no' => 'integer'];
    }
}
