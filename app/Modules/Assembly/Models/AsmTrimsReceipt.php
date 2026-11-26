<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Trims & Accessories Receipt (MRR) into a floor store, counted into stock once completed. Assembly Floor → Accessories Store (AnnotechERP 20/08).
 */
class AsmTrimsReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'COMPLETED', 'CANCELLED'];

    public const PAY_METHODS = ['CASH', 'LC', 'CHEQUE', 'TT', 'CONSIGNMENT'];

    protected $table = 'asm_trims_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'mrr_date', 'store_id', 'supplier_id', 'req_type', 'pay_method', 'pr_no', 'po_no', 'remarks', 'status', 'completed_at', 'completed_by', 'created_by'];

    protected function casts(): array
    {
        return ['mrr_date' => 'date', 'completed_at' => 'datetime'];
    }
}
