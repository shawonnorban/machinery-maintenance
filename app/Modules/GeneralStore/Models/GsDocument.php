<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A store document — Opening Stock, Receive, Issue, Return, Adjustment or
 * Transfer — posted to stock when saved and cancelled by reversing it, never
 * deleted. General Store → Inventory (AnnotechERP 10/03).
 */
class GsDocument extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['OPENING', 'RECEIVE', 'ISSUE', 'RETURN', 'ADJUST', 'TRANSFER'];

    /** type => number prefix (OP-2026-000001). */
    public const PREFIX = ['OPENING' => 'OP', 'RECEIVE' => 'RC', 'ISSUE' => 'IS', 'RETURN' => 'RT', 'ADJUST' => 'AD', 'TRANSFER' => 'TR'];

    /** type => the screen it is made on. */
    public const SCREEN = ['OPENING' => 'gs-opening', 'RECEIVE' => 'gs-receive', 'ISSUE' => 'gs-issue', 'RETURN' => 'gs-return', 'ADJUST' => 'gs-adjustment', 'TRANSFER' => 'gs-transfer'];

    public const ADJUSTMENT_TYPES = ['INCREASE', 'DECREASE', 'DAMAGE', 'LOST', 'COUNT', 'OTHER'];

    protected $table = 'gs_documents';

    protected $fillable = [
        'company_id', 'doc_type', 'doc_no', 'doc_date', 'gs_store_id', 'to_store_id', 'gs_supplier_id', 'challan_no', 'reference_no', 'gs_department_id',
        'person', 'against_id', 'requisition_id', 'adjustment_type', 'remarks', 'status', 'created_by', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'cancelled_at' => 'datetime'];
    }
}
