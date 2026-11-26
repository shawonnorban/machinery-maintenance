<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A delivery challan return (DCR-…): goods coming back on a delivered challan; the order's balance moves only once confirmed. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsChallanReturn extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'CONFIRMED', 'CANCELLED'];

    public const REASONS = ['Quality Issue', 'Wrong Item', 'Excess Delivery', 'Buyer Rejection', 'Damaged in Transit', 'Other'];

    protected $table = 'ss_challan_returns';

    protected $fillable = ['company_id', 'return_no', 'challan_id', 'return_date', 'reason', 'remarks', 'state', 'confirmed_at', 'confirmed_by', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
