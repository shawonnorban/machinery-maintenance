<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An accessories purchase indent against an order (Trims & Accessories, AnnotechERP 06/06). */
class AccRequisition extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CLOSED'];

    /** Only a draft, or a rejected requisition being corrected, is changed or removed. */
    public const EDITABLE = ['DRAFT', 'REJECTED'];

    protected $table = 'merch_acc_requisitions';

    protected $fillable = [
        'company_id', 'req_no', 'req_date', 'buyer_office_id', 'order_id', 'dummy', 'supplier_id', 'addition_for', 'bom_version_id', 'remarks',
        'status', 'submitted_at', 'decided_at', 'decided_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'dummy' => 'boolean', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }
}
