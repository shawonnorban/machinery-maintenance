<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Trims & Accessories Issue from a floor store to an order and sewing line, taken off stock once approved. Assembly Floor → Accessories Store (AnnotechERP 20/08).
 */
class AsmTrimsIssue extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'];

    protected $table = 'asm_trims_issues';

    protected $fillable = ['company_id', 'issue_no', 'issue_date', 'store_id', 'requisition_no', 'order_id', 'line_id', 'remarks', 'status', 'submitted_at', 'submitted_by', 'decided_at', 'decided_by', 'reject_reason', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
