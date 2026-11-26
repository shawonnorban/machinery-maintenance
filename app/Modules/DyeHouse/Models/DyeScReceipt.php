<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Out-House Floor Receipt (DF-SC-FR-) or Challan Receipt (DF-SC-MRR-): dyed fabric coming back on an out-house challan, per programme — the floor's check first, the store's booking after. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScReceipt extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['FLOOR', 'STORE'];

    protected $table = 'dye_sc_receipts';

    protected $fillable = ['company_id', 'kind', 'mrr_no', 'issue_date', 'challan_id', 'supplier_id', 'transfer_party_id', 'store_id', 'batch_no', 'qc_no', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }
}
