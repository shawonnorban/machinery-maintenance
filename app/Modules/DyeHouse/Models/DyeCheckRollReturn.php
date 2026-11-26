<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A check roll QC failed, returned for re-processing (Dye Batch Re-Process): the type, batch, shade, weight and defect, and when it was sent back. Dye House → Requisition (AnnotechERP 17/05).
 */
class DyeCheckRollReturn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_check_roll_returns';

    protected $fillable = ['company_id', 'return_no', 'req_date', 'not_ok_date', 'reprocess_type', 'party_id', 'store_id', 'batch_id', 'colour_id', 'dye_machine_id', 'qty_kg', 'defect', 'remarks', 'sent_back_at', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'not_ok_date' => 'date', 'qty_kg' => 'decimal:3', 'sent_back_at' => 'datetime'];
    }
}
