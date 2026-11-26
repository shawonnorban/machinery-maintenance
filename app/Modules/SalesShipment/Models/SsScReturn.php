<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-contract return (SCRT-…): received goods sent back to the party; it moves the chalan's balance once confirmed. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScReturn extends BaseModel
{
    use BelongsToTenant;

    public const REASONS = ['Rework', 'Rejection Return', 'Wrong Delivery', 'Other'];

    protected $table = 'ss_sc_returns';

    protected $fillable = ['company_id', 'return_no', 'receipt_id', 'return_date', 'reason', 'remarks', 'state', 'confirmed_at', 'confirmed_by', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
