<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One material a requisition asks for: a fabric or an accessory from the
 * master data, its quantity in a unit of measure and the date it is needed.
 * Open for quotation from the start; the supplier whose price is approved
 * is set when the price review decides it.
 */
class PurchaseRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    /** The two tabs of Material Requirements, and the master each draws from. */
    public const KINDS = ['FABRIC', 'TRIMS'];

    /** Open for quotation until the price review approves a supplier on it or rejects it outright. */
    public const STATUSES = ['OPEN_FOR_QUOTATION', ...self::DELIVERY, 'REJECTED'];

    /**
     * An approved line's delivery steps, in order, as its follow-ups record them
     * (Approved → … → In-House, Brickland's nine).
     */
    public const DELIVERY = ['APPROVED', 'SUPPLIER_CONFIRMED', 'PRODUCTION', 'READY_FOR_DISPATCH', 'DISPATCHED', 'IN_TRANSIT', 'PARTIALLY_RECEIVED', 'FULLY_RECEIVED', 'IN_HOUSE'];

    protected $table = 'mp_requisition_lines';

    protected $fillable = ['company_id', 'requisition_id', 'kind', 'item_id', 'req_qty', 'uom', 'req_date', 'specification', 'remarks', 'status', 'approved_supplier_id', 'sort_order', 'approved_quotation_id', 'followup_user_id', 'decided_by', 'decided_at', 'reject_reason'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'req_qty' => 'decimal:3', 'decided_at' => 'datetime'];
    }
}
