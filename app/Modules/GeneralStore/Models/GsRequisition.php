<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A department's requisition for store items — drafted, submitted, approved
 * line by line, then issued against. General Store → Requisition (AnnotechERP 10/04–10/05).
 */
class GsRequisition extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'PARTIAL', 'ISSUED', 'REJECTED', 'CANCELLED'];

    /** What the store may still issue against. */
    public const ISSUABLE = ['APPROVED', 'PARTIAL'];

    protected $table = 'gs_requisitions';

    protected $fillable = [
        'company_id', 'req_no', 'req_date', 'gs_department_id', 'gs_store_id', 'reason', 'status', 'requested_by', 'submitted_at',
        'decided_by', 'decided_at', 'approval_note', 'closing_reason', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
