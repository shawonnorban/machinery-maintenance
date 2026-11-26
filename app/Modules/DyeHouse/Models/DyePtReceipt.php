<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Pre-treated fabric received back on a challan (Sub-Contract Pre-Treatment Receipt): the programmes it closes are the challan's. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyePtReceipt extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_pt_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'supplier_id', 'transfer_party_id', 'issue_date', 'qc_no', 'challan_id', 'store_id', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }
}
