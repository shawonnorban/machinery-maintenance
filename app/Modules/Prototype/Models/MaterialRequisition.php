<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a sample asks of the stores (MR-2026-00001): Draft → Submitted →
 * Dept. Approved → Store Verified (the lines locked) → Approved, then issued
 * against until Partially or Fully Issued. Rejected with a saved reason
 * before approval; cancelled until anything is issued.
 */
class MaterialRequisition extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'SUBMITTED', 'DEPT_APPROVED', 'STORE_VERIFIED', 'APPROVED', 'PARTIALLY_ISSUED', 'FULLY_ISSUED', 'REJECTED', 'CANCELLED'];

    /** Waiting on one of the three approvals. */
    public const AWAITING = ['SUBMITTED', 'DEPT_APPROVED', 'STORE_VERIFIED'];

    /** What Material Issue releases against. */
    public const ISSUABLE = ['APPROVED', 'PARTIALLY_ISSUED'];

    /** Brickland's material groups, in its order. */
    public const MATERIAL_GROUPS = ['FABRIC', 'THREAD', 'BUTTON', 'ZIPPER', 'LABEL', 'HANGTAG', 'ELASTIC', 'INTERLINING', 'LINING', 'POLY', 'CARTON', 'PACKAGING', 'OTHER_ACCESSORIES'];

    protected $table = 'proto_material_requisitions';

    protected $fillable = [
        'company_id', 'mr_no', 'sample_id', 'req_date', 'required_date', 'department_id', 'requested_by', 'requisition_type', 'requisition_to_id', 'delivery_to_id', 'remarks', 'state',
        'dept_approved_by', 'dept_approved_at', 'store_verified_by', 'store_verified_at', 'approved_by', 'approved_at', 'rejection_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'required_date' => 'date', 'dept_approved_at' => 'datetime', 'store_verified_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** The header is saved until approval. */
    public function isEditable(): bool
    {
        return in_array($this->state, ['DRAFT', 'SUBMITTED', 'DEPT_APPROVED', 'STORE_VERIFIED', 'REJECTED'], true);
    }

    /** The lines are saved until the store verifies them. */
    public function linesOpen(): bool
    {
        return in_array($this->state, ['DRAFT', 'SUBMITTED', 'DEPT_APPROVED', 'REJECTED'], true);
    }
}
