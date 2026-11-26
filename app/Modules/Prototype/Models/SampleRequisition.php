<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The merchandiser's official ask for a sample (Prototype Desk, AnnotechERP
 * 07/03): Draft → Pending Approval at each level in turn — Merchandiser,
 * Merchandising Manager, Sample Manager — → Approved (the sample is minted)
 * → Assigned → In Development → Completed. A rejection sends it back to the
 * merchandiser; saving a rejected one returns it to Draft.
 */
class SampleRequisition extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'ASSIGNED', 'IN_DEVELOPMENT', 'COMPLETED', 'CANCELLED'];

    /** The approval levels, in the order they decide. */
    public const LEVELS = ['MERCHANDISER', 'MERCHANDISING_MANAGER', 'SAMPLE_MANAGER'];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT', 'CRITICAL'];

    /** Changed from the form only while a draft or after a rejection. */
    public const EDITABLE = ['DRAFT', 'REJECTED'];

    protected $table = 'proto_requisitions';

    protected $fillable = [
        'company_id', 'req_no', 'req_date', 'buyer_id', 'buyer_office_id', 'order_id', 'style_id', 'season', 'product_category', 'sample_type_id', 'sample_stage',
        'qty', 'required_date', 'priority', 'requested_by', 'department_id', 'assigned_to', 'remarks', 'attachment', 'status', 'pending_level', 'sample_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'required_date' => 'date', 'qty' => 'integer'];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }
}
