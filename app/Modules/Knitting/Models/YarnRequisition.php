<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A yarn purchase indent: the yarn an order needs, before it is ordered. Knit Production → Yarn Procurement (AnnotechERP 15/02).
 */
class YarnRequisition extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAITING_APPROVAL', 'APPROVED', 'REJECTED', 'ORDERED', 'CLOSED', 'CANCELLED'];

    /** A draft, or one sent back by the approver, is rewritten; once submitted the form refuses it. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['DRAFT', 'REJECTED'], true);
    }

    protected $table = 'knit_yarn_requisitions';

    protected $fillable = ['company_id', 'requisition_no', 'requisition_date', 'store_id', 'buyer_id', 'order_id', 'required_date', 'remarks', 'status', 'submitted_at', 'decision_note', 'decided_at', 'decided_by', 'created_by'];

    protected function casts(): array
    {
        return ['requisition_date' => 'date', 'required_date' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
