<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A request to delete a posted document, with its reason and decision (Access Control → Document Deletion Console, AnnotechERP 02/04).
 */
class DeletionRequest extends BaseModel
{
    use BelongsToTenant;

    /** Brickland's deletion types. */
    public const DOCUMENT_TYPES = [
        'GENERAL_PR', 'TRIMS_ACC_PR', 'IMPORT_FABRIC_PR', 'GENERAL_PO', 'TRIMS_ACC_PO', 'IMPORT_FABRIC_PO',
        'MRR', 'GRN', 'STORE_REQUISITION', 'SALES_INVOICE', 'DELIVERY_CHALLAN',
    ];

    public const STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'deletion_requests';

    protected $fillable = ['company_id', 'document_type', 'document_no', 'reason', 'status', 'approval_remarks', 'requested_by'];
}
