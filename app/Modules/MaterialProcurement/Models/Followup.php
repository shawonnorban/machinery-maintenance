<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One follow-up on an approved line: the delivery step it reached, what the
 * supplier said, the quantity received in this delivery (adding to the
 * line's total) and the next follow-up date; a receipt may be raised as a
 * draft goods receipt (MRR) on Merchandise Desk.
 */
class Followup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mp_followups';

    protected $fillable = [
        'company_id', 'requisition_line_id', 'followup_date', 'contact_person', 'status', 'received_qty', 'expected_delivery', 'actual_delivery',
        'supplier_response', 'remarks', 'next_followup_date', 'grn_type', 'grn_id', 'grn_no', 'created_by',
    ];

    protected function casts(): array
    {
        return ['followup_date' => 'date', 'expected_delivery' => 'date', 'actual_delivery' => 'date', 'next_followup_date' => 'date', 'received_qty' => 'decimal:3'];
    }
}
