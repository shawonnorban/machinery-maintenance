<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A material requisition (PR-2026-00001), raised for a confirmed order (By
 * Order: the order's buyer, style, quantity and delivery date come with it)
 * or as a direct requisition. A draft is edited or deleted; submitted, it
 * goes to every active supplier and can never be deleted. It is approved
 * or rejected as its lines' prices are decided.
 */
class PurchaseRequisition extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['BY_ORDER', 'DIRECT'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'REJECTED', 'APPROVED'];

    protected $table = 'mp_requisitions';

    protected $fillable = ['company_id', 'pr_no', 'req_type', 'req_date', 'order_id', 'status', 'remarks', 'created_by', 'department_id', 'submitted_at'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'submitted_at' => 'datetime'];
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }
}
