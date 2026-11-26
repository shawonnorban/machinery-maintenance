<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A check bill (CB-…) of a customer's delivered challan lines: submitted, verified, approved (finalized) and paid — or rejected back to the desk. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsBill extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'WAITING_FOR_APPROVAL', 'VERIFIED', 'APPROVED', 'PAID', 'REJECTED'];

    /** The states the desk may still rewrite. */
    public const EDITABLE = ['DRAFT', 'REJECTED'];

    /** Finalized: the quantities are locked against the challans. */
    public const FINAL = ['APPROVED', 'PAID'];

    protected $table = 'ss_bills';

    protected $fillable = ['company_id', 'bill_no', 'customer_id', 'currency_id', 'loc_exchange_rate', 'bill_date', 'deduction', 'remarks', 'state', 'reject_reason', 'submitted_at', 'submitted_by', 'verified_at', 'verified_by', 'approved_at', 'approved_by', 'paid_at', 'paid_by', 'rejected_at', 'rejected_by', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'loc_exchange_rate' => 'float', 'deduction' => 'float', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'rejected_at' => 'datetime'];
    }
}
