<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sales order amendment (SOA-…): size-wise revised quantity and rate and revised dates — approved, then applied onto the order as its next version. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsAmendment extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'APPLIED', 'REJECTED', 'CANCELLED'];

    /** The states an order's one open amendment may be in. */
    public const OPEN = ['DRAFT', 'SUBMITTED', 'APPROVED'];

    protected $table = 'ss_amendments';

    protected $fillable = ['company_id', 'amend_no', 'order_id', 'amend_date', 'reason', 'remarks', 'prev_target_date', 'revised_target_date', 'prev_shipment_date', 'revised_shipment_date', 'version', 'state', 'snapshot', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'applied_at', 'applied_by', 'rejected_at', 'rejected_by', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return ['amend_date' => 'date', 'prev_target_date' => 'date', 'revised_target_date' => 'date', 'prev_shipment_date' => 'date', 'revised_shipment_date' => 'date', 'version' => 'integer', 'snapshot' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'applied_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
