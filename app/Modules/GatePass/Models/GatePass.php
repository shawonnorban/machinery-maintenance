<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A gate pass — an employee (Main) or goods (Item) going out, returnable or
 * not — from its draft through approval and Gate OUT to its return and
 * close. Gate Pass → Create, Approval, Out, In, Reconciliation (AnnotechERP 11/03–11/04).
 */
class GatePass extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'OUT', 'PARTIAL', 'SHORT', 'CLOSED', 'COMPLETED'];

    /** Out of the gate and still owed back. */
    public const AWAITING_RETURN = ['OUT', 'PARTIAL'];

    /** Gone out at some point. */
    public const WENT_OUT = ['OUT', 'PARTIAL', 'SHORT', 'CLOSED', 'COMPLETED'];

    public const SHORT_REASONS = ['MISSING', 'DAMAGE', 'REJECTED', 'QUALITY', 'OTHER'];

    protected $table = 'gate_passes';

    protected $fillable = [
        'company_id', 'gp_no', 'gp_date', 'category_id', 'kind', 'pass_type', 'buyer_id', 'order_id', 'style', 'po_no', 'purpose_id', 'location_id',
        'destination_text', 'expected_return', 'carrier_type', 'employee_id', 'carrier_name', 'carrier_company', 'carrier_mobile', 'carrier_nid',
        'vehicle_no', 'carrier_remarks', 'remarks', 'documents', 'status', 'prepared_by', 'approved_by', 'approved_at', 'out_at', 'out_by',
        'out_remarks', 'closed_at', 'closed_by',
    ];

    public function returnable(): bool
    {
        return $this->pass_type === 'RETURNABLE';
    }

    protected function casts(): array
    {
        return ['gp_date' => 'date', 'expected_return' => 'date', 'approved_at' => 'datetime', 'out_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
