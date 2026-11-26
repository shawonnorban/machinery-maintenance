<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A batch passed from one finishing stage to the next — on receipt, by a posted sheet or from the Process-Wise Delivery board — so its trail is complete end to end. Finishing Line → DF Production (AnnotechERP 18/02).
 */
class FinProcessDelivery extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_process_deliveries';

    protected $fillable = ['company_id', 'receipt_id', 'from_stage', 'to_stage', 'delivered_at', 'source', 'sheet_id', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
